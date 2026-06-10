<?php
/**
 * Profile Page
 * 
 * User profile management: personal info, billing, settings, subscription
 */

require '../inc/bootstrap.php';
require 'app/auth.php';
require_once '../inc/stripePaymentFinalizer.php';

// Redirect ak nie je prihlásený
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Get active tab from URL
$activeTab = $_GET['tab'] ?? 'profile';
$allowedTabs = ['profile', 'billing', 'settings', 'reviews'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'profile';
}

// Load user's active subscriptions (if any)
$subscriptions = [];
$subscription = null;
$db = Database::getInstance()->getConnection();
$hasApprovedPromotionChatbot = false;

try {
    $approvedChatbotStmt = $db->prepare("
        SELECT COUNT(*)
        FROM chatbots
        WHERE owner_user_id = :user_id
          AND deleted_at IS NULL
          AND status = 'active'
    ");
    $approvedChatbotStmt->execute(['user_id' => $user['id']]);
    $hasApprovedPromotionChatbot = (int) $approvedChatbotStmt->fetchColumn() > 0;
} catch (Exception $e) {
    error_log('Profile approved chatbot check error: ' . $e->getMessage());
}

if (!$hasApprovedPromotionChatbot && $activeTab === 'billing') {
    $activeTab = 'profile';
}

try {
    $pendingStripeStmt = $db->prepare("
        SELECT COUNT(*)
        FROM payments
        WHERE user_id = :user_id
          AND payment_method = 'stripe'
          AND status IN ('pending', 'processing')
          AND stripe_payment_intent_id IS NOT NULL
    ");
    $pendingStripeStmt->execute(['user_id' => $user['id']]);

    if ((int) $pendingStripeStmt->fetchColumn() > 0) {
        $stripeConfig = require '../config/stripe.php';
        if (!empty($stripeConfig['secret_key'])) {
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
            if (!empty($stripeConfig['api_version'])) {
                \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
            }
            syncRecentPaidStripeInvoicesForUser($db, $user['id']);
        }
    }
} catch (Exception $e) {
    error_log('Profile Stripe payment sync error: ' . $e->getMessage());
}

try {
    $scheduledPromotionRepairStmt = $db->prepare("
        UPDATE subscriptions scheduled
        JOIN (
            SELECT
                user_id,
                chatbot_id,
                stripe_subscription_id,
                MAX(end_date) AS max_end_date
            FROM subscriptions
            WHERE user_id = :repair_group_user_id
              AND subscription_type = 'promotion'
              AND stripe_subscription_id IS NOT NULL
              AND status IN ('active', 'trialing', 'past_due', 'pending')
              AND end_date >= NOW()
            GROUP BY user_id, chatbot_id, stripe_subscription_id
        ) active_group
          ON active_group.user_id = scheduled.user_id
         AND active_group.chatbot_id = scheduled.chatbot_id
         AND active_group.stripe_subscription_id = scheduled.stripe_subscription_id
        SET scheduled.end_date = active_group.max_end_date,
            scheduled.updated_at = NOW()
        WHERE scheduled.user_id = :repair_user_id
          AND scheduled.subscription_type = 'promotion'
          AND scheduled.cancel_at_period_end = TRUE
          AND scheduled.auto_renew = FALSE
          AND scheduled.stripe_subscription_id IS NOT NULL
          AND scheduled.end_date < active_group.max_end_date
    ");
    $scheduledPromotionRepairStmt->execute([
        'repair_group_user_id' => $user['id'],
        'repair_user_id' => $user['id'],
    ]);
} catch (Exception $e) {
    error_log('Profile scheduled promotion period repair error: ' . $e->getMessage());
}

$subStmt = $db->prepare("
    SELECT 
        s.*,
        p.slot_type        AS plan_name,
        p.price,
        p.discount_percentage,
        c.symbol      AS currency_symbol,
        c.code        AS currency_code,
        bp.type       AS billing_period,
        ch.name       AS chatbot_name,
        COALESCE((
            SELECT pay.amount
            FROM payments pay
            WHERE pay.subscription_id = s.id
              AND pay.status = 'succeeded'
            ORDER BY COALESCE(pay.succeeded_at, pay.created_at) DESC
            LIMIT 1
        ), p.price) AS current_period_price,
        (
            SELECT COALESCE(pay.invoice_url, pay.receipt_url)
            FROM payments pay
            WHERE pay.subscription_id = s.id
              AND pay.status = 'succeeded'
            ORDER BY COALESCE(pay.succeeded_at, pay.created_at) DESC
            LIMIT 1
        ) AS latest_invoice_url,
        (
            SELECT COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(pay.metadata, '$.billing_period')),
                JSON_UNQUOTE(JSON_EXTRACT(pay.metadata, '$.period_type'))
            )
            FROM payments pay
            WHERE pay.subscription_id = s.id
              AND pay.status = 'succeeded'
            ORDER BY COALESCE(pay.succeeded_at, pay.created_at) DESC
            LIMIT 1
        ) AS latest_payment_billing_period,
        (
            SELECT COUNT(DISTINCT sibling.promotions_id)
            FROM subscriptions sibling
            WHERE sibling.user_id = s.user_id
              AND sibling.chatbot_id = s.chatbot_id
              AND sibling.subscription_type = 'promotion'
              AND sibling.status IN ('active', 'trialing', 'past_due', 'pending')
              AND sibling.stripe_subscription_id IS NOT NULL
              AND sibling.stripe_subscription_id = s.stripe_subscription_id
        ) AS stripe_subscription_line_count
    FROM subscriptions s
    JOIN promotions     p  ON s.promotions_id     = p.id
    JOIN currencies     c  ON p.currency_id        = c.id
    JOIN billing_periods bp ON p.billing_period_id = bp.id
    JOIN chatbots       ch ON s.chatbot_id         = ch.id
    WHERE s.user_id          = :user_id
      AND (
          s.status IN ('active', 'trialing', 'past_due', 'pending')
      )
      AND s.subscription_type = 'promotion'
      AND (
          s.status = 'pending'
          OR s.end_date >= NOW()
      )
    ORDER BY
        CASE s.status
            WHEN 'active' THEN 1
            WHEN 'past_due' THEN 2
            WHEN 'pending' THEN 3
            ELSE 4
        END,
        s.created_at DESC
");
$subStmt->execute(['user_id' => $user['id']]);
$subscriptions = $subStmt->fetchAll();
$hasBlockingActiveSubscriptions = !empty($subscriptions);

$dedupedSubscriptions = [];
$seenSubscriptionSlots = [];
foreach ($subscriptions as $sub) {
    $slotKey = !empty($sub['stripe_subscription_id'])
        ? $sub['stripe_subscription_id'] . '|' . $sub['chatbot_id'] . '|' . ($sub['plan_name'] ?? '')
        : $sub['id'];

    if (isset($seenSubscriptionSlots[$slotKey])) {
        continue;
    }

    $seenSubscriptionSlots[$slotKey] = true;
    $dedupedSubscriptions[] = $sub;
}
$subscriptions = $dedupedSubscriptions;

$subscriptionGroupStates = [];
foreach ($subscriptions as $sub) {
    $groupKey = !empty($sub['stripe_subscription_id']) ? $sub['stripe_subscription_id'] : $sub['id'];
    if (!isset($subscriptionGroupStates[$groupKey])) {
        $subscriptionGroupStates[$groupKey] = [
            'line_count' => 0,
            'has_scheduled_cancel' => false,
        ];
    }

    $subscriptionGroupStates[$groupKey]['line_count']++;
    if (!empty($sub['cancel_at_period_end'])) {
        $subscriptionGroupStates[$groupKey]['has_scheduled_cancel'] = true;
    }
}

foreach ($subscriptions as &$sub) {
    $planBillingPeriod = promotionNormalizeBillingPeriod($sub['billing_period'] ?? 'monthly');
    $paymentBillingPeriod = promotionNormalizeBillingPeriod($sub['latest_payment_billing_period'] ?? '', '');
    $dateBillingPeriod = promotionBillingPeriodFromDates(
        $sub['start_date'] ?? null,
        $sub['end_date'] ?? null,
        $planBillingPeriod
    );
    $sub['billing_period'] = $paymentBillingPeriod
        ?: (in_array($dateBillingPeriod, ['monthly', 'yearly'], true) ? $dateBillingPeriod : $planBillingPeriod);
    $sub['display_plan_name'] = match($sub['plan_name'] ?? '') {
        'Homepage'      => 'Homepage Visibility',
        'Chatbots page' => 'Chatbots Page Visibility',
        default         => $sub['plan_name'] ?: 'Promotion',
    };
    $sub['status_class'] = match($sub['status']) {
        'past_due' => 'past-due',
        'pending'  => 'pending',
        'trialing' => 'pending',
        default    => 'active',
    };
    $sub['status_label'] = match($sub['status']) {
        'past_due' => 'Payment Failed',
        'pending'  => 'Pending Payment',
        'trialing' => 'Trialing',
        default    => !empty($sub['cancel_at_period_end']) ? 'Cancels at period end' : 'Active Subscription',
    };
    $isBundleStripeSubscription = !empty($sub['stripe_subscription_id'])
        && (int) ($sub['stripe_subscription_line_count'] ?? 0) > 1;
    $groupKey = !empty($sub['stripe_subscription_id']) ? $sub['stripe_subscription_id'] : $sub['id'];
    $groupHasScheduledCancel = !empty($subscriptionGroupStates[$groupKey]['has_scheduled_cancel']);
    $standalonePeriodPrice = promotionSingleTotalPrice(
        (float) $sub['price'],
        (float) ($sub['discount_percentage'] ?? 0),
        $sub['billing_period']
    );
    $bundleLinePrice = promotionBundleLineTotalPrice((float) $sub['price'], $sub['billing_period']);
    $sub['period_price'] = $isBundleStripeSubscription
        ? $bundleLinePrice
        : $standalonePeriodPrice;
    $sub['price_note'] = '';

    if ($isBundleStripeSubscription && $groupHasScheduledCancel && empty($sub['cancel_at_period_end']) && !empty($sub['auto_renew'])) {
        $sub['period_price'] = $standalonePeriodPrice;
        $sub['price_note'] = 'Next renewal price without bundle discount';
    }
}
unset($sub);

$subscriptionCancelGroups = [];
foreach ($subscriptions as $sub) {
    $groupKey = !empty($sub['stripe_subscription_id']) ? $sub['stripe_subscription_id'] : $sub['id'];
    if (!isset($subscriptionCancelGroups[$groupKey])) {
        $subscriptionCancelGroups[$groupKey] = [];
    }

    $subscriptionCancelGroups[$groupKey][] = [
        'id' => $sub['id'],
        'promotion_id' => $sub['promotions_id'],
        'slot_type' => $sub['plan_name'],
        'plan' => $sub['display_plan_name'],
        'chatbot' => $sub['chatbot_name'],
        'price' => $sub['currency_symbol'] . number_format($sub['period_price'], 2),
        'standalone_price' => $sub['currency_symbol'] . number_format(
            promotionSingleTotalPrice(
                (float) $sub['price'],
                (float) ($sub['discount_percentage'] ?? 0),
                $sub['billing_period']
            ),
            2
        ),
        'period' => strtolower($sub['billing_period']),
        'auto_renew' => !empty($sub['auto_renew']),
        'cancel_at_period_end' => !empty($sub['cancel_at_period_end']),
    ];
}

foreach ($subscriptions as &$sub) {
    $groupKey = !empty($sub['stripe_subscription_id']) ? $sub['stripe_subscription_id'] : $sub['id'];
    $sub['cancel_items'] = $subscriptionCancelGroups[$groupKey] ?? [];
}
unset($sub);

$subscription = $subscriptions[0] ?? null;

// Load billing history
$payments = [];
    $paymentStmt = $db->prepare("
        SELECT
            p.*,
            c.symbol AS currency_symbol,
            c.code AS currency_code,
            COALESCE(pr.slot_type, metadata_pr.slot_type) AS package_slot_type,
            COALESCE(ch.name, payment_ch.name) AS package_chatbot_name,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.billing_period')),
                JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.period_type'))
            ) AS package_metadata_billing_period,
            COALESCE(bp.type, metadata_bp.type) AS package_plan_billing_period,
            s.start_date AS package_start_date,
            s.end_date AS package_end_date
        FROM payments p
        JOIN currencies c ON p.currency_id = c.id
        LEFT JOIN subscriptions s ON p.subscription_id = s.id
        LEFT JOIN promotions pr ON s.promotions_id = pr.id
        LEFT JOIN billing_periods bp ON pr.billing_period_id = bp.id
        LEFT JOIN promotions metadata_pr ON metadata_pr.id = JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.promotion_id'))
        LEFT JOIN billing_periods metadata_bp ON metadata_pr.billing_period_id = metadata_bp.id
        LEFT JOIN chatbots ch ON s.chatbot_id = ch.id
        LEFT JOIN chatbots payment_ch ON p.chatbot_id = payment_ch.id
        WHERE p.user_id = :user_id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $paymentStmt->execute(['user_id' => $user['id']]);
    $payments = $paymentStmt->fetchAll();

    foreach ($payments as &$payment) {
        $packageName = match($payment['package_slot_type'] ?? '') {
            'Homepage'      => 'Homepage Visibility',
            'Chatbots page' => 'Chatbots Page Visibility',
            default         => $payment['package_slot_type'] ?: 'Promotion',
        };
        $billingPeriod = promotionNormalizeBillingPeriod($payment['package_metadata_billing_period'] ?? '', '');
        if (!$billingPeriod) {
            $billingPeriod = promotionBillingPeriodFromDates(
                $payment['package_start_date'] ?? null,
                $payment['package_end_date'] ?? null,
                $payment['package_plan_billing_period'] ?? ''
            );
        }

        $packageMeta = array_filter([
            $payment['package_chatbot_name'] ?? '',
            $billingPeriod ? promotionBillingPeriodLabel($billingPeriod) : '',
        ]);

        $payment['package_name'] = $packageName;
        $payment['package_meta'] = implode(' · ', $packageMeta);
    }
    unset($payment);

require '../inc/header.php';
?>

<link rel="stylesheet" href="css/profile.css?v=<?= filemtime(__DIR__ . '/css/profile.css') ?>">
<link rel="stylesheet" href="css/profileReviews.css?v=<?= filemtime(__DIR__ . '/css/profileReviews.css') ?>">

<div class="profile-page">
    <div class="profile-container">
        
        <!-- Page Header -->
        <div class="profile-header">
            <h1>My Account</h1>
            <p>Manage your profile, billing, and settings</p>
        </div>

        <!-- Tab Navigation -->
        <div class="profile-tabs">
            <button class="tab-btn <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" 
                    data-tab="profile">
                Profile
            </button>
            <?php if ($hasApprovedPromotionChatbot): ?>
            <button class="tab-btn <?php echo $activeTab === 'billing' ? 'active' : ''; ?>"
                    data-tab="billing">
                Billing Info
            </button>
            <?php endif; ?>
            <button class="tab-btn <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" 
                    data-tab="settings">
                Settings
            </button>
            <button class="tab-btn <?php echo $activeTab === 'reviews' ? 'active' : ''; ?>" 
                    data-tab="reviews">
                My Reviews
            </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            
            <!-- PROFILE TAB -->
            <div class="tab-pane <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" 
                 id="profile-tab">
                
                <div class="profile-grid">
                    
                    <!-- Avatar Section -->
                    <div class="profile-card avatar-card">
                        <div class="avatar-wrapper">                     
                            <img src="img/default-avatar2.png"
                                data-src="<?php echo htmlspecialchars($user['profile_picture_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Profile picture"
                                class="profile-avatar user-avatar-img"
                                id="current-avatar"
>
                            <div class="avatar-overlay">
                                <label for="avatar-upload" class="avatar-upload-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    <span>Change photo</span>
                                </label>
                                <input type="file" 
                                       id="avatar-upload" 
                                       accept="image/jpeg,image/png,image/webp"
                                       style="display: none;">
                            </div>
                        </div>
                        <p class="avatar-hint">Max 400x400px, 5MB</p>
                    </div>

                    <!-- Personal Info -->
                    <div class="profile-card info-card">
                        <h2>Personal Information</h2>
                        
                        <!-- Name (inline edit) -->
                        <div class="info-field" id="name-field">
                            <p class="info-field-static-label">Name</p>
                            <div class="field-display">
                                <span id="name-display">
                                    <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                                </span>
                                <button class="edit-btn" id="edit-name-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="field-edit" style="display: none;">
                                <div class="name-inputs">
                                    <input type="text" 
                                           id="first-name-input" 
                                           placeholder="First name"
                                           maxlength="50"
                                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                    <input type="text" 
                                           id="last-name-input" 
                                           placeholder="Last name"
                                           maxlength="50"
                                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                </div>
                                <div class="edit-actions">
                                    <button class="cancel-btn" id="cancel-name-btn">Cancel</button>
                                    <button class="save-btn" id="save-name-btn">Save</button>
                                </div>
                            </div>
                        </div>

                        <!-- Email (read-only) -->
                        <div class="info-field">
                            <p class="info-field-static-label">Email</p>
                            <div class="field-display">
                                <span id="email-display"></span>
                                <input type="hidden" id="email-full" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="button" id="email-toggle" aria-label="Show email">
                                    <svg id="email-icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg id="email-icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                                <?php if ($user['is_email_verified']): ?>
                                    <span class="verified-badge">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                        Verified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="info-field">
                            <button class="secondary-btn" id="change-password-btn">Change Password</button>
                        </div>

                        <!-- Change Password Form (hidden) -->
                        <div id="password-form" style="display: none;">
                            <div class="password-fields">
                                
                                <!-- Current Password -->
                                <div class="form-group">
                                    <p class="info-field-static-label">Current Password</p>
                                    <div class="password-wrapper">
                                        <input type="password" 
                                            id="current-password" 
                                            autocomplete="current-password">
                                        <button type="button" 
                                                class="toggle-password" 
                                                data-target="current-password"
                                                aria-label="Toggle password visibility">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- New Password -->
                                <div class="form-group">
                                    <p class="info-field-static-label">New Password</p>
                                    <div class="password-wrapper">
                                        <input type="password" 
                                            id="new-password" 
                                            autocomplete="new-password">
                                        <button type="button" 
                                                class="toggle-password" 
                                                data-target="new-password"
                                                aria-label="Toggle password visibility">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Confirm Password -->
                                <div class="form-group">
                                    <p class="info-field-static-label">Confirm New Password</p>
                                    <div class="password-wrapper">
                                        <input type="password" 
                                            id="confirm-password" 
                                            autocomplete="new-password">
                                        <button type="button" 
                                                class="toggle-password" 
                                                data-target="confirm-password"
                                                aria-label="Toggle password visibility">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                            </div>
                            <div class="edit-actions">
                                <button class="cancel-btn" id="cancel-password-btn">Cancel</button>
                                <button class="save-btn" id="save-password-btn">Update Password</button>
                            </div>
                        </div>
                    </div>

                    <?php if ($hasApprovedPromotionChatbot): ?>
                    <!-- Subscriptions -->
                    <div class="profile-card subscriptions-card" id="your-promotions">
                        <div class="subscriptions-card__header">
                            <div>
                                <p class="info-field-static-label">Subscriptions</p>
                                <h2>Your Promotions</h2>
                            </div>
                            <?php $subscriptionCount = count($subscriptions); ?>
                            <span class="subscription-count">
                                <?php echo $subscriptionCount; ?> <?php echo $subscriptionCount === 1 ? 'promotion' : 'promotions'; ?>
                            </span>
                        </div>

                        <?php if (empty($subscriptions)): ?>
                            <div class="subscriptions-empty">
                                <strong>No active promotions yet</strong>
                                <span>Promote your approved chatbot on the homepage or Chatbots page.</span>
                                <a href="advertise.php#plans" class="subscriptions-empty__action">
                                    View Promotion Plans
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="subscription-list">
                                <?php foreach ($subscriptions as $sub): ?>
                                    <?php
                                        $periodEnd = $sub['status'] === 'pending'
                                            ? 'Awaiting payment confirmation'
                                            : date('M d, Y', strtotime($sub['end_date']));
                                        $periodEndLabel = $sub['status'] === 'pending'
                                            ? 'Payment'
                                            : (!empty($sub['auto_renew']) ? 'Current period ends' : 'Subscription ends');
                                        $autoRenewLabel = !empty($sub['auto_renew']) ? 'On' : 'Off';
                                        $isScheduledEnd = !empty($sub['cancel_at_period_end']) && strtotime($sub['end_date']) >= time();
                                        $canCancel = in_array($sub['status'], ['active', 'trialing', 'past_due'], true) || $isScheduledEnd;
                                    ?>
                                    <article class="subscription-card <?php echo htmlspecialchars($sub['status_class']); ?>">
                                        <div class="subscription-card__top">
                                            <span class="subscription-status-pill status-<?php echo htmlspecialchars($sub['status']); ?>">
                                                <?php echo htmlspecialchars($sub['status_label']); ?>
                                            </span>
                                            <span class="subscription-card__period"><?php echo htmlspecialchars(promotionBillingPeriodLabel($sub['billing_period'])); ?></span>
                                        </div>

                                        <div class="subscription-card__body">
                                            <h3><?php echo htmlspecialchars($sub['display_plan_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($sub['chatbot_name']); ?></p>
                                            <strong>
                                                <?php echo $sub['currency_symbol']; ?><?php echo number_format($sub['period_price'], 2); ?>
                                                <span>/ <?php echo strtolower($sub['billing_period']); ?></span>
                                            </strong>
                                            <?php if (!empty($sub['price_note'])): ?>
                                                <small class="subscription-card__price-note">
                                                    <?php echo htmlspecialchars($sub['price_note']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="subscription-card__meta">
                                            <span><?php echo htmlspecialchars($periodEndLabel); ?> <b><?php echo htmlspecialchars($periodEnd); ?></b></span>
                                            <span>Auto-renew <b><?php echo htmlspecialchars($autoRenewLabel); ?></b></span>
                                        </div>

                                        <button
                                            class="primary-btn manage-subscription-btn"
                                            type="button"
                                            data-subscription-id="<?php echo htmlspecialchars($sub['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-plan="<?php echo htmlspecialchars($sub['display_plan_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-chatbot="<?php echo htmlspecialchars($sub['chatbot_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-price="<?php echo htmlspecialchars($sub['currency_symbol'] . number_format($sub['period_price'], 2), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-period="<?php echo htmlspecialchars(strtolower($sub['billing_period']), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-period-end="<?php echo htmlspecialchars($periodEnd, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-period-end-label="<?php echo htmlspecialchars($periodEndLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-auto-renew="<?php echo htmlspecialchars($autoRenewLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($sub['status_label'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-can-cancel="<?php echo $canCancel ? '1' : '0'; ?>"
                                            data-cancel-at-period-end="<?php echo !empty($sub['cancel_at_period_end']) ? '1' : '0'; ?>"
                                            data-invoice-url="<?php echo htmlspecialchars($sub['latest_invoice_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-cancel-items="<?php echo htmlspecialchars(json_encode($sub['cancel_items'] ?? []), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Manage
                                        </button>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Active Subscription Card -->
                    <?php if (false && $subscription): ?>
                    <?php
                        $subStatusClass = match($subscription['status']) {
                            'past_due' => 'past-due',
                            'pending'  => 'pending',
                            default    => 'active',
                        };
                        $subStatusIcon = match($subscription['status']) {
                            'past_due' => '⚠️',
                            'pending'  => '⏳',
                            default    => '',
                        };
                        $subStatusLabel = match($subscription['status']) {
                            'past_due' => 'Payment Failed',
                            'pending'  => 'Pending Payment',
                            default    => 'Active Subscription',
                        };
                    ?>
                    <div class="profile-card subscription-card <?php echo $subStatusClass; ?>">
                        <div class="subscription-header">
                            <?php if ($subStatusIcon): ?>
                                <span style="font-size:18px"><?php echo $subStatusIcon; ?></span>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            <?php endif; ?>
                            <h3><?php echo $subStatusLabel; ?></h3>
                        </div>
                        <div class="subscription-details">
                            <div class="sub-plan"><?php echo htmlspecialchars($subscription['plan_name']); ?></div>
                            <div class="sub-chatbot">🤖 <?php echo htmlspecialchars($subscription['chatbot_name']); ?></div>
                            <div class="sub-price">
                                <?php echo $subscription['currency_symbol']; ?><?php echo number_format(floatval($subscription['period_price'] ?? $subscription['price']), 2); ?>
                                / <?php echo strtolower($subscription['billing_period']); ?>
                            </div>
                            <?php if ($subscription['status'] === 'past_due'): ?>
                                <div class="sub-next-billing" style="color:#b45309;background:#fef3c7;padding:8px;border-radius:6px;margin-top:8px;font-size:13px;">
                                    ⚠️ Last payment failed — please update your payment method.
                                </div>
                            <?php elseif ($subscription['status'] === 'pending'): ?>
                                <div class="sub-next-billing">Awaiting payment confirmation...</div>
                            <?php else: ?>
                                <div class="sub-next-billing">
                                    Next billing: <strong><?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="primary-btn" id="manage-subscription-btn">
                            Manage Subscription
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Delete Account -->
                    <div class="profile-card account-danger-card">
                        <!-- <p class="info-field-static-label">Danger zone</p> -->
                        <h2>Delete Account</h2>
                        <p>Once you delete your account, there is no going back. Please be certain.</p>
                        <button class="danger-btn" id="delete-account-btn">Delete Account</button>
                    </div>

                </div>
            </div>

            <?php if ($hasApprovedPromotionChatbot): ?>
            <!-- BILLING INFO TAB -->
            <div class="tab-pane <?php echo $activeTab === 'billing' ? 'active' : ''; ?>" 
                 id="billing-tab">
                
                <div class="billing-container">
                    
                    <!-- Filters -->
                    <div class="billing-filters">
                        <div class="filter-group">
                            <p class="info-field-static-label">Date</p>
                            <select id="date-filter">
                                <option value="all">All</option>
                                <option value="this-month">This Month</option>
                                <option value="last-month">Last Month</option>
                                <option value="this-year">This Year</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <p class="info-field-static-label">Status</p>
                            <select id="status-filter">
                                <option value="all">All</option>
                                <option value="succeeded">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <p class="info-field-static-label">Method</p>
                            <select id="method-filter">
                                <option value="all">All</option>
                                <option value="stripe">Card</option>
                                <option value="paypal">PayPal</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <button class="reset-btn" id="reset-filters">Reset</button>
                    </div>

                    <!-- Payments Table -->
                    <div class="payments-table-wrapper">
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Package</th>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Invoice</th>
                                </tr>
                            </thead>
                            <tbody id="payments-tbody">
                                <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="6" class="no-payments">No payment history yet</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr data-date="<?php echo $payment['created_at']; ?>" 
                                        data-status="<?php echo $payment['status']; ?>" 
                                        data-method="<?php echo $payment['payment_method']; ?>">
                                        <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                        <td class="payment-package-cell">
                                            <strong><?php echo htmlspecialchars($payment['package_name']); ?></strong>
                                            <?php if (!empty($payment['package_meta'])): ?>
                                                <span><?php echo htmlspecialchars($payment['package_meta']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                        <td><?php echo $payment['currency_symbol'] . number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                <?php 
                                                    echo match($payment['status']) {
                                                        'succeeded' => 'Paid',
                                                        'pending'   => 'Processing...',
                                                        'failed'    => 'Failed',
                                                        'refunded'  => 'Refunded',
                                                        'cancelled' => 'Cancelled',
                                                        default     => ucfirst($payment['status']),
                                                    };
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['invoice_url']): ?>
                                                <a href="<?php echo htmlspecialchars($payment['invoice_url']); ?>" 
                                                   target="_blank" 
                                                   class="invoice-link">
                                                    Download
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            <?php endif; ?>

            <!-- SETTINGS TAB -->
            <div class="tab-pane <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" 
                 id="settings-tab">
                
                <div class="settings-container">
                    <div class="profile-card">
                        <h2>Email Preferences</h2>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <div class="setting-title">Product updates and marketing emails</div>
                                <div class="setting-description">
                                    Get notified about new features, updates, and special offers
                                </div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       id="marketing-emails-toggle"
                                       <?php echo ($user['marketing_emails'] ?? false) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

        </div>

            <!-- MY REVIEWS TAB -->
            <div class="tab-pane <?php echo $activeTab === 'reviews' ? 'active' : ''; ?>"
                 id="reviews-tab">

                <div class="pr-loading" id="myReviewsLoading">
                    <div class="pr-loading-spinner"></div>
                </div>

                <div class="pr-empty" id="myReviewsEmpty">
                    <p>You haven't written any reviews yet.<br>
                    <a href="chatbotShowcase.php">Browse chatbots</a> and share your experience!</p>
                </div>

                <div class="pr-cards" id="myReviewsContainer"></div>

            </div>

        </div>
    </div>
</div>

<!-- EDIT REVIEW MODAL -->
<div class="pr-modal-overlay" id="editReviewOverlay">
    <div class="pr-modal-content">
        <div class="pr-modal-header">
            <h2 id="editReviewModalTitle">Edit Review</h2>
            <button class="pr-modal-close-btn" id="editReviewCloseBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.75"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="m15 9-6 6"/><path d="m9 9 6 6"/>
                </svg>
            </button>
        </div>
        <div class="pr-modal-body">
            <div class="pr-modal-stars-wrapper">
                <div class="pr-modal-stars" id="editRatingStarsPicker">
                    <span class="pr-modal-star" data-value="1">★</span>
                    <span class="pr-modal-star" data-value="2">★</span>
                    <span class="pr-modal-star" data-value="3">★</span>
                    <span class="pr-modal-star" data-value="4">★</span>
                    <span class="pr-modal-star" data-value="5">★</span>
                </div>
                <p class="pr-modal-stars-label" id="editRatingStarsLabel">Select a rating</p>
            </div>
            <div class="pr-modal-field">
                <label for="editReviewTitleInput">Title <span class="pr-modal-optional">(optional)</span></label>
                <input type="text" id="editReviewTitleInput" maxlength="200" placeholder="Short summary of your experience">
            </div>
            <div class="pr-modal-field">
                <label for="editReviewTextInput">Your review <span class="pr-modal-required">*</span></label>
                <textarea id="editReviewTextInput" maxlength="5000" rows="5" placeholder="Share your experience..."></textarea>
            </div>
            <p class="pr-modal-error"   id="editReviewError"></p>
            <p class="pr-modal-success" id="editReviewSuccess"></p>
        </div>
        <div class="pr-modal-footer">
            <button class="pr-modal-cancel-btn" id="editReviewCancelBtn">Cancel</button>
            <button class="pr-modal-submit-btn" id="editReviewSubmitBtn">Save Changes</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="pr-modal-overlay" id="deleteReviewOverlay">
    <div class="pr-delete-modal-content">
        <div class="pr-delete-modal-header">
            <h2>Delete Review</h2>
            <button class="pr-modal-close-btn" id="deleteReviewCloseBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.75"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="m15 9-6 6"/><path d="m9 9 6 6"/>
                </svg>
            </button>
        </div>
        <div class="pr-delete-modal-body">
            <p>Are you sure you want to delete this review? This action cannot be undone.</p>
        </div>
        <div class="pr-delete-modal-footer">
            <button class="pr-modal-cancel-btn" id="deleteReviewCancelBtn">Cancel</button>
            <button class="pr-delete-confirm-btn" id="deleteReviewConfirmBtn">Yes, delete</button>
        </div>
    </div>
</div>

<!-- Manage Subscription Modal -->
<div id="manage-subscription-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Your Subscription</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="selected-subscription-id" value="">
            <input type="hidden" id="selected-subscription-can-cancel" value="0">
            <input type="hidden" id="selected-subscription-invoice-url" value="">

            <div class="subscription-info subscription-info--dynamic">
                <div class="info-row">
                    <span class="label">Plan</span>
                    <span class="value" id="modal-subscription-plan">-</span>
                </div>
                <div class="info-row">
                    <span class="label">Chatbot</span>
                    <span class="value" id="modal-subscription-chatbot">-</span>
                </div>
                <div class="info-row">
                    <span class="label">Price</span>
                    <span class="value" id="modal-subscription-price">-</span>
                </div>
                <div class="info-row">
                    <span class="label" id="modal-subscription-period-end-label">Period end</span>
                    <span class="value" id="modal-subscription-next-billing">-</span>
                </div>
                <div class="info-row">
                    <span class="label">Auto-renew</span>
                    <span class="value" id="modal-subscription-auto-renew">-</span>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value" id="modal-subscription-status">-</span>
                </div>
            </div>

            <div class="modal-actions modal-actions--dynamic">
                <button class="modal-action-btn" id="change-plan-btn">
                    Upgrade Plan
                </button>
                <button class="modal-action-btn" id="update-payment-btn">
                    Update Payment Method
                </button>
                <button class="modal-action-btn" id="renew-subscription-btn" style="display: none;">
                    Renew Promotion
                </button>
                <button class="modal-action-btn" id="view-invoice-btn">
                    View Latest Invoice
                </button>
                <button class="modal-action-btn danger" id="cancel-subscription-modal-btn">
                    Cancel Subscription
                </button>
            </div>
            <?php if (false && $subscription): ?>
                <div class="subscription-info">
                    <div class="info-row">
                        <span class="label">📦 Plan</span>
                        <span class="value"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">💰 Price</span>
                        <span class="value">
                            <?php echo $subscription['currency_symbol']; ?><?php echo number_format(floatval($subscription['period_price'] ?? $subscription['price']), 2); ?>
                            / <?php echo strtolower($subscription['billing_period']); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">📅 Next billing</span>
                        <span class="value"><?php echo date('F j, Y', strtotime($subscription['end_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">🔄 Auto-renew</span>
                        <span class="value"><?php echo $subscription['auto_renew'] ? 'ON' : 'OFF'; ?></span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="modal-action-btn" id="change-plan-btn">
                        🔄 Change Plan
                    </button>
                    <button class="modal-action-btn" id="update-payment-btn">
                        💳 Update Payment Method
                    </button>
                    <button class="modal-action-btn" id="view-invoice-btn">
                        📄 View Latest Invoice
                    </button>
                    <button class="modal-action-btn danger" id="cancel-subscription-modal-btn">
                        ❌ Cancel Subscription
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Subscription Modal -->
<div id="cancel-subscription-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Cancel Subscription?</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="cancel-form-state" class="cancel-form-state">
                <div id="cancel-subscription-items" class="cancel-confirm-summary"></div>

                <div class="cancel-options">
                    <label class="radio-option">
                        <input type="radio" name="cancel-type" value="at-period-end" checked>
                        <div class="option-content">
                            <div class="option-title">Cancel at end of billing period</div>
                            <div class="option-description">
                                <span id="cancel-period-end-date"></span>
                                <br>You'll keep access until then.
                            </div>
                        </div>
                    </label>

                    <label class="radio-option">
                        <input type="radio" name="cancel-type" value="immediately">
                        <div class="option-content">
                            <div class="option-title">Cancel immediately</div>
                            <div class="option-description">
                                You'll lose access now. No refund for remaining days.
                            </div>
                        </div>
                    </label>
                </div>

                <div class="modal-actions" style="flex-direction:row; justify-content:flex-end;">
                    <button class="cancel-btn" id="keep-plan-btn">Keep Plan</button>
                    <button class="danger-btn" id="confirm-cancel-btn">Cancel Subscription</button>
                </div>
            </div>

            <div id="cancel-processing-state" class="cancel-processing-state" hidden>
                <span class="cancel-processing-state__spinner" aria-hidden="true"></span>
                <div>
                    <strong id="cancel-processing-title">Updating subscription</strong>
                    <p id="cancel-processing-message">Please wait while we update your subscription.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div id="delete-account-modal" class="modal" style="display: none;">
    <div class="modal-content delete-account-modal__content">
        <div class="modal-header delete-account-modal__header">
            <div>
                <span class="delete-account-modal__eyebrow">Permanent action</span>
                <h2>Delete Account</h2>
            </div>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body delete-account-modal__body">
            <div class="delete-account-modal__warning">
                <span class="delete-account-modal__warning-icon">!</span>
                <div class="delete-account-modal__warning-copy">
                    <strong>This cannot be undone.</strong>
                    <span>Your profile, saved account details, and active access will be removed.</span>
                </div>
            </div>
            <?php if ($hasBlockingActiveSubscriptions): ?>
            <div class="delete-account-modal__condition">
                <strong>You cannot delete this profile while promotions are active or pending.</strong>
                <span>Cancel every active promotion first and wait until access ends. After no active or pending subscriptions remain, account deletion will be available.</span>
            </div>
            <?php endif; ?>
            
            <div class="form-group delete-account-modal__confirm">
                <label class="info-field-static-label" for="delete-confirmation-input">Type DELETE to confirm</label>
                <input
                    type="text"
                    id="delete-confirmation-input"
                    placeholder="DELETE"
                    autocomplete="off"
                    <?php echo $hasBlockingActiveSubscriptions ? 'disabled' : ''; ?>
                >
            </div>

            <div class="modal-actions delete-account-modal__actions">
                <button class="cancel-btn" id="delete-account-cancel-btn">Cancel</button>
                <button
                    class="danger-btn"
                    id="confirm-delete-btn"
                    disabled
                    data-blocked-by-subscriptions="<?php echo $hasBlockingActiveSubscriptions ? '1' : '0'; ?>"
                >
                    Delete My Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirm Modal -->
<div id="confirm-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
            <h2 id="confirm-modal-title">Are you sure?</h2>
        </div>
        <div class="modal-body">
            <p id="confirm-modal-message" style="color:var(--profile-text);font-size:15px;margin:0 0 24px 0;"></p>
            <div id="confirm-modal-extra"></div>
            <div class="modal-actions" style="flex-direction:row;justify-content:flex-end;gap:12px;">
                <button class="cancel-btn" id="confirm-modal-cancel">Go Back</button>
                <button class="danger-btn" id="confirm-modal-confirm">Yes, Cancel</button>
            </div>
        </div>
    </div>
</div>

<script src="js/profile.js?v=<?= filemtime(__DIR__ . '/js/profile.js') ?>"></script>
<script src="js/profileReviews.js?v=<?= filemtime(__DIR__ . '/js/profileReviews.js') ?>" defer></script>
<script src="js/togglePasswordProfile.js?v=<?= filemtime(__DIR__ . '/js/togglePasswordProfile.js') ?>"></script>

<?php
require '../inc/footer.php';
?>
