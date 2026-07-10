<?php
/**
 * Checkout Page — Stripe Elements
 *
 * Prijíma chatbot_id + promotion_id (+ voliteľne promotion_id_2 pre bundle).
 * JS logika je v js/checkout.js.
 */

require '../inc/bootstrap.php';
require 'app/auth.php';

if (!$isLoggedIn) {
    header('Location: login?redirect=advertise');
    exit;
}

$chatbotId    = trim($_GET['chatbot_id']    ?? '');
$promotionId  = trim($_GET['promotion_id']  ?? '');
$promotionId2 = trim($_GET['promotion_id_2'] ?? '');
$requestedBillingPeriod = trim($_GET['billing_period'] ?? '');
$upgradeFromSubscriptionId = trim($_GET['upgrade_from_subscription_id'] ?? '');

if (!$chatbotId || !$promotionId) {
    header('Location: advertise');
    exit;
}

// ── Load promotion + chatbot details for order summary ───────────────────────
$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT
        p.id            AS promotion_id,
        p.slot_type,
        p.price,
        p.discount_percentage,
        bp.type         AS billing_period_type,
        c.symbol        AS currency_symbol,
        c.code          AS currency_code,
        ch.name         AS chatbot_name
    FROM promotions p
    JOIN billing_periods bp ON bp.id = p.billing_period_id
    JOIN currencies      c  ON c.id  = p.currency_id
    JOIN chatbots        ch ON ch.id = :chatbot_id
    WHERE p.id             = :promotion_id
      AND p.is_active      = 1
      AND ch.owner_user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'promotion_id' => $promotionId,
    'chatbot_id'   => $chatbotId,
    'user_id'      => $user['id'],
]);
$orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orderDetails) {
    header('Location: advertise');
    exit;
}

$billingPeriod = in_array($requestedBillingPeriod, ['monthly', 'yearly'], true)
    ? $requestedBillingPeriod
    : $orderDetails['billing_period_type'];

$finalPrice = promotionSingleTotalPrice(
    floatval($orderDetails['price']),
    floatval($orderDetails['discount_percentage']),
    $billingPeriod
);

// Bundle: načítaj druhý promotion ak existuje
$orderDetails2 = null;
$finalPrice2   = 0;
if ($promotionId2) {
    $stmt2 = $db->prepare("
        SELECT
            p.id            AS promotion_id,
            p.slot_type,
            p.price,
            p.discount_percentage,
            bp.type         AS billing_period_type,
            c.symbol        AS currency_symbol,
            c.code          AS currency_code
        FROM promotions p
        JOIN billing_periods bp ON bp.id = p.billing_period_id
        JOIN currencies      c  ON c.id  = p.currency_id
        WHERE p.id        = :promotion_id
          AND p.is_active = 1
        LIMIT 1
    ");
    $stmt2->execute(['promotion_id' => $promotionId2]);
    $orderDetails2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($orderDetails2) {
        if ($orderDetails2['currency_code'] !== $orderDetails['currency_code']) {
            header('Location: advertise');
            exit;
        }

        $finalPrice2 = promotionBundleLineTotalPrice(
            floatval($orderDetails2['price']),
            $billingPeriod
        );
    }
}

if ($orderDetails2) {
    $finalPrice = promotionBundleLineTotalPrice(
        floatval($orderDetails['price']),
        $billingPeriod
    );
}

$upgradeContext = null;
if ($upgradeFromSubscriptionId !== '') {
    if ($promotionId2) {
        header('Location: advertise');
        exit;
    }

    $upgradeStmt = $db->prepare("
        SELECT
            s.id AS subscription_id,
            s.stripe_subscription_id,
            s.start_date,
            s.end_date,
            s.cancel_at_period_end,
            p.id AS existing_promotion_id,
            p.slot_type AS existing_slot_type,
            p.price AS existing_price,
            p.discount_percentage AS existing_discount_percentage,
            bp.type AS existing_plan_billing_period,
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
            ) AS latest_payment_billing_period
        FROM subscriptions s
        JOIN promotions p ON p.id = s.promotions_id
        JOIN billing_periods bp ON bp.id = p.billing_period_id
        WHERE s.id = :subscription_id
          AND s.user_id = :user_id
          AND s.chatbot_id = :chatbot_id
          AND s.subscription_type = 'promotion'
          AND s.status IN ('active', 'trialing', 'past_due')
        LIMIT 1
    ");
    $upgradeStmt->execute([
        'subscription_id' => $upgradeFromSubscriptionId,
        'user_id' => $user['id'],
        'chatbot_id' => $chatbotId,
    ]);
    $upgradeContext = $upgradeStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$upgradeContext || empty($upgradeContext['stripe_subscription_id'])) {
        header('Location: advertise');
        exit;
    }

    $existingPlanBillingPeriod = promotionNormalizeBillingPeriod($upgradeContext['existing_plan_billing_period'] ?? 'monthly');
    $existingPaymentBillingPeriod = promotionNormalizeBillingPeriod($upgradeContext['latest_payment_billing_period'] ?? '', '');
    $existingDateBillingPeriod = promotionBillingPeriodFromDates(
        $upgradeContext['start_date'] ?? null,
        $upgradeContext['end_date'] ?? null,
        $existingPlanBillingPeriod
    );
    $existingBillingPeriod = $existingPaymentBillingPeriod
        ?: (in_array($existingDateBillingPeriod, ['monthly', 'yearly'], true) ? $existingDateBillingPeriod : $existingPlanBillingPeriod);

    $missingSlot = $upgradeContext['existing_slot_type'] === 'Homepage' ? 'Chatbots page' : 'Homepage';
    if ($existingBillingPeriod !== $billingPeriod || $orderDetails['slot_type'] !== $missingSlot) {
        header('Location: advertise');
        exit;
    }
}

