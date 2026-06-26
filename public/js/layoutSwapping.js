const listBtn = document.getElementById('listBtn');
const gridBtn = document.getElementById('gridBtn');
const gridView = document.getElementById('gridView');
const listView = document.getElementById('listView');

const LEGACY_LAYOUT_KEY = 'preferredLayout';
const CHATBOT_LAYOUT_KEY = 'chatbot_showcase_view';
const FAVOURITES_LAYOUT_KEY = 'favourites_showcase_view';

function hasPreferencesConsent() {
    if (
        window.chatbotScoutConsent &&
        typeof window.chatbotScoutConsent.hasPreferences === 'function'
    ) {
        return window.chatbotScoutConsent.hasPreferences();
    }

    try {
        const raw = localStorage.getItem('cookie_consent_v2');
        if (!raw) return false;

        const consent = JSON.parse(raw);
        return Boolean(consent && consent.preferences === true);
    } catch (_) {
        return false;
    }
}

function currentLayoutKey() {
    const path = window.location.pathname.toLowerCase();
    return path.includes('favourite') ? FAVOURITES_LAYOUT_KEY : CHATBOT_LAYOUT_KEY;
}

function removeLegacyLayout() {
    try { localStorage.removeItem(LEGACY_LAYOUT_KEY); } catch (_) {}
}

function migrateLegacyLayoutIfNeeded() {
    if (!hasPreferencesConsent()) {
        removeLegacyLayout();
        return;
    }

    try {
        const key = currentLayoutKey();
        const current = localStorage.getItem(key);
        const legacy = localStorage.getItem(LEGACY_LAYOUT_KEY);

        if (!current && (legacy === 'grid' || legacy === 'list')) {
            localStorage.setItem(key, legacy);
        }

        removeLegacyLayout();
    } catch (_) {}
}

function getSavedLayout() {
    if (!hasPreferencesConsent()) {
        removeLegacyLayout();
        return 'grid';
    }

    try {
        const saved = localStorage.getItem(currentLayoutKey());
        return saved === 'list' ? 'list' : 'grid';
    } catch (_) {
        return 'grid';
    }
}

function setActiveLayout(layout) {
    if (!listBtn || !gridBtn || !gridView || !listView) return;

    listBtn.classList.remove('active');
    gridBtn.classList.remove('active');

    gridView.classList.remove('active');
    listView.classList.remove('active');

    if (layout === 'list') {
        listBtn.classList.add('active');
        listView.classList.add('active');
    } else {
        gridBtn.classList.add('active');
        gridView.classList.add('active');
    }

    if (hasPreferencesConsent()) {
        try { localStorage.setItem(currentLayoutKey(), layout); } catch (_) {}
    } else {
        removeLegacyLayout();
    }
}

migrateLegacyLayoutIfNeeded();

if (listBtn && gridBtn) {
    listBtn.addEventListener('click', () => setActiveLayout('list'));
    gridBtn.addEventListener('click', () => setActiveLayout('grid'));
}

setActiveLayout(getSavedLayout());
