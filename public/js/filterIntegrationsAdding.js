/**
 * filterIntegrationsAdding.js
 *
 * Správca integrations filtrov vo filter popupe.
 *  - Fetchne integrácie z DB (getIntegrations.php) a vykreslí ich
 *  - OR logika — chatbot musí mať aspoň jednu z vybraných integrácií
 *  - Limit 10 položiek + "+N" show more / show less (rovnaký vzor ako features)
 *  - Info button s hover tooltipom (OR vysvetlenie)
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
        ? 'favourites-filters-modal-body-integrations-single'
        : 'chatbot-filters-modal-body-integrations-single';

    const intContainer = document.getElementById('integrationsContainer');
    if (!intContainer) return; // filter popup nie je na tejto stránke

    // ============================================
    // CONSTANTS
    // ============================================

    const INTEGRATION_LIMIT = 10;

    // ============================================
    // DOM
    // ============================================

    const filtersCountBadge  = document.getElementById('filtersCount');
    const filtersCountNum    = document.getElementById('filtersCountNum');
    // ============================================
    // BUILD SPAN
    // ============================================

    function buildSpan(integration) {
        const span = document.createElement('span');
        span.className              = spanClass;
        span.dataset.integrationId  = integration.id;
        span.dataset.integrationName = integration.name;
        span.textContent            = `${integration.name} (...)`;

        span.addEventListener('click', () => {
            span.classList.toggle('active');
            updateBadge();
            scheduleFetch(); // refreshne integration počty
            if (typeof window.triggerFeatureCountsRefresh === 'function') {
                window.triggerFeatureCountsRefresh(); // refreshne aj feature počty
            }
            if (typeof window.triggerPricingCountsRefresh === 'function') {
                window.triggerPricingCountsRefresh();
            }
            if (typeof window.triggerCurrencyCountsRefresh === 'function') {
                window.triggerCurrencyCountsRefresh();
            }
        });

        return span;
    }

    // ============================================
    // RENDER WITH LIMIT (rovnaký vzor ako features)
    // ============================================

    function renderWithLimit(container, integrations) {
        const visible = integrations.slice(0, INTEGRATION_LIMIT);
        const hidden  = integrations.slice(INTEGRATION_LIMIT);

        visible.forEach(i => container.appendChild(buildSpan(i)));

        if (hidden.length === 0) return;

        // Hidden wrapper
        const hiddenWrapper = document.createElement('div');
        hiddenWrapper.className    = 'integrations-hidden-wrapper';
        hiddenWrapper.style.display = 'none';
        hidden.forEach(i => hiddenWrapper.appendChild(buildSpan(i)));
        container.appendChild(hiddenWrapper);

        // Show more button
        const showMoreBtn = document.createElement('span');
        showMoreBtn.className   = 'features-show-more-btn'; // reuse rovnaký CSS
        showMoreBtn.textContent = `+${hidden.length}`;
        container.appendChild(showMoreBtn);

        let expanded = false;

        showMoreBtn.addEventListener('click', () => {
            expanded = !expanded;

            if (expanded) {
                hiddenWrapper.style.display = 'contents';
                showMoreBtn.textContent     = 'Show less';
                showMoreBtn.classList.add('features-show-more-btn--open');
            } else {
                const hasActiveHidden = hiddenWrapper.querySelector(`.${spanClass}.active`);
                if (hasActiveHidden) return;

                hiddenWrapper.style.display = 'none';
                showMoreBtn.textContent     = `+${hidden.length}`;
                showMoreBtn.classList.remove('features-show-more-btn--open');
            }
        });
    }

    // ============================================
    // BADGE UPDATE
    // Spolupracuje s filterFeaturesAdding.js — obaja čítajú z DOM
    // ============================================

    function updateBadge() {
        if (typeof window.updateFilterBadge === 'function') {
            // filterFeaturesAdding.js prepočíta celý badge vrátane integrácií
            window.updateFilterBadge();
        }
    }

    // ============================================
    // FETCH INTEGRATION COUNTS
    // ============================================

    let debounceTimer = null;

    function scheduleFetch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchAndUpdateCounts, 300);
    }

    function getActiveState() {
        const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };
        return {
            ratingStart:          val('ratingInputStart'),
            ratingEnd:            val('ratingInputEnd'),
            votesStart:           val('votesInputStart'),
            votesEnd:             val('votesInputEnd'),
            favoritesStart:       val('favoritesInputStart'),
            favoritesEnd:         val('favoritesInputEnd'),
            priceStart:           val('priceInputStart'),
            priceEnd:             val('priceInputEnd'),
            activeCurrencies: typeof window.getActiveCurrencies === 'function'
                ? window.getActiveCurrencies() : [],
            activeFeatureIds:     [...document.querySelectorAll(
                isFavourites
                    ? '.favourites-filters-modal-body-features-single.active'
                    : '.chatbot-filters-modal-body-features-single.active'
            )].map(s => s.dataset.featureId).filter(Boolean),
            activeIntegrationIds: [...document.querySelectorAll(`.${spanClass}.active`)]
                .map(s => s.dataset.integrationId).filter(Boolean),
            activePricingModels: typeof window.getActivePricingModels === 'function'
                ? window.getActivePricingModels() : [],
            isFavourites,
        };
    }

    function fetchAndUpdateCounts() {
        fetch('api/getIntegrationCounts.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(getActiveState()),
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            // Aktualizuj počty
            const counts = data.counts;
            document.querySelectorAll(`.${spanClass}`).forEach(span => {
                const iid   = span.dataset.integrationId;
                const name  = span.dataset.integrationName;
                const count = counts[iid] ?? 0;
                span.textContent = `${name} (${count})`;
            });

        })
        .catch(err => console.error('getIntegrationCounts fetch failed:', err));
    }

    // ============================================
    // FETCH INTEGRATIONS + RENDER
    // ============================================

    fetch('api/getIntegrations.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.integrations.length) {
                intContainer.innerHTML =
                    '<span style="font-size:1.2rem;color:var(--grey-color)">No integrations available.</span>';
                return;
            }

            intContainer.innerHTML = '';
            renderWithLimit(intContainer, data.integrations);
            fetchAndUpdateCounts();
        })
        .catch(err => console.error('getIntegrations fetch failed:', err));

    // ============================================
    // EXPOSE — volané z filterFeaturesAdding (badge sync) a chatbotFilters (clear)
    // ============================================

    window.clearIntegrationFilters = function () {
        document.querySelectorAll(`.${spanClass}.active`).forEach(s => s.classList.remove('active'));
        updateBadge();
        scheduleFetch();
    };

    window.getActiveIntegrationIds = function () {
        return [...document.querySelectorAll(`.${spanClass}.active`)]
            .map(s => s.dataset.integrationId)
            .filter(Boolean);
    };

    window.triggerIntegrationCountsRefresh = scheduleFetch;

})();
