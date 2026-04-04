const listBtn = document.getElementById('listBtn');
const gridBtn = document.getElementById('gridBtn');
const gridView = document.getElementById('gridView');
const listView = document.getElementById('listView');

function hasPreferencesConsent() {
    try {
        var raw = localStorage.getItem('cookie_consent_v2');
        if (raw) {
            var c = JSON.parse(raw);
            return c && c.preferences === true;
        }
    } catch(e) {}
    return false;
}

function setActiveLayout(layout) {
    // Odstráň active triedu z oboch tlačidiel
    listBtn.classList.remove('active');
    gridBtn.classList.remove('active');

    // Skry oba layouty
    gridView.classList.remove('active');
    listView.classList.remove('active');

    // Aktivuj vybraný layout
    if (layout === 'list') {
        listBtn.classList.add('active');
        listView.classList.add('active');
    } else {
        gridBtn.classList.add('active');
        gridView.classList.add('active');
    }

    // Ulož preferenicu do localStorage len ak má používateľ súhlas
    if (hasPreferencesConsent()) {
        localStorage.setItem('preferredLayout', layout);
    }
}

listBtn.addEventListener('click', () => setActiveLayout('list'));
gridBtn.addEventListener('click', () => setActiveLayout('grid'));

// Načítaj uloženú preferenicu pri načítaní stránky (len ak má súhlas)
const savedLayout = (hasPreferencesConsent() && localStorage.getItem('preferredLayout')) || 'grid';
setActiveLayout(savedLayout);
