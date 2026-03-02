// Burger menu functionality
const burgerBtn = document.querySelector('.header-burger-btn');
const mobileMenu = document.querySelector('.header-mobile-menu');
const closeBtn = document.querySelector('.header-mobile-close');

burgerBtn.addEventListener('click', () => {
    mobileMenu.classList.add('active');
    document.body.style.overflow = 'hidden';
});

closeBtn.addEventListener('click', () => {
    mobileMenu.classList.remove('active');
    document.body.style.overflow = '';
});

// Close menu when clicking outside
mobileMenu.addEventListener('click', (e) => {
    if (e.target === mobileMenu) {
        mobileMenu.classList.remove('active');
        document.body.style.overflow = '';
    }
});