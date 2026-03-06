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

    const filtersCountBadge  = document.getElementById('filtersCount');
    const filtersCountNum    = document.getElementById('filtersCountNum');
    // Live results count (iba na favouritesShowcase)
    const filterResultsCount = document.getElementById('filterResultsCount');

    const NUMBER_INPUT_IDS = [
        'ratingInputStart', 'ratingInputEnd',
        'votesInputStart',  'votesInputEnd',
        'favoritesInputStart', 'favoritesInputEnd',
        'priceInputStart',  'priceInputEnd',
    ];

    const numberInputPairs = [
        ['ratingInputStart', 'ratingInputEnd'],
        ['votesInputStart',  'votesInputEnd'],
        ['favoritesInputStart', 'favoritesInputEnd'],
        ['priceInputStart',  'priceInputEnd'],
    ];

    // ============================================
    // BADGE
    // ============================================

    function updateBadge() {
        let count = 0;

        numberInputPairs.forEach(([a, b]) => {
            const elA = document.getElementById(a);
            const elB = document.getElementById(b);
            if ((elA && elA.value.trim() !== '') || (elB && elB.value.trim() !== '')) count++;
        });

        count += document.querySelectorAll(`.${spanClass}.active`).length;

        if (filtersCountBadge) filtersCountBadge.style.display = count > 0 ? 'flex' : 'none';
        if (filtersCountNum)   filtersCountNum.textContent = count;
    }

    // ============================================
    // GET ACTIVE FILTERS STATE
    // ============================================

    function getActiveFilters() {
        const val = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };
        return {
            ratingStart:      val('ratingInputStart'),
            ratingEnd:        val('ratingInputEnd'),
            votesStart:       val('votesInputStart'),
            votesEnd:         val('votesInputEnd'),
            favoritesStart:   val('favoritesInputStart'),
            favoritesEnd:     val('favoritesInputEnd'),
            priceStart:       val('priceInputStart'),
            priceEnd:         val('priceInputEnd'),
            activeFeatureIds: [...document.querySelectorAll(`.${spanClass}.active`)]
                .map(s => s.dataset.featureId)
                .filter(Boolean),
            isFavourites: isFavourites,
        };
    }

    // ============================================
    // FETCH FEATURE COUNTS + UPDATE SPANS + LIVE RESULTS COUNT
    // ============================================

    let debounceTimer = null;

    function fetchAndUpdateCounts() {
        fetch('../api/getFeatureCounts.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(getActiveFilters()),
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            // Aktualizuj počty pri feature tagoch
            const counts = data.counts;
            document.querySelectorAll(`.${spanClass}`).forEach(span => {
                const fid   = span.dataset.featureId;
                const name  = span.dataset.featureName;
                const count = counts[fid] ?? 0;
                span.textContent = `${name} (${count})`;
            });

            // Live aktualizácia počtu výsledkov vedľa Clear all (iba na favouritesShowcase)
            if (filterResultsCount && data.total !== undefined) {
                const t = data.total;
                filterResultsCount.textContent = `Found ${t} chatbot${t !== 1 ? 's' : ''}`;
            }
        })
        .catch(() => {}); // counts sú non-critical, ticho ignorujeme
    }

    function scheduleFetch() {
        clearTimeout(debounceTimer);
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

        span.addEventListener('click', function () {
            this.classList.toggle('active');
            updateBadge();
            scheduleFetch();
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

    fetch('../api/getFeatures.php')
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

})();