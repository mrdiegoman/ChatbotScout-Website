const filtersBtnBtn = document.getElementById("filtersBtn");
const overlay = document.getElementById("filtersModalOverlay");
const closeFiltersBtn = document.getElementById("filtersCloseBtn");

// zatvor modal
function closeModal() {
    overlay.style.display = "none";
    document.body.style.overflow = '';
}

filtersBtnBtn.addEventListener('click', () => {
    overlay.style.display = "flex";
    document.body.style.overflow = 'hidden';
});


// klik na X
closeFiltersBtn.addEventListener("click", closeModal);

// klik mimo modal-content
overlay.addEventListener("click", (e) => {
    if(e.target === overlay) {
        closeModal();
    }
});
