const selectDiv = document.querySelector('.chatbot-page-main-content-topbar-group-sort-select-div');
const selected = document.querySelector('.chatbot-page-main-content-topbar-group-sort-select-selected');
const options = document.querySelectorAll('.chatbot-page-main-content-topbar-group-sort-select-option');

// Otvoriť/zatvoriť dropdown
selectDiv.addEventListener('click', function(e) {
    // Zabráň otvoreniu ak sa kliklo na option
    if (!e.target.classList.contains('chatbot-page-main-content-topbar-group-sort-select-option')) {
        this.classList.toggle('active');
    }
});

// Výber možnosti
options.forEach(option => {
    option.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // Odstráň selected class zo všetkých opcií
        options.forEach(opt => opt.classList.remove('selected'));
        
        // Pridaj selected class na vybratú možnosť
        this.classList.add('selected');
        
        // Aktualizuj zobrazený text
        selected.textContent = this.textContent;
        
        // Zavri dropdown
        selectDiv.classList.remove('active');
        
        // Získaj hodnotu (môžeš ju použiť pre ďalšie spracovanie)
        const value = this.getAttribute('data-value');
        console.log('Vybraná hodnota:', value);
    });
});

// Zatvoriť dropdown pri kliknutí mimo
document.addEventListener('click', function(e) {
    if (!selectDiv.contains(e.target)) {
        selectDiv.classList.remove('active');
    }
});

// Zatvoriť dropdown pri stlačení Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        selectDiv.classList.remove('active');
    }
});