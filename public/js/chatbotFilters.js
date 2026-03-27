/**
 * chatbotFilters.js
 * - Otváranie/zatváranie filter modalu
 * - Apply Filters — zavolá window.applyFilters() vystavené z chatbotShowcase.js / favouritesShowcase.js
 * - Clear all filtre
 *
 * Badge + feature counts spravuje filterFeaturesAdding.js
 */

const filtersBtnBtn   = document.getElementById('filtersBtn');
const overlay         = document.getElementById('filtersModalOverlay');
const closeFiltersBtn = document.getElementById('filtersCloseBtn');
const clearFiltersBtn = document.getElementById('filtersClearBtn');
const applyFiltersBtn = document.querySelector(
    '.chatbot-filters-modal-apply-btn, .favourites-filters-modal-apply-btn'
);

// ============================================
// OTVORIŤ / ZATVORIŤ MODAL
// ============================================

function openModal() {
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    overlay.style.display = 'none';
    document.body.style.overflow = '';
}

filtersBtnBtn.addEventListener('click', openModal);
closeFiltersBtn.addEventListener('click', closeModal);
overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

// ============================================
// COLLECT ACTIVE FILTERS → params objekt
// Vystavené ako window.getActiveFilterParams()
// Volané z chatbotShowcase.js + favouritesShowcase.js
// ============================================

window.getActiveFilterParams = function () {
    const val = (id) => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

    const params = {};

    if (val('ratingInputStart')) params.ratingStart = val('ratingInputStart');
    if (val('ratingInputEnd'))   params.ratingEnd   = val('ratingInputEnd');
    if (val('votesInputStart'))  params.votesStart  = val('votesInputStart');
    if (val('votesInputEnd'))    params.votesEnd    = val('votesInputEnd');
    if (val('favoritesInputStart')) params.favoritesStart = val('favoritesInputStart');
    if (val('favoritesInputEnd'))   params.favoritesEnd   = val('favoritesInputEnd');
    if (val('priceInputStart'))  params.priceStart  = val('priceInputStart');
    if (val('priceInputEnd'))    params.priceEnd    = val('priceInputEnd');

    // Zachytí oba typy spanoch (chatbotShowcase aj favouritesShowcase)
    const activeFeatures = document.querySelectorAll(
        '.chatbot-filters-modal-body-features-single.active, ' +
        '.favourites-filters-modal-body-features-single.active'
    );
    activeFeatures.forEach(span => {
        if (span.dataset.featureId) {
            if (!params['features[]']) params['features[]'] = [];
            params['features[]'].push(span.dataset.featureId);
        }
    });

    if (typeof window.getActiveCurrencies === 'function') {
        const currencies = window.getActiveCurrencies();
        if (currencies.length > 0) params['currency[]'] = currencies;
    }

    // Integrations — OR filter
    if (typeof window.getActiveIntegrationIds === 'function') {
        const intIds = window.getActiveIntegrationIds();
        if (intIds.length > 0) params['integrations[]'] = intIds;
    }

    // Pricing models — OR filter
    if (typeof window.getActivePricingModels === 'function') {
        const pmIds = window.getActivePricingModels();
        if (pmIds.length > 0) params['pricing[]'] = pmIds;
    }

    return params;
};

// ============================================
// APPLY FILTERS
// ============================================

if (applyFiltersBtn) {
    applyFiltersBtn.addEventListener('click', () => {
        closeModal();
        if (typeof window.applyFilters === 'function') window.applyFilters();
    });
}

// ============================================
// CLEAR ALL FILTERS
// ============================================

function clearAllFilters () {
    ['ratingInputStart', 'ratingInputEnd', 'votesInputStart', 'votesInputEnd', 'favoritesInputStart', 'favoritesInputEnd', 'priceInputStart', 'priceInputEnd']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    document.querySelectorAll(
        '.chatbot-filters-modal-body-features-single.active, ' +
        '.favourites-filters-modal-body-features-single.active'
    ).forEach(span => span.classList.remove('active'));

    if (typeof window.updateFilterBadge === 'function')           window.updateFilterBadge();
    if (typeof window.triggerFeatureCountsRefresh === 'function') window.triggerFeatureCountsRefresh();
    if (typeof window.applyFilters === 'function')                window.applyFilters();
    if (typeof window.clearIntegrationFilters === 'function')     window.clearIntegrationFilters();
    if (typeof window.clearPricingFilters === 'function')         window.clearPricingFilters();
    if (typeof window.clearCurrencyFilters === 'function')        window.clearCurrencyFilters();
}

if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', clearAllFilters);
