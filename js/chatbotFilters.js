/**
 * chatbotFilters.js
 * - Otváranie/zatváranie filter modalu
 * - Clear all filtre
 * - Live počítadlo aktívnych filtrov na Filters buttone
 */

const filtersBtnBtn   = document.getElementById('filtersBtn');
const overlay         = document.getElementById('filtersModalOverlay');
const closeFiltersBtn = document.getElementById('filtersCloseBtn');
const clearFiltersBtn = document.getElementById('filtersClearBtn');

// Badge elementy
const filtersCountBadge = document.getElementById('filtersCount');
const filtersCountNum   = document.getElementById('filtersCountNum');

// Všetky number inputy v modali
const filterNumberInputs = document.querySelectorAll(
    '#ratingInputStart, #ratingInputEnd, #votesInputStart, #votesInputEnd, #priceInputStart, #priceInputEnd'
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

overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
});

// ============================================
// POČÍTANIE AKTÍVNYCH FILTROV
// ============================================

/**
 * Počítame:
 *  - Rating skupina (ratingInputStart ALEBO ratingInputEnd vyplnený) = 1
 *  - Votes skupina (votesInputStart ALEBO votesInputEnd vyplnený) = 1
 *  - Price skupina (priceInputStart ALEBO priceInputEnd vyplnený) = 1
 *  - Každý .active feature span = 1
 */
function countActiveFilters() {
    let count = 0;

    // Rating pair
    const rS = document.getElementById('ratingInputStart');
    const rE = document.getElementById('ratingInputEnd');
    if ((rS && rS.value.trim() !== '') || (rE && rE.value.trim() !== '')) count++;

    // Votes pair
    const vS = document.getElementById('votesInputStart');
    const vE = document.getElementById('votesInputEnd');
    if ((vS && vS.value.trim() !== '') || (vE && vE.value.trim() !== '')) count++;

    // Price pair
    const pS = document.getElementById('priceInputStart');
    const pE = document.getElementById('priceInputEnd');
    if ((pS && pS.value.trim() !== '') || (pE && pE.value.trim() !== '')) count++;

    // Aktívne features
    const activeFeatures = document.querySelectorAll('.chatbot-filters-modal-body-features-single.active');
    count += activeFeatures.length;

    return count;
}

function updateFilterBadge() {
    const count = countActiveFilters();

    if (count === 0) {
        filtersCountBadge.style.display = 'none';
    } else {
        filtersCountBadge.style.display = 'flex';
        filtersCountNum.textContent = count;
    }
}

// Live update pri písaní do inputov
filterNumberInputs.forEach(input => {
    input.addEventListener('input', updateFilterBadge);
});

// Live update pri kliknutí na feature (event delegation – features môžu byť pridané dynamicky)
overlay.addEventListener('click', (e) => {
    if (e.target.classList.contains('chatbot-filters-modal-body-features-single')) {
        // Počkaj tick aby toggle v filterFeaturesAdding.js prebehol skôr
        setTimeout(updateFilterBadge, 0);
    }
});

// ============================================
// CLEAR ALL FILTERS
// ============================================

function clearAllFilters() {
    // Vyprázdni všetky number inputy
    filterNumberInputs.forEach(input => {
        input.value = '';
    });

    // Odznač všetky aktívne features
    document.querySelectorAll('.chatbot-filters-modal-body-features-single.active').forEach(span => {
        span.classList.remove('active');
    });

    // Aktualizuj badge
    updateFilterBadge();
}

clearFiltersBtn.addEventListener('click', clearAllFilters);

// Inicializácia pri načítaní stránky
updateFilterBadge();