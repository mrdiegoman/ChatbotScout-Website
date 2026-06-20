/**
 * filterCurrencyAdding.js
 *
 * Currency filter v filter popupe — OR logika.
 * Načíta currencies z DB, zobrazí ich ako spany (tag štýl).
 * Funguje na chatbotShowcase aj favouritesShowcase.
 */

(function () {
    'use strict';

    const isFavourites   = !!document.querySelector('.favourites-filters-modal-body-features-container');
    const spanClass      = isFavourites
        ? 'favourites-filters-modal-body-pricing-single'  // reuse pricing span štýl
        : 'chatbot-filters-modal-body-pricing-single';

    const currencyContainer = document.getElementById('currencyContainer');
    if (!currencyContainer) return;

    // ── Badge ────────────────────────────────────────────────────────────────
    function updateBadge() {
        if (typeof window.updateFilterBadge === 'function') window.updateFilterBadge();
    }

    // ── Collect active state for count fetches ────────────────────────────────
    function getActiveState() {
        const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

        const featSpanClass = isFavourites
            ? 'favourites-filters-modal-body-features-single'
            : 'chatbot-filters-modal-body-features-single';
        const intSpanClass = isFavourites
            ? 'favourites-filters-modal-body-integrations-single'
            : 'chatbot-filters-modal-body-integrations-single';

        return {
            ratingStart:          val('ratingInputStart'),
            ratingEnd:            val('ratingInputEnd'),
            votesStart:           val('votesInputStart'),
            votesEnd:             val('votesInputEnd'),
            favoritesStart:       val('favoritesInputStart'),
            favoritesEnd:         val('favoritesInputEnd'),
            priceStart:           val('priceInputStart'),
            priceEnd:             val('priceInputEnd'),
            activeFeatureIds:     [...document.querySelectorAll(`.${featSpanClass}.active`)].map(s => s.dataset.featureId).filter(Boolean),
            activeIntegrationIds: [...document.querySelectorAll(`.${intSpanClass}.active`)].map(s => s.dataset.integrationId).filter(Boolean),
            activePricingModels:  typeof window.getActivePricingModels === 'function' ? window.getActivePricingModels() : [],
            hasFreeTrial: document.getElementById('freeTrialFilter')?.classList.contains('active') ?? false,
            isFavourites,
        };
    }

    // ── Fetch counts ──────────────────────────────────────────────────────────
    let debounceTimer = null;

    function scheduleFetch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchAndUpdateCounts, 300);
    }

    function fetchAndUpdateCounts() {
        fetch('api/getCurrencyCounts.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(getActiveState()),
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            const counts = data.counts;
            document.querySelectorAll('.chatbot-filters-modal-body-currency-single, .favourites-filters-modal-body-currency-single')
                .forEach(span => {
                    const code   = span.dataset.currencyCode;
                    const symbol = span.dataset.currencySymbol;
                    const count  = counts[code] ?? 0;
                    span.textContent = `${symbol} ${code} (${count})`;
                });

        })
        .catch(() => {});
    }

    // ── Build span ────────────────────────────────────────────────────────────
    function buildSpan(currency) {
        const cssClass = isFavourites
            ? 'favourites-filters-modal-body-currency-single'
            : 'chatbot-filters-modal-body-currency-single';

        const span = document.createElement('span');
        span.className                = cssClass;
        span.dataset.currencyCode     = currency.code;
        span.dataset.currencySymbol   = currency.symbol;
        span.dataset.currencyName     = currency.name;
        span.textContent              = `${currency.symbol} ${currency.code} (...)`;

        span.addEventListener('click', () => {
            span.classList.toggle('active');
            updateBadge();
            scheduleFetch();
            if (typeof window.triggerFeatureCountsRefresh    === 'function') window.triggerFeatureCountsRefresh();
            if (typeof window.triggerIntegrationCountsRefresh === 'function') window.triggerIntegrationCountsRefresh();
            if (typeof window.triggerPricingCountsRefresh     === 'function') window.triggerPricingCountsRefresh();
        });

        return span;
    }

    // ── Fetch currencies + render ─────────────────────────────────────────────
    fetch('api/getCurrencies.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.currencies.length) return;
            currencyContainer.innerHTML = '';
            data.currencies.forEach(c => currencyContainer.appendChild(buildSpan(c)));
            fetchAndUpdateCounts();
        })
        .catch(() => {});

    // ── Expose ────────────────────────────────────────────────────────────────
    window.clearCurrencyFilters = function () {
        document.querySelectorAll('.chatbot-filters-modal-body-currency-single.active, .favourites-filters-modal-body-currency-single.active')
            .forEach(s => s.classList.remove('active'));
        updateBadge();
        scheduleFetch();
    };

    window.getActiveCurrencies = function () {
        return [...document.querySelectorAll('.chatbot-filters-modal-body-currency-single.active, .favourites-filters-modal-body-currency-single.active')]
            .map(s => s.dataset.currencyCode)
            .filter(Boolean);
    };

    window.triggerCurrencyCountsRefresh = scheduleFetch;

})();
