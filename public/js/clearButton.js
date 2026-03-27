const searchInput = document.querySelector('.chatbot-page-main-content-topbar-search-input');
const clearIcon = document.querySelector('.chatbot-page-main-content-topbar-search-input-clear-icon');
const clearBtn = document.querySelector('.chatbot-page-main-content-topbar-search-input-clear-btn');

searchInput.addEventListener('input', function() {
    if (this.value.length > 0) {
        clearIcon.classList.add('visible'); // Pridaj na SVG, nie na button
    } else {
        clearIcon.classList.remove('visible');
    }
});

clearBtn.addEventListener('click', function() {
    searchInput.value = '';
    clearIcon.classList.remove('visible');
    searchInput.focus();
});