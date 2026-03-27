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

    // Close mobile search bar when clicking outside
    document.addEventListener('click', function(e) {
        if (mobileSearchBar && mobileSearchBar.classList.contains('active')) {
            if (!mobileSearchBar.contains(e.target) && e.target !== mobileSearchBtn) {
                mobileSearchBar.classList.remove('active');
            }
        }
    });

    // Clear button functionality (pre mobilný search)
    if (mobileSearchInput && mobileSearchClearBtn) {
        const clearIcon = mobileSearchClearBtn.querySelector('.chatbot-page-main-content-topbar-search-input-clear-icon');
        
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

    // Prevent search bar from closing when clicking inside it
    if (mobileSearchBar) {
        mobileSearchBar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});