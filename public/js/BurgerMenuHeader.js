// Burger menu functionality
const burgerBtn = document.querySelector('.header-burger-btn');
const mobileMenu = document.querySelector('.header-mobile-menu');
const closeBtn = document.querySelector('.header-mobile-close');

burgerBtn.addEventListener('click', () => {
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
