/**
 * filterFeaturesAdding.js
 *
 * Fetchne features z DB cez API a dynamicky ich vykreslí do filter popupu.
 * Funguje pre chatbotShowcase aj favouritesShowcase (detekuje podľa CSS triedy).
 *
 * Basic features  (advanced = FALSE) → #basicFeaturesContainer
 * Advanced features (advanced = TRUE) → #advancedFeaturesContainer
 */

(function () {

    // Zistíme na akej stránke sme – podľa toho použijeme správnu CSS triedu pre spany
    const isFavourites = !!document.querySelector('.favourites-filters-modal-body-features-container');
    const spanClass    = isFavourites
        ? 'favourites-filters-modal-body-features-single'
        : 'chatbot-filters-modal-body-features-single';

    const basicContainer    = document.getElementById('basicFeaturesContainer');
    const advancedContainer = document.getElementById('advancedFeaturesContainer');
    const advancedToggle    = document.getElementById('advancedFeaturesToggleLabel');

    if (!basicContainer) return; // filter popup nie je na tejto stránke

    // ============================================
    // BUILD SPAN
    // ============================================

    function buildSpan(feature) {
        const span = document.createElement('span');
        span.className = spanClass;
        span.dataset.featureId = feature.id;
        span.textContent = feature.name;
        span.addEventListener('click', function () {
            this.classList.toggle('active');
        });
        return span;
    }

    // ============================================
    // RENDER
    // ============================================

    function renderFeatures(basic, advanced) {
        basicContainer.innerHTML = '';

        // Basic features
        if (basic.length === 0) {
            basicContainer.innerHTML = '<span style="font-size:1.2rem;color:var(--grey-color)">No features available.</span>';
        } else {
            basic.forEach(f => basicContainer.appendChild(buildSpan(f)));
        }

        // Advanced features
        if (advancedContainer) {
            advancedContainer.innerHTML = '';
            if (advanced.length === 0) {
                // Schovaj toggle ak nie sú žiadne advanced features
                if (advancedToggle) advancedToggle.style.display = 'none';
            } else {
                if (advancedToggle) advancedToggle.style.display = '';
                advanced.forEach(f => advancedContainer.appendChild(buildSpan(f)));
            }
        }
    }

    // ============================================
    // FETCH
    // ============================================

    fetch('../api/getFeatures.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.error('getFeatures API error');
                return;
            }
            renderFeatures(data.basic, data.advanced);
        })
        .catch(err => {
            console.error('getFeatures fetch failed:', err);
        });

})();