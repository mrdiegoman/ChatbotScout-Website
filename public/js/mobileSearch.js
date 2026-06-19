// Mobile Search Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');
    const mobileSearchBar = document.getElementById('mobileSearchBar');
    const mobileSearchInput = document.getElementById('mobileSearchInput');
    const mobileSearchClearBtn = document.getElementById('mobileSearchClearBtn');
    const mobileSearchSubmitBtn = document.getElementById('mobileSearchSubmitBtn');

    // Toggle mobile search bar
    if (mobileSearchBtn && mobileSearchBar) {
        mobileSearchBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileSearchBar.classList.toggle('active');
            
            // Focus input keď sa otvori
            if (mobileSearchBar.classList.contains('active')) {
                setTimeout(() => {
                    mobileSearchInput.focus();
                }, 300); // Počkáme na animáciu
            }
        });
    }

    // Clear button functionality (pre mobilný search)
    if (mobileSearchInput && mobileSearchClearBtn) {
        const clearIcon = mobileSearchClearBtn.querySelector('.chatbot-page-main-content-topbar-search-input-clear-icon');

        clearIcon?.classList.toggle('visible', mobileSearchInput.value.length > 0);
        
        mobileSearchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearIcon.classList.add('visible');
            } else {
                clearIcon.classList.remove('visible');
            }
        });

        mobileSearchClearBtn.addEventListener('click', function() {
            mobileSearchInput.value = '';
            clearIcon.classList.remove('visible');
            mobileSearchInput.dispatchEvent(new Event('input', { bubbles: true }));
            mobileSearchInput.focus();
        });
    }

    // Submit button functionality (pre mobilný search)
    if (mobileSearchSubmitBtn && mobileSearchInput) {
        mobileSearchSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const searchValue = mobileSearchInput.value.trim();
            if (searchValue) {
                console.log('Searching for:', searchValue);
                // Tu môžeš pridať svoju search logiku
                // Napríklad: performSearch(searchValue);
            }
        });

        // Enter key support
        mobileSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                mobileSearchSubmitBtn.click();
            }
        });
    }

});
