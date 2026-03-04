/**
 * filterFeaturesAdding.js
 *
 * Centrálny správca feature filtrov:
 *  - Fetchne features z DB (getFeatures.php) a vykreslí ich do popupu
 *  - Spravuje badge počítadlo aktívnych filtrov na Filters buttone
 *  - Fetchne počty chatbotov per feature (getFeatureCounts.php) a zobrazí ich
 *  - Prepočítava počty pri každej zmene filtra (input, klik na feature)
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
    // DOM — badge elementy + number inputy
    // ============================================

    const filtersCountBadge = document.getElementById('filtersCount');
    const filtersCountNum   = document.getElementById('filtersCountNum');

    const NUMBER_INPUT_IDS = [
        'ratingInputStart', 'ratingInputEnd',
        'votesInputStart',  'votesInputEnd',
        'priceInputStart',  'priceInputEnd',
    ];

    const numberInputPairs = [
        ['ratingInputStart', 'ratingInputEnd'],
        ['votesInputStart',  'votesInputEnd'],
        ['priceInputStart',  'priceInputEnd'],
    ];

    // ============================================
    // BADGE
    // ============================================

    function updateBadge() {
        let count = 0;

        // Každý pár (from–to) sa počíta ako 1 aktívny filter
        numberInputPairs.forEach(([a, b]) => {
            const elA = document.getElementById(a);
            const elB = document.getElementById(b);
            if ((elA && elA.value.trim() !== '') || (elB && elB.value.trim() !== '')) count++;
        });

        // Každá aktívna feature = 1
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
            priceStart:       val('priceInputStart'),
            priceEnd:         val('priceInputEnd'),
            activeFeatureIds: [...document.querySelectorAll(`.${spanClass}.active`)]
                .map(s => s.dataset.featureId)
                .filter(Boolean),
        };
    }

    // ============================================
    // FETCH FEATURE COUNTS + UPDATE SPANS
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
            const counts = data.counts;

            document.querySelectorAll(`.${spanClass}`).forEach(span => {
                const fid   = span.dataset.featureId;
                const name  = span.dataset.featureName;
                const count = counts[fid] ?? 0;
                span.textContent = `${name} (${count})`;
            });
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
        span.textContent          = feature.name; // (počet) sa doplní po fetchAndUpdateCounts

        span.addEventListener('click', function () {
            this.classList.toggle('active');
            updateBadge();
            scheduleFetch();
        });

        return span;
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
            basic.forEach(f => basicContainer.appendChild(buildSpan(f)));
        }

        if (advancedContainer) {
            advancedContainer.innerHTML = '';
            if (advanced.length === 0) {
                if (advancedToggle) advancedToggle.style.display = 'none';
            } else {
                if (advancedToggle) advancedToggle.style.display = '';
                advanced.forEach(f => advancedContainer.appendChild(buildSpan(f)));
            }
        }

        // Po vykreslení hneď fetchni počty
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

    // Inicializácia badge pri načítaní stránky
    updateBadge();

    // ============================================
    // EXPOSE GLOBALLY — pre chatbotFilters.js + favouritesShowcase.js
    // Volané napr. po "Clear all"
    // ============================================

    window.updateFilterBadge          = updateBadge;
    window.triggerFeatureCountsRefresh = scheduleFetch;

})();