const favouriteBtns = document.querySelectorAll(
    '.chatbot-page-main-content-chatbots-grid-view-item-top-favourites-container-btn, .chatbot-page-main-content-chatbots-list-view-item-top-favourites-container-btn, .favourites-page-main-content-chatbots-grid-view-item-top-favourites-container-btn, .favourites-page-main-content-chatbots-list-view-item-top-favourites-container-btn'
);

favouriteBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        this.classList.toggle('active');
    });
});