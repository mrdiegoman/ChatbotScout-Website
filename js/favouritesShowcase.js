/**
 * favouritesShowcase.js
 *
 * Dynamické načítanie obľúbených chatbotov pre prihláseného usera.
 * - Grid / List view
 * - Sort + pagination
 * - × tlačidlo na odstránenie z obľúbených (volá toggleFavorite.php)
 * - Filter badge počítadlo
 */

(function () {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    const state = {
        currentView: 'grid',
        currentSort: 'date-added',
        currentPage: 1,
        totalPages:  1,
        isLoading:   false,
    };

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
    const filtersCount  = document.getElementById('filtersCount');
    const filtersCountNum = document.getElementById('filtersCountNum');

    // Filters modal
    const filtersBtn      = document.getElementById('filtersBtn');
    const filtersOverlay  = document.getElementById('filtersModalOverlay');
    const filtersCloseBtn = document.getElementById('filtersCloseBtn');
    const filtersClearBtn = document.getElementById('filtersClearBtn');

    // Filter inputs
    const filterNumberInputs = document.querySelectorAll(
        '#ratingInputStart, #ratingInputEnd, #votesInputStart, #votesInputEnd, #priceInputStart, #priceInputEnd'
    );

    // ============================================
    // HELPERS
    // ============================================

    function setLoading(on) {
        state.isLoading = on;
        loadingEl.style.display = on ? 'flex' : 'none';
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function currentContainer() {
        return state.currentView === 'grid' ? gridView : listView;
    }

    function buildStars(rating) {
        const value = (rating !== null && Number(rating) > 0)
            ? Math.round(Number(rating) * 2) / 2 : 0;
        return `
            <div class="favourites-page-main-content-chatbots-item-top-base-2-rating"
                 data-rating="${value}">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
            </div>`;
    }

    function buildTags(tags) {
        if (!tags || tags.length === 0) return '';
        return tags.map(t => `<span><p>✦ ${escapeHtml(t.name)} ✦</p></span>`).join('');
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

    function logoHtml(chatbot) {
        const src = chatbot.logo_url ? escapeHtml(chatbot.logo_url) : '../img/chatbothub_logo.png';
        return `<img src="../${src}" alt="${escapeHtml(chatbot.name)} logo" loading="lazy">`;
    }

    // ============================================
    // HTML BUILDERS
    // ============================================

    function buildRemoveBtn(chatbotId) {
        return `
            <button class="favourites-page-main-content-chatbots-grid-view-item-top-remove-btn fav-remove-btn"
                    data-chatbot-id="${escapeHtml(chatbotId)}"
                    aria-label="Remove from favourites"
                    title="Remove from favourites">
                ×
            </button>`;
    }

    function buildGridItem(chatbot) {
        const stars   = buildStars(chatbot.rating_average);
        const tags    = buildTags(chatbot.tags);
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = escapeHtml(chatbot.pricing);
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const rating  = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount  = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';

        return `
<div class="favourites-page-main-content-chatbots-grid-view-item" data-chatbot-id="${escapeHtml(chatbot.id)}">
    ${buildRemoveBtn(chatbot.id)}
    <div class="favourites-page-main-content-chatbots-grid-view-item-tags">${tags}</div>
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
                    <p>(${rCount})</p>
                </div>
            </div>
        </div>
    </div>
    <div class="favourites-page-main-content-chatbots-grid-view-item-description-container">
        <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-subtitle">Description</p>
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
        const stars   = buildStars(chatbot.rating_average);
        const tags    = buildTags(chatbot.tags);
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = escapeHtml(chatbot.pricing);
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const rating  = chatbot.rating_average ? Number(chatbot.rating_average).toFixed(1) : '—';
        const rCount  = chatbot.rating_count ? Number(chatbot.rating_count).toLocaleString() : '0';

        return `
<div class="favourites-page-main-content-chatbots-list-view-item-containter" data-chatbot-id="${escapeHtml(chatbot.id)}">
    <button class="favourites-page-main-content-chatbots-list-view-item-remove-btn fav-remove-btn"
            data-chatbot-id="${escapeHtml(chatbot.id)}"
            aria-label="Remove from favourites"
            title="Remove from favourites">×</button>
    <div class="favourites-page-main-content-chatbots-list-view-item-tags">${tags}</div>
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
                            <p>(${rCount})</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="favourites-page-main-content-chatbots-list-view-item-description-container">
                <div class="favourites-page-main-content-chatbots-grid-view-item-description-container-header">
                    <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-subtitle">
                        Description</p>
                    <button class="favourites-page-main-content-chatbots-grid-view-item-features-container-1-imp-btn">
                        <p>Platforms</p>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                            stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
                        </svg>
                    </button>
                </div>
                <p class="favourites-page-main-content-chatbots-list-view-item-description-container-text">${desc}</p>
            </div>
        </div>
        <div class="favourites-page-main-content-chatbots-list-view-item-features-container-right">
            <div class="favourites-page-main-content-chatbots-list-view-item-features-container">
                <div class="favourites-page-main-content-chatbots-list-view-item-features-container-1">
                    <p class="favourites-page-main-content-chatbots-list-view-item-description-container-subtitle">Key Features</p>
                </div>
                <div class="favourites-page-main-content-chatbots-list-view-item-scroll-wrapper">
                    <div class="favourites-page-main-content-chatbots-list-view-item-scroll-container">
                        <div class="favourites-page-main-content-chatbots-list-view-item-cards-wrapper">${cards}</div>
                    </div>
                    <div class="favourites-page-main-content-chatbots-list-view-item-scrollbar">
                        <div class="favourites-page-main-content-chatbots-list-view-item-scrollbar-thumb"></div>
                    </div>
                </div>
            </div>
        
            <div class="favourites-page-main-content-chatbots-list-view-item-btns-container">
                <div class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka">
                    <span class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka-text">${pricing}</span>
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

        if (chatbots.length === 0) {
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
    // REMOVE FAVOURITE — event delegation
    // ============================================

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.fav-remove-btn');
        if (!btn) return;

        const chatbotId = btn.dataset.chatbotId;
        if (!chatbotId) return;

        btn.disabled = true;

        // Nájdi kartu a animuj jej odstránenie
        const card = btn.closest(
            '.favourites-page-main-content-chatbots-grid-view-item, .favourites-page-main-content-chatbots-list-view-item-containter'
        );

        fetch('../api/toggleFavorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatbot_id: chatbotId }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && !data.is_favourite) {
                // Animuj a odstráň kartu z DOM
                if (card) {
                    card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.96)';
                    setTimeout(() => {
                        card.remove();
                        // Ak sú prázdne, zobraz empty state
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
        fetch('../api/trackClick.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatbot_id: chatbotId }),
        }).catch(() => {});
    });

    // ============================================
    // FETCH
    // ============================================

    function loadFavourites() {
        if (state.isLoading) return;
        setLoading(true);

        const params = new URLSearchParams({
            view: state.currentView,
            sort: state.currentSort,
            page: state.currentPage,
        });

        fetch(`../api/getFavourites.php?${params}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error('API error');
                state.totalPages = data.totalPages;

                renderChatbots(data.chatbots);
                renderPagination(data.totalPages, data.currentPage);
            })
            .catch(err => {
                console.error('Failed to load favourites:', err);
                emptyState.style.display = 'block';
            })
            .finally(() => setLoading(false));
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

        try { localStorage.setItem('favourites_showcase_view', view); } catch (_) {}

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

    // ============================================
    // FILTERS MODAL
    // ============================================

    filtersBtn.addEventListener('click', () => {
        filtersOverlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });

    function closeFiltersModal() {
        filtersOverlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    filtersCloseBtn.addEventListener('click', closeFiltersModal);
    filtersOverlay.addEventListener('click', (e) => { if (e.target === filtersOverlay) closeFiltersModal(); });

    // Filter badge počítadlo
    function countActiveFilters() {
        let count = 0;
        const rS = document.getElementById('ratingInputStart');
        const rE = document.getElementById('ratingInputEnd');
        if ((rS && rS.value.trim()) || (rE && rE.value.trim())) count++;

        const vS = document.getElementById('votesInputStart');
        const vE = document.getElementById('votesInputEnd');
        if ((vS && vS.value.trim()) || (vE && vE.value.trim())) count++;

        const pS = document.getElementById('priceInputStart');
        const pE = document.getElementById('priceInputEnd');
        if ((pS && pS.value.trim()) || (pE && pE.value.trim())) count++;

        count += document.querySelectorAll('.favourites-filters-modal-body-features-single.active').length;
        return count;
    }

    function updateFilterBadge() {
        const count = countActiveFilters();
        filtersCount.style.display = count === 0 ? 'none' : 'flex';
        filtersCountNum.textContent = count;
    }

    filterNumberInputs.forEach(input => input.addEventListener('input', updateFilterBadge));
    filtersOverlay.addEventListener('click', (e) => {
        if (e.target.classList.contains('favourites-filters-modal-body-features-single')) {
            setTimeout(updateFilterBadge, 0);
        }
    });

    filtersClearBtn.addEventListener('click', () => {
        filterNumberInputs.forEach(input => { input.value = ''; });
        document.querySelectorAll('.favourites-filters-modal-body-features-single.active')
            .forEach(span => span.classList.remove('active'));
        updateFilterBadge();
    });

    // ============================================
    // MOVE UP
    // ============================================

    moveUpBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // ============================================
    // INIT
    // ============================================

    const savedView = (() => {
        try { return localStorage.getItem('favourites_showcase_view'); } catch (_) { return null; }
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

    loadFavourites();

})();