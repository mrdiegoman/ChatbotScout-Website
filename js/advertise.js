/**
 * advertise.js
 * Handles: plan loading, billing toggle, promotion modal, Stripe redirect
 */

// ── State ────────────────────────────────────────────────────────────────────
const state = {
    plans:          {},     // { slot_type: { period_type: [plan, ...] } }
    selectedSlot:   null,   // 'homepage_hero' | 'category_featured' | 'bundle'
    selectedPeriod: 'monthly',
    selectedPlanId: null,
    selectedPlanIdAlt: null, // for bundle (category_featured plan)
    chatbots:       [],
    hasListing:     false
};

// ── DOM ──────────────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);

const plansLoading    = $('plansLoading');
const plansGrid       = $('plansGrid');
const noListingWarn   = $('noListingWarning');
const billingToggle   = $('billingToggle');
const modalOverlay    = $('promotionModalOverlay');
const chatbotSelect   = $('chatbotSelect');
const periodGrid      = $('modalPeriodGrid');
const modalError      = $('modalError');
const confirmBtn      = $('modalConfirmBtn');
const confirmText     = $('modalConfirmText');
const confirmLoader   = $('modalConfirmLoader');

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadPlans();

    if (window.advData?.isLoggedIn) {
        loadChatbots();
    }

    setupBillingToggle();
    setupModal();
});

// ── Load promotion plans ─────────────────────────────────────────────────────
async function loadPlans() {
    try {
        const res  = await fetch('../api/getPromotionPlans.php');
        const data = await res.json();

        if (!data.success) throw new Error(data.message);

        // Group: { slot_type: { period_type: plan } }
        data.plans.forEach(plan => {
            if (!state.plans[plan.slot_type]) {
                state.plans[plan.slot_type] = {};
            }
            state.plans[plan.slot_type][plan.billing_period_type] = plan;
        });

        plansLoading.style.display = 'none';
        plansGrid.style.display    = 'grid';

        updatePriceDisplay();

    } catch (e) {
        plansLoading.innerHTML = '<p>Failed to load plans. Please refresh the page.</p>';
        console.error('Plan load error:', e);
    }
}

// ── Load user chatbots ───────────────────────────────────────────────────────
async function loadChatbots() {
    try {
        const res  = await fetch('../api/getUserChatbots.php');
        const data = await res.json();

        if (!data.success) return;

        state.chatbots  = data.chatbots;
        state.hasListing = data.hasListing;

        if (!state.hasListing) {
            // Show warning, disable buttons
            noListingWarn.style.display = 'block';
            ['btn-homepage_hero', 'btn-category_featured', 'btn-bundle'].forEach(id => {
                const btn = $(id);
                if (btn) {
                    btn.disabled = true;
                    btn.title = 'You need an active listing subscription first';
                }
            });
        }

    } catch (e) {
        console.error('Chatbot load error:', e);
    }
}

// ── Billing toggle ───────────────────────────────────────────────────────────
function setupBillingToggle() {
    billingToggle.querySelectorAll('.adv-billing-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            billingToggle.querySelectorAll('.adv-billing-btn')
                .forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.selectedPeriod = btn.dataset.period;
            updatePriceDisplay();
        });
    });
}

// ── Update card prices based on selected period ───────────────────────────────
function updatePriceDisplay() {
    const period = state.selectedPeriod;
    const periodLabel = { monthly: '/mo', yearly: '/yr' };

    // Homepage Hero
    const hero = state.plans['homepage_hero']?.[period];
    if (hero) {
        $('price-homepage_hero').textContent  = formatPrice(hero.price, hero.discount_percentage);
        $('period-homepage_hero').textContent = periodLabel[period] || '/mo';
    } else {
        $('price-homepage_hero').textContent  = '—';
    }

    // Category Featured
    const cat = state.plans['category_featured']?.[period];
    if (cat) {
        $('price-category_featured').textContent  = formatPrice(cat.price, cat.discount_percentage);
        $('period-category_featured').textContent = periodLabel[period] || '/mo';
    } else {
        $('price-category_featured').textContent  = '—';
    }

    // Bundle
    if (hero && cat) {
        const heroFinal = hero.price * (1 - hero.discount_percentage / 100);
        const catFinal  = cat.price  * (1 - cat.discount_percentage  / 100);
        $('price-bundle').textContent  = (heroFinal + catFinal).toFixed(2);
        $('period-bundle').textContent = periodLabel[period] || '/mo';
        const savings = ((1 - (heroFinal + catFinal) / (hero.price + cat.price)) * 100).toFixed(0);
        if (savings > 0) {
            $('savings-bundle').style.display  = 'block';
            $('savings-amount').textContent    = savings;
        } else {
            $('savings-bundle').style.display  = 'none';
        }
    } else {
        $('price-bundle').textContent  = '—';
        $('savings-bundle').style.display = 'none';
    }
}

