/**
 * favouritesShowcase.js
 *
 * Dynamické načítanie obľúbených chatbotov pre prihláseného usera.
 * - Grid / List view
 * - Sort + pagination
 * - × tlačidlo na odstránenie z obľúbených (volá toggleFavorite.php)
 * - Filter badge počítadlo
 * - Rating modal (rovnaký ako chatbotShowcase)
 */

(function () {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    const state = {
        currentView:     'grid',
        currentSort:     'date-added',
        currentPage:     1,
        totalPages:      1,
        isLoading:       false,
        ratingChatbotId: null,
        ratingValue:     0,
        sortDirection:   'desc',
        searchQuery: '',
    };

    // The cards use only this applied snapshot. Controls inside the modal are
    // intentionally a temporary draft until the user presses Apply Filters.
    let appliedFilterParams = {};

    const LOGIN_URL = 'login.php?redirect=favouritesShowcase.php';

    // ============================================
    // DOM REFERENCES
    // ============================================

    const gridView      = document.getElementById('gridView');
    const listView      = document.getElementById('listView');
    const loadingEl     = document.getElementById('favouritesLoading');
    const emptyState    = document.getElementById('favouritesEmpty');
    const bottomBar     = document.getElementById('bottomBar');
    const paginationEl  = document.getElementById('paginationContainer');
    const moveUpBtn     = document.getElementById('moveUpBtn');
    const listBtn       = document.getElementById('listBtn');
    const gridBtn       = document.getElementById('gridBtn');
    const sortSelectDiv = document.getElementById('sortSelectDiv');
    const sortSelected  = document.getElementById('sortSelected');
    const sortArrow     = document.getElementById('sortArrow');
    const filtersCount  = document.getElementById('filtersCount');
    const filtersCountNum = document.getElementById('filtersCountNum');

    // Filters modal
    const filtersBtn      = document.getElementById('filtersBtn');
    const filtersOverlay  = document.getElementById('filtersModalOverlay');
    const filtersCloseBtn = document.getElementById('filtersCloseBtn');
    const filtersClearBtn = document.getElementById('filtersClearBtn');
    const filterResultsCount = document.getElementById('filterResultsCount');
    const freeTrialFilter = document.getElementById('freeTrialFilter');

    // Rating modal
    const ratingOverlay      = document.getElementById('ratingModalOverlay');
    const ratingModalTitle   = document.getElementById('ratingModalTitle');
    const ratingStarsPicker  = document.getElementById('ratingStarsPicker');
    const ratingStarsLabel   = document.getElementById('ratingStarsLabel');
    const ratingTextInput    = document.getElementById('ratingTextInput');
    const ratingModalError   = document.getElementById('ratingModalError');
    const ratingModalSuccess = document.getElementById('ratingModalSuccess');
    const ratingSubmitBtn    = document.getElementById('ratingModalSubmitBtn');
    const ratingCancelBtn    = document.getElementById('ratingModalCancelBtn');
    const ratingCloseBtn     = document.getElementById('ratingModalCloseBtn');

    const starLabels = ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

    // Filter inputs
    const filterNumberInputs = document.querySelectorAll(
        '#ratingInputStart, #ratingInputEnd, #votesInputStart, #votesInputEnd, #favoritesInputStart, #favoritesInputEnd, #priceInputStart, #priceInputEnd'
    );

    // ============================================
    // HELPERS
    // ============================================

    function setRatingSubmitText(text) {
        ratingSubmitBtn.classList.remove('is-loading');
        ratingSubmitBtn.textContent = text;
    }

    function setRatingSubmitLoading() {
        ratingSubmitBtn.classList.add('is-loading');
        ratingSubmitBtn.innerHTML = '<span class="chatbot-rating-submit-spinner" aria-hidden="true"></span><span>Submitting</span>';
    }

    function hasPreferencesConsent() {
        try {
            const rawConsent = localStorage.getItem('cookie_consent_v2');
            if (!rawConsent) return false;

            const consent = JSON.parse(rawConsent);
            return Boolean(consent && consent.preferences === true);
        } catch (_) {
            return false;
        }
    }

    function getSavedPreference(key) {
        if (!hasPreferencesConsent()) return null;

        try { return localStorage.getItem(key); } catch (_) { return null; }
    }

    function savePreference(key, value) {
        if (!hasPreferencesConsent()) return;

        try { localStorage.setItem(key, value); } catch (_) {}
    }

    function hasStatisticsConsent() {
        return Boolean(
            window.chatbotScoutConsent &&
            typeof window.chatbotScoutConsent.hasStatistics === 'function' &&
            window.chatbotScoutConsent.hasStatistics()
        );
    }

    function ensureVisitorReady() {
        if (!hasStatisticsConsent()) return Promise.resolve(null);

        if (window.chatbotScoutVisitor && typeof window.chatbotScoutVisitor.ready === 'function') {
            return window.chatbotScoutVisitor.ready().catch(() => null);
        }

        if (typeof window.collectAndSendVisitor === 'function') {
            return window.collectAndSendVisitor().catch(() => null);
        }

        return Promise.resolve(null);
    }

    function recordChatbotVisit(chatbotId) {
        return ensureVisitorReady().then(() => fetch('api/recordVisit.php', {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                chatbot_id: chatbotId,
                source_page: window.location.pathname
            }),
        })).catch(() => {});
    }

    function setLoading(on, isSearch = false) {
        if (isSearch) {
            const container = currentContainer();
            container.style.opacity = on ? '0.35' : '1';
            container.style.transition = 'opacity 0.15s ease';
        } else {
            loadingEl.style.display = on ? 'flex' : 'none';
        }
        state.isLoading = on;
    }

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderPricing(pricingStr) {
        if (!pricingStr) return '';
        const parts = pricingStr.split(' | ');
        // Ak je celkový string kratší ako 12 znakov → jedna linka (napr. "FREE" alebo "$50+")
        // Ak je dlhší → rozdeľ na riadky s dividerom
        if (parts.length === 1 || pricingStr.length < 12) {
            return pricingStr;;
        }
        
        return parts.map((part, i) => {
            const line = `<span class="sipka-line">${escapeHtml(part)}</span>`;
            const divider = (i < parts.length - 1) ? '<span class="sipka-divider"></span>' : '';
            return line + divider;
        }).join('');
    }

    function currentContainer() {
        return state.currentView === 'grid' ? gridView : listView;
    }

    function buildStars(rating, chatbotId) {
        const value = (rating !== null && Number(rating) > 0)
            ? Math.round(Number(rating) * 2) / 2 : 0;
        return `
            <div class="favourites-page-main-content-chatbots-item-top-base-2-rating favourites-rating-trigger"
                 data-rating="${value}"
                 data-chatbot-id="${escapeHtml(chatbotId)}"
                 title="Click to rate">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
            </div>`;
    }

    function buildTags(tags) {
        if (!tags || tags.length === 0) return '';
        const systemTags = ['Popular', 'Sponsored'];
        return tags
            .filter(t => !systemTags.includes(t.name))
            .map(t => `<span><p>✦ ${escapeHtml(t.name)} ✦</p></span>`)
            .join('');
    }

    function buildFreeTrialTag(chatbot) {
        if (!chatbot.has_free_trial) return '';

        const days = Number(chatbot.free_trial_days);
        const label = Number.isFinite(days) && days > 0
            ? `${days}-day free trial`
            : 'Free trial available';

        return `<span class="favourites-tag-free-trial"><p>✦ ${label} ✦</p></span>`;
    }

    function buildFeatureCards(features) {
        if (!features || features.length === 0) {
            return '<div class="favourites-page-main-content-chatbots-grid-view-item-card"><ul><li>No features listed</li></ul></div>';
        }
        const chunkSize = 4;
        let html = '';
        for (let i = 0; i < features.length; i += chunkSize) {
            html += `<div class="favourites-page-main-content-chatbots-grid-view-item-card"><ul>`;
            features.slice(i, i + chunkSize).forEach(f => { html += `<li>${escapeHtml(f)}</li>`; });
            html += `</ul></div>`;
        }
        return html;
    }

    function logoSrc(path) {
        const value = String(path || '').trim();
        if (!value) return 'img/ChatbotScout_logo.png';
        if (/^(https?:)?\/\//i.test(value) || value.startsWith('/') || value.startsWith('data:') || value.startsWith('../')) {
            return value;
        }
        if (value.startsWith('public/')) {
            return `../${value}`;
        }
        return value;
    }

    function logoHtml(chatbot) {
        const src = escapeHtml(logoSrc(chatbot.logo_url));
        return `<img src="${src}" alt="${escapeHtml(chatbot.name)} logo" loading="lazy">`;
    }

    // ============================================
    // HTML BUILDERS
    // ============================================

    function buildRemoveBtn(chatbotId) {
        return `
            <button type="button"
                    class="favourites-page-main-content-chatbots-grid-view-item-top-remove-btn fav-remove-btn"
                    data-chatbot-id="${escapeHtml(chatbotId)}"
                    aria-label="Remove from favourites"
                    title="Remove from favourites">
                ×
            </button>`;
    }

    function buildGridItem(chatbot) {
        const stars   = buildStars(chatbot.rating_average, chatbot.id);
        const tags    = buildTags(chatbot.tags);
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = renderPricing(chatbot.pricing);
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const integrations = chatbot.integrations || [];
        const intJson      = escapeAttr(JSON.stringify(integrations));
        const intEmpty     = integrations.length === 0 ? ' platforms-btn--empty' : '';
        const rating  = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount  = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';
        const reviewsUrl = `chatbot-reviews?chatbot=${encodeURIComponent(chatbot.id)}`;
        let badgeHtml = '';
        if (chatbot.is_sponsored) badgeHtml += `<span class="favourites-tag-sponsored"><p>✦ Sponsored ✦</p></span>`;
        badgeHtml += buildFreeTrialTag(chatbot);

        return `
        <div class="favourites-page-main-content-chatbots-grid-view-item" data-chatbot-id="${escapeHtml(chatbot.id)}">
            ${buildRemoveBtn(chatbot.id)}
            <div class="favourites-page-main-content-chatbots-grid-view-item-tags">${badgeHtml}${tags}</div>
            <div class="favourites-page-main-content-chatbots-grid-view-item-top">
                <div class="favourites-page-main-content-chatbots-grid-view-item-top-img-wrapper">
                    ${logoHtml(chatbot)}
                </div>
                <div class="favourites-page-main-content-chatbots-grid-view-item-top-base">
                    <div class="favourites-page-main-content-chatbots-grid-view-item-top-base-1">
                        <p>${name}</p>
                        <div class="favourites-page-main-content-chatbots-grid-view-item-top-base-2">
                            ${stars}
                            <p>${rating}</p>
                            <a class="favourites-reviews-link" href="${reviewsUrl}" aria-label="Read reviews for ${escapeAttr(chatbot.name)}"><p>(${rCount})</p></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="favourites-page-main-content-chatbots-grid-view-item-description-container">
                <div class="favourites-page-main-content-chatbots-grid-view-item-description-container-header">
                    <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-subtitle">Description</p>
                    <button type="button"
                            class="favourites-page-main-content-chatbots-grid-view-item-features-container-1-imp-btn platforms-popup-trigger${intEmpty}"
                            aria-haspopup="dialog"
                            data-integrations="${intJson}"
                            data-chatbot-name="${escapeAttr(name)}">
                        <p>Platforms</p>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
                        </svg>
                    </button>
                </div>
                <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-text">${desc}</p>
            </div>
            <div class="favourites-page-main-content-chatbots-grid-view-item-btns-container">
                <div class="favourites-page-main-content-chatbots-grid-view-item-btns-container-sipka">
                    <span class="favourites-page-main-content-chatbots-grid-view-item-btns-container-sipka-text">${pricing}</span>
                </div>
                <a href="${link}" target="_blank" rel="noopener noreferrer"
                class="favourites-page-main-content-chatbots-grid-view-item-btns-container-go-to-website"
                data-chatbot-id="${escapeHtml(chatbot.id)}">
                    <p>Go to Website</p>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h6v6"/><path d="M10 14 21 3"/>
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    </svg>
                </a>
            </div>
        </div>`;
    }

    function buildListItem(chatbot) {
        const stars   = buildStars(chatbot.rating_average, chatbot.id);
        const tags    = buildTags(chatbot.tags);
        const integrations = chatbot.integrations || [];
        const intJson      = escapeAttr(JSON.stringify(integrations));
        const intEmpty     = integrations.length === 0 ? ' platforms-btn--empty' : '';
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = renderPricing(chatbot.pricing);
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const rating  = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount  = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';
        const reviewsUrl = `chatbot-reviews?chatbot=${encodeURIComponent(chatbot.id)}`;
        let badgeHtml = '';
        if (chatbot.is_sponsored) badgeHtml += `<span class="favourites-tag-sponsored"><p>✦ Sponsored ✦</p></span>`;
        badgeHtml += buildFreeTrialTag(chatbot);

        return `
        <div class="favourites-page-main-content-chatbots-list-view-item-containter" data-chatbot-id="${escapeHtml(chatbot.id)}">
            <button type="button"
                    class="favourites-page-main-content-chatbots-list-view-item-remove-btn fav-remove-btn"
                    data-chatbot-id="${escapeHtml(chatbot.id)}"
                    aria-label="Remove from favourites"
                    title="Remove from favourites">×</button>
            <div class="favourites-page-main-content-chatbots-list-view-item-tags">${badgeHtml}${tags}</div>
            <div class="favourites-page-main-content-chatbots-list-view-item">
                <div class="favourites-page-main-content-chatbots-list-view-item-features-container-left">
                    <div class="favourites-page-main-content-chatbots-list-view-item-top">
                        <div class="favourites-page-main-content-chatbots-list-view-item-top-img-wrapper">
                            ${logoHtml(chatbot)}
                        </div>
                        <div class="favourites-page-main-content-chatbots-list-view-item-top-base">
                            <div class="favourites-page-main-content-chatbots-list-view-item-top-base-1">
                                <p>${name}</p>
                                <div class="favourites-page-main-content-chatbots-list-view-item-top-base-2">
                                    ${stars}
                                    <p>${rating}</p>
                                    <a class="favourites-reviews-link" href="${reviewsUrl}" aria-label="Read reviews for ${escapeAttr(chatbot.name)}"><p>(${rCount})</p></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="favourites-page-main-content-chatbots-list-view-item-description-container">
                        <div class="favourites-page-main-content-chatbots-grid-view-item-description-container-header">
                            <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-subtitle">Description</p>
                            <button type="button"
                                    class="favourites-page-main-content-chatbots-grid-view-item-features-container-1-imp-btn platforms-popup-trigger${intEmpty}"
                                    aria-haspopup="dialog"
                                    data-integrations="${intJson}"
                                    data-chatbot-name="${escapeAttr(name)}">
                                <p>Platforms</p>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
                                </svg>
                            </button>
                        </div>
                        <p class="favourites-page-main-content-chatbots-list-view-item-description-container-text">${desc}</p>
                    </div>
                    <div class="favourites-page-main-content-chatbots-list-view-item-pricing favourites-page-main-content-chatbots-list-view-item-pricing--left">
                        <div class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka">
                            <span class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka-text">${pricing}</span>
                        </div>
                    </div>
                </div>
                <div class="favourites-page-main-content-chatbots-list-view-item-features-container-right">
                    <div class="favourites-page-main-content-chatbots-list-view-item-features-container">
                        <div class="favourites-page-main-content-chatbots-list-view-item-features-container-1">
                            <p class="favourites-page-main-content-chatbots-list-view-item-description-container-subtitle">Features</p>
                        </div>
                        <div class="favourites-page-main-content-chatbots-list-view-item-scroll-wrapper">
                            <div class="favourites-page-main-content-chatbots-list-view-item-scroll-container">
                                <div class="favourites-page-main-content-chatbots-list-view-item-cards-wrapper">
                                    ${cards}
                                </div>
                            </div>
                            <div class="favourites-page-main-content-chatbots-list-view-item-scrollbar">
                                <div class="favourites-page-main-content-chatbots-list-view-item-scrollbar-thumb"></div>
                            </div>
                        </div>
                    </div>
                
                    <div class="favourites-page-main-content-chatbots-list-view-item-btns-container">
                        <div class="favourites-page-main-content-chatbots-list-view-item-pricing favourites-page-main-content-chatbots-list-view-item-pricing--right">
                            <div class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka">
                                <span class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka-text">${pricing}</span>
                            </div>
                        </div>
                        <a href="${link}" target="_blank" rel="noopener noreferrer"
                        class="favourites-page-main-content-chatbots-list-view-item-btns-container-go-to-website"
                        data-chatbot-id="${escapeHtml(chatbot.id)}">
                            <p>Go to Website</p>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h6v6"/><path d="M10 14 21 3"/>
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            </svg>
                        </a>
                    </div>
                </div>    
            </div>
        </div>`;
    }

    // ============================================
    // RENDER
    // ============================================

    function renderChatbots(chatbots) {
        const container = currentContainer();
        container.innerHTML = '';

        if (chatbots.length === 0 && !append) {
            emptyState.innerHTML = state.searchQuery
                ? `<p>There are no chatbots with the name "<strong>${escapeHtml(state.searchQuery)}</strong>".</p>`
                : `<p>You have no favourites yet.</p>`;
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';

        const html = chatbots.map(c =>
            state.currentView === 'grid' ?
            buildGridItem(c) : buildListItem(c)
        ).join('');

        container.insertAdjacentHTML('beforeend', html);

        if (typeof initializeScrollbars === 'function') initializeScrollbars();
    }

    // ============================================
    // REMOVE FAVOURITE — event delegation
    // ============================================

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.fav-remove-btn');
        if (!btn) return;

        const chatbotId = btn.dataset.chatbotId;
        if (!chatbotId) return;

        btn.disabled = true;

        const card = btn.closest(
            '.favourites-page-main-content-chatbots-grid-view-item, .favourites-page-main-content-chatbots-list-view-item-containter'
        );

        fetch('api/toggleFavorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatbot_id: chatbotId }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && !data.is_favourite) {
                if (card) {
                    card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.96)';
                    setTimeout(() => {
                        card.remove();
                        const container = currentContainer();
                        if (container.children.length === 0) {
                            emptyState.style.display = 'block';
                            bottomBar.style.display = 'none';
                        }
                    }, 250);
                }
            } else {
                btn.disabled = false;
            }
        })
        .catch(() => { btn.disabled = false; });
    });

    // ============================================
    // CLICK TRACKING (Go to Website)
    // ============================================

    document.addEventListener('click', (e) => {
        const link = e.target.closest(
            '.favourites-page-main-content-chatbots-grid-view-item-btns-container-go-to-website, ' +
            '.favourites-page-main-content-chatbots-list-view-item-btns-container-go-to-website'
        );
        if (!link) return;
        const chatbotId = link.dataset.chatbotId;
        if (!chatbotId) return;
        recordChatbotVisit(chatbotId);
    });

    // ============================================
    // RATING MODAL
    // ============================================

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.favourites-rating-trigger');
        if (!trigger) return;

        const chatbotId = trigger.dataset.chatbotId;
        const container = trigger.closest(
            '.favourites-page-main-content-chatbots-grid-view-item, .favourites-page-main-content-chatbots-list-view-item-containter'
        );
        const nameEl = container
            ? container.querySelector(
                '.favourites-page-main-content-chatbots-grid-view-item-top-base-1 > p, ' +
                '.favourites-page-main-content-chatbots-list-view-item-top-base-1 > p'
              )
            : null;
        const chatbotName = nameEl ? nameEl.textContent.trim() : 'this chatbot';

        openRatingModal(chatbotId, chatbotName);
    });

    // ============================================
