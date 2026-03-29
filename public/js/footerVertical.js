document.addEventListener('DOMContentLoaded', function() {
    const accordionButtons = document.querySelectorAll('.footer-accordion-btn');

    accordionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const isActive = this.classList.contains('active');
            const content = this.nextElementSibling;

            // Toggle active class
            this.classList.toggle('active');
            this.setAttribute('aria-expanded', !isActive);

            if (!isActive) {
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
            }
        });
    });
});
