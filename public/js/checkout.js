/**
 * checkout.js
 * Stripe Elements payment form logic.
 */

const {
    chatbotId,
    promotionId,
    promotionId2,
    billingPeriod,
    upgradeFromSubscriptionId,
    stripePubKey
} = window.checkoutData;

const stripe = Stripe(stripePubKey);
const isUpgradeCheckout = Boolean(upgradeFromSubscriptionId);

let elements = null;
let clientSecret = null;
let paymentFormReady = false;
let upgradePaymentCreated = false;
let isProcessing = false;

const stripeLoading = document.getElementById('stripe-loading');
const paymentEl = document.getElementById('payment-element');
const payBtn = document.getElementById('payBtn');
const payBtnText = document.getElementById('payBtnText');
const payBtnLoader = document.getElementById('payBtnLoader');
const errorDiv = document.getElementById('checkoutError');
const checkoutTotalDue = document.getElementById('checkoutTotalDue');
const savedPaymentMethod = document.getElementById('saved-payment-method');
const missingPaymentMethod = document.getElementById('missing-payment-method');
const savedPaymentMethodTitle = document.getElementById('saved-payment-method-title');
const savedPaymentMethodMeta = document.getElementById('saved-payment-method-meta');
const managePaymentMethodBtn = document.getElementById('manage-payment-method-btn');

payBtn.disabled = true;

async function init() {
    if (isUpgradeCheckout) {
        await initUpgradePreview();
        return;
    }

    await initPaymentIntent();
}

async function initUpgradePreview() {
    try {
        const data = await fetchStripeAction('preview_bundle_upgrade');

        if (!data.success) {
            showError(data.message || 'Failed to calculate upgrade price.');
            stripeLoading.style.display = 'none';
            syncPayButton();
            return;
        }

        updateAmount(data);
        renderPaymentMethod(data.payment_method || null);
        stripeLoading.style.display = 'none';
        paymentEl.style.display = 'none';
        syncPayButton();
    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        stripeLoading.style.display = 'none';
        syncPayButton();
        console.error(e);
    }
}

async function initPaymentIntent() {
    try {
        const data = await fetchStripeAction('create_payment_intent');

        if (!data.success) {
            showError(data.message || 'Failed to initialize payment.');
            stripeLoading.style.display = 'none';
            syncPayButton();
            return;
        }

        updateAmount(data);

        if (data.paid) {
            handleAlreadyPaidCheckout(data);
            return;
        }

        clientSecret = data.client_secret;
        if (!clientSecret) {
            showError('Payment form is not available for this invoice. Please refresh and try again.');
            stripeLoading.style.display = 'none';
            syncPayButton();
            return;
        }

        mountPaymentElement();
    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        stripeLoading.style.display = 'none';
        syncPayButton();
        console.error(e);
    }
}

payBtn.addEventListener('click', async () => {
    if (isUpgradeCheckout && !upgradePaymentCreated) {
        await startUpgradePayment();
        return;
    }

    if (!elements || !clientSecret || !paymentFormReady) return;

    setLoading(true);
    clearError();

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: new URL('payment-success.php', window.location.href).href,
        },
    });

    if (error) {
        showError(error.message);
        setLoading(false);
    }
});

if (managePaymentMethodBtn) {
    managePaymentMethodBtn.addEventListener('click', openPaymentMethodPortal);
}

async function startUpgradePayment() {
    setLoading(true);
    clearError();

    try {
        const data = await fetchStripeAction('create_payment_intent');

        if (!data.success) {
            showError(data.message || 'Failed to start upgrade payment.');
            setLoading(false);
            return;
        }

        updateAmount(data);

        if (data.paid) {
            handleAlreadyPaidCheckout(data);
            return;
        }

        clientSecret = data.client_secret;
        if (!clientSecret) {
            showError('Payment form is not available for this invoice. Please refresh and try again.');
            setLoading(false);
            return;
        }

        upgradePaymentCreated = true;
        stripeLoading.innerHTML = '<p style="color:#6b7280;font-size:14px;">Loading payment form...</p>';
        stripeLoading.style.display = 'block';
        mountPaymentElement();
        setLoading(false);
    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        setLoading(false);
        console.error(e);
    }
}

