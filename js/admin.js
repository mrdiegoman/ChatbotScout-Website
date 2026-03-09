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
// MAIN TAB SWITCHING (Messages / Reviews / Settings)
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

        // Lazy load settings pri prvom otvorení
        if (tab === 'settings') {
            const settingsTab = document.getElementById('settings-tab');
            if (!settingsTab.dataset.loaded) {
                settingsTab.dataset.loaded = '1';
                loadAdminSettings();
            }
        }
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
    try {
        const res    = await fetch('../app/adminContactActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_helpful', id })
        });
        const result = await res.json();

        if (result.success) {
            btn.classList.toggle('active', result.helpful);
            showNotification(result.helpful ? 'Marked as helpful' : 'Removed helpful mark', 'success');
        } else {
            showNotification(result.message || 'Failed', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
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
            const card = clickedBtn.closest('.admin-review-card');
            card.querySelectorAll('.review-status-action-btn').forEach(b => b.classList.remove('active'));
            clickedBtn.classList.add('active');

            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = `admin-status-badge review-status-${status}`;
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
            showNotification(`Review ${status}`, 'success');
        } else {
            showNotification(result.message || 'Failed to update status', 'error');
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

        showNotification(result.success ? 'Notes saved!' : (result.message || 'Failed'), result.success ? 'success' : 'error');
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Notes';
    }
}

// ============================================
// SETTINGS — LOAD
// ============================================

async function loadAdminSettings() {
    const form       = document.getElementById('adminSettingsForm');
    const loadingEl  = document.getElementById('settingsLoading');
    const contentEl  = document.getElementById('settingsContent');

    if (loadingEl) loadingEl.style.display = 'flex';
    if (contentEl) contentEl.style.display = 'none';

    try {
        const res  = await fetch('../app/adminSettingsActions.php', { method: 'GET' });
        const data = await res.json();

        if (!data.success) {
            showNotification('Failed to load settings', 'error');
            return;
        }

        const s = data.settings;

        const emailInput = document.getElementById('settingReplyEmail');
        const nameInput  = document.getElementById('settingReplyName');
        const updatedEl  = document.getElementById('settingsLastUpdated');

        if (emailInput) emailInput.value = s['reply_from_email']?.value ?? '';
        if (nameInput)  nameInput.value  = s['reply_from_name']?.value  ?? '';

        // Zobraz kedy boli naposledy zmenené
        const updatedAt = s['reply_from_email']?.updated_at || s['reply_from_name']?.updated_at;
        if (updatedEl) {
            updatedEl.textContent = updatedAt ? `Last updated: ${updatedAt}` : '';
        }

        if (contentEl) contentEl.style.display = 'block';

    } catch (err) {
        showNotification('Failed to load settings', 'error');
    } finally {
        if (loadingEl) loadingEl.style.display = 'none';
    }
}

// ============================================
// SETTINGS — SAVE
// ============================================

async function saveAdminSettings(btn) {
    const emailInput = document.getElementById('settingReplyEmail');
    const nameInput  = document.getElementById('settingReplyName');

    const replyEmail = emailInput?.value.trim() ?? '';
    const replyName  = nameInput?.value.trim()  ?? '';

    if (!replyEmail) {
        showNotification('Reply-from email cannot be empty', 'error');
        emailInput?.focus();
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Saving...';

    try {
        const res  = await fetch('../app/adminSettingsActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                reply_from_email: replyEmail,
                reply_from_name:  replyName,
            }),
        });
        const data = await res.json();

        if (data.success) {
            showNotification('Settings saved!', 'success');

            // Aktualizuj "Last updated" na aktuálny čas
            const updatedEl = document.getElementById('settingsLastUpdated');
            if (updatedEl) {
                const now = new Date();
                const formatted = now.toLocaleDateString('en-GB', {
                    day: '2-digit', month: 'short', year: 'numeric',
                }) + ', ' + now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                updatedEl.textContent = `Last updated: ${formatted}`;
            }
        } else {
            showNotification(data.message || 'Failed to save settings', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Settings';
    }
}

// ============================================
// INIT — ak je settings tab aktívny pri načítaní
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    const settingsTab = document.getElementById('settings-tab');
    if (settingsTab?.classList.contains('active')) {
        settingsTab.dataset.loaded = '1';
        loadAdminSettings();
    }
});

// ============================================
// SUBMISSIONS — MARK REVIEWED (toggle, no email)
// ============================================

async function approveSubmission(id, clickedBtn) {
    clickedBtn.disabled = true;

    const card      = clickedBtn.closest('.admin-message-card');
    const isActive  = clickedBtn.classList.contains('active');
    const newStatus = isActive ? 'pending' : 'reviewed';

    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'approve', id, status: newStatus })
        });
        const result = await res.json();

        if (result.success) {
            // Toggle active state on button
            clickedBtn.classList.toggle('active', newStatus === 'reviewed');

            // Remove active from Reject button
            card.querySelectorAll('.submission-action-btn').forEach(b => {
                if (b !== clickedBtn) b.classList.remove('active');
            });

            // Update badge
            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = `admin-status-badge submission-status-${newStatus}`;
                badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }

            showNotification(
                newStatus === 'reviewed' ? 'Marked as reviewed' : 'Moved back to pending',
                'success'
            );

            // Update pending count badge in tab
            if (result.pendingCount !== undefined) {
                updateMainTabBadge('submissions', result.pendingCount);
            }
        } else {
            showNotification(result.message || 'Failed to update status', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        clickedBtn.disabled = false;
    }
}

// ============================================
// SUBMISSIONS — REJECT (sends email)
// ============================================

async function rejectSubmission(id, btn) {
    const section  = document.getElementById(`sub-reject-${id}`);
    const textarea = section.querySelector('textarea');
    const reason   = textarea.value.trim();

    if (!reason) {
        showNotification('Please enter a rejection reason.', 'error');
        return;
    }

    btn.disabled    = true;
    btn.textContent = 'Sending...';

    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'reject', id, reason })
        });
        const result = await res.json();

        if (result.success) {
            const card = btn.closest('.admin-message-card');

            // Update badge
            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = 'admin-status-badge submission-status-rejected';
                badge.textContent = 'Rejected';
            }

            // Update action buttons
            card.querySelectorAll('.submission-action-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.status === 'rejected');
            });

            // Close reject section
            section.style.display = 'none';
            textarea.value = '';

            showNotification('Rejection sent successfully.', 'success');

            if (result.pendingCount !== undefined) {
                updateMainTabBadge('submissions', result.pendingCount);
            }
        } else {
            showNotification(result.message || 'Failed to reject submission', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Send Rejection';
    }
}

// ============================================
// SUBMISSIONS — SAVE NOTES
// ============================================

async function saveSubmissionNotes(id, btn) {
    const section  = document.getElementById(`sub-notes-${id}`);
    const textarea = section.querySelector('textarea');
    const notes    = textarea.value.trim();

    btn.disabled    = true;
    btn.textContent = 'Saving...';

    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'save_notes', id, notes })
        });
        const result = await res.json();

        showNotification(
            result.success ? 'Notes saved!' : (result.message || 'Failed'),
            result.success ? 'success' : 'error'
        );
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Notes';
    }
}