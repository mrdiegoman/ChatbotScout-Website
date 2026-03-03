/**
 * chatbotShowcase.js
 *
 * Handles:
 *  - AJAX loading of chatbots from api/getChatbots.php
 *  - Grid / List view toggle
 *  - Sort (Popularity, Price, Alphabetical, Rating, Number of ratings)
 *  - Desktop pagination (numbered pages)
 *  - Mobile Show More button (append items)
 *  - Move Up button (scroll to top)
 *  - Click tracking via api/trackClick.php
 */

(function () {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    const state = {
        currentView:  'grid',   // 'grid' | 'list'
        currentSort:  'popularity',
        currentPage:  1,
        totalPages:   1,
        totalItems:   0,
        isLoading:    false,
        isMobile:     window.innerWidth <= 768,
    };

    // ============================================
    // DOM REFERENCES
    // ============================================

    const gridView          = document.getElementById('gridView');
    const listView          = document.getElementById('listView');
    const loading           = document.getElementById('chatbotsLoading');
    const emptyState        = document.getElementById('chatbotsEmpty');
    const showMoreWrapper   = document.getElementById('showMoreWrapper');
    const showMoreBtn       = document.getElementById('showMoreBtn');
    const bottomBar         = document.getElementById('bottomBar');
    const paginationEl      = document.getElementById('paginationContainer');
    const moveUpBtn         = document.getElementById('moveUpBtn');
    const listBtn           = document.getElementById('listBtn');
    const gridBtn           = document.getElementById('gridBtn');
    const sortOptions       = document.querySelectorAll('.chatbot-page-main-content-topbar-group-sort-select-option');
    const sortSelected      = document.getElementById('sortSelected');
    const sortSelectDiv     = document.getElementById('sortSelectDiv');
    const sortArrow         = document.getElementById('sortArrow');

    // ============================================
    // HELPERS
    // ============================================

    function isMobileView() {
        return window.innerWidth <= 768;
    }

    function setLoading(on) {
        state.isLoading = on;
        loading.style.display = on ? 'flex' : 'none';
    }

    function currentContainer() {
        return state.currentView === 'grid' ? gridView : listView;
    }

    /**
     * Build rating stars HTML.
     * Uses existing CSS data-rating approach from the project.
     */
    function buildStars(rating) {
        const rounded = Math.round(rating * 2) / 2; // round to nearest 0.5
        return `
            <div class="chatbot-page-main-content-chatbots-item-top-base-2-rating" data-rating="${rounded}">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
            </div>`;
    }

    /**
     * Build pricing label text.
     * Server already computes this, just pass through.
     */
    function pricingLabel(chatbot) {
        return chatbot.pricing || 'Contact';
    }

    /**
     * Build tags HTML from array of {name, slug}.
     */
    function buildTags(tags) {
        if (!tags || tags.length === 0) return '';
        return tags.map(t => `<span><p>✦ ${t.name} ✦</p></span>`).join('');
    }

    /**
     * Build key features cards HTML.
     * Groups features into cards of 4 items each.
     */
    function buildFeatureCards(features) {
        if (!features || features.length === 0) {
            return '<div class="chatbot-page-main-content-chatbots-list-view-item-card"><ul><li>No features listed</li></ul></div>';
        }

        const chunkSize = 4;
        let html = '';
        for (let i = 0; i < features.length; i += chunkSize) {
            const chunk = features.slice(i, i + chunkSize);
            html += `<div class="chatbot-page-main-content-chatbots-list-view-item-card"><ul>`;
            chunk.forEach(f => { html += `<li>${escapeHtml(f)}</li>`; });
            html += `</ul></div>`;
        }
        return html;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function logoHtml(chatbot) {
        if (chatbot.logo_url) {
            return `<img src="../${escapeHtml(chatbot.logo_url)}" alt="${escapeHtml(chatbot.name)} logo" loading="lazy">`;
        }
        return `<img src="../img/chatbothub_logo.png" alt="${escapeHtml(chatbot.name)} logo">`;
    }

    // ============================================
    // HTML BUILDERS
    // ============================================

    function buildListItem(chatbot) {
        const stars    = buildStars(chatbot.rating_average);
        const tags     = buildTags(chatbot.tags);
        const cards    = buildFeatureCards(chatbot.features);
        const pricing  = escapeHtml(pricingLabel(chatbot));
        const link     = escapeHtml(chatbot.link);
        const name     = escapeHtml(chatbot.name);
        const desc     = escapeHtml(chatbot.description || '');
        const rating   = chatbot.rating_average.toFixed(1);
        const rCount   = chatbot.rating_count.toLocaleString();

        return `
<div class="chatbot-page-main-content-chatbots-list-view-item-containter">
    <div class="chatbot-page-main-content-chatbots-list-view-item-tags">
        ${tags}
    </div>
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
                        <p>${chatbot.favorite_count}</p>
                        <button class="chatbot-page-main-content-chatbots-list-view-item-top-favourites-container-btn" aria-label="Add to favourites">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
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
                        <div class="chatbot-page-main-content-chatbots-list-view-item-cards-wrapper">
                            ${cards}
                        </div>
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

    function buildGridItem(chatbot) {
        const stars   = buildStars(chatbot.rating_average);
        const tags    = buildTags(chatbot.tags);
        const cards   = buildFeatureCards(chatbot.features);
        const pricing = escapeHtml(pricingLabel(chatbot));
        const link    = escapeHtml(chatbot.link);
        const name    = escapeHtml(chatbot.name);
        const desc    = escapeHtml(chatbot.description || '');
        const rating  = chatbot.rating_average.toFixed(1);
        const rCount  = chatbot.rating_count.toLocaleString();

        return `
<div class="chatbot-page-main-content-chatbots-grid-view-item">
    <div class="chatbot-page-main-content-chatbots-grid-view-item-tags">
        ${tags}
    </div>
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
                <p>${chatbot.favorite_count}</p>
                <button class="chatbot-page-main-content-chatbots-grid-view-item-top-favourites-container-btn" aria-label="Add to favourites">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
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

    // ============================================
    // RENDER
    // ============================================

    function renderChatbots(chatbots, append) {
        const container = currentContainer();

        if (!append) {
            container.innerHTML = '';
        }

        if (chatbots.length === 0 && !append) {
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';

        const html = chatbots.map(c =>
            state.currentView === 'grid' ? buildGridItem(c) : buildListItem(c)
        ).join('');

        container.insertAdjacentHTML('beforeend', html);

        // Re-init scrollbars (from featuresSwiping.js)
        if (typeof initializeScrollbars === 'function') {
            initializeScrollbars();
        }
    }

    // ============================================
    // PAGINATION
    // ============================================

    function renderPagination(totalPages, currentPage) {
        paginationEl.innerHTML = '';

        if (totalPages <= 1) {
            bottomBar.style.display = 'none';
            return;
        }

        bottomBar.style.display = 'flex';

        // Build page numbers with ellipsis
        const pages = getPageRange(currentPage, totalPages);

        pages.forEach(p => {
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
            btn.setAttribute('aria-label', `Page ${p}`);
            if (p === currentPage) btn.setAttribute('aria-current', 'page');

            btn.addEventListener('click', () => {
                if (p !== state.currentPage) {
                    state.currentPage = p;
                    loadChatbots(false);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });

            paginationEl.appendChild(btn);
        });

        // Prev / Next arrows
        const prevBtn = document.createElement('button');
        prevBtn.className = 'chatbot-showcase-pagination-arrow';
        prevBtn.innerHTML = '&lsaquo;';
        prevBtn.setAttribute('aria-label', 'Previous page');
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            if (state.currentPage > 1) {
                state.currentPage--;
                loadChatbots(false);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'chatbot-showcase-pagination-arrow';
        nextBtn.innerHTML = '&rsaquo;';
        nextBtn.setAttribute('aria-label', 'Next page');
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            if (state.currentPage < state.totalPages) {
                state.currentPage++;
                loadChatbots(false);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        paginationEl.insertBefore(prevBtn, paginationEl.firstChild);
        paginationEl.appendChild(nextBtn);
    }

    function getPageRange(current, total) {
        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }

        const pages = [];

        if (current <= 4) {
            pages.push(1, 2, 3, 4, 5, '...', total);
        } else if (current >= total - 3) {
            pages.push(1, '...', total - 4, total - 3, total - 2, total - 1, total);
        } else {
            pages.push(1, '...', current - 1, current, current + 1, '...', total);
        }

        return pages;
    }

    // ============================================
    // SHOW MORE (mobile)
    // ============================================

    function updateShowMore() {
        if (isMobileView()) {
            const hasMore = state.currentPage < state.totalPages;
            showMoreWrapper.style.display = hasMore ? 'flex' : 'none';
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

                // Desktop: pagination
                if (!isMobileView()) {
                    renderPagination(data.totalPages, data.currentPage);
                } else {
                    bottomBar.style.display = data.totalPages > 1 ? 'flex' : 'none';
                    paginationEl.innerHTML = '';
                }

                updateShowMore();
            })
            .catch(err => {
                console.error('Failed to load chatbots:', err);
                emptyState.style.display = 'block';
            })
            .finally(() => {
                setLoading(false);
            });
    }

    // ============================================
    // CLICK TRACKING
    // ============================================

    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-chatbot-id]');
        if (!link) return;

        const chatbotId = link.dataset.chatbotId;
        if (!chatbotId) return;

        // Fire and forget
        fetch('../api/trackClick.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatbot_id: chatbotId }),
        }).catch(() => {});
    });

    // ============================================
    // VIEW TOGGLE
    // ============================================

    function switchView(view) {
        if (view === state.currentView) return;

        state.currentView  = view;
        state.currentPage  = 1;

        // Toggle active classes on buttons
        listBtn.classList.toggle('active', view === 'list');
        gridBtn.classList.toggle('active', view === 'grid');

        // Toggle active classes on containers
        gridView.classList.toggle('active', view === 'grid');
        listView.classList.toggle('active', view === 'list');

        loadChatbots(false);
    }

    listBtn.addEventListener('click', () => switchView('list'));
    gridBtn.addEventListener('click', () => switchView('grid'));

    // ============================================
    // SORT
    // ============================================

    sortOptions.forEach(option => {
        option.addEventListener('click', () => {
            const value = option.dataset.value;
            if (value === state.currentSort) return;

            state.currentSort = value;
            state.currentPage = 1;

            // Update UI
            sortOptions.forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
            sortSelected.textContent = option.textContent;

            // Close dropdown
            sortSelectDiv.classList.remove('active');

            loadChatbots(false);
        });
    });

    // Sort dropdown open/close
    sortSelectDiv.addEventListener('click', () => {
        sortSelectDiv.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!sortSelectDiv.contains(e.target)) {
            sortSelectDiv.classList.remove('active');
        }
    });

    // Sort arrow direction toggle
    let sortAscending = false;
    if (sortArrow) {
        sortArrow.addEventListener('click', () => {
            sortAscending = !sortAscending;
            sortArrow.style.transform = sortAscending ? 'scaleY(-1)' : 'scaleY(1)';
            // Future: pass direction to API
        });
    }

    // ============================================
    // SHOW MORE (mobile)
    // ============================================

    showMoreBtn.addEventListener('click', () => {
        if (state.currentPage < state.totalPages) {
            state.currentPage++;
            loadChatbots(true); // append = true
        }
    });

    // ============================================
    // MOVE UP
    // ============================================

    moveUpBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // ============================================
    // RESPONSIVE: switch mode on resize
    // ============================================

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const wasMobile = state.isMobile;
            state.isMobile = isMobileView();

            if (wasMobile !== state.isMobile) {
                // Switching between desktop and mobile
                state.currentPage = 1;
                updateShowMore();

                if (!isMobileView()) {
                    renderPagination(state.totalPages, state.currentPage);
                } else {
                    paginationEl.innerHTML = '';
                }
            }
        }, 200);
    });

    // ============================================
    // INIT
    // ============================================

    // Set initial view: grid is default, list active if viewport < 768 would still be grid
    gridView.classList.add('active');
    listView.classList.remove('active');

    loadChatbots(false);

})();