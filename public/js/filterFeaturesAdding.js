/**
 * filterFeaturesAdding.js
 *
 * Centrálny správca feature filtrov:
 *  - Fetchne features z DB (getFeatures.php) a vykreslí ich do popupu
 *  - Spravuje badge počítadlo aktívnych filtrov na Filters buttone
 *  - Fetchne počty chatbotov per feature (getFeatureCounts.php) a zobrazí ich
 *  - Prepočítava počty pri každej zmene filtra (input, klik na feature)
 *  - Na favouritesShowcase: live aktualizuje počet výsledkov vedľa Clear all
 *  - Limit 10 features + "+N" show more / show less button
 *
 * Funguje na chatbotShowcase aj favouritesShowcase.
 * Vystavuje window.updateFilterBadge() a window.triggerFeatureCountsRefresh()
 * pre volanie z chatbotFilters.js / favouritesShowcase.js (napr. po Clear all).
 */

(function () {
    'use strict';

    // ============================================
    // DETECT PAGE — podľa CSS triedy kontajnera
    // ============================================

    const isFavourites = !!document.querySelector('.favourites-filters-modal-body-features-container');
    const spanClass    = isFavourites
        ? 'favourites-filters-modal-body-features-single'
        : 'chatbot-filters-modal-body-features-single';

    const basicContainer    = document.getElementById('basicFeaturesContainer');
    const advancedContainer = document.getElementById('advancedFeaturesContainer');
    const advancedToggle    = document.getElementById('advancedFeaturesToggleLabel');

    if (!basicContainer) return; // filter popup nie je na tejto stránke

    // ============================================
    // CONSTANTS
    // ============================================

    const FEATURE_LIMIT = 10;

    // ============================================
    // DOM — badge elementy + number inputy + results count
    // ============================================

    const filtersButton      = document.getElementById('filtersBtn');
    const filtersCountBadge  = document.getElementById('filtersCount');
    const filtersCountNum    = document.getElementById('filtersCountNum');
    // Jediný zdroj live počtu výsledkov pre celý filter popup.
    const filterResultsCount = document.getElementById('filterResultsCount');

    const NUMBER_INPUT_IDS = [
        'ratingInputStart', 'ratingInputEnd',
        'votesInputStart',  'votesInputEnd',
        'favoritesInputStart', 'favoritesInputEnd',
        'priceInputStart',  'priceInputEnd',
    ];

    const appliedNumberFilterPairs = [
        ['ratingStart', 'ratingEnd'],
        ['votesStart', 'votesEnd'],
        ['favoritesStart', 'favoritesEnd'],
        ['priceStart', 'priceEnd'],
    ];

    // ============================================
    // BADGE
    // ============================================

    function hasParamValue(value) {
        return value !== undefined && value !== null && String(value).trim() !== '';
    }

    function countParamItems(value) {
        if (Array.isArray(value)) return value.filter(hasParamValue).length;
        return hasParamValue(value) ? 1 : 0;
    }

    function countAppliedFilters(params) {
        let count = 0;

        appliedNumberFilterPairs.forEach(([a, b]) => {
            if (hasParamValue(params[a]) || hasParamValue(params[b])) count++;
        });

        count += countParamItems(params['features[]']);
        count += countParamItems(params['currency[]']);
        count += countParamItems(params['integrations[]']);
        count += countParamItems(params['pricing[]']);

        if (params.hasFreeTrial === true || params.hasFreeTrial === '1') count++;

        return count;
    }

    function updateBadge(params = null) {
        const appliedParams = params && typeof params === 'object'
            ? params
            : (typeof window.getActiveFilterParams === 'function' ? window.getActiveFilterParams() : {});
        const count = countAppliedFilters(appliedParams);

        if (filtersButton) {
            filtersButton.classList.toggle('has-active-filters', count > 0);
            filtersButton.setAttribute(
                'aria-label',
                count > 0 ? `Open filters, ${count} active` : 'Open filters'
            );
        }
        if (filtersCountBadge) filtersCountBadge.style.display = count > 0 ? 'flex' : 'none';
        if (filtersCountNum)   filtersCountNum.textContent = count;

    }

    // ============================================
    // GET ACTIVE FILTERS STATE
    // ============================================

    function getActiveFilters() {
        const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

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
            activeCurrencies: typeof window.getActiveCurrencies === 'function'
                ? window.getActiveCurrencies() : [],
            activeFeatureIds:     [...document.querySelectorAll(`.${spanClass}.active`)]
                .map(s => s.dataset.featureId)
                .filter(Boolean),
            activeIntegrationIds: [...document.querySelectorAll(`.${intSpanClass}.active`)]
                .map(s => s.dataset.integrationId)
                .filter(Boolean),
            activePricingModels: typeof window.getActivePricingModels === 'function'
                ? window.getActivePricingModels() : [],
            hasFreeTrial: document.getElementById('freeTrialFilter')?.classList.contains('active') ?? false,
            isFavourites: isFavourites,
        };
    }

    // ============================================
    // FETCH FEATURE COUNTS + UPDATE SPANS + LIVE RESULTS COUNT
    // ============================================

    let debounceTimer = null;
    let resultsRequestVersion = 0;

    function clearResultsCount() {
        if (!filterResultsCount) return;
        filterResultsCount.classList.remove('is-loading');
        filterResultsCount.setAttribute('aria-busy', 'false');
        filterResultsCount.textContent = '';
    }

    function setResultsCountLoading() {
        if (!filterResultsCount) return;
        filterResultsCount.classList.add('is-loading');
        filterResultsCount.setAttribute('aria-live', 'polite');
        filterResultsCount.setAttribute('aria-busy', 'true');
        filterResultsCount.textContent = 'Searching...';
    }

    function setResultsCountFound(total) {
        if (!filterResultsCount) return;
        filterResultsCount.classList.remove('is-loading');
        filterResultsCount.setAttribute('aria-live', 'polite');
        filterResultsCount.setAttribute('aria-busy', 'false');
        filterResultsCount.textContent = `Found ${total} chatbot${total !== 1 ? 's' : ''}`;
    }

    function setStableChipText(span, text) {
        const currentWidth = span.getBoundingClientRect().width;
        const lockedWidth = parseFloat(span.dataset.stableWidth || '0');

        if (currentWidth > 0) {
            const stableWidth = Math.ceil(Math.max(currentWidth, lockedWidth));
            span.dataset.stableWidth = String(stableWidth);
            span.style.minWidth = `${stableWidth}px`;
        }

        span.textContent = text;

        requestAnimationFrame(() => {
            const nextWidth = span.getBoundingClientRect().width;
            const nextLockedWidth = parseFloat(span.dataset.stableWidth || '0');

            if (nextWidth > nextLockedWidth) {
                const stableWidth = Math.ceil(nextWidth);
                span.dataset.stableWidth = String(stableWidth);
                span.style.minWidth = `${stableWidth}px`;
            }
        });
    }

    function fetchAndUpdateCounts() {
        const requestVersion = ++resultsRequestVersion;

        fetch('api/getFeatureCounts.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(getActiveFilters()),
        })
        .then(res => res.json())
        .then(data => {
            // Staršia odpoveď nesmie prepísať počet po zmene filtrov.
            if (requestVersion !== resultsRequestVersion) return;
            if (!data.success) {
                clearResultsCount();
                return;
            }

            // Aktualizuj počty pri feature tagoch
            const counts = data.counts;
            document.querySelectorAll(`.${spanClass}`).forEach(span => {
                const fid   = span.dataset.featureId;
                const name  = span.dataset.featureName;
                const count = counts[fid] ?? 0;
                setStableChipText(span, `${name} (${count})`);
                // span.style.display = count === 0 && !span.classList.contains('active') ? 'none' : '';
            });

            const freeTrialButton = document.getElementById('freeTrialFilter');
            if (freeTrialButton && data.free_trial_count !== undefined) {
                freeTrialButton.textContent = `Free trial (${data.free_trial_count})`;
            }

            // Live aktualizácia počtu výsledkov vedľa Clear all (iba na favouritesShowcase)
            if (filterResultsCount && data.total !== undefined) {
                setResultsCountFound(data.total);
            } else {
                clearResultsCount();
            }
        })
        .catch(() => {
            if (requestVersion === resultsRequestVersion) clearResultsCount();
        }); // counts sú non-critical, ticho ignorujeme
    }

    function scheduleFetch() {
        clearTimeout(debounceTimer);
        resultsRequestVersion++;
        setResultsCountLoading();
        debounceTimer = setTimeout(fetchAndUpdateCounts, 350);
    }

    // ============================================
    // BUILD FEATURE SPAN
    // ============================================

    function buildSpan(feature) {
        const span = document.createElement('span');
        span.className            = spanClass;
        span.dataset.featureId   = feature.id;
        span.dataset.featureName = feature.name;
        span.textContent          = feature.name;

        span.addEventListener('click', () => {
            span.classList.toggle('active');
            updateBadge();
            scheduleFetch(); // refreshne feature počty
            if (typeof window.triggerIntegrationCountsRefresh === 'function') {
                window.triggerIntegrationCountsRefresh(); // refreshne aj integration počty
            }
            if (typeof window.triggerPricingCountsRefresh === 'function') {
                window.triggerPricingCountsRefresh();
            }
            if (typeof window.triggerCurrencyCountsRefresh === 'function'){
                window.triggerCurrencyCountsRefresh();
            }
        });

        return span;
    }

    // ============================================
    // RENDER WITH LIMIT — helper pre basic aj advanced
    // ============================================

    function renderWithLimit(container, features) {
        container.innerHTML = '';

        if (features.length === 0) return;

        const visible = features.slice(0, FEATURE_LIMIT);
        const hidden  = features.slice(FEATURE_LIMIT);

        // Vykreslí prvých 10
        visible.forEach(f => container.appendChild(buildSpan(f)));

        if (hidden.length === 0) return;

        // Wrapper pre schované features
        const hiddenWrapper = document.createElement('div');
        hiddenWrapper.className = 'features-hidden-wrapper';
        hiddenWrapper.style.display = 'none';
        hidden.forEach(f => hiddenWrapper.appendChild(buildSpan(f)));
        container.appendChild(hiddenWrapper);

        // Show more button (+N)
        const showMoreBtn = document.createElement('span');
        showMoreBtn.className   = 'features-show-more-btn';
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
                // Pri collapse — ak sú aktívne schované features, neschovaj ich
                const hasActiveHidden = hiddenWrapper.querySelector(`.${spanClass}.active`);
                if (hasActiveHidden) {
                    // Ostatok schovaných features ostane viditeľný
                    return;
                }
                hiddenWrapper.style.display = 'none';
                showMoreBtn.textContent     = `+${hidden.length}`;
                showMoreBtn.classList.remove('features-show-more-btn--open');
            }
        });
    }

    // ============================================
    // RENDER FEATURES INTO CONTAINERS
    // ============================================

    function renderFeatures(basic, advanced) {
        basicContainer.innerHTML = '';

        if (basic.length === 0) {
            basicContainer.innerHTML =
                '<span style="font-size:1.2rem;color:var(--grey-color)">No features available.</span>';
        } else {
            renderWithLimit(basicContainer, basic);
        }

        if (advancedContainer) {
            advancedContainer.innerHTML = '';
            if (advanced.length === 0) {
                if (advancedToggle) advancedToggle.style.display = 'none';
            } else {
                if (advancedToggle) advancedToggle.style.display = '';
                renderWithLimit(advancedContainer, advanced);
            }
        }

        fetchAndUpdateCounts();
    }

    // ============================================
    // LISTEN: number inputs → badge + counts
    // ============================================

    NUMBER_INPUT_IDS.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => {
                updateBadge();
                scheduleFetch();
            });
        }
    });

    // ============================================
    // FETCH FEATURES FROM API
    // ============================================

    fetch('api/getFeatures.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) { console.error('getFeatures API error'); return; }
            renderFeatures(data.basic, data.advanced);
        })
        .catch(err => console.error('getFeatures fetch failed:', err));

    updateBadge();

    // ============================================
    // EXPOSE GLOBALLY
    // ============================================

    window.updateFilterBadge           = updateBadge;
    window.triggerFeatureCountsRefresh = scheduleFetch;
    window.clearFilterResultsCount     = function () {
        clearTimeout(debounceTimer);
        resultsRequestVersion++;
        clearResultsCount();
    };

})();
