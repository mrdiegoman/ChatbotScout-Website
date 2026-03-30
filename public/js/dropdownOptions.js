const selectDiv = document.querySelector('.dropdown-wrapper');
const selected = document.querySelector('.dropdown-selected');
const options = document.querySelectorAll('.dropdown-option');

if (!selectDiv) return;

// Otvoriť/zatvoriť dropdown
selectDiv.addEventListener('click', function(e) {
    // Zabráň otvoreniu ak sa kliklo na option
    if (!e.target.classList.contains('dropdown-option')) {
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