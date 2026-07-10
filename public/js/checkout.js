/**
 * checkout.js
 * Stripe Elements payment form logic.
 */

const checkoutConfig = document.getElementById('checkoutConfig');
const checkoutDataset = checkoutConfig?.dataset || {};
const chatbotId = checkoutDataset.chatbotId || '';
const promotionId = checkoutDataset.promotionId || '';
const secondaryPromotionId = checkoutDataset.secondaryPromotionId || null;
const billingPeriod = checkoutDataset.billingPeriod || '';
const upgradeFromSubscriptionId = checkoutDataset.upgradeFromSubscriptionId || null;
const stripePubKey = checkoutDataset.stripePubKey || '';

const stripe = Stripe(stripePubKey);
const isUpgradeCheckout = Boolean(upgradeFromSubscriptionId);

let elements = null;
let paymentElement = null;
let expressCheckoutElement = null;
let clientSecret = null;
let paymentFormReady = false;
let upgradePaymentCreated = false;
let isProcessing = false;
let savedPaymentMethodForCheckout = null;
let availableSavedPaymentMethod = null;
let isUsingDifferentPaymentMethod = false;
let initialPaymentIntentStarted = false;

const stripeLoading = document.getElementById('stripe-loading');
const paymentEl = document.getElementById('payment-element');
const expressCheckoutWrapper = document.getElementById('express-checkout-wrapper');
const payBtn = document.getElementById('payBtn');
const payBtnText = document.getElementById('payBtnText');
const payBtnLoader = document.getElementById('payBtnLoader');
const errorDiv = document.getElementById('checkoutError');
const checkoutTotalDue = document.getElementById('checkoutTotalDue');
const savedPaymentMethod = document.getElementById('saved-payment-method');
const missingPaymentMethod = document.getElementById('missing-payment-method');
const savedPaymentMethodTitle = document.getElementById('saved-payment-method-title');
const savedPaymentMethodMeta = document.getElementById('saved-payment-method-meta');
const useDifferentPaymentMethodBtn = document.getElementById('use-different-payment-method-btn');
const managePaymentMethodBtn = document.getElementById('manage-payment-method-btn');
const useSavedPaymentMethodBtn = document.getElementById('use-saved-payment-method-btn');
const businessCustomerAck = document.getElementById('businessCustomerAck');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

payBtn.disabled = true;

function setHidden(element, hidden) {
    if (element) element.hidden = hidden;
}

function setStripeLoadingMessage(message, variant = '') {
    if (!stripeLoading) return;

    const text = document.createElement('p');
    text.className = 'checkout-payment__loading-text';
    if (variant === 'success') {
        text.classList.add('checkout-payment__loading-text--success');
    }
    text.textContent = message;

    stripeLoading.replaceChildren(text);
    setHidden(stripeLoading, false);
}

function hideStripeLoading() {
    setHidden(stripeLoading, true);
}

function setPaymentElementVisible(visible) {
    setHidden(paymentEl, !visible);
}

async function init() {
    if (isUpgradeCheckout) {
        await initUpgradePreview();
        return;
    }

    showBusinessConfirmationPrompt();
    syncPayButton();
}

if (businessCustomerAck) {
    businessCustomerAck.addEventListener('change', async () => {
        clearError();
        syncPayButton();

        if (!businessCustomerAck.checked || isUpgradeCheckout || initialPaymentIntentStarted) {
            return;
        }

        initialPaymentIntentStarted = true;
        setStripeLoadingMessage('Loading payment form...');
        await initPaymentIntent();
    });
}

async function initUpgradePreview() {
    try {
        const data = await fetchStripeAction('preview_bundle_upgrade');

        if (!data.success) {
            showError(data.message || 'Failed to calculate upgrade price.');
            hideStripeLoading();
            syncPayButton();
            return;
        }

        updateAmount(data);
        renderPaymentMethod(data.payment_method || null);
        hideStripeLoading();
        setPaymentElementVisible(false);
        syncPayButton();
    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        hideStripeLoading();
        syncPayButton();
        console.error(e);
    }
}

