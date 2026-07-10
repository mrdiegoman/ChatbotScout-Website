(() => {
    const selectDiv = document.querySelector('.dropdown-wrapper');
    const selected = document.querySelector('.dropdown-selected');
    const options = document.querySelectorAll('.dropdown-option');

    if (!selectDiv || !selected) {
        return;
    }

    selectDiv.addEventListener('click', function(e) {
        if (!e.target.classList.contains('dropdown-option')) {
            this.classList.toggle('active');
        }
    });

    options.forEach(option => {
        option.addEventListener('click', function(e) {
            e.stopPropagation();

            options.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            selected.textContent = this.textContent;
            selectDiv.classList.remove('active');
        });
    });

    document.addEventListener('click', function(e) {
        if (!selectDiv.contains(e.target)) {
            selectDiv.classList.remove('active');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            selectDiv.classList.remove('active');
        }
    });
})();
