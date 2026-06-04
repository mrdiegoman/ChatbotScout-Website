<?php
/**
 * Payment Success Page
 */

require '../inc/bootstrap.php';
require 'app/auth.php';
require_once '../inc/stripePaymentFinalizer.php';

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Stripe redirects back with ?payment_intent=pi_xxx from the invoice PaymentIntent.
$paymentIntentId = trim($_GET['payment_intent'] ?? '');
$paymentStatus = '';
$paymentVerificationError = '';
$stripeBillingPeriodType = '';
$db = Database::getInstance()->getConnection();

if ($paymentIntentId) {
    try {
        $localPaymentStmt = $db->prepare("
            SELECT id, stripe_invoice_id
            FROM payments
            WHERE stripe_payment_intent_id = :pi_id
              AND user_id = :user_id
            LIMIT 1
        ");
        $localPaymentStmt->execute([
            'pi_id' => $paymentIntentId,
            'user_id' => $user['id'],
        ]);

        $localPayment = $localPaymentStmt->fetch(PDO::FETCH_ASSOC);

        if ($localPayment) {
            $stripeConfig = require '../config/stripe.php';
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
            if (!empty($stripeConfig['api_version'])) {
                \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
            }

            if (!empty($localPayment['stripe_invoice_id'])) {
                $invoice = \Stripe\Invoice::retrieve($localPayment['stripe_invoice_id']);
                $paymentStatus = (string) ($invoice->status ?? '');

                if ($paymentStatus === 'paid' || !empty($invoice->paid)) {
                    $finalized = finalizeStripePaidInvoice($db, $invoice, $user['id']);
                    $stripeBillingPeriodType = promotionNormalizeBillingPeriod(
                        stripePaymentMetadataValue($invoice->metadata ?? null, 'period_type', ''),
                        ''
                    );
                    if (!$stripeBillingPeriodType && !empty($finalized['subscription_ids'])) {
                        $stripeBillingPeriodType = '';
                    }
                }
            } else {
                $paymentVerificationError = 'Stripe invoice is not available yet.';
            }
        } else {
            $paymentVerificationError = 'Payment could not be matched to your account.';
        }
    } catch (Exception $e) {
        $paymentVerificationError = 'Payment could not be verified yet.';
        error_log('Payment success verification error: ' . $e->getMessage());
    }
}

// Load order details podľa payment_intent_id
$orderDetails = null;
if ($paymentIntentId) {
    $stmt = $db->prepare("
        SELECT
            GROUP_CONCAT(pr.slot_type ORDER BY pr.slot_type SEPARATOR ',') AS slot_types,
            COUNT(*) AS line_count,
            MIN(bp.type) AS plan_billing_period_type,
            MIN(c.symbol) AS currency_symbol,
            SUM(pay.amount) AS payment_amount,
            MAX(pay.metadata) AS payment_metadata,
            MAX(ch.name) AS chatbot_name,
            MAX(s.status) AS subscription_status,
            MIN(s.start_date) AS start_date,
            MAX(s.end_date) AS end_date
        FROM payments pay
        JOIN subscriptions   s   ON s.id  = pay.subscription_id
        JOIN promotions      pr  ON pr.id = s.promotions_id
        JOIN billing_periods bp  ON bp.id = pr.billing_period_id
        JOIN currencies      c   ON c.id  = pr.currency_id
        JOIN chatbots        ch  ON ch.id = pay.chatbot_id
        WHERE pay.stripe_payment_intent_id = :pi_id
          AND pay.user_id                  = :user_id
        GROUP BY pay.stripe_invoice_id, pay.stripe_payment_intent_id
        LIMIT 1
    ");
    $stmt->execute([
        'pi_id'   => $paymentIntentId,
        'user_id' => $user['id'],
    ]);
    $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($orderDetails) {
        $paymentMetadata = json_decode((string) ($orderDetails['payment_metadata'] ?? ''), true);
        $metadataBillingPeriodType = is_array($paymentMetadata)
            ? promotionNormalizeBillingPeriod($paymentMetadata['billing_period'] ?? $paymentMetadata['period_type'] ?? '', '')
            : '';
        $dateBillingPeriodType = promotionBillingPeriodFromDates(
            $orderDetails['start_date'] ?? null,
            $orderDetails['end_date'] ?? null,
            $orderDetails['plan_billing_period_type'] ?? 'monthly'
        );

        $orderDetails['billing_period_type'] = promotionNormalizeBillingPeriod(
            $stripeBillingPeriodType ?: $metadataBillingPeriodType ?: $dateBillingPeriodType,
            $orderDetails['plan_billing_period_type'] ?? 'monthly'
        );
    }
}

$slotLabel = '';
if ($orderDetails) {
    $slotTypes = array_filter(explode(',', (string) ($orderDetails['slot_types'] ?? '')));
    $slotLabel = ((int) ($orderDetails['line_count'] ?? 1) > 1)
        ? 'Full Bundle'
        : match($slotTypes[0] ?? '') {
            'Homepage'      => 'Homepage Visibility',
            'Chatbots page' => 'Chatbots Page Visibility',
            default         => $slotTypes[0] ?? 'Promotion'
        };
}

$isVerifiedSuccess = $orderDetails && !$paymentVerificationError;
$resultTitle = 'Payment processing';
$resultSubtitle = 'Stripe is still confirming this payment. Your profile will update automatically once the payment is confirmed.';
$resultIconClass = 'success-card__icon success-card__icon--pending';
$resultNote = 'No subscription is activated until Stripe confirms that the payment succeeded.';

if ($isVerifiedSuccess) {
    $resultTitle = 'Payment successful';
    $resultSubtitle = 'Your promotion is active and ready to bring more visibility to your chatbot.';
    $resultIconClass = 'success-card__icon';
    $resultNote = 'A confirmation email is on its way. You can manage this subscription anytime from your profile.';
} elseif ($paymentVerificationError) {
    $resultTitle = 'Payment not verified';
    $resultSubtitle = $paymentVerificationError . ' Please refresh this page in a moment or check your billing history.';
    $resultIconClass = 'success-card__icon success-card__icon--error';
    $resultNote = 'Your subscription will not be activated unless Stripe confirms that the payment succeeded.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — ChatbotScout</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="css/payment-success.css?v=<?= filemtime(__DIR__ . '/css/payment-success.css') ?>">
</head>
<body>

<div class="success-page">
    <div class="success-card">

        <div class="<?php echo htmlspecialchars($resultIconClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>

        <h1 class="success-card__title"><?php echo htmlspecialchars($resultTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="success-card__subtitle"><?php echo htmlspecialchars($resultSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if ($orderDetails): ?>
        <div class="success-card__details">
            <div class="success-card__detail-row">
                <span>Plan</span>
                <strong><?php echo htmlspecialchars($slotLabel); ?></strong>
            </div>
            <div class="success-card__detail-row">
                <span>Chatbot</span>
                <strong><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></strong>
            </div>
            <div class="success-card__detail-row">
                <span>Billing</span>
                <strong><?php echo htmlspecialchars(promotionBillingPeriodLabel($orderDetails['billing_period_type'])); ?></strong>
            </div>
            <div class="success-card__detail-row success-card__detail-row--total">
                <span>Amount paid</span>
                <strong><?php echo $orderDetails['currency_symbol'] . number_format(floatval($orderDetails['payment_amount']), 2); ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <div class="success-card__actions">
            <a href="profile.php?tab=billing" class="success-card__btn success-card__btn--primary">
                View My Subscriptions
            </a>
            <a href="chatbotShowcase.php" class="success-card__btn success-card__btn--secondary">
                Browse Chatbots
            </a>
        </div>

        <p class="success-card__note">
            <?php echo htmlspecialchars($resultNote, ENT_QUOTES, 'UTF-8'); ?>
        </p>

    </div>
</div>

</body>
</html>
