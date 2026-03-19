<?php
/**
 * Payment Success Page
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Stripe redirectuje s ?payment_intent=pi_xxx
$paymentIntentId = trim($_GET['payment_intent'] ?? '');

// Load order details podľa payment_intent_id
$orderDetails = null;
if ($paymentIntentId) {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT
            pr.slot_type,
            bp.type         AS billing_period_type,
            c.symbol        AS currency_symbol,
            pay.amount      AS payment_amount,
            ch.name         AS chatbot_name,
            s.status        AS subscription_status
        FROM payments pay
        JOIN subscriptions   s   ON s.id  = pay.subscription_id
        JOIN promotions      pr  ON pr.id = s.promotions_id
        JOIN billing_periods bp  ON bp.id = pr.billing_period_id
        JOIN currencies      c   ON c.id  = pr.currency_id
        JOIN chatbots        ch  ON ch.id = pay.chatbot_id
        WHERE pay.stripe_payment_intent_id = :pi_id
          AND pay.user_id                  = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        'pi_id'   => $paymentIntentId,
        'user_id' => $user['id'],
    ]);
    $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);
}

$slotLabel = '';
if ($orderDetails) {
    $slotLabel = match($orderDetails['slot_type']) {
        'Homepage'      => 'Homepage Visibility',
        'Chatbots page' => 'Chatbots Page Visibility',
        default         => $orderDetails['slot_type']
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — ChatbotScout</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="../css/payment-success.css">
</head>
<body>

<div class="success-page">
    <div class="success-card">

        <div class="success-card__icon">✅</div>

        <h1 class="success-card__title">Payment Successful!</h1>
        <p class="success-card__subtitle">Your promotion is now active.</p>

        <?php if ($orderDetails): ?>
        <div class="success-card__details">
            <div class="success-card__detail-row">
                <span>Plan</span>
                <span><?php echo htmlspecialchars($slotLabel); ?></span>
            </div>
            <div class="success-card__detail-row">
                <span>Chatbot</span>
                <span><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></span>
            </div>
            <div class="success-card__detail-row">
                <span>Billing</span>
                <span><?php echo ucfirst($orderDetails['billing_period_type']); ?></span>
            </div>
            <div class="success-card__detail-row">
                <span>Amount paid</span>
                <span><?php echo $orderDetails['currency_symbol'] . number_format(floatval($orderDetails['payment_amount']), 2); ?></span>
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
            You will receive a confirmation email shortly.
            You can manage your subscription anytime from your profile.
        </p>

    </div>
</div>

</body>
</html>