$isBundleUpgrade = (bool) $upgradeContext;
$totalPrice = $finalPrice + $finalPrice2;

$stripeConfig   = require '../config/stripe.php';
$publishableKey = $stripeConfig['publishable_key'];

$slotLabel = match($orderDetails['slot_type']) {
    'Homepage'      => 'Homepage Visibility',
    'Chatbots page' => 'Chatbots Page Visibility',
    default         => $orderDetails['slot_type'],
};

$slotLabel2 = $orderDetails2 ? match($orderDetails2['slot_type']) {
    'Homepage'      => 'Homepage Visibility',
    'Chatbots page' => 'Chatbots Page Visibility',
    default         => $orderDetails2['slot_type'],
} : '';

$existingUpgradeSlotLabel = $upgradeContext ? match($upgradeContext['existing_slot_type']) {
    'Homepage'      => 'Homepage Visibility',
    'Chatbots page' => 'Chatbots Page Visibility',
    default         => $upgradeContext['existing_slot_type'],
} : '';

$periodLabel = $billingPeriod === 'yearly' ? 'Annual' : 'Monthly';
$renewIntervalLabel = $billingPeriod === 'yearly' ? 'year' : 'month';
$standalonePeriodLabel = $billingPeriod === 'yearly' ? 'annual' : 'monthly';
$isUpgradeCancelsAtPeriodEnd = $isBundleUpgrade && !empty($upgradeContext['cancel_at_period_end']);
$renewSummaryLabel = $isUpgradeCancelsAtPeriodEnd
    ? $slotLabel . ' renews every ' . $renewIntervalLabel
    : 'Renews automatically every ' . $renewIntervalLabel;
$upgradeNote = $isUpgradeCancelsAtPeriodEnd
    ? 'Bundle pricing applies until your current period ends. After that, ' . $slotLabel . ' renews at its standalone ' . $standalonePeriodLabel . ' price.'
    : 'Starts today. Your renewal date stays aligned with your current subscription.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — ChatbotScout</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="css/checkout.css?v=<?= filemtime(__DIR__ . '/css/checkout.css') ?>">
</head>
<body>

