<?php
/**
 * Profile Page
 * 
 * User profile management: personal info, billing, settings, subscription
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

// Redirect ak nie je prihlásený
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Get active tab from URL
$activeTab = $_GET['tab'] ?? 'profile';
$allowedTabs = ['profile', 'billing', 'settings'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'profile';
}

// Load user's active subscription (if any)
$subscription = null;
$db = Database::getInstance()->getConnection();

$subStmt = $db->prepare("
    SELECT 
        s.*,
        lp.name AS plan_name,
        lpp.price,
        c.symbol AS currency_symbol,
        c.code AS currency_code,
        bp.name AS billing_period
    FROM subscriptions s
    JOIN listing_plan_prices lpp ON s.listing_price_id = lpp.id
    JOIN listing_plans lp ON lpp.plan_id = lp.id
    JOIN currencies c ON lpp.currency_id = c.id
    JOIN billing_periods bp ON lpp.billing_period_id = bp.id
    WHERE s.user_id = :user_id 
    AND s.status = 'active'
    AND s.subscription_type = 'listing'
    LIMIT 1
");
$subStmt->execute(['user_id' => $user['id']]);
$subscription = $subStmt->fetch();

// Load billing history
$payments = [];
if ($activeTab === 'billing') {
    $paymentStmt = $db->prepare("
        SELECT 
            p.*,
            c.symbol AS currency_symbol,
            c.code AS currency_code
        FROM payments p
        JOIN currencies c ON p.currency_id = c.id
        WHERE p.user_id = :user_id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $paymentStmt->execute(['user_id' => $user['id']]);
    $payments = $paymentStmt->fetchAll();
}

require '../inc/header.php';
?>

<link rel="stylesheet" href="../css/profile.css">

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
            <button class="tab-btn <?php echo $activeTab === 'billing' ? 'active' : ''; ?>" 
                    data-tab="billing">
                Billing Info
            </button>
            <button class="tab-btn <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" 
                    data-tab="settings">
                Settings
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
                        <img src="<?php echo isset($user['profile_picture_url'])
                                        ? '../' . $user['profile_picture_url']
                                        : '../img/default-avatar2.png'; ?>" 
                                 alt="Profile picture" 
                                 class="profile-avatar user-avatar-img"
                                 id="current-avatar">
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
                            <label>Name</label>
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
                            <label>Email</label>
                            <div class="field-display">
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
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
                                    <label>Current Password</label>
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
                                    <label>New Password</label>
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
                                    <label>Confirm New Password</label>
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

                    <!-- Active Subscription Card -->
                    <?php if ($subscription): ?>
                    <div class="profile-card subscription-card active">
                        <div class="subscription-header">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h3>Active Subscription</h3>
                        </div>
                        <div class="subscription-details">
                            <div class="sub-plan"><?php echo htmlspecialchars($subscription['plan_name']); ?></div>
                            <div class="sub-price">
                                <?php echo $subscription['currency_symbol']; ?><?php echo number_format($subscription['price'], 2); ?> 
                                / <?php echo strtolower($subscription['billing_period']); ?>
                            </div>
                            <div class="sub-next-billing">
                                Next billing: <strong><?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></strong>
                            </div>
                        </div>
                        <button class="primary-btn" id="manage-subscription-btn">
                            Manage Subscription
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Delete Account -->
                    <div class="profile-card danger-card">
                        
                        <p>Once you delete your account, there is no going back. Please be certain.</p>
                        <button class="danger-btn" id="delete-account-btn">Delete Account</button>
                    </div>

                </div>
            </div>

            <!-- BILLING INFO TAB -->
            <div class="tab-pane <?php echo $activeTab === 'billing' ? 'active' : ''; ?>" 
                 id="billing-tab">
                
                <div class="billing-container">
                    
                    <!-- Filters -->
                    <div class="billing-filters">
                        <div class="filter-group">
                            <label>Date</label>
                            <select id="date-filter">
                                <option value="all">All</option>
                                <option value="this-month">This Month</option>
                                <option value="last-month">Last Month</option>
                                <option value="this-year">This Year</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select id="status-filter">
                                <option value="all">All</option>
                                <option value="succeeded">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Method</label>
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
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Invoice</th>
                                </tr>
                            </thead>
                            <tbody id="payments-tbody">
                                <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="5" class="no-payments">No payment history yet</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr data-date="<?php echo $payment['created_at']; ?>" 
                                        data-status="<?php echo $payment['status']; ?>" 
                                        data-method="<?php echo $payment['payment_method']; ?>">
                                        <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                        <td><?php echo $payment['currency_symbol'] . number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                <?php 
                                                    echo $payment['status'] === 'succeeded' ? 'Paid' : ucfirst($payment['status']); 
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
            <?php if ($subscription): ?>
                <div class="subscription-info">
                    <div class="info-row">
                        <span class="label">📦 Plan</span>
                        <span class="value"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">💰 Price</span>
                        <span class="value">
                            <?php echo $subscription['currency_symbol']; ?><?php echo number_format($subscription['price'], 2); ?> 
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
            <div class="warning-message">
                ⚠️ Are you sure you want to cancel your subscription?
            </div>
            
            <div class="cancel-options">
                <label class="radio-option">
                    <input type="radio" name="cancel-type" value="at-period-end" checked>
                    <div class="option-content">
                        <div class="option-title">Cancel at end of billing period</div>
                        <div class="option-description">
                            <?php if ($subscription): ?>
                                (<?php echo date('F j, Y', strtotime($subscription['end_date'])); ?>)
                                <br>You'll keep access until then.
                            <?php endif; ?>
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

            <div class="form-group">
                <label>Optional: Why are you cancelling?</label>
                <select id="cancel-reason">
                    <option value="">Select a reason...</option>
                    <option value="too-expensive">Too expensive</option>
                    <option value="not-useful">Not getting enough value</option>
                    <option value="found-alternative">Found a better alternative</option>
                    <option value="technical-issues">Technical issues</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="modal-actions">
                <button class="cancel-btn modal-close">Keep Plan</button>
                <button class="danger-btn" id="confirm-cancel-btn">Cancel Subscription</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div id="delete-account-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Delete Account</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="warning-message danger">
                ⚠️ This action cannot be undone. Your account will be permanently deleted.
            </div>
            <p>If you want to recover your account later, please contact support instead.</p>
            
            <div class="form-group">
                <label>Type "DELETE" to confirm:</label>
                <input type="text" id="delete-confirmation-input" placeholder="DELETE">
            </div>

            <div class="modal-actions">
                <button class="cancel-btn modal-close">Cancel</button>
                <button class="danger-btn" id="confirm-delete-btn" disabled>Delete My Account</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/profile.js"></script>
<script src="../js/togglePasswordProfile.js"></script>

<?php
require '../inc/footer.php';
?>
