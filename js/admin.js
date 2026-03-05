// ============================================
// NOTIFICATION SYSTEM
// ============================================

function showNotification(message, type = 'info') {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

const notifStyles = document.createElement('style');
notifStyles.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        opacity: 0;
        transform: translateX(100px);
        transition: all 0.3s ease;
        max-width: 400px;
    }
    .notification.show { opacity: 1; transform: translateX(0); }
    .notification-success { border-left: 4px solid #4CAF50; color: #2E7D32; }
    .notification-error   { border-left: 4px solid #EF233C; color: #C62828; }
    .notification-info    { border-left: 4px solid #2196F3; color: #1976D2; }
`;
document.head.appendChild(notifStyles);

// ============================================
// MAIN TAB SWITCHING (Messages / Reviews)
// ============================================

document.querySelectorAll('.admin-main-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;

        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        // Clear sub-filters when switching tabs
        url.searchParams.delete('status');
        url.searchParams.delete('review_status');
        url.searchParams.delete('helpful');
        window.history.pushState({}, '', url);

        // Update button states
        document.querySelectorAll('.admin-main-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Update pane visibility
        document.querySelectorAll('.admin-tab-pane').forEach(p => p.classList.remove('active'));
        document.getElementById(tab + '-tab').classList.add('active');
    });
});

// ============================================
// TOGGLE REPLY / NOTES SECTIONS
// ============================================

function toggleSection(btn, sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    const isVisible = section.style.display !== 'none';
    section.style.display = isVisible ? 'none' : 'block';
    btn.classList.toggle('active', !isVisible);
}

// ============================================
// MESSAGES — UPDATE STATUS
// ============================================

async function updateStatus(id, status, clickedBtn) {
    try {
        const res    = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', id, status })
        });
        const result = await res.json();

        if (result.success) {
            const card = clickedBtn.closest('.admin-message-card');
            card.querySelectorAll('.admin-status-btn').forEach(b => b.classList.remove('active'));
            clickedBtn.classList.add('active');

            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = `admin-status-badge status-${status}`;
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
            showNotification(`Status updated to "${status}"`, 'success');
        } else {
            showNotification(result.message || 'Failed to update status', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    }
}

// ============================================
// MESSAGES — SEND REPLY
// ============================================

async function sendReply(id, btn) {
    const section  = document.getElementById(`reply-${id}`);
    const textarea = section.querySelector('textarea');
    const response = textarea.value.trim();

    if (!response) {
        showNotification('Reply is empty — nothing was sent.', 'error');
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Sending...';

    try {
        const res    = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_reply', id, response })
        });
        const result = await res.json();

        if (result.success) {
            showNotification('Reply sent successfully!', 'success');

            const card  = btn.closest('.admin-message-card');
            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = 'admin-status-badge status-replied';
                badge.textContent = 'Replied';
            }
            card.querySelectorAll('.admin-status-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.status === 'replied');
            });

            let responseBlock = card.querySelector('.admin-existing-response');
            if (!responseBlock) {
                responseBlock = document.createElement('div');
                responseBlock.className = 'admin-existing-response';
                card.querySelector('.admin-message-body').after(responseBlock);
            }
            responseBlock.innerHTML = `<strong>Your response:</strong><p>${response.replace(/\n/g, '<br>')}</p>`;
        } else {
            showNotification(result.message || 'Failed to send reply', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = '📨 Send Reply';
    }
}

// ============================================
// MESSAGES — SAVE NOTES
// ============================================

async function saveNotes(id, btn) {
    const section  = document.getElementById(`notes-${id}`);
    const textarea = section.querySelector('textarea');
    const notes    = textarea.value.trim();

    btn.disabled    = true;
    btn.textContent = 'Saving...';

    try {
        const res    = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_notes', id, notes })
        });
        const result = await res.json();

        showNotification(result.success ? 'Notes saved!' : (result.message || 'Failed'), result.success ? 'success' : 'error');
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Notes';
    }
}

// ============================================
// MESSAGES — TOGGLE HELPFUL
// ============================================

async function toggleHelpful(id, btn) {
    btn.disabled = true;

    try {
        const res    = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_helpful', id })
        });
        const result = await res.json();

        if (result.success) {
            const card      = btn.closest('.admin-message-card');
            const isHelpful = result.helpful;

            btn.classList.toggle('active', isHelpful);
            card.classList.toggle('is-helpful', isHelpful);

            const senderRow = card.querySelector('.admin-message-sender-row');
            let badge = senderRow.querySelector('.admin-helpful-badge');
            if (isHelpful) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className   = 'admin-helpful-badge';
                    badge.textContent = '⭐ Helpful';
                    senderRow.appendChild(badge);
                }
            } else {
                if (badge) badge.remove();
            }

            showNotification(isHelpful ? 'Marked as helpful ⭐' : 'Helpful mark removed', 'success');
        } else {
            showNotification(result.message || 'Failed to update', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled = false;
    }
}

// ============================================
// REVIEWS — UPDATE STATUS
// ============================================

async function updateReviewStatus(id, status, clickedBtn) {
    try {
        const res    = await fetch('../app/adminReviewActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', id, status })
        });
        const result = await res.json();

        if (result.success) {
            const card = clickedBtn.closest('.admin-message-card');
            card.querySelectorAll('.review-status-action-btn').forEach(b => b.classList.remove('active'));
            clickedBtn.classList.add('active');

            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = `admin-status-badge review-status-${status}`;
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
            showNotification(`Review ${status}`, 'success');
        } else {
            showNotification(result.message || 'Failed to update review', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    }
}

// ============================================
// REVIEWS — SAVE MODERATION NOTES
// ============================================

async function saveReviewNotes(id, btn) {
    const section  = document.getElementById(`review-notes-${id}`);
    const textarea = section.querySelector('textarea');
    const notes    = textarea.value.trim();

    btn.disabled    = true;
    btn.textContent = 'Saving...';

    try {
        const res    = await fetch('../app/adminReviewActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_notes', id, notes })
        });
        const result = await res.json();

        if (result.success) {
            // Update or insert moderation notes block
            const card = btn.closest('.admin-message-card');
            let notesBlock = card.querySelector('.admin-existing-response');
            if (notes) {
                if (!notesBlock) {
                    notesBlock = document.createElement('div');
                    notesBlock.className = 'admin-existing-response';
                    card.querySelector('.admin-message-body').after(notesBlock);
                }
                notesBlock.innerHTML = `<strong>Moderation notes:</strong><p>${notes.replace(/\n/g, '<br>')}</p>`;
            } else if (notesBlock) {
                notesBlock.remove();
            }
            showNotification('Notes saved!', 'success');
        } else {
            showNotification(result.message || 'Failed to save notes', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Notes';
    }
}