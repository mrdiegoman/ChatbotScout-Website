/**
 * filterPricingAdding.js
 *
 * Správca pricing model filtrov vo filter popupe.
 *  - Fetchne modely z getPricingModels.php (vždy všetky 6 ENUM hodnôt)
 *  - OR logika — chatbot musí mať aspoň jeden z vybraných modelov
 *  - Live count (getPricingCounts.php) pri každom kliknutí
 *  - Aktualizuje badge počítadlo filtrov
 *  - Funguje na chatbotShowcase aj favouritesShowcase
 */

(function () {
    'use strict';

    // ============================================
    // DETECT PAGE
    // ============================================

    const isFavourites = !!document.querySelector('.favourites-filters-modal-body-features-container');
    const spanClass    = isFavourites
        ? 'favourites-filters-modal-body-pricing-single'
        : 'chatbot-filters-modal-body-pricing-single';

    const pricingContainer = document.getElementById('pricingContainer');
    if (!pricingContainer) return; // filter popup nie je na tejto stránke

    // ============================================
    // DOM
    // ============================================

    const filtersCountBadge  = document.getElementById('filtersCount');
    const filtersCountNum    = document.getElementById('filtersCountNum');
    const filterResultsCount = document.getElementById('filterResultsCount');

    // ============================================
    // BADGE UPDATE
    // Spolupracuje s filterFeaturesAdding.js
    // ============================================

    function updateBadge() {
        if (typeof window.updateFilterBadge === 'function') {
            window.updateFilterBadge();
        }
    }

    // ============================================
    // GET ACTIVE STATE — pre fetch počtov
    // ============================================

    function getActiveState() {
        const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

        const featSpanClass = isFavourites
            ? 'favourites-filters-modal-body-features-single'
            : 'chatbot-filters-modal-body-features-single';
        const intSpanClass = isFavourites
            ? 'favourites-filters-modal-body-integrations-single'
            : 'chatbot-filters-modal-body-integrations-single';

        return {
            ratingStart:         val('ratingInputStart'),
            ratingEnd:           val('ratingInputEnd'),
            votesStart:          val('votesInputStart'),
            votesEnd:            val('votesInputEnd'),
            favoritesStart:      val('favoritesInputStart'),
            favoritesEnd:        val('favoritesInputEnd'),
            priceStart:          val('priceInputStart'),
            priceEnd:            val('priceInputEnd'),
            activeFeatureIds:    [...document.querySelectorAll(`.${featSpanClass}.active`)]
                .map(s => s.dataset.featureId).filter(Boolean),
            activeIntegrationIds: [...document.querySelectorAll(`.${intSpanClass}.active`)]
                .map(s => s.dataset.integrationId).filter(Boolean),
            activePricingModels: [...document.querySelectorAll(`.${spanClass}.active`)]
                .map(s => s.dataset.pricingModel).filter(Boolean),
            isFavourites,
        };
    }

    // ============================================
    // FETCH PRICING COUNTS
    // ============================================

    let debounceTimer = null;

    function scheduleFetch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchAndUpdateCounts, 300);
    }

    function fetchAndUpdateCounts() {
        fetch('../api/getPricingCounts.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(getActiveState()),
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            const counts = data.counts;
            document.querySelectorAll(`.${spanClass}`).forEach(span => {
                const model = span.dataset.pricingModel;
                const name  = span.dataset.pricingName;
                const count = counts[model] ?? 0;
                span.textContent = `${name} (${count})`;
            });

            // Live count (favouritesShowcase + chatbotShowcase)
            if (filterResultsCount && data.total !== undefined) {
                const t = data.total;
                filterResultsCount.textContent = `Found ${t} chatbot${t !== 1 ? 's' : ''}`;
            }
        })
        .catch(() => {});
    }

    // ============================================
    // BUILD SPAN
    // ============================================

    function buildSpan(model) {
        const span = document.createElement('span');
        span.className             = spanClass;
        span.dataset.pricingModel  = model.id;
        span.dataset.pricingName   = model.name;
        span.textContent           = `${model.name} (...)`;

        span.addEventListener('click', () => {
            span.classList.toggle('active');
            updateBadge();
            scheduleFetch();
            if (typeof window.triggerFeatureCountsRefresh === 'function') {
                window.triggerFeatureCountsRefresh();
            }
            if (typeof window.triggerIntegrationCountsRefresh === 'function') {
                window.triggerIntegrationCountsRefresh();
            }
        });

        return span;
    }

    // ============================================
    // FETCH MODELS + RENDER
    // ============================================

    fetch('../api/getPricingModels.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.models.length) return;

            pricingContainer.innerHTML = '';
            data.models.forEach(m => pricingContainer.appendChild(buildSpan(m)));
            fetchAndUpdateCounts();
        })
        .catch(() => {});

    // ============================================
    // EXPOSE — volané z chatbotFilters (clear) a filterFeaturesAdding (badge)
    // ============================================

    window.clearPricingFilters = function () {
        document.querySelectorAll(`.${spanClass}.active`).forEach(s => s.classList.remove('active'));
        updateBadge();
        scheduleFetch();
    };

    window.getActivePricingModels = function () {
        return [...document.querySelectorAll(`.${spanClass}.active`)]
            .map(s => s.dataset.pricingModel)
            .filter(Boolean);
    };

    window.triggerPricingCountsRefresh = scheduleFetch;

})();