<div class="checkout-page">
    <div class="checkout-container">
        <!-- ── Order Summary ──────────────────────────────────────────────── -->
        <div class="checkout-summary">
            <div class="checkout-summary__logo">
                <img src="img/ChatbotScout_logo.png" alt="ChatbotScout">
            </div>

            <h2 class="checkout-summary__title">Order Summary</h2>

            <div class="checkout-summary__item">
                <div class="checkout-summary__item-icon" aria-hidden="true"></div>
                <div class="checkout-summary__item-info">
                    <strong><?php echo htmlspecialchars($isBundleUpgrade ? 'Full Bundle Upgrade' : $slotLabel); ?></strong>
                    <span>For: <b><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></b></span>
                    <span>Type: <b class="checkout-summary__period"><?php echo htmlspecialchars($periodLabel); ?> subscription</b></span>
                    <?php if ($isBundleUpgrade): ?>
                    <span>Converts: <b><?php echo htmlspecialchars($existingUpgradeSlotLabel); ?></b> + <b><?php echo htmlspecialchars($slotLabel); ?></b></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($orderDetails2): ?>
            <div class="checkout-summary__item">
                <div class="checkout-summary__item-icon" aria-hidden="true"></div>
                <div class="checkout-summary__item-info">
                    <strong><?php echo htmlspecialchars($slotLabel2); ?></strong>
                    <span>For: <b><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></b></span>
                    <span>Type: <b class="checkout-summary__period"><?php echo htmlspecialchars($periodLabel); ?> subscription</b></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="checkout-summary__divider"></div>

            <div class="checkout-summary__total">
                <span>Total due today</span>
                <strong id="checkoutTotalDue"><?php echo $isBundleUpgrade ? 'Calculated by Stripe' : $orderDetails['currency_symbol'] . number_format($totalPrice, 2); ?></strong>
            </div>

            <?php if ($isBundleUpgrade): ?>
            <div class="checkout-summary__upgrade-note">
                <?php echo htmlspecialchars($upgradeNote); ?>
            </div>
            <?php endif; ?>

            <div class="checkout-summary__renew" data-renew-label="<?php echo htmlspecialchars($renewSummaryLabel); ?>" aria-label="<?php echo htmlspecialchars($renewSummaryLabel); ?>"></div>

            <a href="advertise" class="checkout-summary__back">&larr; Cancel and go back</a>
        </div>

        <!-- ── Payment Form ───────────────────────────────────────────────── -->
        <div class="checkout-payment">
            <h2 class="checkout-payment__title">Payment details</h2>
            <p class="checkout-payment__secured" aria-label="Secured by Stripe"></p>
            <div id="saved-payment-method" class="checkout-payment__saved-card" hidden>
                <div class="checkout-payment__saved-card-icon" aria-hidden="true"></div>
                <div class="checkout-payment__saved-card-main">
                    <span class="checkout-payment__saved-card-label">Payment method</span>
                    <strong id="saved-payment-method-title">Saved card</strong>
                    <span id="saved-payment-method-meta"></span>
                </div>
                <div class="checkout-payment__saved-card-actions">
                    <button id="use-different-payment-method-btn" class="checkout-payment__saved-card-action" type="button">
                        Use a different payment method
                    </button>
                    <button id="manage-payment-method-btn" class="checkout-payment__saved-card-action checkout-payment__saved-card-action--secondary" type="button">
                        Manage saved method
                    </button>
                </div>
            </div>
            <div id="missing-payment-method" class="checkout-payment__saved-card checkout-payment__saved-card--empty" hidden>
                <div class="checkout-payment__saved-card-icon" aria-hidden="true"></div>
                <div class="checkout-payment__saved-card-main">
                    <span class="checkout-payment__saved-card-label">Payment method</span>
                    <strong>No saved card yet</strong>
                    <span>A Stripe payment form will open after you continue.</span>
                </div>
            </div>

            <div id="stripe-loading">
                <p class="checkout-payment__loading-text"><?php echo $isBundleUpgrade ? 'Calculating upgrade amount...' : 'Loading payment form...'; ?></p>
            </div>
            <button id="use-saved-payment-method-btn" class="checkout-payment__back-to-saved" type="button" hidden>
                &larr; Use saved payment method
            </button>
            <div id="express-checkout-wrapper" class="checkout-payment__express-checkout" hidden>
                <div id="express-checkout-element"></div>
                <div class="checkout-payment__express-divider" aria-hidden="true"><span>or use another payment method</span></div>
            </div>
            <div id="payment-element" hidden></div>

            <div id="checkoutError" class="checkout-payment__error" hidden></div>

            <div class="checkbox-container checkout-payment__business-confirm">
                <div class="checkbox-group">
                    <input type="checkbox" id="businessCustomerAck" name="business_customer_acknowledged">
                    <label for="businessCustomerAck">
                        I confirm that I am purchasing this promotional service as a business customer, not as a consumer,
                        and I agree to the <a href="terms-and-conditions" target="_blank" rel="noopener">Terms and Conditions</a>
                        and <a href="privacy-policy" target="_blank" rel="noopener">Privacy and Cookie Policy</a>.
                    </label>
                </div>
            </div>

            <div class="checkout-payment__refund-notice">
                <strong>Promotion purchase note</strong>
                Promotion purchases are <strong>non-refundable except where mandatory law requires otherwise</strong>.
                Please review your chatbot, placement, billing period, and total before paying.
            </div>

            <button id="payBtn" class="checkout-payment__btn">
                <span id="payBtnText"><?php echo $isBundleUpgrade ? 'Continue to secure payment' : 'Pay ' . $orderDetails['currency_symbol'] . number_format($totalPrice, 2); ?></span>
                <span id="payBtnLoader" hidden>Processing...</span>
            </button>

        </div>
    </div>
</div>

<div id="checkoutConfig"
     hidden
     data-chatbot-id="<?php echo htmlspecialchars($chatbotId, ENT_QUOTES, 'UTF-8'); ?>"
     data-promotion-id="<?php echo htmlspecialchars($promotionId, ENT_QUOTES, 'UTF-8'); ?>"
     data-promotion-id-2="<?php echo htmlspecialchars($promotionId2 ?: '', ENT_QUOTES, 'UTF-8'); ?>"
     data-billing-period="<?php echo htmlspecialchars($billingPeriod, ENT_QUOTES, 'UTF-8'); ?>"
     data-upgrade-from-subscription-id="<?php echo htmlspecialchars($upgradeFromSubscriptionId ?: '', ENT_QUOTES, 'UTF-8'); ?>"
     data-stripe-pub-key="<?php echo htmlspecialchars($publishableKey, ENT_QUOTES, 'UTF-8'); ?>"></div>

<script src="https://js.stripe.com/v3/"></script>
<script src="js/checkout.js?v=<?= filemtime(__DIR__ . '/js/checkout.js') ?>"></script>

</body>
</html>
