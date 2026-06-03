/**
 * checkout.js
 * Stripe Elements — payment form logic
 *
 * Očakáva window.checkoutData nastavený v checkout.php:
 * {
 *   chatbotId, promotionId, promotionId2, billingPeriod, stripePubKey
 * }
 */

const { chatbotId, promotionId, promotionId2, billingPeriod, stripePubKey } = window.checkoutData;

const stripe  = Stripe(stripePubKey);
let elements  = null;
let clientSecret = null;

const stripeLoading = document.getElementById('stripe-loading');
const paymentEl     = document.getElementById('payment-element');
const payBtn        = document.getElementById('payBtn');
const payBtnText    = document.getElementById('payBtnText');
const payBtnLoader  = document.getElementById('payBtnLoader');
const errorDiv      = document.getElementById('checkoutError');

// ── Init: fetch PaymentIntent client_secret ───────────────────────────────────
async function init() {
    try {
        const res  = await fetch('app/stripeActions.php?action=create_payment_intent', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                chatbot_id:    chatbotId,
                promotion_id:  promotionId,
                promotion_id2: promotionId2,
                billing_period: billingPeriod,
            }),
        });
        const data = await res.json();

        if (!data.success) {
            showError(data.message || 'Failed to initialize payment.');
            payBtn.disabled = true;
            stripeLoading.style.display = 'none';
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

        // Skry loading keď je Stripe formulár ready
        paymentElement.on('ready', () => {
            stripeLoading.style.display = 'none';
            paymentEl.style.display     = 'block';
        });

    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        stripeLoading.style.display = 'none';
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
            return_url: new URL('payment-success.php', window.location.href).href,
        },
    });

    // confirmPayment sa dostane sem len ak nastane chyba
    if (error) {
        showError(error.message);
        setLoading(false);
    }
});

// ── Helpers ───────────────────────────────────────────────────────────────────
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
