// Burger menu functionality
const burgerBtn = document.querySelector('.header-burger-btn');
const mobileMenu = document.querySelector('.header-mobile-menu');
const closeBtn = document.querySelector('.header-mobile-close');

function hideNotifications() {
    if (typeof window.hideNotifications === 'function') {
        window.hideNotifications();
    }
}

if (burgerBtn && mobileMenu && closeBtn) {
    burgerBtn.addEventListener('click', () => {
        hideNotifications();
        mobileMenu.classList.add('active');
        lockScroll();
    });

    closeBtn.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
        unlockScroll();
    });

    // Close menu when clicking outside
    mobileMenu.addEventListener('click', (e) => {
        if (e.target === mobileMenu) {
            mobileMenu.classList.remove('active');
            unlockScroll();
        }
    });
}
