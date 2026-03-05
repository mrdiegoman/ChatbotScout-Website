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
// UPDATE STATUS
// ============================================

async function updateStatus(id, status, clickedBtn) {
    try {
        const response = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', id, status })
        });

        const result = await response.json();

        if (result.success) {
            const card = clickedBtn.closest('.admin-message-card');
            card.querySelectorAll('.admin-status-btn').forEach(b => b.classList.remove('active'));
            clickedBtn.classList.add('active');

            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className  = `admin-status-badge status-${status}`;
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }

            showNotification(`Status updated to "${status}"`, 'success');
        } else {
            showNotification(result.message || 'Failed to update status', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong. Please try again.', 'error');
        console.error(err);
    }
}

// ============================================
// SEND REPLY (with email)
// ============================================

async function sendReply(id, btn) {
    const section  = document.getElementById(`reply-${id}`);
    const textarea = section.querySelector('textarea');
    const response = textarea.value.trim();

    // Empty guard — do not send
    if (!response) {
        showNotification('Reply is empty — nothing was sent.', 'error');
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Sending...';

    try {
        const res = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_reply', id, response })
        });

        const result = await res.json();

        if (result.success) {
            showNotification('Reply sent successfully!', 'success');

            // Update status badge to "replied"
            const card  = btn.closest('.admin-message-card');
            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = 'admin-status-badge status-replied';
                badge.textContent = 'Replied';
            }

            // Mark replied button as active
            card.querySelectorAll('.admin-status-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.status === 'replied');
            });

            // Update or insert existing response block
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
        showNotification('Something went wrong. Please try again.', 'error');
        console.error(err);
    } finally {
        btn.disabled    = false;
        btn.textContent = '📨 Send Reply';
    }
}

// ============================================
// SAVE INTERNAL NOTES
// ============================================

async function saveNotes(id, btn) {
    const section  = document.getElementById(`notes-${id}`);
    const textarea = section.querySelector('textarea');
    const notes    = textarea.value.trim();

    btn.disabled    = true;
    btn.textContent = 'Saving...';

    try {
        const res = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_notes', id, notes })
        });

        const result = await res.json();

        if (result.success) {
            showNotification('Notes saved!', 'success');
        } else {
            showNotification(result.message || 'Failed to save notes', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong. Please try again.', 'error');
        console.error(err);
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Notes';
    }
}

// ============================================
// TOGGLE HELPFUL
// ============================================

async function toggleHelpful(id, btn) {
    btn.disabled = true;

    try {
        const res = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_helpful', id })
        });

        const result = await res.json();

        if (result.success) {
            const card     = btn.closest('.admin-message-card');
            const isHelpful = result.helpful;

            // Toggle button style
            btn.classList.toggle('active', isHelpful);

            // Toggle card highlight
            card.classList.toggle('is-helpful', isHelpful);

            // Toggle badge in header
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

            showNotification(
                isHelpful ? 'Marked as helpful ⭐' : 'Helpful mark removed',
                'success'
            );
        } else {
            showNotification(result.message || 'Failed to update', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong. Please try again.', 'error');
        console.error(err);
    } finally {
        btn.disabled = false;
    }
}