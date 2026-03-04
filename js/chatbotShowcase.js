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
        currentView:     'grid',
        currentSort:     'popularity',
        currentPage:     1,
        totalPages:      1,
        totalItems:      0,
        isLoading:       false,
        ratingChatbotId: null,
        ratingValue:     0,
    };

    const LOGIN_URL = '../public/login.php?redirect=chatbotShowcase.php';

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

    // Rating modal
    const ratingOverlay      = document.getElementById('ratingModalOverlay');
    const ratingModalTitle   = document.getElementById('ratingModalTitle');
    const ratingStarsPicker  = document.getElementById('ratingStarsPicker');
    const ratingStarsLabel   = document.getElementById('ratingStarsLabel');
    const ratingTitleInput   = document.getElementById('ratingTitleInput');
    const ratingTextInput    = document.getElementById('ratingTextInput');
    const ratingModalError   = document.getElementById('ratingModalError');
    const ratingModalSuccess = document.getElementById('ratingModalSuccess');
    const ratingSubmitBtn    = document.getElementById('ratingModalSubmitBtn');
    const ratingCancelBtn    = document.getElementById('ratingModalCancelBtn');
    const ratingCloseBtn     = document.getElementById('ratingModalCloseBtn');

    const starLabels = ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

    // ============================================
    // HELPERS
    // ============================================

    function isMobileView() { return window.innerWidth <= 768; }

    function setLoading(on) {
        state.isLoading = on;
        loadingEl.style.display = on ? 'flex' : 'none';
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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

    function pricingLabel(chatbot) { return chatbot.pricing || 'Contact'; }

    function buildTags(tags) {
        if (!tags || tags.length === 0) return '';
        return tags.map(t => `<span><p>✦ ${escapeHtml(t.name)} ✦</p></span>`).join('');
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

    function logoHtml(chatbot) {
        const src = chatbot.logo_url ? escapeHtml(chatbot.logo_url) : '../img/chatbothub_logo.png';
        return `<img src="../${src}" alt="${escapeHtml(chatbot.name)} logo" loading="lazy">`;
    }

    // ============================================
    // HTML BUILDERS
    // Favorites button má data-chatbot-id pre event delegation
    // ============================================

    function buildGridItem(chatbot) {
        const stars   = buildStars(chatbot.rating_average, chatbot.id);
        const tags    = buildTags(chatbot.tags);
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = escapeHtml(pricingLabel(chatbot));
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const rating  = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount  = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';
        const favCount = chatbot.favorite_count ?? 0;

        return `
<div class="chatbot-page-main-content-chatbots-grid-view-item">
    <div class="chatbot-page-main-content-chatbots-grid-view-item-tags">${tags}</div>
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
                    <p>(${rCount})</p>
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
            <button class="chatbot-page-main-content-chatbots-grid-view-item-features-container-1-imp-btn">
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
        const pricing  = escapeHtml(pricingLabel(chatbot));
        const link     = escapeHtml(chatbot.link);
        const name     = escapeHtml(chatbot.name);
        const desc     = escapeHtml(chatbot.description || '');
        const rating   = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount   = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';
        const favCount = chatbot.favorite_count ?? 0;

        return `
<div class="chatbot-page-main-content-chatbots-list-view-item-containter">
    <div class="chatbot-page-main-content-chatbots-list-view-item-tags">${tags}</div>
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
                            <p>(${rCount})</p>
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
                    <button class="chatbot-page-main-content-chatbots-list-view-item-features-container-1-imp-btn">
                        <p>Platforms</p>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
                        </svg>
                    </button>
                </div>
                <p class="chatbot-page-main-content-chatbots-list-view-item-description-container-text">${desc}</p>
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
                <div class="chatbot-page-main-content-chatbots-list-view-item-btns-container-sipka">
                    <span class="chatbot-page-main-content-chatbots-list-view-item-btns-container-sipka-text">${pricing}</span>
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
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';

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
            bottomBar.style.display = 'none';
            return;
        }
        bottomBar.style.display = 'flex';

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
            showMoreWrapper.style.display = state.currentPage < state.totalPages ? 'flex' : 'none';
        } else {
            showMoreWrapper.style.display = 'none';
        }
    }

    // ============================================
    // FETCH
    // ============================================

    function loadChatbots(append) {
        if (state.isLoading) return;
        setLoading(true);

        const params = new URLSearchParams({
            view: state.currentView,
            sort: state.currentSort,
            page: state.currentPage,
        });

        fetch(`../api/getChatbots.php?${params}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error('API error');
                state.totalPages = data.totalPages;
                state.totalItems = data.total;

                renderChatbots(data.chatbots, append);

                if (!isMobileView()) {
                    renderPagination(data.totalPages, data.currentPage);
                } else {
                    paginationEl.innerHTML = '';
                    bottomBar.style.display = data.totalPages > 1 ? 'flex' : 'none';
                }
                updateShowMore();
            })
            .catch(err => {
                console.error('Failed to load chatbots:', err);
                emptyState.style.display = 'block';
            })
            .finally(() => setLoading(false));
    }

    // ============================================
    // CLICK TRACKING (Go to Website)
    // ============================================

    document.addEventListener('click', (e) => {
        // console.log('e.target:', e.target);
        // console.log('e.target class:', e.target.className);
        
        const link = e.target.closest('.chatbot-page-main-content-chatbots-grid-view-item-btns-container-go-to-website, .chatbot-page-main-content-chatbots-list-view-item-btns-container-go-to-website');
        if (!link) return;

        const chatbotId = link.dataset.chatbotId;
        // console.log('chatbotId:', chatbotId);
        if (!chatbotId) return;

        fetch('../api/recordVisit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatbot_id: chatbotId }),
        }).catch(() => {});
    });

    // ============================================
    // FAVORITES — event delegation
    // Buttons sú vytvárané dynamicky, preto querySelectorAll nestačí.
    // ============================================

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.chatbot-fav-btn');
        if (!btn) return;

        // Neprihlásený → redirect
        if (!CHATBOT_USER_LOGGED_IN) {
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
        fetch('../api/toggleFavorite.php', {
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
        if (!CHATBOT_USER_LOGGED_IN) {
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

    function openRatingModal(chatbotId, chatbotName) {
        state.ratingChatbotId = chatbotId;
        state.ratingValue = 0;

        ratingModalTitle.textContent = `Rate: ${chatbotName}`;
        ratingTitleInput.value = '';
        ratingTextInput.value = '';
        ratingModalError.textContent = '';
        ratingModalError.style.display = 'none';
        ratingModalSuccess.textContent = '';
        ratingModalSuccess.style.display = 'none';
        ratingSubmitBtn.disabled = false;
        ratingSubmitBtn.textContent = 'Submit Review';

        updatePickerStars(0);

        ratingOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeRatingModal() {
        ratingOverlay.classList.remove('active');
        document.body.style.overflow = '';
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
    ratingOverlay.addEventListener('click', (e) => { if (e.target === ratingOverlay) closeRatingModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && ratingOverlay.classList.contains('active')) closeRatingModal();
    });

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
        if (!ratingTextInput.value.trim()) {
            ratingModalError.textContent = 'Please write your review.';
            ratingModalError.style.display = 'block';
            return;
        }

        ratingSubmitBtn.disabled = true;
        ratingSubmitBtn.textContent = 'Submitting...';

        fetch('../api/submitReview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                chatbot_id:  state.ratingChatbotId,
                rating:      state.ratingValue,
                title:       ratingTitleInput.value.trim(),
                review_text: ratingTextInput.value.trim(),
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                ratingModalSuccess.textContent = data.message;
                ratingModalError.style.display = 'block';
                ratingSubmitBtn.textContent = 'Submitted!';
                setTimeout(closeRatingModal, 2000);
            } else {
                ratingModalError.textContent = data.message || 'Something went wrong.';
                ratingModalError.style.display = 'block';
                ratingSubmitBtn.disabled = false;
                ratingSubmitBtn.textContent = 'Submit Review';
            }
        })
        .catch(() => {
            ratingModalError.textContent = 'Server error. Please try again.';
            ratingSubmitBtn.disabled = false;
            ratingSubmitBtn.textContent = 'Submit Review';
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

        // Uloz preferovany layout
        try { localStorage.setItem('chatbot_showcase_view', view); } catch (_) {}

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
        let sortAscending = false;
        sortArrow.addEventListener('click', () => {
            sortAscending = !sortAscending;
            sortArrow.style.transform = sortAscending ? 'scaleY(-1)' : 'scaleY(1)';
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
    const savedView = (() => {
        try { return localStorage.getItem('chatbot_showcase_view'); } catch (_) { return null; }
    })();
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
    loadChatbots(false);

})();