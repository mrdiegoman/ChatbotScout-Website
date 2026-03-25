<?php
/**
 * Checkout Page — Stripe Elements
 *
 * Prijíma chatbot_id + promotion_id (+ voliteľne promotion_id_2 pre bundle).
 * JS logika je v js/checkout.js.
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

if (!$isLoggedIn) {
    header('Location: login.php?redirect=advertise.php');
    exit;
}

$chatbotId    = trim($_GET['chatbot_id']    ?? '');
$promotionId  = trim($_GET['promotion_id']  ?? '');
$promotionId2 = trim($_GET['promotion_id_2'] ?? '');

if (!$chatbotId || !$promotionId) {
    header('Location: advertise.php');
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
    header('Location: advertise.php');
    exit;
}

$finalPrice = floatval($orderDetails['price']) * (1 - floatval($orderDetails['discount_percentage']) / 100);

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
        $finalPrice2 = floatval($orderDetails2['price']) * (1 - floatval($orderDetails2['discount_percentage']) / 100);
    }
}

$totalPrice = $finalPrice + $finalPrice2;

$stripeConfig   = require '../config/stripe.php';
$publishableKey = $stripeConfig['publishable_key'];

$slotLabel = match($orderDetails['slot_type']) {
    'Homepage'      => 'Homepage Visibility',
    'Chatbots page' => 'Chatbots Page Visibility',
    default         => $orderDetails['slot_type'],
};

$periodLabel = $orderDetails['billing_period_type'] === 'yearly' ? 'Annual' : 'Monthly';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — ChatbotScout</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="../css/checkout.css">
</head>
<body>

<div class="checkout-page">
    <div class="checkout-container">
        <!-- ── Order Summary ──────────────────────────────────────────────── -->
        <div class="checkout-summary">
            <div class="checkout-summary__logo">
                <img src="../img/ChatbotScout_logo.png" alt="ChatbotScout">
            </div>

            <h2 class="checkout-summary__title">Order Summary</h2>

            <div class="checkout-summary__item">
                <div class="checkout-summary__item-icon">📢</div>
                <div class="checkout-summary__item-info">
                    <strong><?php echo htmlspecialchars($slotLabel); ?></strong> <br>  For:
                    <span><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></span> <br> Type:
                    <span class="checkout-summary__period"><?php echo strtoupper($periodLabel); ?> SUBSCRIPTION</span>
                </div>
            </div>

            <?php if ($orderDetails2): ?>
            <div class="checkout-summary__item">
                <div class="checkout-summary__item-icon">📢</div>
                <div class="checkout-summary__item-info">
                    <strong><?php echo htmlspecialchars($orderDetails2['slot_type']); ?> Visibility</strong>
                    <span><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></span>
                    <span class="checkout-summary__period"><?php echo strtoupper($periodLabel); ?> SUBSCRIPTION</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="checkout-summary__divider"></div>

            <div class="checkout-summary__total">
                <span>Total due today</span>
                <strong><?php echo $orderDetails['currency_symbol'] . number_format($totalPrice, 2); ?></strong>
            </div>

            <div class="checkout-summary__renew">
                🔄 Renews automatically every <?php echo $orderDetails['billing_period_type'] === 'yearly' ? 'year' : 'month'; ?>
            </div>

            <a href="advertise.php" class="checkout-summary__back">← Cancel and go back</a>
        </div>

        <!-- ── Payment Form ───────────────────────────────────────────────── -->
        <div class="checkout-payment">
            <h2 class="checkout-payment__title">Payment details</h2>
            <p class="checkout-payment__secured">Secured by Stripe 🔒</p>

            <div id="stripe-loading">
                <p style="color:#6b7280;font-size:14px;">⏳ Loading payment form...</p>
            </div>
            <div id="payment-element" style="display:none;"></div>

            <div id="checkoutError" class="checkout-payment__error" style="display:none;"></div>

            <button id="payBtn" class="checkout-payment__btn">
                <span id="payBtnText">Pay <?php echo $orderDetails['currency_symbol'] . number_format($totalPrice, 2); ?></span>
                <span id="payBtnLoader" style="display:none;">Processing...</span>
            </button>

            <!-- <p class="checkout-payment__terms">
                By completing this purchase you agree to our
                <a href="terms.php">Terms of Service</a>.
                You can cancel your subscription at any time from your profile.
            </p> -->
        </div>
    </div>
</div>

<!-- PHP premenné pre checkout.js -->
<script nonce="<?= $cspNonce ?>">
window.checkoutData = {
    chatbotId:     <?php echo json_encode($chatbotId); ?>,
    promotionId:   <?php echo json_encode($promotionId); ?>,
    promotionId2:  <?php echo json_encode($promotionId2 ?: null); ?>,
    stripePubKey:  <?php echo json_encode($publishableKey); ?>,
};
</script>

<script src="https://js.stripe.com/v3/"></script>
<script src="../js/checkout.js"></script>

</body>
</html>