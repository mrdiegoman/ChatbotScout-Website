/**
 * profileReviews.js
 *
 * My Reviews tab v profile.php
 * - Načítanie reviews usera
 * - Edit (pre-filled modal, submit → updateReview.php?action=edit)
 * - Delete (confirm dialog → updateReview.php?action=delete)
 * - Ak je user is_review_blocked → edit tlačidlá sú disabled/greyed out
 */

(function () {
    'use strict';

    // ============================================
    // STATE
    // ============================================

    let editingReviewId  = null;
    let currentRating    = 0;
    let isReviewBlocked  = false;   // nastavené po načítaní z API
    const reviewState = {
        offset: 0,
        total: 0,
        hasMore: false,
        loading: false,
    };

    const starLabels = {
        1: 'Poor',
        2: 'Fair',
        3: 'Good',
        4: 'Very Good',
        5: 'Excellent',
    };

    // ============================================
    // DOM REFERENCES
    // ============================================

    const reviewsContainer = document.getElementById('myReviewsContainer');
    const reviewsLoading   = document.getElementById('myReviewsLoading');
    const reviewsEmpty     = document.getElementById('myReviewsEmpty');
    const reviewsLoadMore  = document.getElementById('myReviewsLoadMore');
    const reviewsLoadMoreWrap = reviewsLoadMore ? reviewsLoadMore.closest('.pr-load-more-wrap') : null;

    // Edit modal
    const editOverlay      = document.getElementById('editReviewOverlay');
    const editModalTitle   = document.getElementById('editReviewModalTitle');
    const editStarsPicker  = document.getElementById('editRatingStarsPicker');
    const editStarsLabel   = document.getElementById('editRatingStarsLabel');
    const editTextInput    = document.getElementById('editReviewTextInput');
    const editErrorEl      = document.getElementById('editReviewError');
    const editSuccessEl    = document.getElementById('editReviewSuccess');
    const editSubmitBtn    = document.getElementById('editReviewSubmitBtn');
    const editCancelBtn    = document.getElementById('editReviewCancelBtn');
    const editCloseBtn     = document.getElementById('editReviewCloseBtn');

    // Confirm delete modal
    const deleteOverlay    = document.getElementById('deleteReviewOverlay');
    const deleteConfirmBtn = document.getElementById('deleteReviewConfirmBtn');
    const deleteCancelBtn  = document.getElementById('deleteReviewCancelBtn');
    const deleteCloseBtn   = document.getElementById('deleteReviewCloseBtn');

    let pendingDeleteId = null;

    function setHidden(element, hidden) {
        if (element) element.hidden = hidden;
    }

    function clearEditFeedback() {
        if (editErrorEl) {
            editErrorEl.textContent = '';
            setHidden(editErrorEl, true);
        }
        if (editSuccessEl) {
            editSuccessEl.textContent = '';
            setHidden(editSuccessEl, true);
        }
    }

    function showEditError(message) {
        if (!editErrorEl) return;
        editErrorEl.textContent = message;
        setHidden(editErrorEl, false);
    }

    function showEditSuccess(message) {
        if (!editSuccessEl) return;
        editSuccessEl.textContent = message;
        setHidden(editSuccessEl, false);
    }

    // ============================================
    // LOAD REVIEWS
    // ============================================

    function loadReviews(reset = false) {
        if (!reviewsContainer || reviewState.loading) return;

        if (reset) {
            reviewState.offset = 0;
            reviewState.total = 0;
            reviewState.hasMore = false;
            reviewsContainer.innerHTML = '';
            setHidden(reviewsEmpty, true);
            setHidden(reviewsLoadMore, true);
            setHidden(reviewsLoadMoreWrap, true);
        }

        reviewState.loading = true;
        setLoading(true);

        const url = new URL('api/getUserReviews.php', window.location.href);
        url.searchParams.set('offset', reviewState.offset);

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    if (reviewState.offset === 0) showEmpty();
                    return;
                }

                // Ulož blocked flag
                isReviewBlocked = !!data.is_review_blocked;

                const reviews = Array.isArray(data.reviews) ? data.reviews : [];
                reviewState.total = Number(data.total) || 0;
                reviewState.hasMore = !!data.has_more;

                if (reviews.length === 0 && reviewState.offset === 0) {
                    showEmpty();
                    return;
                }

                appendReviews(reviews);
                reviewState.offset += reviews.length;
                updateLoadMore();
            })
            .catch(() => {
                if (reviewState.offset === 0) showEmpty();
            })
            .finally(() => {
                reviewState.loading = false;
                setLoading(false);
                updateLoadMore();
            });
    }

    function setLoading(state) {
        setHidden(reviewsLoading, !(state && reviewState.offset === 0));

        if (reviewsLoadMore) {
            reviewsLoadMore.disabled = state;
            reviewsLoadMore.textContent = state && reviewState.offset > 0 ? 'Loading...' : 'Load more reviews';
        }
    }

    function showEmpty() {
        setHidden(reviewsEmpty, false);
        if (reviewsContainer) reviewsContainer.innerHTML = '';
        setHidden(reviewsLoadMore, true);
        setHidden(reviewsLoadMoreWrap, true);
    }

    function updateLoadMore() {
        if (!reviewsLoadMore) return;
        const shouldShow = reviewState.hasMore && reviewState.offset > 0;
        setHidden(reviewsLoadMore, !shouldShow);
        setHidden(reviewsLoadMoreWrap, !shouldShow);
        if (!reviewState.loading) reviewsLoadMore.textContent = 'Load more reviews';
    }

    // ============================================
    // RENDER
    // ============================================

    function appendReviews(reviews) {
        setHidden(reviewsEmpty, true);
        if (!reviews.length) return;
        reviewsContainer.insertAdjacentHTML('beforeend', reviews.map(buildReviewCard).join(''));
    }

    function logoSrc(path) {
        const value = String(path || '').trim();
        if (!value) return 'img/ChatbotScout_logo.png';
        if (/^(https?:)?\/\//i.test(value) || value.startsWith('/') || value.startsWith('data:') || value.startsWith('../')) {
            return value;
        }
        if (value.startsWith('websiteLogos/')) {
            return `img/${value}`;
        }
        if (value.startsWith('public/')) {
            return `../${value}`;
        }
        return value;
    }

    function buildReviewCard(r) {
        const statusConfig = {
            pending:  { label: 'Pending',  cls: 'pr-status-pending'  },
            approved: { label: 'Approved', cls: 'pr-status-approved' },
            rejected: { label: 'Rejected', cls: 'pr-status-rejected' },
            flagged:  { label: 'Flagged',  cls: 'pr-status-flagged'  },
            removed:  { label: 'Removed',  cls: 'pr-status-removed'  },
        };
        const sc = statusConfig[r.status] || { label: r.status, cls: '' };

        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += `<span class="pr-star ${i <= r.rating ? 'pr-star-filled' : 'pr-star-empty'}">★</span>`;
        }

        const reviewTextBlock = r.review_text
            ? `<div class="pr-review-text">${escHtmlNl(r.review_text)}</div>` : '';

        const notesBlock = r.moderation_notes
            ? `<div class="pr-moderation-note">
                   <span class="pr-moderation-note-label">Admin note:</span>
                   ${escHtml(r.moderation_notes)}
               </div>` : '';

        // Edit disabled ak je user blokovaný
        const editDisabled  = isReviewBlocked ? ' disabled title="You are blocked from editing reviews."' : '';
        const editExtraClass = isReviewBlocked ? ' pr-btn-blocked' : '';

        // Delete disabled pre removed status
        const deleteDisabled = r.status === 'removed' ? ' disabled' : '';
        const chatbotLogo = escHtml(logoSrc(r.chatbot_logo_url));
        const chatbotName = escHtml(r.chatbot_name);
        const chatbotReviewsUrl = r.chatbot_id
            ? escHtml(`chatbot-reviews?chatbot=${encodeURIComponent(String(r.chatbot_id))}`)
            : 'chatbots';

        return `
        <div class="pr-card" data-id="${escHtml(r.id)}">
            <div class="pr-card-header">
                <div class="pr-card-header-left">
                    <a class="pr-chatbot-name" href="${chatbotReviewsUrl}">
                        <span class="pr-chatbot-logo" aria-hidden="true">
                            <img src="${chatbotLogo}" alt="" loading="lazy" data-fallback-src="img/ChatbotScout_logo.png">
                        </span>
                        <span class="pr-chatbot-name-text">${chatbotName}</span>
                    </a>
                    <div class="pr-stars-row">
                        ${stars}
                        <span class="pr-rating-num">${r.rating}/5</span>
                    </div>
                </div>
                <div class="pr-card-header-right">
                    <span class="pr-status-badge ${sc.cls}">${sc.label}</span>
                    <span class="pr-date">${escHtml(r.created_at_formatted)}</span>
                </div>
            </div>

            ${reviewTextBlock}

            ${notesBlock}

            <div class="pr-card-actions">
                <button class="pr-edit-btn${editExtraClass}"${editDisabled} data-review-id="${escHtml(r.id)}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/>
                    </svg>
                    Edit
                </button>
                <button class="pr-delete-btn"${deleteDisabled} data-review-id="${escHtml(r.id)}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                        <path d="M10 11v6"/><path d="M14 11v6"/>
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                    Delete
                </button>
            </div>
        </div>`;
    }

    // ============================================
    // EDIT MODAL
    // ============================================

    window.openEditReviewModal = function (reviewId, btn) {
        // Ak je user blokovaný, neotvori modal
        if (isReviewBlocked) return;

        const card = btn.closest('.pr-card');
        if (!card) return;

        editingReviewId = reviewId;

        // Prečítaj dáta z karty
        const chatbotName = card.querySelector('.pr-chatbot-name-text')?.textContent.trim()
            || card.querySelector('.pr-chatbot-name').textContent.trim();
        const ratingNum   = parseInt(card.querySelector('.pr-rating-num').textContent);
        const textEl      = card.querySelector('.pr-review-text');

        editModalTitle.textContent = `Edit review — ${chatbotName}`;
        editTextInput.value        = textEl  ? textEl.innerText : '';

        clearEditFeedback();
        if (editSubmitBtn) { editSubmitBtn.disabled = false; editSubmitBtn.textContent = 'Save Changes'; }

        // Nastav hviezdy
        currentRating = ratingNum || 0;
        updatePickerStars(currentRating);

        editOverlay.classList.add('active');
        lockScroll();
    };

    function closeEditModal() {
        editOverlay.classList.remove('active');
        unlockScroll();
        editingReviewId = null;
    }

    if (editCloseBtn)  editCloseBtn.addEventListener('click', closeEditModal);
    if (editCancelBtn) editCancelBtn.addEventListener('click', closeEditModal);

    // Stars picker
    if (editStarsPicker) {
        editStarsPicker.querySelectorAll('.pr-modal-star').forEach(star => {
            star.addEventListener('click', () => {
                currentRating = parseInt(star.dataset.value);
                updatePickerStars(currentRating);
            });
            star.addEventListener('mouseenter', () => updatePickerStars(parseInt(star.dataset.value)));
        });
        editStarsPicker.addEventListener('mouseleave', () => updatePickerStars(currentRating));
    }

    function updatePickerStars(value) {
        if (!editStarsPicker) return;
        editStarsPicker.querySelectorAll('.pr-modal-star').forEach(s => {
            const v = parseInt(s.dataset.value);
            s.classList.toggle('active', v <= value);
        });
        if (editStarsLabel) {
            editStarsLabel.textContent = starLabels[value] || 'Select a rating';
        }
    }

    // Submit edit
    if (editSubmitBtn) {
        editSubmitBtn.addEventListener('click', async () => {
            if (!editingReviewId) return;

            clearEditFeedback();

            if (!currentRating) {
                showEditError('Please select a rating.');
                return;
            }
            editSubmitBtn.disabled    = true;
            editSubmitBtn.textContent = 'Saving...';

            try {
                const res  = await fetch('api/updateReview.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action:      'edit',
                        id:          editingReviewId,
                        rating:      currentRating,
                        review_text: editTextInput.value.trim(),
                    }),
                });
                const data = await res.json();

                if (data.success) {
                    showEditSuccess(data.message);
                    editSubmitBtn.textContent   = 'Saved!';

                    updateCardAfterEdit(editingReviewId, {
                        rating:      currentRating,
                        review_text: editTextInput.value.trim(),
                        status:      'pending',
                    });

                    setTimeout(closeEditModal, 1500);
                } else {
                    showEditError(data.message || 'Something went wrong.');
                    editSubmitBtn.disabled    = false;
                    editSubmitBtn.textContent = 'Save Changes';
                }
            } catch (err) {
                showEditError('Something went wrong.');
                editSubmitBtn.disabled    = false;
                editSubmitBtn.textContent = 'Save Changes';
            }
        });
    }

    function updateCardAfterEdit(id, updates) {
        const card = reviewsContainer.querySelector(`.pr-card[data-id="${id}"]`);
        if (!card) return;

        // Stars
        const starEls = card.querySelectorAll('.pr-star');
        starEls.forEach((s, i) => {
            s.classList.toggle('pr-star-filled', i < updates.rating);
            s.classList.toggle('pr-star-empty',  i >= updates.rating);
        });
        const ratingNum = card.querySelector('.pr-rating-num');
        if (ratingNum) ratingNum.textContent = `${updates.rating}/5`;

        // Text
        let textEl = card.querySelector('.pr-review-text');
        if (updates.review_text) {
            if (!textEl) {
                textEl = document.createElement('div');
                textEl.className = 'pr-review-text';
                card.querySelector('.pr-card-header').after(textEl);
            }
            textEl.innerHTML = escHtmlNl(updates.review_text);
        } else if (textEl) {
            textEl.remove();
        }

        // Status badge → pending
        const badge = card.querySelector('.pr-status-badge');
        if (badge) {
            badge.className   = 'pr-status-badge pr-status-pending';
            badge.textContent = 'Pending';
        }

        // Odstrán moderation note (admin musí znova moderovať)
        const noteEl = card.querySelector('.pr-moderation-note');
        if (noteEl) noteEl.remove();
    }

    // ============================================
    // DELETE MODAL
    // ============================================

    window.openDeleteReviewModal = function (reviewId) {
        if (!deleteOverlay) return;
        pendingDeleteId = reviewId;
        deleteOverlay.classList.add('active');
        lockScroll();
    };

    function closeDeleteModal() {
        if (!deleteOverlay) return;
        deleteOverlay.classList.remove('active');
        unlockScroll();
        pendingDeleteId = null;
        if (deleteConfirmBtn) {
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.textContent = 'Yes, delete';
        }
    }

    if (deleteCloseBtn)  deleteCloseBtn.addEventListener('click', closeDeleteModal);
    if (deleteCancelBtn) deleteCancelBtn.addEventListener('click', closeDeleteModal);

    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', async () => {
            if (!pendingDeleteId) return;

            const reviewId = pendingDeleteId;
            deleteConfirmBtn.disabled    = true;
            deleteConfirmBtn.textContent = 'Deleting...';

            try {
                const res  = await fetch('api/updateReview.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: reviewId }),
                });
                const data = await res.json();

                if (data.success) {
                    const card = reviewsContainer.querySelector(`.pr-card[data-id="${reviewId}"]`);
                    closeDeleteModal();
                    showNotification('Review deleted.', 'success');

                    if (card) {
                        card.classList.add('is-removing');
                        setTimeout(() => {
                            card.remove();
                            reviewState.offset = Math.max(0, reviewState.offset - 1);
                            reviewState.total = Math.max(0, reviewState.total - 1);

                            if (!reviewsContainer.querySelector('.pr-card')) {
                                if (reviewState.hasMore) {
                                    loadReviews();
                                } else {
                                    showEmpty();
                                }
                            } else {
                                updateLoadMore();
                            }
                        }, 300);
                    }
                } else {
                    deleteConfirmBtn.disabled    = false;
                    deleteConfirmBtn.textContent = 'Yes, delete';
                    showNotification(data.message || 'Failed to delete review.', 'error');
                }
            } catch (err) {
                deleteConfirmBtn.disabled    = false;
                deleteConfirmBtn.textContent = 'Yes, delete';
                showNotification('Failed to delete review. Please try again.', 'error');
            }
        });
    }

    if (reviewsContainer) {
        reviewsContainer.addEventListener('click', (event) => {
            const editBtn = event.target.closest('.pr-edit-btn');
            if (editBtn && reviewsContainer.contains(editBtn)) {
                if (editBtn.disabled) return;
                window.openEditReviewModal(editBtn.dataset.reviewId, editBtn);
                return;
            }

            const deleteBtn = event.target.closest('.pr-delete-btn');
            if (deleteBtn && reviewsContainer.contains(deleteBtn)) {
                if (deleteBtn.disabled) return;
                window.openDeleteReviewModal(deleteBtn.dataset.reviewId);
            }
        });
    }

    // ============================================
    // HELPERS
    // ============================================

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escHtmlNl(str) {
        return escHtml(str).replace(/\n/g, '<br>');
    }

    // ============================================
    // INIT — načítaj keď sa otvorí tab
    // ============================================

    const reviewsTabBtn = document.querySelector('.tab-btn[data-tab="reviews"]');
    if (reviewsTabBtn) {
        reviewsTabBtn.addEventListener('click', () => {
            if (!reviewsContainer.dataset.loaded) {
                reviewsContainer.dataset.loaded = '1';
                loadReviews(true);
            }
        });
    }

    if (reviewsLoadMore) {
        reviewsLoadMore.addEventListener('click', () => loadReviews());
    }

    if (document.getElementById('reviews-tab')?.classList.contains('active')) {
        reviewsContainer.dataset.loaded = '1';
        loadReviews(true);
    }

})();