function formatPrice(price, discount) {
    const final = price * (1 - discount / 100);
    return final.toFixed(2);
}

// ── Modal setup ──────────────────────────────────────────────────────────────
function setupModal() {
    // Open buttons
    ['homepage_hero', 'category_featured', 'bundle'].forEach(slot => {
        const btn = $('btn-' + slot);
        if (btn) {
            btn.addEventListener('click', () => openModal(slot));
        }
    });

    // Close
    $('modalCloseBtn').addEventListener('click',  closeModal);
    $('modalCancelBtn').addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', e => {
        if (e.target === modalOverlay) closeModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    // Chatbot select
    chatbotSelect.addEventListener('change', updateSummary);

    // Confirm
    confirmBtn.addEventListener('click', handleConfirm);
}

function openModal(slot) {
    if (!window.advData?.isLoggedIn) {
        window.location.href = window.advData.loginUrl + '?redirect=advertise.php';
        return;
    }

    state.selectedSlot = slot;
    modalError.textContent = '';

    // Set title
    const titles = {
        homepage_hero:     'Get Homepage Hero',
        category_featured: 'Get Category Featured',
        bundle:            'Get Full Bundle'
    };
    $('modalTitle').textContent    = titles[slot] || 'Get Promotion';
    $('modalSubtitle').textContent = 'Select your chatbot and billing period';

    // Populate chatbot dropdown
    populateChatbotSelect();

    // Populate period options
    populatePeriodOptions(slot);

    // Update summary
    updateSummary();

    modalOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modalOverlay.classList.remove('open');
    document.body.style.overflow = '';
    state.selectedSlot   = null;
    state.selectedPlanId = null;
    state.selectedPlanIdAlt = null;
    modalError.textContent = '';
}

function populateChatbotSelect() {
    chatbotSelect.innerHTML = '';

    if (state.chatbots.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'No chatbots with active listing found';
        chatbotSelect.appendChild(opt);
        return;
    }

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select a chatbot...';
    chatbotSelect.appendChild(placeholder);

    state.chatbots.forEach(bot => {
        const opt = document.createElement('option');
        opt.value = bot.id;
        opt.textContent = bot.name + (bot.plan_name ? ` (${bot.plan_name})` : '');
        chatbotSelect.appendChild(opt);
    });
}

function populatePeriodOptions(slot) {
    periodGrid.innerHTML = '';

    const periods     = ['monthly', 'yearly'];
    const periodLabel = { monthly: 'Monthly', yearly: 'Yearly' };
    const periodSub   = { monthly: '/month', yearly: '/year' };

    periods.forEach(period => {
        let plan = null;

        if (slot === 'bundle') {
            const hero = state.plans['homepage_hero']?.[period];
            const cat  = state.plans['category_featured']?.[period];
            if (hero && cat) {
                const heroFinal = hero.price * (1 - hero.discount_percentage / 100);
                const catFinal  = cat.price  * (1 - cat.discount_percentage  / 100);
                plan = {
                    ...hero,
                    price: (heroFinal + catFinal).toFixed(2),
                    discount_percentage: 0
                };
            }
        } else {
            plan = state.plans[slot]?.[period];
        }

        if (!plan) return;

        const finalPrice = slot === 'bundle'
            ? parseFloat(plan.price).toFixed(2)
            : (plan.price * (1 - plan.discount_percentage / 100)).toFixed(2);

        const div = document.createElement('div');
        div.className = 'adv-period-option' + (period === state.selectedPeriod ? ' selected' : '');
        div.dataset.period = period;
        div.innerHTML = `
            <div class="adv-period-option__name">${periodLabel[period]}</div>
            <div class="adv-period-option__price">$${finalPrice}</div>
            <div class="adv-period-option__sub">${periodSub[period]}</div>
        `;

        div.addEventListener('click', () => {
            periodGrid.querySelectorAll('.adv-period-option')
                .forEach(o => o.classList.remove('selected'));
            div.classList.add('selected');
            state.selectedPeriod = period;

            billingToggle.querySelectorAll('.adv-billing-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.period === period);
            });
            updatePriceDisplay();
            updateSummary();
        });

        periodGrid.appendChild(div);
    });
}

