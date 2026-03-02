const featuresbtn = document.querySelectorAll('.chatbot-filters-modal-body-features-single');

featuresbtn.forEach(btn => {
    btn.addEventListener('click', function() {
        this.classList.toggle('active');
    });
});