async function fetchStripeAction(action) {
    const res = await fetch(`app/stripeActions.php?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            chatbot_id: chatbotId,
            promotion_id: promotionId,
            promotion_id2: promotionId2,
            billing_period: billingPeriod,
            upgrade_from_subscription_id: upgradeFromSubscriptionId,
        }),
    });

    const text = await res.text();
    let data = null;
    try {
        data = text ? JSON.parse(text) : null;
    } catch (e) {
        data = null;
    }

    if (!data) {
        return {
            success: false,
            message: res.ok ? 'Invalid server response.' : 'Server error',
        };
    }

    return data;
}

function mountPaymentElement() {
    paymentFormReady = false;
    syncPayButton();

    elements = stripe.elements({
        clientSecret,
        appearance: {
            theme: 'stripe',
            variables: {
                colorPrimary: '#6b3ff6',
                colorBackground: '#ffffff',
                colorText: '#1a1a2e',
                colorDanger: '#ef4444',
                fontFamily: 'Outfit, system-ui, sans-serif',
                borderRadius: '10px',
                spacingUnit: '5px',
            },
        },
    });

    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');

    paymentElement.on('ready', () => {
        stripeLoading.style.display = 'none';
        paymentEl.style.display = 'block';
        paymentFormReady = true;
        syncPayButton();
    });
}

function setLoading(loading) {
    isProcessing = loading;
    payBtnText.style.display = loading ? 'none' : 'inline';
    payBtnLoader.style.display = loading ? 'inline' : 'none';
    syncPayButton();
}

function syncPayButton() {
    const needsReadyPaymentForm = clientSecret && !paymentFormReady;
    const waitingForInitialForm = !isUpgradeCheckout && !clientSecret;
    payBtn.disabled = isProcessing || waitingForInitialForm || needsReadyPaymentForm;
    if (managePaymentMethodBtn) {
        managePaymentMethodBtn.disabled = isProcessing;
    }
}

function updateAmount(data) {
    if (data.amount === undefined || !data.currency) {
        return;
    }

    const formattedAmount = formatMoney(Number(data.amount), data.currency);
    payBtnText.textContent = `Pay ${formattedAmount}`;
    if (checkoutTotalDue) {
        checkoutTotalDue.textContent = formattedAmount;
    }
}

function handleAlreadyPaidCheckout(data) {
    clearError();
    paymentFormReady = false;
    isProcessing = false;
    stripeLoading.innerHTML = '<p style="color:#16a34a;font-size:14px;">Payment confirmed. Redirecting...</p>';
    stripeLoading.style.display = 'block';
    paymentEl.style.display = 'none';
    payBtn.disabled = true;
    payBtnText.style.display = 'inline';
    payBtnLoader.style.display = 'none';
    payBtnText.textContent = 'Payment confirmed';

    const redirectUrl = data.redirect_url || 'profile.php?tab=billing';
    window.setTimeout(() => {
        window.location.href = new URL(redirectUrl, window.location.href).href;
    }, 700);
}

function renderPaymentMethod(paymentMethod) {
    if (!savedPaymentMethod || !missingPaymentMethod) {
        return;
    }

    if (!paymentMethod) {
        savedPaymentMethod.hidden = true;
        missingPaymentMethod.hidden = false;
        return;
    }

    missingPaymentMethod.hidden = true;
    savedPaymentMethod.hidden = false;

    const brand = paymentMethod.brand || paymentMethod.label || 'Card';
    const last4 = paymentMethod.last4 ? ` ending in ${paymentMethod.last4}` : '';
    savedPaymentMethodTitle.textContent = `${brand}${last4}`;

    if (paymentMethod.exp_month && paymentMethod.exp_year) {
        savedPaymentMethodMeta.textContent = `Expires ${String(paymentMethod.exp_month).padStart(2, '0')}/${paymentMethod.exp_year}`;
    } else {
        savedPaymentMethodMeta.textContent = paymentMethod.type || 'Saved payment method';
    }
}

async function openPaymentMethodPortal() {
    if (!upgradeFromSubscriptionId) {
        return;
    }

    managePaymentMethodBtn.disabled = true;
    clearError();

    try {
        const response = await fetch('app/profileActions.php?action=stripe_customer_portal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                subscription_id: upgradeFromSubscriptionId,
                return_url: window.location.href,
            }),
        });
        const text = await response.text();
        let result = null;
        try {
            result = text ? JSON.parse(text) : null;
        } catch (e) {
            result = null;
        }

        if (!result?.success || !result.url) {
            showError(result?.message || 'Could not open payment method settings.');
            managePaymentMethodBtn.disabled = false;
            return;
        }

        window.location.href = result.url;
    } catch (e) {
        showError('Could not open payment method settings. Please try again.');
        managePaymentMethodBtn.disabled = false;
        console.error(e);
    }
}

function showError(msg) {
    errorDiv.textContent = msg;
    errorDiv.style.display = 'block';
}

function clearError() {
    errorDiv.textContent = '';
    errorDiv.style.display = 'none';
}

function formatMoney(amount, currency) {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: String(currency).toUpperCase(),
        }).format(amount);
    } catch (e) {
        return `${String(currency).toUpperCase()} ${amount.toFixed(2)}`;
    }
}

init();
