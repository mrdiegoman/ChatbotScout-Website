<?php
/**
 * Checkout Page — Stripe Elements
 *
 * ZMENA: Neprijíma subscription_id (subscription ešte neexistuje).
 * Prijíma chatbot_id + promotion_id a načíta details priamo z promotions tabuľky.
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

// Must be logged in
if (!$isLoggedIn) {
    header('Location: login.php?redirect=advertise.php');
    exit;
}

$chatbotId   = trim($_GET['chatbot_id']   ?? '');
$promotionId = trim($_GET['promotion_id'] ?? '');

// Bundle support (druhý promotion)
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
    WHERE p.id           = :promotion_id
      AND p.is_active    = 1
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

// Vypočítaj finálnu cenu
$finalPrice = floatval($orderDetails['price']) * (1 - (floatval($orderDetails['discount_percentage']) / 100));

// Bundle: načítaj druhý promotion ak existuje
$orderDetails2  = null;
$finalPrice2    = 0;
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
        $finalPrice2 = floatval($orderDetails2['price']) * (1 - (floatval($orderDetails2['discount_percentage']) / 100));
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
    <title>Checkout — ChatbotHub</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="../css/checkout.css">
</head>
<body>

<div class="checkout-page">

    <!-- ── Order Summary ──────────────────────────────────────────────── -->
    <div class="checkout-summary">
        <div class="checkout-summary__logo">
            <img src="../img/chatbothub_logo.png" alt="ChatbotHub">
        </div>

        <h2 class="checkout-summary__title">Order Summary</h2>

        <div class="checkout-summary__item">
            <div class="checkout-summary__item-icon">📢</div>
            <div class="checkout-summary__item-info">
                <strong><?php echo htmlspecialchars($slotLabel); ?></strong>
                <span><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></span>
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
            🔄 Renews automatically every <?php echo strtolower($orderDetails['billing_period_type'] === 'yearly' ? 'year' : 'month'); ?>
        </div>

        <a href="advertise.php" class="checkout-summary__back">← Cancel and go back</a>
    </div>

    <!-- ── Payment Form ───────────────────────────────────────────────── -->
    <div class="checkout-payment">
        <h2 class="checkout-payment__title">Payment details</h2>
        <p class="checkout-payment__secured">Secured by Stripe 🔒</p>

        <!-- payment-element skrytý na začiatku -->
        <div id="stripe-loading">
            <p style="color: #6b7280; font-size: 14px;">⏳ Loading payment form...</p>
        </div>
        <div id="payment-element" style="display: none;"></div>
        <div id="payment-element"></div>

        <div id="checkoutError" class="checkout-payment__error" style="display:none;"></div>

        <button id="payBtn" class="checkout-payment__btn">
            <span id="payBtnText">Pay <?php echo $orderDetails['currency_symbol'] . number_format($totalPrice, 2); ?></span>
            <span id="payBtnLoader" style="display:none;">Processing...</span>
        </button>

        <p class="checkout-payment__terms">
            By completing this purchase you agree to our
            <a href="terms.php">Terms of Service</a>.
            You can cancel your subscription at any time from your profile.
        </p>
    </div>

</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
const CHATBOT_ID    = <?php echo json_encode($chatbotId); ?>;
const PROMOTION_ID  = <?php echo json_encode($promotionId); ?>;
const PROMOTION_ID2 = <?php echo json_encode($promotionId2 ?: null); ?>;
const STRIPE_PUB_KEY = <?php echo json_encode($publishableKey); ?>;

const stripe  = Stripe(STRIPE_PUB_KEY);
let elements  = null;
let clientSecret = null;

const payBtn       = document.getElementById('payBtn');
const payBtnText   = document.getElementById('payBtnText');
const payBtnLoader = document.getElementById('payBtnLoader');
const errorDiv     = document.getElementById('checkoutError');

// ── Init: fetch PaymentIntent client_secret ───────────────────────────────────
async function init() {
    try {
        const res  = await fetch('../app/stripeActions.php?action=create_payment_intent', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                chatbot_id:    CHATBOT_ID,
                promotion_id:  PROMOTION_ID,
                promotion_id2: PROMOTION_ID2,
            }),
        });
        const data = await res.json();

        if (!data.success) {
            showError(data.message || 'Failed to initialize payment.');
            payBtn.disabled = true;
            return;
        }

        clientSecret = data.client_secret;

        elements = stripe.elements({
            clientSecret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary:    '#6b3ff6',
                    colorBackground: '#ffffff',
                    colorText:       '#1a1a2e',
                    colorDanger:     '#ef4444',
                    fontFamily:      'Outfit, system-ui, sans-serif',
                    borderRadius:    '10px',
                    spacingUnit:     '5px',
                },
            },
        });

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        paymentElement.on('ready', () => {
            // Skry loading text
            document.getElementById('stripe-loading').style.display = 'none';
            // Zobraz Stripe formulár (so svojim skeletonom)
            document.getElementById('payment-element').style.display = 'block';
        });

    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        console.error(e);
    }
}

// ── Submit payment ────────────────────────────────────────────────────────────
payBtn.addEventListener('click', async () => {
    if (!elements || !clientSecret) return;

    setLoading(true);
    clearError();

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: window.location.origin + '/mainwebsite/public/payment-success.php',
        },
    });

    if (error) {
        showError(error.message);
        setLoading(false);
    }
});

function setLoading(loading) {
    payBtn.disabled            = loading;
    payBtnText.style.display   = loading ? 'none'   : 'inline';
    payBtnLoader.style.display = loading ? 'inline' : 'none';
}

function showError(msg) {
    errorDiv.textContent   = msg;
    errorDiv.style.display = 'block';
}

function clearError() {
    errorDiv.textContent   = '';
    errorDiv.style.display = 'none';
}

init();
</script>

</body>
</html>