document.addEventListener('DOMContentLoaded', function() {
    const accordionButtons = document.querySelectorAll('.footer-accordion-btn');
    
    accordionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const isActive = this.classList.contains('active');
            const content = this.nextElementSibling;
            
            // Toggle active class
            this.classList.toggle('active');
            content.classList.toggle('active');
            
            // Update aria-expanded
            this.setAttribute('aria-expanded', !isActive);
        });
    });
});