function updateSummary() {
    const slot   = state.selectedSlot;
    const period = state.selectedPeriod;
    const botId  = chatbotSelect.value;

    if (!slot) return;

    const slotNames   = {
        homepage_hero:     'Homepage Hero',
        category_featured: 'Category Featured',
        bundle:            'Full Bundle'
    };
    const periodNames = { monthly: 'Monthly', yearly: 'Yearly' };

    $('summaryPlan').textContent    = slotNames[slot] || slot;
    $('summaryBilling').textContent = periodNames[period] || period;

    const selectedBot = state.chatbots.find(b => b.id === botId);
    $('summaryChatbot').textContent = selectedBot ? selectedBot.name : '—';

    let total = '—';
    state.selectedPlanId    = null;
    state.selectedPlanIdAlt = null;

    if (slot === 'bundle') {
        const hero = state.plans['homepage_hero']?.[period];
        const cat  = state.plans['category_featured']?.[period];
        if (hero && cat) {
            const heroFinal = hero.price * (1 - hero.discount_percentage / 100);
            const catFinal  = cat.price  * (1 - cat.discount_percentage  / 100);
            total = '$' + (heroFinal + catFinal).toFixed(2);
            state.selectedPlanId    = hero.id;
            state.selectedPlanIdAlt = cat.id;
        }
    } else {
        const plan = state.plans[slot]?.[period];
        if (plan) {
            total = '$' + (plan.price * (1 - plan.discount_percentage / 100)).toFixed(2);
            state.selectedPlanId = plan.id;
        }
    }

    $('summaryTotal').textContent = total + ' ' + (period === 'yearly' ? '/yr' : '/mo');

    const canConfirm = botId && state.selectedPlanId;
    confirmBtn.disabled = !canConfirm;
}

// ── Handle confirm / submit ───────────────────────────────────────────────────
async function handleConfirm() {
    const botId = chatbotSelect.value;
    modalError.textContent = '';

    if (!botId) {
        modalError.textContent = 'Please select a chatbot.';
        return;
    }

    if (!state.selectedPlanId) {
        modalError.textContent = 'Please select a billing period.';
        return;
    }

    // Show loading
    confirmText.style.display   = 'none';
    confirmLoader.style.display = 'inline';
    confirmBtn.disabled         = true;

    try {
        if (state.selectedSlot === 'bundle') {
            // Create two promotions sequentially
            await createPromotion(botId, state.selectedPlanId);
            await createPromotion(botId, state.selectedPlanIdAlt);
            window.location.href = 'https://checkout.stripe.com/placeholder?bundle=1';
        } else {
            const result = await createPromotion(botId, state.selectedPlanId);
            if (result.success) {
                window.location.href = result.redirect_url;
            } else {
                throw new Error(result.message || 'Failed to create promotion');
            }
        }
    } catch (e) {
        modalError.textContent    = e.message || 'Something went wrong. Please try again.';
        confirmText.style.display = 'inline';
        confirmLoader.style.display = 'none';
        confirmBtn.disabled       = false;
    }
}

async function createPromotion(chatbotId, promotionId) {
    const res = await fetch('../app/advertiseActions.php?action=create_promotion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ chatbot_id: chatbotId, promotion_id: promotionId })
    });
    return await res.json();
}