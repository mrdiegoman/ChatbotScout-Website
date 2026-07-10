document.addEventListener('DOMContentLoaded', () => {
    const section = document.querySelector('[data-chatbot-reviews]');
    if (!section) return;

    const reviewsContainer = document.getElementById('reviewsContainer');
    const emptyState = document.getElementById('reviewsEmpty');
    const loadingState = document.getElementById('reviewsLoading');
    const loadMoreButton = document.getElementById('reviewsLoadMore');
    const filterSummary = document.getElementById('reviewsFilterSummary');
    const filterButtons = document.querySelectorAll('.reviews-filter-btn');
    const breakdownToggle = document.getElementById('ratingBreakdownToggle');
    const breakdown = document.getElementById('ratingBreakdown');

    const state = {
        chatbotId: section.dataset.chatbotId,
        chatbotName: section.dataset.chatbotName,
        apiUrl: section.dataset.apiUrl,
        rating: Number(section.dataset.initialRating) || 0,
        offset: 0,
        loading: false,
        requestId: 0,
        controller: null,
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function profilePictureUrl(value) {
        const url = String(value ?? '').trim();
        return /^img\/avatars\/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)$/i.test(url)
            ? url
            : '';
    }

    function buildStars(rating) {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += `<span class="${i <= rating ? 'is-filled' : ''}" aria-hidden="true">★</span>`;
        }
        return `<span class="review-card-stars" aria-label="${rating} out of 5 stars">${stars}</span>`;
    }

    function buildReviewCard(review) {
        const hasText = String(review.review_text ?? '').trim() !== '';
        const profilePicture = profilePictureUrl(review.profile_picture_url);
        const profilePictureSrc = profilePicture || 'img/default-avatar2.png';
        const profilePictureFallback = profilePicture
            ? ' data-fallback-src="img/default-avatar2.png"'
            : '';
        const reviewText = hasText
            ? `<p class="review-card-message">${escapeHtml(review.review_text).replace(/\n/g, '<br>')}</p>`
            : '';

        return `
            <article class="review-card${hasText ? '' : ' review-card--rating-only'}">
                <header class="review-card-header">
                    <img class="review-card-avatar" src="${escapeHtml(profilePictureSrc)}"${profilePictureFallback} alt="">
                    <div class="review-card-author">
                        <h3>${escapeHtml(review.reviewer_name)}</h3>
                        <time datetime="${escapeHtml(review.created_at)}">${escapeHtml(review.created_at_formatted)}</time>
                    </div>
                    ${buildStars(Number(review.rating))}
                </header>
                ${reviewText}
            </article>`;
    }

    function updateFilterUi() {
        filterButtons.forEach((button) => {
            const rating = Number(button.dataset.ratingFilter);
            const isActive = rating === state.rating;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', String(isActive));
        });
    }

    function updateUrl() {
        const url = new URL(window.location.href);
        if (state.rating) {
            url.searchParams.set('rating', state.rating);
        } else {
            url.searchParams.delete('rating');
        }
        url.hash = 'reviews-list';
        window.history.replaceState({}, '', url);
    }

    function setLoading(isLoading) {
        loadingState.hidden = !isLoading;
        loadMoreButton.disabled = isLoading;
        if (isLoading && state.offset > 0) loadMoreButton.textContent = 'Loading…';
    }

    function showEmptyState(total) {
        const ratingLabel = state.rating ? `${state.rating}-star ` : '';
        emptyState.textContent = total === 0
            ? `No ${ratingLabel}reviews yet.`
            : '';
        emptyState.hidden = total !== 0;
    }

    function updateSummary(total) {
        const label = total === 1 ? 'review' : 'reviews';
        filterSummary.textContent = state.rating
            ? `${total} ${label} with ${state.rating} stars`
            : `${total} ${label}`;
    }

    async function loadReviews(reset = false) {
        if (state.loading && !reset) return;
        if (state.loading && reset && state.controller) state.controller.abort();

        if (reset) {
            state.offset = 0;
            reviewsContainer.innerHTML = '';
            emptyState.hidden = true;
            loadMoreButton.hidden = true;
        }

        state.loading = true;
        const requestId = ++state.requestId;
        const controller = new AbortController();
        state.controller = controller;
        setLoading(true);

        try {
            const url = new URL(state.apiUrl, window.location.href);
            url.searchParams.set('chatbot', state.chatbotId);
            url.searchParams.set('offset', state.offset);
            if (state.rating) url.searchParams.set('rating', state.rating);

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load reviews');
            if (requestId !== state.requestId) return;

            if (data.reviews.length) {
                reviewsContainer.insertAdjacentHTML('beforeend', data.reviews.map(buildReviewCard).join(''));
                state.offset += data.reviews.length;
            }

            updateSummary(Number(data.total));
            showEmptyState(Number(data.total));
            loadMoreButton.hidden = !data.has_more;
            loadMoreButton.textContent = 'Load more reviews';
        } catch (error) {
            if (requestId !== state.requestId) return;
            if (error.name === 'AbortError') return;
            emptyState.textContent = 'Reviews could not be loaded. Please try again.';
            emptyState.hidden = false;
            loadMoreButton.hidden = true;
        } finally {
            if (requestId === state.requestId) {
                state.loading = false;
                state.controller = null;
                setLoading(false);
            }
        }
    }

    function changeFilter(rating, shouldScroll = false) {
        state.rating = rating;
        updateFilterUi();
        updateUrl();
        loadReviews(true);

        if (shouldScroll) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section.focus({ preventScroll: true });
        }
    }

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => changeFilter(Number(button.dataset.ratingFilter)));
    });

    if (breakdownToggle && breakdown) {
        breakdownToggle.addEventListener('click', () => {
            const isExpanded = breakdownToggle.getAttribute('aria-expanded') === 'true';
            breakdownToggle.setAttribute('aria-expanded', String(!isExpanded));
            breakdown.hidden = isExpanded;
        });
    }

    document.querySelectorAll('.reviews-breakdown-row').forEach((button) => {
        button.addEventListener('click', () => changeFilter(Number(button.dataset.ratingFilter), true));
    });

    loadMoreButton.addEventListener('click', () => loadReviews());

    updateFilterUi();
    loadReviews(true);
});