// PATCH — openRatingModal (rovnaká zmena pre oba súbory)
// chatbotShowcase.js  &  favouritesShowcase.js
// ============================================
//
// Nahraď pôvodnú funkciu openRatingModal touto:

function openRatingModal(chatbotId, chatbotName) {
    state.ratingChatbotId = chatbotId;
    state.ratingValue = 0;

    ratingModalTitle.textContent = `Rate: ${chatbotName}`;
    ratingTextInput.value = '';
    ratingModalError.textContent = '';
    ratingModalError.style.display = 'none';
    ratingModalSuccess.textContent = '';
    ratingModalSuccess.style.display = 'none';

    // Zablokuj submit kým sa neoverí či user už dal review
    ratingSubmitBtn.disabled = true;
    setRatingSubmitText('Checking...');

    updatePickerStars(0);

    ratingOverlay.classList.add('active');
    lockScroll();

    // Skontroluj či user už dal review pre tento chatbot
    fetch(`api/checkReview.php?chatbot_id=${encodeURIComponent(chatbotId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.has_reviewed) {
                ratingModalError.textContent = 'You have already submitted a review for this chatbot.';
                ratingModalError.style.display = 'block';
                ratingSubmitBtn.disabled = true;
                setRatingSubmitText('Already Reviewed');
            } else {
                ratingSubmitBtn.disabled = false;
                setRatingSubmitText('Submit Review');
            }
        })
        .catch(() => {
            // Pri chybe siete odblokuj — submit server-side validácia to aj tak chytí
            ratingSubmitBtn.disabled = false;
            setRatingSubmitText('Submit Review');
        });
}

    function closeRatingModal() {
        ratingOverlay.classList.remove('active');
        unlockScroll();
        state.ratingChatbotId = null;
        state.ratingValue = 0;
    }

    function updatePickerStars(value) {
        state.ratingValue = value;
        ratingStarsPicker.querySelectorAll('.chatbot-rating-modal-star').forEach(star => {
            star.classList.toggle('active', parseInt(star.dataset.value) <= value);
        });
        ratingStarsLabel.textContent = value > 0 ? starLabels[value] : 'Select a rating';
    }

    ratingStarsPicker.addEventListener('mouseover', (e) => {
        const star = e.target.closest('.chatbot-rating-modal-star');
        if (!star) return;
        const hoverVal = parseInt(star.dataset.value);
        ratingStarsPicker.querySelectorAll('.chatbot-rating-modal-star').forEach(s => {
            s.classList.toggle('hover', parseInt(s.dataset.value) <= hoverVal);
        });
    });

    ratingStarsPicker.addEventListener('mouseleave', () => {
        ratingStarsPicker.querySelectorAll('.chatbot-rating-modal-star').forEach(s => s.classList.remove('hover'));
    });

    ratingStarsPicker.addEventListener('click', (e) => {
        const star = e.target.closest('.chatbot-rating-modal-star');
        if (star) updatePickerStars(parseInt(star.dataset.value));
    });

    ratingCloseBtn.addEventListener('click', closeRatingModal);
    ratingCancelBtn.addEventListener('click', closeRatingModal);

    ratingSubmitBtn.addEventListener('click', () => {
        ratingModalError.textContent = '';
        ratingModalError.style.display = 'none';
        ratingModalSuccess.textContent = '';
        ratingModalSuccess.style.display = 'none';

        if (state.ratingValue === 0) {
            ratingModalError.textContent = 'Please select a star rating.';
            ratingModalError.style.display = 'block';
            return;
        }
        ratingSubmitBtn.disabled = true;
        setRatingSubmitLoading();

        fetch('api/submitReview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                chatbot_id:  state.ratingChatbotId,
                rating:      state.ratingValue,
                review_text: ratingTextInput.value.trim(),
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeRatingModal();
                showNotification('Review submitted.', 'success');
            } else {
                ratingModalError.textContent = data.message || 'Something went wrong.';
                ratingModalError.style.display = 'block';
                ratingSubmitBtn.disabled = false;
                setRatingSubmitText('Submit Review');
            }
        })
        .catch(() => {
            ratingModalError.textContent = 'Server error. Please try again.';
            ratingModalError.style.display = 'block';
            ratingSubmitBtn.disabled = false;
            setRatingSubmitText('Submit Review');
        });
    });

    // ============================================
    // FETCH
    // ============================================

    function cloneFilterParams(params) {
        return Object.fromEntries(
            Object.entries(params).map(([key, value]) => [key, Array.isArray(value) ? [...value] : value])
        );
    }

    function collectDraftFilterParams() {
        const val = (id) => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };

        const params = {};

        if (val('ratingInputStart')) params.ratingStart = val('ratingInputStart');
        if (val('ratingInputEnd'))   params.ratingEnd   = val('ratingInputEnd');
        if (val('votesInputStart'))  params.votesStart  = val('votesInputStart');
        if (val('votesInputEnd'))    params.votesEnd    = val('votesInputEnd');
        if (val('favoritesInputStart'))  params.favoritesStart  = val('favoritesInputStart');
        if (val('favoritesInputEnd'))    params.favoritesEnd    = val('favoritesInputEnd');
        if (val('priceInputStart'))  params.priceStart  = val('priceInputStart');
        if (val('priceInputEnd'))    params.priceEnd    = val('priceInputEnd');

        document.querySelectorAll('.favourites-filters-modal-body-features-single.active')
            .forEach(span => {
                if (span.dataset.featureId) {
                    if (!params['features[]']) params['features[]'] = [];
                    params['features[]'].push(span.dataset.featureId);
                }
            });

        if (typeof window.getActiveCurrencies === 'function') {
            const currencies = window.getActiveCurrencies();
            if (currencies.length > 0) params['currency[]'] = currencies;
        }
        
        // Integrations — OR filter
        if (typeof window.getActiveIntegrationIds === 'function') {
            const intIds = window.getActiveIntegrationIds();
            if (intIds.length > 0) params['integrations[]'] = intIds;
        }

        // Pricing models — OR filter
        if (typeof window.getActivePricingModels === 'function') {
            const pmIds = window.getActivePricingModels();
            if (pmIds.length > 0) params['pricing[]'] = pmIds;
        }

        if (freeTrialFilter?.classList.contains('active')) params.hasFreeTrial = '1';

        return params;
    }

    window.getActiveFilterParams = function () {
        return cloneFilterParams(appliedFilterParams);
    };

    function loadFavourites(isSearch = false) {
        if (state.isLoading) return;
        setLoading(true, isSearch);

        const params = new URLSearchParams({
            view: state.currentView,
            sort: state.currentSort,
            page: state.currentPage,
            direction: state.sortDirection,
            search: state.searchQuery,
        });

        if (typeof window.getActiveFilterParams === 'function') {
            const filterParams = window.getActiveFilterParams();
            Object.entries(filterParams).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach(v => params.append(key, v));
                } else {
                    params.set(key, value);
                }
            });
        }

        fetch(`api/getFavourites.php?${params}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error('API error');
                state.totalPages = data.totalPages;

                // Sync count po Apply / sort / page zmene
                if (filterResultsCount) {
                    const t = data.total;
                    filterResultsCount.classList.remove('is-loading');
                    filterResultsCount.setAttribute('aria-busy', 'false');
                    filterResultsCount.textContent = `Found ${t} chatbot${t !== 1 ? 's' : ''}`;
                }

                renderChatbots(data.chatbots);
                renderPagination(data.totalPages, data.currentPage);
            })
            .catch(err => {
                console.error('Failed to load favourites:', err);
                emptyState.style.display = 'block';
            })
            .finally(() => setLoading(false, isSearch));
    }

    window.applyFilters = function () {
        state.currentPage = 1;
        loadFavourites();
    };

    // ============================================
    // PAGINATION
    // ============================================

    function getPageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (current >= total - 3) return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        return [1, '...', current - 1, current, current + 1, '...', total];
    }

    function renderPagination(totalPages, currentPage) {
        paginationEl.innerHTML = '';

        if (totalPages <= 1) {
            bottomBar.style.display = 'none';
            return;
        }
        bottomBar.style.display = 'flex';

        const goToPage = (p) => {
            state.currentPage = p;
            loadFavourites();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        const prevBtn = document.createElement('button');
        prevBtn.className = 'favourites-showcase-pagination-arrow';
        prevBtn.innerHTML = '&lsaquo;';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => { if (state.currentPage > 1) goToPage(state.currentPage - 1); });
        paginationEl.appendChild(prevBtn);

        getPageRange(currentPage, totalPages).forEach(p => {
            if (p === '...') {
                const span = document.createElement('span');
                span.className = 'favourites-showcase-pagination-ellipsis';
                span.textContent = '…';
                paginationEl.appendChild(span);
                return;
            }
            const btn = document.createElement('button');
            btn.className = 'favourites-showcase-pagination-btn' + (p === currentPage ? ' active' : '');
            btn.textContent = p;
            if (p === currentPage) btn.setAttribute('aria-current', 'page');
            btn.addEventListener('click', () => { if (p !== state.currentPage) goToPage(p); });
            paginationEl.appendChild(btn);
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'favourites-showcase-pagination-arrow';
        nextBtn.innerHTML = '&rsaquo;';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => { if (state.currentPage < state.totalPages) goToPage(state.currentPage + 1); });
        paginationEl.appendChild(nextBtn);
    }

    // ============================================
    // VIEW TOGGLE
    // ============================================

    function switchView(view) {
        if (view === state.currentView) return;
        state.currentView = view;
        state.currentPage = 1;

        listBtn.classList.toggle('active', view === 'list');
        gridBtn.classList.toggle('active', view === 'grid');
        gridView.classList.toggle('active', view === 'grid');
        listView.classList.toggle('active', view === 'list');

        savePreference('favourites_showcase_view', view);

        loadFavourites();
    }

    listBtn.addEventListener('click', () => switchView('list'));
    gridBtn.addEventListener('click', () => switchView('grid'));

    // ============================================
    // SORT
    // ============================================

    document.querySelectorAll('#sortOptions .favourites-page-main-content-topbar-group-sort-select-option').forEach(option => {
        option.addEventListener('click', (e) => {
            e.stopPropagation();
            const value = option.dataset.value;

            document.querySelectorAll('#sortOptions .favourites-page-main-content-topbar-group-sort-select-option')
                .forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
            sortSelected.textContent = option.textContent.trim();
            sortSelectDiv.classList.remove('active');

            if (value !== state.currentSort) {
                state.currentSort = value;
                state.currentPage = 1;
                loadFavourites();
            }
        });
    });

    sortSelectDiv.addEventListener('click', (e) => {
        if (!e.target.closest('.favourites-page-main-content-topbar-group-sort-select-option')) {
            sortSelectDiv.classList.toggle('active');
        }
    });

    document.addEventListener('click', (e) => {
        if (sortSelectDiv && !sortSelectDiv.contains(e.target)) {
            sortSelectDiv.classList.remove('active');
        }
    });

    if (sortArrow) {
        sortArrow.addEventListener('click', () => {
            state.sortDirection = state.sortDirection === 'desc' ? 'asc' : 'desc';
            sortArrow.style.transform = state.sortDirection === 'asc' ? 'scaleY(-1)' : 'scaleY(1)';
            state.currentPage = 1;
            loadFavourites();
        });
    }

    // ============================================
    // FILTERS MODAL
    // ============================================

    const favouriteNumberFilterKeys = {
        ratingInputStart: 'ratingStart',
        ratingInputEnd: 'ratingEnd',
        votesInputStart: 'votesStart',
        votesInputEnd: 'votesEnd',
        favoritesInputStart: 'favoritesStart',
        favoritesInputEnd: 'favoritesEnd',
        priceInputStart: 'priceStart',
        priceInputEnd: 'priceEnd',
    };

    const favouriteFilterGroups = [
        ['.favourites-filters-modal-body-features-single', 'features[]', 'featureId'],
        ['.favourites-filters-modal-body-currency-single', 'currency[]', 'currencyCode'],
        ['.favourites-filters-modal-body-integrations-single', 'integrations[]', 'integrationId'],
        ['.favourites-filters-modal-body-pricing-single', 'pricing[]', 'pricingModel'],
    ];

    function refreshFavouriteDraftUi() {
        if (typeof window.clearFilterResultsCount === 'function') window.clearFilterResultsCount();
        if (typeof window.updateFilterBadge === 'function') window.updateFilterBadge();
        if (typeof window.triggerFeatureCountsRefresh === 'function') window.triggerFeatureCountsRefresh();
        if (typeof window.triggerIntegrationCountsRefresh === 'function') window.triggerIntegrationCountsRefresh();
        if (typeof window.triggerPricingCountsRefresh === 'function') window.triggerPricingCountsRefresh();
        if (typeof window.triggerCurrencyCountsRefresh === 'function') window.triggerCurrencyCountsRefresh();
    }

    function setFavouriteFreeTrialFilterActive(active) {
        if (!freeTrialFilter) return;
        freeTrialFilter.classList.toggle('active', active);
        freeTrialFilter.setAttribute('aria-pressed', String(active));
    }

    function restoreFavouriteDraftFromApplied() {
        Object.entries(favouriteNumberFilterKeys).forEach(([id, key]) => {
            const input = document.getElementById(id);
            if (input) input.value = appliedFilterParams[key] ?? '';
        });

        favouriteFilterGroups.forEach(([selector, key, dataKey]) => {
            const selected = new Set(appliedFilterParams[key] ?? []);
            document.querySelectorAll(selector).forEach(element => {
                element.classList.toggle('active', selected.has(element.dataset[dataKey]));
            });
        });

        setFavouriteFreeTrialFilterActive(appliedFilterParams.hasFreeTrial === '1' || appliedFilterParams.hasFreeTrial === true);

        refreshFavouriteDraftUi();
    }

    function openFavouriteFiltersModal() {
        restoreFavouriteDraftFromApplied();
        filtersOverlay.style.display = 'flex';
        lockScroll();
    }

    function closeFavouriteFiltersModal(discardDraft = true) {
        if (discardDraft) restoreFavouriteDraftFromApplied();
        filtersOverlay.style.display = 'none';
        unlockScroll();
    }

    if (filtersBtn && filtersOverlay) {
        filtersBtn.addEventListener('click', openFavouriteFiltersModal);
    }

    if (filtersCloseBtn) filtersCloseBtn.addEventListener('click', () => closeFavouriteFiltersModal());

    if (filtersClearBtn) {
        filtersClearBtn.addEventListener('click', () => {
            filterNumberInputs.forEach(input => { input.value = ''; });
            favouriteFilterGroups.forEach(([selector]) => {
                document.querySelectorAll(selector).forEach(element => element.classList.remove('active'));
            });
            setFavouriteFreeTrialFilterActive(false);
            refreshFavouriteDraftUi();
        });
    }

    if (freeTrialFilter) {
        freeTrialFilter.addEventListener('click', () => {
            setFavouriteFreeTrialFilterActive(!freeTrialFilter.classList.contains('active'));
            refreshFavouriteDraftUi();
        });
    }

    const applyFavFiltersBtn = document.querySelector('.favourites-filters-modal-apply-btn');
    if (applyFavFiltersBtn) {
        applyFavFiltersBtn.addEventListener('click', () => {
            appliedFilterParams = cloneFilterParams(collectDraftFilterParams());
            if (typeof window.updateFilterBadge === 'function') window.updateFilterBadge(appliedFilterParams);
            closeFavouriteFiltersModal(false);
            window.applyFilters();
        });
    }

    if (filtersOverlay) {
        filtersOverlay.addEventListener('click', event => {
            if (event.target === filtersOverlay) closeFavouriteFiltersModal();
        });
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && filtersOverlay?.style.display === 'flex') {
            closeFavouriteFiltersModal();
        }
    });

    // ============================================
    // MOVE UP BUTTON
    // ============================================

    if (moveUpBtn) {
        window.addEventListener('scroll', () => {
            moveUpBtn.style.display = window.scrollY > 300 ? 'flex' : 'none';
        });
        moveUpBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ============================================
    // INIT
    // ============================================

    const savedView = getSavedPreference('favourites_showcase_view');
    if (savedView === 'list' || savedView === 'grid') {
        state.currentView = savedView;
        listBtn.classList.toggle('active', savedView === 'list');
        gridBtn.classList.toggle('active', savedView === 'grid');
        gridView.classList.toggle('active', savedView === 'grid');
        listView.classList.toggle('active', savedView === 'list');
    } else {
        gridView.classList.add('active');
    }

    // ============================================
    // SEARCH
    // ============================================

    const favDesktopSearch = document.querySelector('.favourites-page-main-content-topbar-search-desktop');
    const favSearchInput   = document.getElementById('searchInput');
    const favMobileInput   = document.getElementById('mobileSearchInput');
    const favClearBtn      = document.getElementById('searchClearBtn');
    const favClearIcon     = favClearBtn?.querySelector('.favourites-page-main-content-topbar-search-input-clear-icon');
    const favSubmitBtn     = favDesktopSearch?.querySelector('.favourites-page-main-content-topbar-search-submit-btn');

    let searchDebounce = null;

    function handleSearchInput(value) {
        state.searchQuery = value.trim();
        state.currentPage = 1;
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => loadFavourites(true), 350);
    }

    if (favSubmitBtn && favDesktopSearch) {
        favSubmitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isActive = favDesktopSearch.classList.contains('active');
            if (isActive) {
                favDesktopSearch.classList.remove('active');
                favSearchInput?.blur();
            } else if (!isActive) {
                favDesktopSearch.classList.add('active');
                setTimeout(() => favSearchInput?.focus(), 50);
            }
        });
    }

    if (favSearchInput) {
        favSearchInput.addEventListener('input', function () {
            favClearIcon?.classList.toggle('visible', this.value.length > 0);
            handleSearchInput(this.value);
        });

        favSearchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && favSearchInput.value === '') {
                favDesktopSearch?.classList.remove('active');
                favSearchInput.blur();
            }
        });

    }

    if (favClearBtn && favSearchInput) {
        favClearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            favSearchInput.value = '';
            favClearIcon?.classList.remove('visible');
            handleSearchInput('');
            favSearchInput.focus();
        });
    }

    // Mobile search bar toggle
    const favMobileSearchBtn = document.getElementById('mobileSearchBtn');
    const favMobileSearchBar = document.getElementById('mobileSearchBar');
    const favMobileClearBtn  = document.getElementById('mobileSearchClearBtn');
    const favMobileClearIcon = favMobileClearBtn?.querySelector('.favourites-page-main-content-topbar-search-input-clear-icon');

    if (favMobileSearchBtn && favMobileSearchBar) {
        favMobileSearchBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            favMobileSearchBar.classList.toggle('active');
            if (favMobileSearchBar.classList.contains('active')) {
                setTimeout(() => favMobileInput?.focus(), 300);
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (favMobileSearchBar && favMobileSearchBar.classList.contains('active')) {
            if (!favMobileSearchBar.contains(e.target) && e.target !== favMobileSearchBtn) {
                favMobileSearchBar.classList.remove('active');
            }
        }
    });

    if (favMobileInput) {
        favMobileInput.addEventListener('input', function () {
            favMobileClearIcon?.classList.toggle('visible', this.value.length > 0);
            handleSearchInput(this.value);
        });
    }

    if (favMobileClearBtn && favMobileInput) {
        favMobileClearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            favMobileInput.value = '';
            favMobileClearIcon?.classList.remove('visible');
            handleSearchInput('');
            favMobileInput.focus();
        });
    }

    loadFavourites();

})();