async function initPaymentIntent() {
    try {
        const data = await fetchStripeAction('create_payment_intent');

        if (!data.success) {
            showError(data.message || 'Failed to initialize payment.');
            hideStripeLoading();
            syncPayButton();
            return;
        }

        updateAmount(data);
        renderPaymentMethod(data.payment_method || null);

        if (data.paid) {
            handleAlreadyPaidCheckout(data);
            return;
        }

        clientSecret = data.client_secret;
        if (!clientSecret) {
            showError('Payment form is not available for this invoice. Please refresh and try again.');
            hideStripeLoading();
            syncPayButton();
            return;
        }

        if (savedPaymentMethodForCheckout) {
            hideStripeLoading();
            setPaymentElementVisible(false);
            paymentFormReady = false;
            syncPayButton();
            return;
        }

        mountPaymentElement();
    } catch (e) {
        showError('Something went wrong. Please refresh and try again.');
        hideStripeLoading();
        syncPayButton();
        console.error(e);
    }
}

payBtn.addEventListener('click', async () => {
    if (isUpgradeCheckout && !upgradePaymentCreated) {
        await startUpgradePayment();
        return;
    }

    if (savedPaymentMethodForCheckout && clientSecret) {
        if (!(await validateCheckoutBeforeConfirm())) return;
        await confirmSavedPaymentMethod();
        return;
    }

    if (!elements || !clientSecret || !paymentFormReady) return;

    setLoading(true);
    clearError();

    if (!(await validateCheckoutBeforeConfirm())) {
        setLoading(false);
        return;
    }

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: paymentSuccessReturnUrl(),
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

if (useDifferentPaymentMethodBtn) {
    useDifferentPaymentMethodBtn.addEventListener('click', showDifferentPaymentMethodForm);
}

if (useSavedPaymentMethodBtn) {
    useSavedPaymentMethodBtn.addEventListener('click', restoreSavedPaymentMethod);
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
        renderPaymentMethod(data.payment_method || savedPaymentMethodForCheckout);

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
        if (savedPaymentMethodForCheckout) {
            hideStripeLoading();
            setPaymentElementVisible(false);
            setLoading(false);
            return;
        }

        setStripeLoadingMessage('Loading payment form...');
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
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
        },
        body: JSON.stringify({
            chatbot_id: chatbotId,
            promotion_id: promotionId,
            promotion_id_2: secondaryPromotionId,
            billing_period: billingPeriod,
            upgrade_from_subscription_id: upgradeFromSubscriptionId,
            business_customer_acknowledged: hasBusinessAcknowledgement(),
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

async function validateCheckoutBeforeConfirm() {
    if (!hasBusinessAcknowledgement()) {
        showError('Please confirm that you are purchasing this promotional service as a business customer.');
        return false;
    }

    const data = await fetchStripeAction('validate_checkout');
    if (data.success) {
        return true;
    }

    showError(data.message || 'This checkout is no longer available. Please refresh and try again.');
    return false;
}

function mountPaymentElement() {
    paymentFormReady = false;
    savedPaymentMethodForCheckout = null;
    unmountPaymentElement();
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

    mountExpressCheckoutElement();

    paymentElement = elements.create('payment', {
        // Apple Pay and Google Pay are wallets presented by Stripe for the existing card PaymentIntent.
        // Each is shown only on supported browsers/devices with an eligible Wallet card.
        wallets: {
            applePay: 'auto',
            googlePay: 'auto',
        },
        paymentMethodOrder: ['apple_pay', 'google_pay', 'card'],
    });
    paymentElement.mount('#payment-element');

    const mountedPaymentElement = paymentElement;
    paymentElement.on('ready', () => {
        if (paymentElement !== mountedPaymentElement) {
            return;
        }

        hideStripeLoading();
        setPaymentElementVisible(true);
        paymentFormReady = true;
        syncPayButton();
    });
}

function mountExpressCheckoutElement() {
    if (!elements || !expressCheckoutWrapper) {
        return;
    }

    // Mount this separately from the Payment Element so eligible wallets are never
    // hidden among card and local-method choices. It still confirms the same Stripe
    // invoice/client secret as the regular form below.
    expressCheckoutWrapper.hidden = false;
    expressCheckoutElement = elements.create('expressCheckout', {
        paymentMethods: {
            applePay: 'auto',
            googlePay: 'auto',
            link: 'never',
            amazonPay: 'never',
            paypal: 'never',
            klarna: 'never',
        },
        buttonType: {
            applePay: 'buy',
            googlePay: 'buy',
        },
        layout: {
            maxColumns: 1,
            maxRows: 1,
            overflow: 'never',
        },
    });
    expressCheckoutElement.mount('#express-checkout-element');

    const mountedExpressCheckoutElement = expressCheckoutElement;
    expressCheckoutElement.on('ready', (event) => {
        if (expressCheckoutElement !== mountedExpressCheckoutElement) {
            return;
        }

        const methods = event.availablePaymentMethods || {};
        expressCheckoutWrapper.hidden = !(methods.googlePay || methods.applePay);
    });

    expressCheckoutElement.on('confirm', confirmExpressCheckout);
    expressCheckoutElement.on('loaderror', (event) => {
        if (expressCheckoutElement === mountedExpressCheckoutElement) {
            expressCheckoutWrapper.hidden = true;
        }
        console.warn('Stripe wallet checkout could not be loaded.', event.error);
    });
}

async function confirmExpressCheckout(event) {
    if (isProcessing || !elements || !clientSecret) {
        event.paymentFailed({ message: 'Payment is not ready yet. Please try again.' });
        return;
    }

    setLoading(true);
    clearError();

    if (!(await validateCheckoutBeforeConfirm())) {
        event.paymentFailed({ message: 'Please confirm the checkout details before paying.' });
        setLoading(false);
        return;
    }

    const { error: submitError } = await elements.submit();
    if (submitError) {
        event.paymentFailed({ message: submitError.message || 'Payment details could not be submitted.' });
        showError(submitError.message || 'Payment details could not be submitted.');
        setLoading(false);
        return;
    }

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: paymentSuccessReturnUrl(),
        },
    });

    if (error) {
        event.paymentFailed({ message: error.message || 'Payment could not be completed.' });
        showError(error.message || 'Payment could not be completed.');
        setLoading(false);
    }
}

function unmountPaymentElement() {
    if (expressCheckoutElement) {
        expressCheckoutElement.unmount();
        expressCheckoutElement = null;
    }
    if (expressCheckoutWrapper) {
        expressCheckoutWrapper.hidden = true;
    }

    if (paymentElement) {
        paymentElement.unmount();
        paymentElement = null;
    }

    elements = null;
}

function setLoading(loading) {
    isProcessing = loading;
    if (expressCheckoutWrapper) {
        expressCheckoutWrapper.classList.toggle('is-loading', loading);
    }
    setHidden(payBtnText, loading);
    setHidden(payBtnLoader, !loading);
    syncPayButton();
}

function syncPayButton() {
    const usingSavedPaymentMethod = Boolean(savedPaymentMethodForCheckout && clientSecret);
    const needsReadyPaymentForm = clientSecret && !paymentFormReady && !usingSavedPaymentMethod;
    const waitingForInitialForm = !isUpgradeCheckout && !clientSecret;
    const missingBusinessConfirmation = !hasBusinessAcknowledgement();
    payBtn.disabled = isProcessing || missingBusinessConfirmation || waitingForInitialForm || needsReadyPaymentForm;
    if (managePaymentMethodBtn) {
        managePaymentMethodBtn.disabled = isProcessing;
    }
    if (useDifferentPaymentMethodBtn) {
        useDifferentPaymentMethodBtn.disabled = isProcessing || !clientSecret;
    }
    if (useSavedPaymentMethodBtn) {
        useSavedPaymentMethodBtn.hidden = !isUsingDifferentPaymentMethod || !availableSavedPaymentMethod;
        useSavedPaymentMethodBtn.disabled = isProcessing;
    }
}

function hasBusinessAcknowledgement() {
    return !businessCustomerAck || businessCustomerAck.checked;
}

function showBusinessConfirmationPrompt() {
    setStripeLoadingMessage('Confirm the business purchase statement to load the secure payment form.');
    setPaymentElementVisible(false);
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
    setStripeLoadingMessage('Payment confirmed. Redirecting...', 'success');
    setPaymentElementVisible(false);
    isUsingDifferentPaymentMethod = false;
    if (useSavedPaymentMethodBtn) {
        useSavedPaymentMethodBtn.hidden = true;
    }
    payBtn.disabled = true;
    setHidden(payBtnText, false);
    setHidden(payBtnLoader, true);
    payBtnText.textContent = 'Payment confirmed';

    const redirectUrl = data.redirect_url || profileSubscriptionsUrl();
    window.setTimeout(() => {
        window.location.href = new URL(redirectUrl, window.location.href).href;
    }, 700);
}

function renderPaymentMethod(paymentMethod) {
    if (!savedPaymentMethod || !missingPaymentMethod) {
        return;
    }

    if (!paymentMethod) {
        availableSavedPaymentMethod = null;
        isUsingDifferentPaymentMethod = false;
        savedPaymentMethodForCheckout = null;
        savedPaymentMethod.hidden = true;
        missingPaymentMethod.hidden = false;
        return;
    }

    availableSavedPaymentMethod = paymentMethod;
    isUsingDifferentPaymentMethod = false;
    savedPaymentMethodForCheckout = paymentMethod;
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

function showDifferentPaymentMethodForm() {
    if (!clientSecret || isProcessing || !savedPaymentMethodForCheckout) {
        return;
    }

    clearError();
    availableSavedPaymentMethod = savedPaymentMethodForCheckout;
    isUsingDifferentPaymentMethod = true;
    savedPaymentMethodForCheckout = null;
    paymentFormReady = false;
    savedPaymentMethod.hidden = true;
    missingPaymentMethod.hidden = true;
    setStripeLoadingMessage('Loading payment options...');
    setPaymentElementVisible(false);
    mountPaymentElement();
}

function restoreSavedPaymentMethod() {
    if (!availableSavedPaymentMethod || isProcessing) {
        return;
    }

    clearError();
    unmountPaymentElement();
    isUsingDifferentPaymentMethod = false;
    paymentFormReady = false;
    savedPaymentMethodForCheckout = availableSavedPaymentMethod;
    missingPaymentMethod.hidden = true;
    savedPaymentMethod.hidden = false;
    hideStripeLoading();
    setPaymentElementVisible(false);
    syncPayButton();
}

async function openPaymentMethodPortal() {
    managePaymentMethodBtn.disabled = true;
    clearError();

    try {
        const response = await fetch('app/profileActions.php?action=stripe_customer_portal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                subscription_id: upgradeFromSubscriptionId || '',
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

async function confirmSavedPaymentMethod() {
    if (!clientSecret || !savedPaymentMethodForCheckout?.id) {
        return;
    }

    setLoading(true);
    clearError();

    try {
        const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
            payment_method: savedPaymentMethodForCheckout.id,
            return_url: paymentSuccessReturnUrl(),
        });

        if (error) {
            showError(error.message || 'Payment could not be completed with the saved card.');
            setLoading(false);
            return;
        }

        if (paymentIntent?.id) {
            window.location.href = new URL(
                `payment-success?payment_intent=${encodeURIComponent(paymentIntent.id)}`,
                window.location.href
            ).href;
            return;
        }

        showError('Payment could not be verified. Please refresh and try again.');
        setLoading(false);
    } catch (e) {
        showError('Payment could not be completed with the saved card.');
        setLoading(false);
        console.error(e);
    }
}

function paymentSuccessReturnUrl() {
    return new URL('payment-success', window.location.href).href;
}

function profileSubscriptionsUrl() {
    return 'profile?tab=profile#your-promotions';
}

function showError(msg) {
    errorDiv.textContent = msg;
    setHidden(errorDiv, false);
}

function clearError() {
    errorDiv.textContent = '';
    setHidden(errorDiv, true);
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
