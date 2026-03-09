<?php
/**
 * Checkout Page — Stripe Elements
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

// Must be logged in
if (!$isLoggedIn) {
    header('Location: login.php?redirect=advertise.php');
    exit;
}

$subscriptionId = trim($_GET['subscription_id'] ?? '');

if (!$subscriptionId) {
    header('Location: advertise.php');
    exit;
}

// Load subscription details for display
$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT
        s.id            AS subscription_id,
        p.slot_type,
        p.price,
        p.discount_percentage,
        bp.type         AS billing_period_type,
        c.symbol        AS currency_symbol,
        c.code          AS currency_code,
        pay.amount      AS payment_amount,
        pay.id          AS payment_id,
        ch.name         AS chatbot_name
    FROM subscriptions s
    JOIN promotions      p   ON p.id   = s.promotions_id
    JOIN billing_periods bp  ON bp.id  = p.billing_period_id
    JOIN currencies      c   ON c.id   = p.currency_id
    JOIN payments        pay ON pay.subscription_id = s.id
    JOIN chatbots        ch  ON ch.id  = s.chatbot_id
    WHERE s.id      = :subscription_id
      AND s.user_id = :user_id
      AND s.status  = 'pending'
    LIMIT 1
");
$stmt->execute([
    'subscription_id' => $subscriptionId,
    'user_id'         => $user['id']
]);
$orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orderDetails) {
    header('Location: advertise.php');
    exit;
}

$stripeConfig    = require '../config/stripe.php';
$publishableKey  = $stripeConfig['publishable_key'];

$slotLabel = match($orderDetails['slot_type']) {
    'Homepage'      => 'Homepage Visibility',
    'Chatbots page' => 'Chatbots Page Visibility',
    default         => $orderDetails['slot_type']
};

$periodLabel = $orderDetails['billing_period_type'] === 'yearly' ? 'Yearly' : 'Monthly';
$amount      = number_format(floatval($orderDetails['payment_amount']), 2);
$symbol      = $orderDetails['currency_symbol'];
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
    <div class="checkout-container">

        <!-- Left: Order Summary -->
        <div class="checkout-summary">
            <div class="checkout-summary__logo">
                <img src="../img/chatbothub_logo.png" alt="ChatbotHub">
            </div>

            <h2 class="checkout-summary__title">Order Summary</h2>

            <div class="checkout-summary__item">
                <div class="checkout-summary__item-icon">📢</div>
                <div class="checkout-summary__item-info">
                    <div class="checkout-summary__item-name"><?php echo htmlspecialchars($slotLabel); ?></div>
                    <div class="checkout-summary__item-chatbot"><?php echo htmlspecialchars($orderDetails['chatbot_name']); ?></div>
                    <div class="checkout-summary__item-period"><?php echo $periodLabel; ?> subscription</div>
                </div>
            </div>

            <div class="checkout-summary__divider"></div>

            <div class="checkout-summary__total">
                <span>Total due today</span>
                <span class="checkout-summary__total-amount"><?php echo $symbol . $amount; ?></span>
            </div>

            <?php if ($orderDetails['billing_period_type'] === 'yearly'): ?>
            <div class="checkout-summary__renewal">
                🔄 Renews automatically every year
            </div>
            <?php else: ?>
            <div class="checkout-summary__renewal">
                🔄 Renews automatically every month
            </div>
            <?php endif; ?>

            <a href="advertise.php" class="checkout-summary__cancel">← Cancel and go back</a>
        </div>

        <!-- Right: Payment Form -->
        <div class="checkout-payment">
            <h2 class="checkout-payment__title">Payment details</h2>
            <p class="checkout-payment__subtitle">Secured by <strong>Stripe</strong> 🔒</p>

            <div id="checkoutError" class="checkout-error" style="display:none;"></div>

            <div id="payment-element"></div>

            <button class="checkout-payment__btn" id="payBtn">
                <span id="payBtnText">Pay <?php echo $symbol . $amount; ?></span>
                <span id="payBtnLoader" style="display:none;">Processing...</span>
            </button>

            <p class="checkout-payment__terms">
                By completing this purchase you agree to our
                <a href="terms.php">Terms of Service</a>.
                You can cancel your subscription at any time from your profile.
            </p>
        </div>

    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
const SUBSCRIPTION_ID  = <?php echo json_encode($subscriptionId); ?>;
const STRIPE_PUB_KEY   = <?php echo json_encode($publishableKey); ?>;

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
            body:    JSON.stringify({ subscription_id: SUBSCRIPTION_ID })
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
                    colorPrimary:       '#6b3ff6',
                    colorBackground:    '#ffffff',
                    colorText:          '#1a1a2e',
                    colorDanger:        '#ef4444',
                    fontFamily:         'Outfit, system-ui, sans-serif',
                    borderRadius:       '10px',
                    spacingUnit:        '5px',
                }
            }
        });

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

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
            return_url: window.location.origin + '/mainwebsite/public/payment-success.php?subscription_id=' + SUBSCRIPTION_ID,
        }
    });

    // confirmPayment only reaches here if there's an error
    if (error) {
        showError(error.message);
        setLoading(false);
    }
});

function setLoading(loading) {
    payBtn.disabled        = loading;
    payBtnText.style.display  = loading ? 'none'   : 'inline';
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
