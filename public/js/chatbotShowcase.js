/**
 * chatbotShowcase.js
 *
 * Opravy v tejto verzii:
 *  - Favorites: event delegation (nie querySelectorAll pri načítaní) → CSS toggle funguje
 *  - Favorites: DB sync cez toggleFavorite.php + živé počítadlo na karte
 *  - Favorites: neprihlásený user → redirect na login
 *  - Rating: neprihlásený user → redirect na login (nie chyba v modali)
 */

(function () {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    const state = {
        currentView:      'grid',
        currentSort:      'popularity',
        currentPage:      1,
        totalPages:       1,
        totalItems:       0,
        isLoading:        false,
        ratingChatbotId:  null,
        ratingValue:      0,
        sortDirection:    'desc',
        searchQuery:     '', 
    };

    const LOGIN_URL = 'login.php?redirect=chatbotShowcase.php';
    const isUserLoggedIn = document.querySelector('.chatbot-page-wrapper')?.dataset.userLoggedIn === 'true';

    // ============================================
    // DOM REFERENCES
    // ============================================

    const gridView        = document.getElementById('gridView');
    const listView        = document.getElementById('listView');
    const loadingEl       = document.getElementById('chatbotsLoading');
    const emptyState      = document.getElementById('chatbotsEmpty');
    const showMoreWrapper = document.getElementById('showMoreWrapper');
    const showMoreBtn     = document.getElementById('showMoreBtn');
    const bottomBar       = document.getElementById('bottomBar');
    const paginationEl    = document.getElementById('paginationContainer');
    const moveUpBtn       = document.getElementById('moveUpBtn');
    const listBtn         = document.getElementById('listBtn');
    const gridBtn         = document.getElementById('gridBtn');
    const sortSelectDiv   = document.getElementById('sortSelectDiv');
    const sortSelected    = document.getElementById('sortSelected');
    const sortArrow       = document.getElementById('sortArrow');
    const filterResultsCount = document.getElementById('filterResultsCount');

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

    function defaultSortDirection(sort) {
        return sort === 'alphabetical' || sort === 'price' ? 'asc' : 'desc';
    }

    function setSortDirection(direction) {
        state.sortDirection = direction;
        const isReversed = direction !== defaultSortDirection(state.currentSort);
        sortArrow?.classList.toggle('is-reversed', isReversed);
    }

    // ============================================
    // HELPERS
    // ============================================

    function isMobileView() { return window.innerWidth <= 768; }

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

    function setHidden(element, hidden) {
        if (element) element.hidden = hidden;
    }

    function setSearchLoading(on) {
        [gridView, listView].forEach(container => {
            if (container) container.classList.toggle('is-search-loading', on && container === currentContainer());
        });
    }

    function setLoading(on, isSearch = false) {
        if (isSearch) {
            setSearchLoading(on);
        } else {
            setHidden(loadingEl, !on);
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
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderPricing(pricingStr) {
        if (!pricingStr) return '';
        const parts = pricingStr.split(' | ');
        
        // Ak je celkový string kratší ako 12 znakov → jedna linka (napr. "FREE" alebo "$50+")
        // Ak je dlhší → rozdeľ na riadky s dividerom
        if (parts.length === 1 || pricingStr.length < 12) {
            return pricingStr;
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

    // ============================================
    // STARS BUILDER (display - nie picker)
    // NULL alebo 0 → data-rating="0" → všetky hviezdy šedé
    // ============================================

    function buildStars(rating, chatbotId) {
        const value = (rating !== null && Number(rating) > 0)
            ? Math.round(Number(rating) * 2) / 2
            : 0;
        return `
            <div class="chatbot-page-main-content-chatbots-item-top-base-2-rating chatbot-rating-trigger"
                 data-rating="${value}"
                 data-chatbot-id="${escapeHtml(chatbotId)}"
                 title="Click to rate">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
            </div>`;
    }

    function pricingLabel(chatbot) {
        return chatbot.pricing || 'Contact';
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

        return `<span class="chatbot-tag-free-trial"><p>✦ ${label} ✦</p></span>`;
    }

    function buildFeatureCards(features) {
        if (!features || features.length === 0) {
            return '<div class="chatbot-page-main-content-chatbots-list-view-item-card"><ul><li>No features listed</li></ul></div>';
        }
        const chunkSize = 4;
        let html = '';
        for (let i = 0; i < features.length; i += chunkSize) {
            html += `<div class="chatbot-page-main-content-chatbots-list-view-item-card"><ul>`;
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
    // Favorites button má data-chatbot-id pre event delegation
    // ============================================

    function buildGridItem(chatbot) {
        const stars   = buildStars(chatbot.rating_average, chatbot.id);
        const tags    = buildTags(chatbot.tags);
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = renderPricing(pricingLabel(chatbot))
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const integrations = chatbot.integrations || [];
        const intJson      = escapeAttr(JSON.stringify(integrations));
        const intEmpty     = integrations.length === 0 ? ' platforms-btn--empty' : '';
        const rating  = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount  = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';
        const reviewsUrl = `chatbot-reviews?chatbot=${encodeURIComponent(chatbot.id)}`;
        const favCount = chatbot.favorite_count ?? 0;
        let badgeHtml = '';
        if (chatbot.is_sponsored) badgeHtml += `<span class="chatbot-tag-sponsored"><p>✦ Sponsored ✦</p></span>`;
        badgeHtml += buildFreeTrialTag(chatbot);

        return `
        <div class="chatbot-page-main-content-chatbots-grid-view-item">
            <div class="chatbot-page-main-content-chatbots-grid-view-item-tags">${badgeHtml}${tags}</div>
            <div class="chatbot-page-main-content-chatbots-grid-view-item-top">
                <div class="chatbot-page-main-content-chatbots-grid-view-item-top-img-wrapper">
                    ${logoHtml(chatbot)}
                </div>
                <div class="chatbot-page-main-content-chatbots-grid-view-item-top-base">
                    <div class="chatbot-page-main-content-chatbots-grid-view-item-top-base-1">
                        <p>${name}</p>
                        <div class="chatbot-page-main-content-chatbots-grid-view-item-top-base-2">
                            ${stars}
                            <p>${rating}</p>
                            <a class="chatbot-reviews-link" href="${reviewsUrl}" aria-label="Read reviews for ${escapeAttr(chatbot.name)}"><p>(${rCount})</p></a>
                        </div>
                    </div>
                    <div class="chatbot-page-main-content-chatbots-grid-view-item-top-favourites-container">
                        <p class="chatbot-fav-count">${favCount}</p>
                        <button class="chatbot-page-main-content-chatbots-grid-view-item-top-favourites-container-btn chatbot-fav-btn${chatbot.is_favourited ? ' active' : ''}"
                                data-chatbot-id="${escapeHtml(chatbot.id)}"
                                aria-label="Add to favourites">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="chatbot-page-main-content-chatbots-grid-view-item-top-favourites-container-btn-plus">
                                <path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="chatbot-page-main-content-chatbots-grid-view-item-description-container">
                <div class="chatbot-page-main-content-chatbots-grid-view-item-description-container-header">
                    <p class="chatbot-page-main-content-chatbots-grid-view-item-description-container-subtitle">Description</p>
                    <button type="button"
                            class="chatbot-page-main-content-chatbots-grid-view-item-features-container-1-imp-btn platforms-popup-trigger${intEmpty}"
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
                <p class="chatbot-page-main-content-chatbots-grid-view-item-description-container-text">${desc}</p>
            </div>
            <div class="chatbot-page-main-content-chatbots-grid-view-item-btns-container">
                <div class="chatbot-page-main-content-chatbots-grid-view-item-btns-container-sipka">
                    <span class="chatbot-page-main-content-chatbots-grid-view-item-btns-container-sipka-text">${pricing}</span>
                </div>
                <a href="${link}" target="_blank" rel="noopener noreferrer"
                class="chatbot-page-main-content-chatbots-grid-view-item-btns-container-go-to-website"
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
        const stars    = buildStars(chatbot.rating_average, chatbot.id);
        const tags     = buildTags(chatbot.tags);
        const cards    = buildFeatureCards(chatbot.features);
        const pricing  = renderPricing(pricingLabel(chatbot))
        const link     = escapeHtml(chatbot.link);
        const name     = escapeHtml(chatbot.name);
        const desc     = escapeHtml(chatbot.description || '');
        const integrations = chatbot.integrations || [];
        const intJson      = escapeAttr(JSON.stringify(integrations));
        const intEmpty     = integrations.length === 0 ? ' platforms-btn--empty' : '';
        const rating   = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount   = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';
        const reviewsUrl = `chatbot-reviews?chatbot=${encodeURIComponent(chatbot.id)}`;
        const favCount = chatbot.favorite_count ?? 0;
        let badgeHtml = '';
        if (chatbot.is_sponsored) badgeHtml += `<span class="chatbot-tag-sponsored"><p>✦ Sponsored ✦</p></span>`;
        badgeHtml += buildFreeTrialTag(chatbot);

        return `
        <div class="chatbot-page-main-content-chatbots-list-view-item-containter">
            <div class="chatbot-page-main-content-chatbots-list-view-item-tags">${badgeHtml}${tags}</div>
            <div class="chatbot-page-main-content-chatbots-list-view-item">
                <div class="chatbot-page-main-content-chatbots-list-view-item-features-container-left">
                    <div class="chatbot-page-main-content-chatbots-list-view-item-top">
                        <div class="chatbot-page-main-content-chatbots-list-view-item-top-img-wrapper">
                            ${logoHtml(chatbot)}
                        </div>
                        <div class="chatbot-page-main-content-chatbots-list-view-item-top-base">
                            <div class="chatbot-page-main-content-chatbots-list-view-item-top-base-1">
                                <p>${name}</p>
                                <div class="chatbot-page-main-content-chatbots-list-view-item-top-base-2">
                                    ${stars}
                                    <p>${rating}</p>
                                    <a class="chatbot-reviews-link" href="${reviewsUrl}" aria-label="Read reviews for ${escapeAttr(chatbot.name)}"><p>(${rCount})</p></a>
                                </div>
                            </div>
                            <div class="chatbot-page-main-content-chatbots-list-view-item-top-favourites-container">
                                <p class="chatbot-fav-count">${favCount}</p>
                                <button class="chatbot-page-main-content-chatbots-list-view-item-top-favourites-container-btn chatbot-fav-btn${chatbot.is_favourited ? ' active' : ''}"
                                        data-chatbot-id="${escapeHtml(chatbot.id)}"
                                        aria-label="Add to favourites">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="chatbot-page-main-content-chatbots-list-view-item-top-favourites-container-btn-plus">
                                        <path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="chatbot-page-main-content-chatbots-list-view-item-description-container">
                        <div class="chatbot-page-main-content-chatbots-list-view-item-description-container-header">
                            <p class="chatbot-page-main-content-chatbots-list-view-item-description-container-subtitle">Description</p>
                            <button type="button"
                                    class="chatbot-page-main-content-chatbots-list-view-item-features-container-1-imp-btn platforms-popup-trigger${intEmpty}"
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
                        <p class="chatbot-page-main-content-chatbots-list-view-item-description-container-text">${desc}</p>
                    </div>
                    <div class="chatbot-page-main-content-chatbots-list-view-item-pricing chatbot-page-main-content-chatbots-list-view-item-pricing--left">
                        <div class="chatbot-page-main-content-chatbots-list-view-item-btns-container-sipka">
                            <span class="chatbot-page-main-content-chatbots-list-view-item-btns-container-sipka-text">${pricing}</span>
                        </div>
                    </div>
                </div>
                <div class="chatbot-page-main-content-chatbots-list-view-item-features-container-right">
                    <div class="chatbot-page-main-content-chatbots-list-view-item-features-container">
                        <div class="chatbot-page-main-content-chatbots-list-view-item-features-container-1">
                            <p class="chatbot-page-main-content-chatbots-list-view-item-description-container-subtitle">Key Features</p>
                        </div>
                        <div class="chatbot-page-main-content-chatbots-list-view-item-scroll-wrapper">
                            <div class="chatbot-page-main-content-chatbots-list-view-item-scroll-container">
                                <div class="chatbot-page-main-content-chatbots-list-view-item-cards-wrapper">${cards}</div>
                            </div>
                            <div class="chatbot-page-main-content-chatbots-list-view-item-scrollbar">
                                <div class="chatbot-page-main-content-chatbots-list-view-item-scrollbar-thumb"></div>
                            </div>
                        </div>
                    </div>
                    <div class="chatbot-page-main-content-chatbots-list-view-item-btns-container">
                        <div class="chatbot-page-main-content-chatbots-list-view-item-pricing chatbot-page-main-content-chatbots-list-view-item-pricing--right">
                            <div class="chatbot-page-main-content-chatbots-list-view-item-btns-container-sipka">
                                <span class="chatbot-page-main-content-chatbots-list-view-item-btns-container-sipka-text">${pricing}</span>
                            </div>
                        </div>
                        <a href="${link}" target="_blank" rel="noopener noreferrer"
                        class="chatbot-page-main-content-chatbots-list-view-item-btns-container-go-to-website"
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

    function renderChatbots(chatbots, append) {

        const container = currentContainer();
        if (!append) container.innerHTML = '';

        if (chatbots.length === 0 && !append) {
            const hasSearch = state.searchQuery !== '';
            const hasFilters = typeof window.hasAppliedChatbotFilters === 'function'
                && window.hasAppliedChatbotFilters();

            if (hasSearch && hasFilters) {
                emptyState.innerHTML = `
                    <p>No chatbots match "<strong>${escapeHtml(state.searchQuery)}</strong>" and the selected filters.</p>
                    <button type="button" class="showcase-empty-clear" data-clear-chatbot-filters>Clear filters</button>`;
            } else if (hasFilters) {
                emptyState.innerHTML = `
                    <p>No chatbots match the selected filters.</p>
                    <button type="button" class="showcase-empty-clear" data-clear-chatbot-filters>Clear filters</button>`;
            } else if (hasSearch) {
                emptyState.innerHTML = `<p>No chatbots match "<strong>${escapeHtml(state.searchQuery)}</strong>".</p>`;
            } else {
                emptyState.innerHTML = '<p>No chatbots found.</p>';
            }

            setHidden(emptyState, false);
            return;
        }
        setHidden(emptyState, true);

        const html = chatbots.map(c =>
            state.currentView === 'grid' ? buildGridItem(c) : buildListItem(c)
        ).join('');

        container.insertAdjacentHTML('beforeend', html);

        if (typeof initializeScrollbars === 'function') initializeScrollbars();
    }

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
            setHidden(bottomBar, true);
            return;
        }
        setHidden(bottomBar, false);

        const goToPage = (p) => {
            state.currentPage = p;
            loadChatbots(false);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        const prevBtn = document.createElement('button');
        prevBtn.className = 'chatbot-showcase-pagination-arrow';
        prevBtn.innerHTML = '&lsaquo;';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => { if (state.currentPage > 1) goToPage(state.currentPage - 1); });
        paginationEl.appendChild(prevBtn);

        getPageRange(currentPage, totalPages).forEach(p => {
            if (p === '...') {
                const span = document.createElement('span');
                span.className = 'chatbot-showcase-pagination-ellipsis';
                span.textContent = '…';
                paginationEl.appendChild(span);
                return;
            }
            const btn = document.createElement('button');
            btn.className = 'chatbot-showcase-pagination-btn' + (p === currentPage ? ' active' : '');
            btn.textContent = p;
            if (p === currentPage) btn.setAttribute('aria-current', 'page');
            btn.addEventListener('click', () => { if (p !== state.currentPage) goToPage(p); });
            paginationEl.appendChild(btn);
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'chatbot-showcase-pagination-arrow';
        nextBtn.innerHTML = '&rsaquo;';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => { if (state.currentPage < state.totalPages) goToPage(state.currentPage + 1); });
        paginationEl.appendChild(nextBtn);
    }

    function updateShowMore() {
        if (isMobileView()) {
            setHidden(showMoreWrapper, state.currentPage >= state.totalPages);
        } else {
            setHidden(showMoreWrapper, true);
        }
    }

    // ============================================
    // FETCH
    // ============================================

    function loadChatbots(append, isSearch = false) {
        if (state.isLoading) return;
        setLoading(true, isSearch);
 
        const params = new URLSearchParams({
            view:      state.currentView,
            sort:      state.currentSort,
            page:      state.currentPage,
            direction: state.sortDirection,
            search:    state.searchQuery,
        });
 
        // Pridaj aktívne filtre z modalu
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

        fetch(`api/getChatbots.php?${params}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error('API error');
                state.totalPages = data.totalPages;
                state.totalItems = data.total;

                // Sync count po Apply / sort / page zmene
                if (filterResultsCount) {
                    const t = data.total;
                    filterResultsCount.classList.remove('is-loading');
                    filterResultsCount.setAttribute('aria-busy', 'false');
                    filterResultsCount.textContent = `Found ${t} chatbot${t !== 1 ? 's' : ''}`;
                }

                renderChatbots(data.chatbots, append);

                if (!isMobileView()) {
                    renderPagination(data.totalPages, data.currentPage);
                } else {
                    paginationEl.innerHTML = '';
                    setHidden(bottomBar, data.totalPages <= 1);
                }
                updateShowMore();
            })
            .catch(err => {
                console.error('Failed to load chatbots:', err);
                setHidden(emptyState, false);
            })
            .finally(() => setLoading(false, isSearch));
    }

    // Vystavené pre chatbotFilters.js (Apply + Clear button)
    window.applyFilters = function () {
        state.currentPage = 1;
        loadChatbots(false);
    };

    if (emptyState) {
        emptyState.addEventListener('click', event => {
            if (!event.target.closest('[data-clear-chatbot-filters]')) return;
            if (typeof window.clearAppliedChatbotFilters === 'function') {
                window.clearAppliedChatbotFilters();
            }
        });
    }

    // ============================================
    // CLICK TRACKING (Go to Website)
    // ============================================

    document.addEventListener('click', (e) => {
        const link = e.target.closest('.chatbot-page-main-content-chatbots-grid-view-item-btns-container-go-to-website, .chatbot-page-main-content-chatbots-list-view-item-btns-container-go-to-website');
        if (!link) return;

        const chatbotId = link.dataset.chatbotId;
        if (!chatbotId) return;

        recordChatbotVisit(chatbotId);
    });

    // ============================================
    // FAVORITES — event delegation
    // Buttons sú vytvárané dynamicky, preto querySelectorAll nestačí.
    // ============================================

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.chatbot-fav-btn');
        if (!btn) return;

        // Neprihlásený → redirect
        if (!isUserLoggedIn) {
            window.location.href = LOGIN_URL;
            return;
        }

        const chatbotId = btn.dataset.chatbotId;
        if (!chatbotId) return;

        // Optimistický UI update (okamžitá odozva)
        btn.disabled = true;
        const wasActive = btn.classList.contains('active');
        btn.classList.toggle('active');

        // Aktualizuj počítadlo optimisticky
        const countEl = btn.closest(
            '.chatbot-page-main-content-chatbots-grid-view-item-top-favourites-container, .chatbot-page-main-content-chatbots-list-view-item-top-favourites-container'
        )?.querySelector('.chatbot-fav-count');

        if (countEl) {
            const current = parseInt(countEl.textContent) || 0;
            countEl.textContent = wasActive ? Math.max(0, current - 1) : current + 1;
        }

        // Sync s DB
        fetch('api/toggleFavorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatbot_id: chatbotId }),
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                // Revert optimistický update
                btn.classList.toggle('active');
                if (countEl) {
                    const current = parseInt(countEl.textContent) || 0;
                    countEl.textContent = wasActive ? current + 1 : Math.max(0, current - 1);
                }
            } else {
                // Nastav presný počet z DB
                if (countEl) countEl.textContent = data.new_count;
                // Nastav správny active stav
                btn.classList.toggle('active', data.is_favourite);
            }
        })
        .catch(() => {
            // Revert pri chybe siete
            btn.classList.toggle('active');
            if (countEl) {
                const current = parseInt(countEl.textContent) || 0;
                countEl.textContent = wasActive ? current + 1 : Math.max(0, current - 1);
            }
        })
        .finally(() => { btn.disabled = false; });
    });

    // ============================================
    // RATING MODAL
    // ============================================

    // Klik na hviezdy v karte → redirect ak neprihlásený, inak otvor modal
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.chatbot-rating-trigger');
        if (!trigger) return;

        // Neprihlásený → redirect (nie chyba v modali)
        if (!isUserLoggedIn) {
            window.location.href = LOGIN_URL;
            return;
        }

        const chatbotId = trigger.dataset.chatbotId;
        const container = trigger.closest(
            '.chatbot-page-main-content-chatbots-grid-view-item, .chatbot-page-main-content-chatbots-list-view-item-containter'
        );
        const nameEl = container
            ? container.querySelector(
                '.chatbot-page-main-content-chatbots-grid-view-item-top-base-1 > p, ' +
                '.chatbot-page-main-content-chatbots-list-view-item-top-base-1 > p'
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

        ratingModalTitle.textContent = `Review — ${chatbotName}`;
        ratingTextInput.value = '';
        ratingModalError.textContent = '';
        setHidden(ratingModalError, true);
        ratingModalSuccess.textContent = '';
        setHidden(ratingModalSuccess, true);

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
                    setHidden(ratingModalError, false);
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
        setHidden(ratingModalError, true);
        ratingModalSuccess.textContent = '';
        setHidden(ratingModalSuccess, true);

        if (state.ratingValue === 0) {
            ratingModalError.textContent = 'Please select a star rating.';
            setHidden(ratingModalError, false);
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
                setHidden(ratingModalError, false);
                ratingSubmitBtn.disabled = false;
                setRatingSubmitText('Submit Review');
            }
        })
        .catch(() => {
            ratingModalError.textContent = 'Server error. Please try again.';
            setHidden(ratingModalError, false);
            ratingSubmitBtn.disabled = false;
            setRatingSubmitText('Submit Review');
        });
    });

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

        savePreference('chatbot_showcase_view', view);

        loadChatbots(false);
    }

    listBtn.addEventListener('click', () => switchView('list'));
    gridBtn.addEventListener('click', () => switchView('grid'));

    // ============================================
    // SORT — chatbotShowcase.js spravuje sort sám (sortSelect.js NIE je na tejto stránke)
    // ============================================

    const sortOptionEls = document.querySelectorAll('#sortOptions .chatbot-page-main-content-topbar-group-sort-select-option');

    sortOptionEls.forEach(option => {
        option.addEventListener('click', (e) => {
            e.stopPropagation();
            const value = option.dataset.value;

            sortOptionEls.forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
            sortSelected.textContent = option.textContent.trim();
            sortSelectDiv.classList.remove('active');

            if (value !== state.currentSort) {
                state.currentSort = value;
                setSortDirection(defaultSortDirection(value));
                state.currentPage = 1;
                loadChatbots(false);
            }
        });
    });

    sortSelectDiv.addEventListener('click', (e) => {
        if (!e.target.closest('.chatbot-page-main-content-topbar-group-sort-select-option')) {
            sortSelectDiv.classList.toggle('active');
        }
    });

    document.addEventListener('click', (e) => {
        if (sortSelectDiv && !sortSelectDiv.contains(e.target)) {
            sortSelectDiv.classList.remove('active');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') sortSelectDiv && sortSelectDiv.classList.remove('active');
    });

    if (sortArrow) {
        sortArrow.addEventListener('click', () => {
            setSortDirection(state.sortDirection === 'desc' ? 'asc' : 'desc');
            state.currentPage = 1;
            loadChatbots(false);
        });
    }

    // ============================================
    // SHOW MORE (mobile)
    // ============================================

    showMoreBtn.addEventListener('click', () => {
        if (state.currentPage < state.totalPages) {
            state.currentPage++;
            loadChatbots(true);
        }
    });

    // ============================================
    // MOVE UP
    // ============================================

    moveUpBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // ============================================
    // RESPONSIVE RESIZE
    // ============================================

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            updateShowMore();
            if (!isMobileView()) {
                renderPagination(state.totalPages, state.currentPage);
            } else {
                paginationEl.innerHTML = '';
            }
        }, 200);
    });

    // INIT
    // Nacitaj ulozeny layout (ak existuje)
    const savedView = getSavedPreference('chatbot_showcase_view');
    if (savedView === 'list') {
        state.currentView = 'list';
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
        gridView.classList.remove('active');
        listView.classList.add('active');
    } else {
        gridView.classList.add('active');
        listView.classList.remove('active');
    }
    // ============================================
    // SEARCH
    // ============================================

    const desktopSearchInput = document.getElementById('desktopSearchInput');
    const mobileSearchInput  = document.getElementById('mobileSearchInput');
    const desktopSearch      = document.querySelector('.chatbot-page-main-content-topbar-search-desktop');
    const desktopClearBtn    = desktopSearch?.querySelector('.chatbot-page-main-content-topbar-search-input-clear-btn');
    const desktopClearIcon   = desktopSearch?.querySelector('.chatbot-page-main-content-topbar-search-input-clear-icon');
    const desktopSubmitBtn   = desktopSearch?.querySelector('.chatbot-page-main-content-topbar-search-submit-btn');

    let searchDebounce = null;

    function handleSearchInput(value) {
        state.searchQuery = value.trim();
        state.currentPage = 1;
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => loadChatbots(false, true), 350);
    }

    if (desktopSubmitBtn && desktopSearch) {
        desktopSubmitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isActive = desktopSearch.classList.contains('active');
            if (isActive) {
                desktopSearch.classList.remove('active');
                desktopSearchInput.blur();
            } else if (!isActive) {
                desktopSearch.classList.add('active');
                setTimeout(() => desktopSearchInput.focus(), 50);
            }
        });
    }

    if (desktopSearchInput) {
        desktopSearchInput.addEventListener('input', function () {
            desktopClearIcon?.classList.toggle('visible', this.value.length > 0);
            handleSearchInput(this.value);
        });

        desktopSearchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && desktopSearchInput.value === '') {
                desktopSearch.classList.remove('active');
                desktopSearchInput.blur();
            }
        });

    }

    if (desktopClearBtn && desktopSearchInput) {
        desktopClearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            desktopSearchInput.value = '';
            desktopClearIcon?.classList.remove('visible');
            handleSearchInput('');
            desktopSearchInput.focus();
        });
    }

    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function () {
            handleSearchInput(this.value);
        });
    }
    
    // ============================================
    // INIT — read ?search= from URL (e.g. from homepage popular section)
    // ============================================
    const urlParams = new URLSearchParams(window.location.search);
    const urlSearch = urlParams.get('search');
    if (urlSearch && urlSearch.trim() !== '') {
        state.searchQuery = urlSearch.trim();

        if (desktopSearchInput) {
            desktopSearchInput.value = urlSearch.trim();
            desktopSearch?.classList.add('active');
            desktopClearIcon?.classList.add('visible');
        }
        if (mobileSearchInput) {
            mobileSearchInput.value = urlSearch.trim();
        }
    }

    loadChatbots(false);

})();
