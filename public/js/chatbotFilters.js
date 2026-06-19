/**
 * chatbotFilters.js
 *
 * Filter modal keeps a draft state in its controls. The list itself only uses
 * the last applied snapshot, so closing the modal never changes the page.
 */

const filtersBtnBtn   = document.getElementById('filtersBtn');
const overlay         = document.getElementById('filtersModalOverlay');
const closeFiltersBtn = document.getElementById('filtersCloseBtn');
const clearFiltersBtn = document.getElementById('filtersClearBtn');
const applyFiltersBtn = document.querySelector(
    '.chatbot-filters-modal-apply-btn, .favourites-filters-modal-apply-btn'
);

const NUMBER_FILTERS = {
    ratingInputStart: 'ratingStart',
    ratingInputEnd: 'ratingEnd',
    votesInputStart: 'votesStart',
    votesInputEnd: 'votesEnd',
    favoritesInputStart: 'favoritesStart',
    favoritesInputEnd: 'favoritesEnd',
    priceInputStart: 'priceStart',
    priceInputEnd: 'priceEnd',
};

const FILTER_GROUPS = [
    {
        selector: '.chatbot-filters-modal-body-features-single, .favourites-filters-modal-body-features-single',
        key: 'features[]',
        dataKey: 'featureId',
    },
    {
        selector: '.chatbot-filters-modal-body-currency-single, .favourites-filters-modal-body-currency-single',
        key: 'currency[]',
        dataKey: 'currencyCode',
    },
    {
        selector: '.chatbot-filters-modal-body-integrations-single, .favourites-filters-modal-body-integrations-single',
        key: 'integrations[]',
        dataKey: 'integrationId',
    },
    {
        selector: '.chatbot-filters-modal-body-pricing-single, .favourites-filters-modal-body-pricing-single',
        key: 'pricing[]',
        dataKey: 'pricingModel',
    },
];

let appliedFilterParams = {};

function cloneParams(params) {
    return Object.fromEntries(
        Object.entries(params).map(([key, value]) => [key, Array.isArray(value) ? [...value] : value])
    );
}

function collectDraftFilterParams() {
    const params = {};

    Object.entries(NUMBER_FILTERS).forEach(([id, key]) => {
        const value = document.getElementById(id)?.value.trim() ?? '';
        if (value) params[key] = value;
    });

    FILTER_GROUPS.forEach(({ selector, key, dataKey }) => {
        const selected = [...document.querySelectorAll(selector)]
            .filter(element => element.classList.contains('active'))
            .map(element => element.dataset[dataKey])
            .filter(Boolean);

        if (selected.length) params[key] = selected;
    });

    return params;
}

function refreshDraftUi() {
    if (typeof window.clearFilterResultsCount === 'function') window.clearFilterResultsCount();
    if (typeof window.updateFilterBadge === 'function') window.updateFilterBadge();
    if (typeof window.triggerFeatureCountsRefresh === 'function') window.triggerFeatureCountsRefresh();
    if (typeof window.triggerIntegrationCountsRefresh === 'function') window.triggerIntegrationCountsRefresh();
    if (typeof window.triggerPricingCountsRefresh === 'function') window.triggerPricingCountsRefresh();
    if (typeof window.triggerCurrencyCountsRefresh === 'function') window.triggerCurrencyCountsRefresh();
}

function restoreDraftFromApplied() {
    Object.entries(NUMBER_FILTERS).forEach(([id, key]) => {
        const input = document.getElementById(id);
        if (input) input.value = appliedFilterParams[key] ?? '';
    });

    FILTER_GROUPS.forEach(({ selector, key, dataKey }) => {
        const selected = new Set(appliedFilterParams[key] ?? []);
        document.querySelectorAll(selector).forEach(element => {
            element.classList.toggle('active', selected.has(element.dataset[dataKey]));
        });
    });

    refreshDraftUi();
}

function openModal() {
    restoreDraftFromApplied();
    overlay.style.display = 'flex';
    lockScroll();
}

function closeModal(discardDraft = true) {
    if (discardDraft) restoreDraftFromApplied();
    overlay.style.display = 'none';
    unlockScroll();
}

// The showcase reads this snapshot, never the temporary modal controls.
window.getActiveFilterParams = function () {
    return cloneParams(appliedFilterParams);
};

function clearAllFilters() {
    Object.keys(NUMBER_FILTERS).forEach(id => {
        const input = document.getElementById(id);
        if (input) input.value = '';
    });

    FILTER_GROUPS.forEach(({ selector }) => {
        document.querySelectorAll(selector).forEach(element => element.classList.remove('active'));
    });

    refreshDraftUi();
}

if (filtersBtnBtn) filtersBtnBtn.addEventListener('click', openModal);
if (closeFiltersBtn) closeFiltersBtn.addEventListener('click', () => closeModal());
if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', clearAllFilters);

if (overlay) {
    overlay.addEventListener('click', event => {
        if (event.target === overlay) closeModal();
    });
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && overlay?.style.display === 'flex') closeModal();
});

if (applyFiltersBtn) {
    applyFiltersBtn.addEventListener('click', () => {
        appliedFilterParams = cloneParams(collectDraftFilterParams());
        closeModal(false);
        if (typeof window.applyFilters === 'function') window.applyFilters();
    });
}

Object.keys(NUMBER_FILTERS).forEach(id => {
    const input = document.getElementById(id);
    if (!input) return;

    input.addEventListener('keydown', event => {
        if (['-', '+', 'e', 'E'].includes(event.key)) event.preventDefault();
    });
    input.addEventListener('input', () => {
        if (parseFloat(input.value) < 0) input.value = '';
    });
});
