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

    // Edit modal
    const editOverlay      = document.getElementById('editReviewOverlay');
    const editModalTitle   = document.getElementById('editReviewModalTitle');
    const editStarsPicker  = document.getElementById('editRatingStarsPicker');
    const editStarsLabel   = document.getElementById('editRatingStarsLabel');
    const editTitleInput   = document.getElementById('editReviewTitleInput');
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

    // ============================================
    // LOAD REVIEWS
    // ============================================

    function loadReviews() {
        setLoading(true);

        fetch('api/getUserReviews.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showEmpty();
                    return;
                }

                // Ulož blocked flag
                isReviewBlocked = !!data.is_review_blocked;

                if (data.reviews.length === 0) {
                    showEmpty();
                    return;
                }
                renderReviews(data.reviews);
            })
            .catch(() => showEmpty())
            .finally(() => setLoading(false));
    }

    function setLoading(state) {
        if (reviewsLoading) reviewsLoading.style.display = state ? 'flex' : 'none';
    }

    function showEmpty() {
        if (reviewsEmpty)     reviewsEmpty.style.display     = 'block';
        if (reviewsContainer) reviewsContainer.innerHTML     = '';
    }

    // ============================================
    // RENDER
    // ============================================

    function renderReviews(reviews) {
        if (reviewsEmpty) reviewsEmpty.style.display = 'none';
        reviewsContainer.innerHTML = reviews.map(buildReviewCard).join('');
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

        const titleBlock = r.title
            ? `<div class="pr-review-title">"${escHtml(r.title)}"</div>` : '';

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

        return `
        <div class="pr-card" data-id="${escHtml(r.id)}">
            <div class="pr-card-header">
                <div class="pr-card-header-left">
                    <span class="pr-chatbot-name">🤖 ${escHtml(r.chatbot_name)}</span>
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

            ${titleBlock}

            <div class="pr-review-text">${escHtmlNl(r.review_text)}</div>

            ${notesBlock}

            <div class="pr-card-actions">
                <button class="pr-edit-btn${editExtraClass}"${editDisabled} onclick="openEditReviewModal('${escHtml(r.id)}', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/>
                    </svg>
                    Edit
                </button>
                <button class="pr-delete-btn"${deleteDisabled} onclick="openDeleteReviewModal('${escHtml(r.id)}')">
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
        const chatbotName = card.querySelector('.pr-chatbot-name').textContent.replace('🤖 ', '').trim();
        const ratingNum   = parseInt(card.querySelector('.pr-rating-num').textContent);
        const titleEl     = card.querySelector('.pr-review-title');
        const textEl      = card.querySelector('.pr-review-text');

        editModalTitle.textContent = `Edit review — ${chatbotName}`;
        editTitleInput.value       = titleEl ? titleEl.textContent.replace(/^"|"$/g, '') : '';
        editTextInput.value        = textEl  ? textEl.innerText : '';

        // Reset error/success
        if (editErrorEl)   { editErrorEl.textContent   = ''; editErrorEl.style.display   = 'none'; }
        if (editSuccessEl) { editSuccessEl.textContent = ''; editSuccessEl.style.display = 'none'; }
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
            s.style.color = v <= value ? '#f59e0b' : '#d1d5db';
        });
        if (editStarsLabel) {
            editStarsLabel.textContent = starLabels[value] || 'Select a rating';
        }
    }

    // Submit edit
    if (editSubmitBtn) {
        editSubmitBtn.addEventListener('click', async () => {
            if (!editingReviewId) return;

            if (editErrorEl) { editErrorEl.textContent = ''; editErrorEl.style.display = 'none'; }

            if (!currentRating) {
                editErrorEl.textContent   = 'Please select a rating.';
                editErrorEl.style.display = 'block';
                return;
            }
            if (!editTextInput.value.trim()) {
                editErrorEl.textContent   = 'Review text cannot be empty.';
                editErrorEl.style.display = 'block';
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
                        title:       editTitleInput.value.trim(),
                        review_text: editTextInput.value.trim(),
                    }),
                });
                const data = await res.json();

                if (data.success) {
                    editSuccessEl.textContent   = data.message;
                    editSuccessEl.style.display = 'block';
                    editSubmitBtn.textContent   = 'Saved!';

                    updateCardAfterEdit(editingReviewId, {
                        rating:      currentRating,
                        title:       editTitleInput.value.trim(),
                        review_text: editTextInput.value.trim(),
                        status:      'pending',
                    });

                    setTimeout(closeEditModal, 1500);
                } else {
                    editErrorEl.textContent   = data.message || 'Something went wrong.';
                    editErrorEl.style.display = 'block';
                    editSubmitBtn.disabled    = false;
                    editSubmitBtn.textContent = 'Save Changes';
                }
            } catch (err) {
                editErrorEl.textContent   = 'Something went wrong.';
                editErrorEl.style.display = 'block';
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

        // Title
        let titleEl = card.querySelector('.pr-review-title');
        if (updates.title) {
            if (!titleEl) {
                titleEl = document.createElement('div');
                titleEl.className = 'pr-review-title';
                card.querySelector('.pr-card-header').after(titleEl);
            }
            titleEl.textContent = `"${updates.title}"`;
        } else if (titleEl) {
            titleEl.remove();
        }

        // Text
        const textEl = card.querySelector('.pr-review-text');
        if (textEl) textEl.innerHTML = escHtmlNl(updates.review_text);

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
        pendingDeleteId = reviewId;
        deleteOverlay.classList.add('active');
        lockScroll();
    };

    function closeDeleteModal() {
        deleteOverlay.classList.remove('active');
        unlockScroll();
        pendingDeleteId = null;
    }

    if (deleteCloseBtn)  deleteCloseBtn.addEventListener('click', closeDeleteModal);
    if (deleteCancelBtn) deleteCancelBtn.addEventListener('click', closeDeleteModal);

    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', async () => {
            if (!pendingDeleteId) return;

            deleteConfirmBtn.disabled    = true;
            deleteConfirmBtn.textContent = 'Deleting...';

            try {
                const res  = await fetch('api/updateReview.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: pendingDeleteId }),
                });
                const data = await res.json();

                if (data.success) {
                    const card = reviewsContainer.querySelector(`.pr-card[data-id="${pendingDeleteId}"]`);
                    if (card) {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity    = '0';
                        card.style.transform  = 'translateY(-8px)';
                        setTimeout(() => {
                            card.remove();
                            if (!reviewsContainer.querySelector('.pr-card')) showEmpty();
                        }, 300);
                    }
                    closeDeleteModal();
                } else {
                    deleteConfirmBtn.disabled    = false;
                    deleteConfirmBtn.textContent = 'Yes, delete';
                    alert(data.message || 'Failed to delete review');
                }
            } catch (err) {
                deleteConfirmBtn.disabled    = false;
                deleteConfirmBtn.textContent = 'Yes, delete';
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
                loadReviews();
            }
        });
    }

    if (document.getElementById('reviews-tab')?.classList.contains('active')) {
        reviewsContainer.dataset.loaded = '1';
        loadReviews();
    }

})();
