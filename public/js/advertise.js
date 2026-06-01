/**
 * advertise.js
 * Handles: plan loading, billing toggle, promotion modal, Stripe redirect
 */

// ── State ────────────────────────────────────────────────────────────────────
const state = {
    plans:          {},     // { slot_type: { period_type: [plan, ...] } }
    selectedSlot:   null,   // 'Homepage' | 'Chatbots_Page' | 'bundle'
    selectedPeriod: 'yearly',
    selectedPlanId: null,
    selectedPlanIdAlt: null, // for bundle (category_featured plan)
    chatbots:       [],
    hasListing:     false,
    activeSlots:    [],     // slot_types ktoré má vybraný chatbot aktívne
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

const YEARLY_DISCOUNT_PERCENTAGE = toNumber(window.advData?.yearlyDiscountPercentage, 20);
const BUNDLE_DISCOUNT_PERCENTAGE = toNumber(window.advData?.bundleDiscountPercentage, 10);

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadPlans();

    if (window.advData?.isLoggedIn) {
        loadChatbots();
    }

    setupBillingToggle();
    setupModal();
    setupYearlyToggles();
});

// ── Load promotion plans ─────────────────────────────────────────────────────
async function loadPlans() {
    try {
        const res  = await fetch('api/getPromotionPlans.php');
        const data = await res.json();

        if (!data.success) throw new Error(data.message);

        // Group: { slot_type: { period_type: plan } }
        data.plans.forEach(plan => {
            const key = plan.slot_type === 'Chatbots page' ? 'Chatbots_Page' : plan.slot_type;
            if (!state.plans[key]) {
                state.plans[key] = {};
            }
            state.plans[key][plan.billing_period_type] = plan;
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
        const res  = await fetch('api/getUserChatbots.php');
        const data = await res.json();

        if (!data.success) return;

        state.chatbots  = data.chatbots;
        state.hasListing = data.hasListing;

        if (!state.hasListing) {
            // Show warning, disable buttons
            noListingWarn.style.display = 'block';
            ['btn-Homepage', 'btn-Chatbots_Page', 'btn-bundle'].forEach(id => {
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

    // Update billing badge with the yearly bundle saving.
    const badgeEl = $('billingBadge');
    if (badgeEl) {
        badgeEl.textContent = `Save ${formatDiscount(bundleDiscount('yearly'))}%`;
    }

    // Helper: update a single card
    function updateCard(slot) {
        const plan        = getBasePlan(slot);
        const monthlyPlan = plan;

        const priceEl     = $('price-'        + slot);
        const periodEl    = $('period-'       + slot);
        const yearlyInfo  = $('yearly-info-'  + slot);
        const originalEl  = $('original-'     + slot);
        const savingPctEl = $('saving-pct-'   + slot);
        const totalBtn    = $('total-btn-'    + slot);

        if (!plan) {
            if (priceEl)    priceEl.textContent    = '—';
            if (yearlyInfo) yearlyInfo.style.display = 'none';
            if (totalBtn)   totalBtn.style.display   = 'none';
            return;
        }

        if (period === 'yearly') {
            const perMonth = packageMonthlyPrice(plan, period);
            const pct      = packageDiscount(plan, period);

            priceEl.textContent  = perMonth.toFixed(2);
            periodEl.textContent = '/mo';

            if (monthlyPlan && yearlyInfo) {
                const monthlyPrice = toNumber(monthlyPlan.price);
                originalEl.textContent  = '$' + monthlyPrice.toFixed(2) + '/mo';
                savingPctEl.textContent = '-' + formatDiscount(pct) + '%';
                yearlyInfo.style.display = 'flex';
            }

            if (totalBtn) {
                totalBtn.style.display        = 'inline-block';
                totalBtn.dataset.showingTotal = 'false';
                totalBtn.textContent          = 'Show total (12 months)';
            }
        } else {
            const monthlyPrice   = packageMonthlyPrice(plan, period);
            const pct            = packageDiscount(plan, period);
            priceEl.textContent  = monthlyPrice.toFixed(2);
            periodEl.textContent = '/mo';
            if (yearlyInfo) {
                if (pct > 0) {
                    originalEl.textContent  = '$' + toNumber(plan.price).toFixed(2) + '/mo';
                    savingPctEl.textContent = '-' + formatDiscount(pct) + '%';
                    yearlyInfo.style.display = 'flex';
                } else {
                    yearlyInfo.style.display = 'none';
                }
            }
            if (totalBtn)   totalBtn.style.display   = 'none';
        }
    }

    updateCard('Homepage');
    updateCard('Chatbots_Page');

    // Bundle
    const hero = getBasePlan('Homepage');
    const cat  = getBasePlan('Chatbots_Page');

    if (hero && cat) {
        if (period === 'yearly') {
            const perMonth = bundleMonthlyPrice(hero, cat, period);
            const pct      = bundleDiscount(period);

            $('price-bundle').textContent  = perMonth.toFixed(2);
            $('period-bundle').textContent = '/mo';

            const heroM = state.plans['Homepage']?.['monthly'];
            const catM  = state.plans['Chatbots_Page']?.['monthly'];
            if (heroM && catM) {
                const monthlyTotal = toNumber(heroM.price) + toNumber(catM.price);
                $('original-bundle').textContent   = '$' + monthlyTotal.toFixed(2) + '/mo';
                $('saving-pct-bundle').textContent = '-' + formatDiscount(pct) + '%';
                $('yearly-info-bundle').style.display = 'flex';
            }

            $('total-btn-bundle').style.display        = 'inline-block';
            $('total-btn-bundle').dataset.showingTotal = 'false';
            $('total-btn-bundle').textContent          = 'Show total (12 months)';
            $('savings-bundle').style.display          = 'none';

        } else {
            $('price-bundle').textContent  = bundleMonthlyPrice(hero, cat, period).toFixed(2);
            $('period-bundle').textContent = '/mo';
            $('yearly-info-bundle').style.display = 'none';
            $('total-btn-bundle').style.display   = 'none';

            const savings = bundleDiscount(period);
            if (savings > 0) {
                $('savings-bundle').style.display = 'block';
                $('savings-amount').textContent   = formatDiscount(savings);
            } else {
                $('savings-bundle').style.display = 'none';
            }
        }
    } else {
        $('price-bundle').textContent         = '—';
        $('savings-bundle').style.display     = 'none';
        $('yearly-info-bundle').style.display = 'none';
        $('total-btn-bundle').style.display   = 'none';
    }
}

function setupYearlyToggles() {
    ['Homepage', 'Chatbots_Page', 'bundle'].forEach(slot => {
        const btn = $('total-btn-' + slot);
        if (!btn) return;

        btn.addEventListener('click', () => {
            const showing = btn.dataset.showingTotal === 'true';

            if (showing) {
                updatePriceDisplay();
            } else {
                showYearlyTotalsForAllCards();
            }
        });
    });
}

function showYearlyTotalsForAllCards() {
    ['Homepage', 'Chatbots_Page', 'bundle'].forEach(slot => {
        const total    = yearlyTotalForSlot(slot);
        const priceEl  = $('price-' + slot);
        const periodEl = $('period-' + slot);
        const btn      = $('total-btn-' + slot);

        if (total === null || !priceEl || !periodEl || !btn || btn.style.display === 'none') {
            return;
        }

        priceEl.textContent  = total.toFixed(2);
        periodEl.textContent = '/yr';
        btn.textContent = 'Show per month';
        btn.dataset.showingTotal = 'true';
    });
}

function yearlyTotalForSlot(slot) {
    if (slot === 'bundle') {
        const hero = getBasePlan('Homepage');
        const cat  = getBasePlan('Chatbots_Page');
        return hero && cat ? bundleTotalPrice(hero, cat, 'yearly') : null;
    }

    const plan = getBasePlan(slot);
    return plan ? packageTotalPrice(plan, 'yearly') : null;
}

function toNumber(value, fallback = 0) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
}

function clampDiscount(discount) {
    return Math.max(0, Math.min(100, toNumber(discount)));
}

function formatDiscount(discount) {
    const rounded = Math.round(clampDiscount(discount) * 100) / 100;
    return Number.isInteger(rounded)
        ? String(rounded)
        : rounded.toFixed(2).replace(/\.?0+$/, '');
}

function billingMultiplier(period) {
    return period === 'yearly' ? 12 : 1;
}

function getBasePlan(slot) {
    return state.plans[slot]?.['monthly']
        || state.plans[slot]?.['yearly']
        || null;
}

function packageDiscount(plan, period) {
    const dbDiscount = toNumber(plan?.discount_percentage);
    const yearlyDiscount = period === 'yearly' ? YEARLY_DISCOUNT_PERCENTAGE : 0;
    return clampDiscount(dbDiscount + yearlyDiscount);
}

function bundleDiscount(period) {
    const yearlyDiscount = period === 'yearly' ? YEARLY_DISCOUNT_PERCENTAGE : 0;
    return clampDiscount(BUNDLE_DISCOUNT_PERCENTAGE + yearlyDiscount);
}

function discountedMonthlyPrice(monthlyPrice, discount) {
    return toNumber(monthlyPrice) * (1 - clampDiscount(discount) / 100);
}

function packageMonthlyPrice(plan, period) {
    if (!plan) return 0;
    return discountedMonthlyPrice(plan.price, packageDiscount(plan, period));
}

function packageTotalPrice(plan, period) {
    return packageMonthlyPrice(plan, period) * billingMultiplier(period);
}

function bundleBaseMonthlyTotal(hero, cat) {
    return toNumber(hero?.price) + toNumber(cat?.price);
}

function bundleMonthlyPrice(hero, cat, period) {
    return discountedMonthlyPrice(bundleBaseMonthlyTotal(hero, cat), bundleDiscount(period));
}

function bundleTotalPrice(hero, cat, period) {
    return bundleMonthlyPrice(hero, cat, period) * billingMultiplier(period);
}

function formatPrice(price) {
    return toNumber(price).toFixed(2);
}

// ── Modal setup ──────────────────────────────────────────────────────────────
function setupModal() {
    // Open buttons
    ['Homepage', 'Chatbots_Page', 'bundle'].forEach(slot => {
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
    chatbotSelect.addEventListener('change', async () => {
        const botId = chatbotSelect.value;
        state.activeSlots = [];
 
        if (botId) {
            await checkActivePromotions(botId);
        }
 
        applySlotRestrictions();
        updateSummary();
    });

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
        Homepage:      'Get Homepage Visibility',
        Chatbots_Page: 'Get Chatbots Page Visibility',
        bundle:        'Get Full Bundle'
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
    lockScroll();
}

function closeModal() {
    modalOverlay.classList.remove('open');
    unlockScroll();
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
            const hero = getBasePlan('Homepage');
            const cat  = getBasePlan('Chatbots_Page');
            if (hero && cat) {
                plan = {
                    ...hero,
                    price: bundleTotalPrice(hero, cat, period).toFixed(2),
                    discount_percentage: 0
                };
            }
        } else {
            plan = getBasePlan(slot);
        }

        if (!plan) return;

        const finalPrice = slot === 'bundle'
            ? bundleTotalPrice(
                getBasePlan('Homepage'),
                getBasePlan('Chatbots_Page'),
                period
            )
            : packageTotalPrice(plan, period);

        const div = document.createElement('div');
        div.className = 'adv-period-option' + (period === state.selectedPeriod ? ' selected' : '');
        div.dataset.period = period;
        div.innerHTML = `
            <div class="adv-period-option__name">${periodLabel[period]}</div>
            <div class="adv-period-option__price">$${formatPrice(finalPrice)}</div>
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

// ── Check active promotions pre vybraný chatbot ──────────────────────────────
async function checkActivePromotions(chatbotId) {
    try {
        const res  = await fetch(`../app/advertiseActions.php?action=check_active_promotions&chatbot_id=${chatbotId}`);
        const data = await res.json();
        if (data.success) {
            state.activeSlots = data.active_slots || [];
        }
    } catch (e) {
        state.activeSlots = [];
    }
}
 
// ── Skryť/zobraziť sloty podľa aktívnych subscriptions ──────────────────────
function applySlotRestrictions() {
    const slot        = state.selectedSlot;
    const hasHomepage = state.activeSlots.includes('Homepage');
    const hasChatbots = state.activeSlots.includes('Chatbots page');
    const hasBoth     = hasHomepage && hasChatbots;
 
    // Reset
    modalError.textContent = '';
    confirmBtn.disabled    = false;
 
    // Obe aktívne → zablokovať
    if (hasBoth) {
        modalError.textContent = 'This chatbot already has all promotions active.';
        confirmBtn.disabled    = true;
        return;
    }
 
    // Homepage modal + Homepage aktívna
    if (slot === 'Homepage' && hasHomepage) {
        modalError.textContent = 'This chatbot already has Homepage Visibility active.';
        confirmBtn.disabled    = true;
        return;
    }
 
    // Chatbots Page modal + Chatbots Page aktívna
    if (slot === 'Chatbots_Page' && hasChatbots) {
        modalError.textContent = 'This chatbot already has Chatbots Page Visibility active.';
        confirmBtn.disabled    = true;
        return;
    }
 
    // Bundle + má Homepage → prepnúť na Chatbots Page
    if (slot === 'bundle' && hasHomepage && !hasChatbots) {
        state.selectedSlot = 'Chatbots_Page';
        $('modalTitle').textContent    = 'Get Chatbots Page Visibility';
        $('modalSubtitle').textContent = 'Homepage Visibility is already active for this chatbot';
        modalError.textContent         = 'Homepage is already active — showing Chatbots Page only.';
        populatePeriodOptions('Chatbots_Page');
        return;
    }
 
    // Bundle + má Chatbots Page → prepnúť na Homepage
    if (slot === 'bundle' && hasChatbots && !hasHomepage) {
        state.selectedSlot = 'Homepage';
        $('modalTitle').textContent    = 'Get Homepage Visibility';
        $('modalSubtitle').textContent = 'Chatbots Page Visibility is already active for this chatbot';
        modalError.textContent         = 'Chatbots Page is already active — showing Homepage only.';
        populatePeriodOptions('Homepage');
        return;
    }
}

function updateSummary() {
    const slot   = state.selectedSlot;
    const period = state.selectedPeriod;
    const botId  = chatbotSelect.value;

    if (!slot) return;

    const slotNames   = {
        Homepage:      'Homepage Visibility',
        Chatbots_Page: 'Chatbots Page Visibility',
        bundle:        'Full Bundle'
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
        const hero = getBasePlan('Homepage');
        const cat  = getBasePlan('Chatbots_Page');
        if (hero && cat) {
            total = '$' + bundleTotalPrice(hero, cat, period).toFixed(2);
            state.selectedPlanId    = hero.id;
            state.selectedPlanIdAlt = cat.id;
        }
    } else {
        const plan = getBasePlan(slot);
        if (plan) {
            total = '$' + packageTotalPrice(plan, period).toFixed(2);
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

    confirmText.style.display   = 'none';
    confirmLoader.style.display = 'inline';
    confirmBtn.disabled         = true;

    try {
        if (state.selectedSlot === 'bundle') {
            // Create both promotions, get first subscription_id for checkout
            const result1 = await createPromotion(botId, state.selectedPlanId);
            const result2 = await createPromotion(botId, state.selectedPlanIdAlt);

            if (!result1.success) throw new Error(result1.message || 'Failed to create Homepage promotion');
            if (!result2.success) throw new Error(result2.message || 'Failed to create Chatbots Page promotion');

            // Redirect to checkout with chatbot_id + promotion_ids
            document.getElementById('stripeRedirectOverlay').classList.add('active');
            window.location.href = 'checkout.php?chatbot_id=' + result1.chatbot_id
                                + '&promotion_id='  + result1.promotion_id
                                + '&promotion_id_2=' + result2.promotion_id
                                + '&billing_period=' + encodeURIComponent(state.selectedPeriod);
        } else {
            const result = await createPromotion(botId, state.selectedPlanId);

            if (!result.success) throw new Error(result.message || 'Failed to create promotion');

            document.getElementById('stripeRedirectOverlay').classList.add('active');
            window.location.href = 'checkout.php?chatbot_id=' + result.chatbot_id
                                + '&promotion_id=' + result.promotion_id
                                + '&billing_period=' + encodeURIComponent(state.selectedPeriod);
        }

    } catch (e) {
        modalError.textContent      = e.message || 'Something went wrong. Please try again.';
        confirmText.style.display   = 'inline';
        confirmLoader.style.display = 'none';
        confirmBtn.disabled         = false;
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
