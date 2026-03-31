// ============================================
// UTILS
// ============================================

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function escapeAttr(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

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
        font-family: outfit-semibold, sans-serif;
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
            responseBlock.innerHTML = `<strong>Your response:</strong><p>${escHtml(response).replace(/\n/g, '<br>')}</p>`;
        } else {
            showNotification(result.message || 'Failed to send reply', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Send Reply';
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
    const loadingEl = document.getElementById('settingsLoading');
    const contentEl = document.getElementById('settingsContent');
 
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
 
        const replyEmailInput  = document.getElementById('settingReplyEmail');
        const replyNameInput   = document.getElementById('settingReplyName');
        const noreplyInput     = document.getElementById('settingNoreplyEmail');
        const noreplyNameInput = document.getElementById('settingNoreplyName');
        const updatedEl        = document.getElementById('settingsLastUpdated');

        if (replyEmailInput)  replyEmailInput.value  = s['reply_from_email']?.value ?? '';
        if (replyNameInput)   replyNameInput.value   = s['reply_from_name']?.value  ?? '';
        if (noreplyInput)     noreplyInput.value     = s['noreply_email']?.value     ?? '';
        if (noreplyNameInput) noreplyNameInput.value = s['noreply_name']?.value      ?? '';

        const updatedAt = s['reply_from_email']?.updated_at || s['noreply_email']?.updated_at;

        if (updatedEl) {
            updatedEl.textContent = updatedAt ? `Last updated: ${updatedAt}` : '';
        }

        if (contentEl) contentEl.style.display = 'block';

        loadNotificationEmails();
 
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
    const replyEmailInput  = document.getElementById('settingReplyEmail');
    const replyNameInput   = document.getElementById('settingReplyName');
    const noreplyInput     = document.getElementById('settingNoreplyEmail');
    const noreplyNameInput = document.getElementById('settingNoreplyName');

    const replyEmail   = replyEmailInput?.value.trim()  ?? '';
    const replyName    = replyNameInput?.value.trim()   ?? '';
    const noreplyEmail = noreplyInput?.value.trim()     ?? '';
    const noreplyName  = noreplyNameInput?.value.trim() ?? '';

    if (!replyEmail) {
        showNotification('Reply-from email cannot be empty', 'error');
        replyEmailInput?.focus();
        return;
    }
    if (!noreplyEmail) {
        showNotification('No-reply email cannot be empty', 'error');
        noreplyInput?.focus();
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
                noreply_email:    noreplyEmail,
                noreply_name:     noreplyName,
            }),
        });
        const data = await res.json();
 
        if (data.success) {
            showNotification('Settings saved!', 'success');
 
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
// NOTIFICATION EMAILS — LOAD
// ============================================

let notifEmails = [];

async function loadNotificationEmails() {
    try {
        const res  = await fetch('../api/getAdminNotificationEmails.php', { method: 'GET' });
        const data = await res.json();

        if (!data.success) return;

        notifEmails = data.emails;
        updateNotifEmailCount();
        renderNotifEmailsList();

    } catch (err) {
        // non-critical, silently fail
    }
}

function updateNotifEmailCount() {
    const countEl = document.getElementById('notifEmailCount');
    if (countEl) countEl.textContent = notifEmails.length;
}

function renderNotifEmailsList() {
    const listEl  = document.getElementById('notifEmailsList');
    const emptyEl = document.getElementById('notifEmailsEmpty');
    if (!listEl) return;

    listEl.innerHTML = '';

    if (notifEmails.length === 0) {
        if (emptyEl) emptyEl.style.display = 'block';
        return;
    }

    if (emptyEl) emptyEl.style.display = 'none';

    notifEmails.forEach(item => {
        const div = document.createElement('div');
        div.className = 'admin-notif-email-item';
        div.dataset.id = item.id;
        div.innerHTML = `
            <span>${escapeHtml(item.email)}</span>
            <button class="admin-notif-delete-btn" onclick="deleteNotificationEmail('${escapeAttr(item.id)}')" title="Remove">&#x2715;</button>
        `;
        listEl.appendChild(div);
    });
}

// ============================================
// NOTIFICATION EMAILS — ADD
// ============================================

async function addNotificationEmail() {
    const input = document.getElementById('settingAdminNotificationEmail');
    const email = input?.value.trim() ?? '';

    if (!email) {
        showNotification('Enter an email address first', 'error');
        input?.focus();
        return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showNotification('Invalid email address', 'error');
        input?.focus();
        return;
    }

    const addBtn = document.querySelector('.admin-notif-add-btn');
    if (addBtn) { addBtn.disabled = true; addBtn.textContent = '...'; }

    try {
        const res  = await fetch('../api/addAdminNotificationEmail.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ email }),
        });
        const data = await res.json();

        if (data.success) {
            notifEmails.push(data.email);
            updateNotifEmailCount();
            renderNotifEmailsList();
            if (input) input.value = '';
            showNotification('Email added', 'success');
        } else {
            showNotification(data.message || 'Failed to add email', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        if (addBtn) { addBtn.disabled = false; addBtn.textContent = '+'; }
    }
}

// ============================================
// NOTIFICATION EMAILS — DELETE
// ============================================

async function deleteNotificationEmail(id) {
    try {
        const res  = await fetch('../api/deleteAdminNotificationEmail.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id }),
        });
        const data = await res.json();

        if (data.success) {
            notifEmails = notifEmails.filter(e => e.id !== id);
            updateNotifEmailCount();
            renderNotifEmailsList();
        } else {
            showNotification(data.message || 'Failed to delete email', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    }
}

// ============================================
// NOTIFICATION EMAILS — POPUP
// ============================================

function openNotifEmailsPopup() {
    const popup = document.getElementById('notifEmailsPopup');
    if (!popup) return;
    popup.style.display = 'flex';
    requestAnimationFrame(() => popup.classList.add('visible'));
}

function closeNotifEmailsPopup() {
    const popup = document.getElementById('notifEmailsPopup');
    if (!popup) return;
    popup.classList.remove('visible');
    popup.addEventListener('transitionend', () => { popup.style.display = 'none'; }, { once: true });
}

function closeNotifEmailsPopupOnOverlay(event) {
    if (event.target === document.getElementById('notifEmailsPopup')) {
        closeNotifEmailsPopup();
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

    const notifInput = document.getElementById('settingAdminNotificationEmail');
    if (notifInput) {
        notifInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); addNotificationEmail(); }
        });
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

// ============================================
// SUBMISSIONS — ACCEPT (validate → popup → confirm)
// ============================================

async function acceptSubmission(id) {
    // Step 1: check "change of mind" if already in a final state
    const card          = document.querySelector(`#submissions-tab .admin-message-card[data-id="${id}"]`);
    const currentStatus = card ? card.dataset.status : '';

    if (['rejected', 'removed'].includes(currentStatus)) {
        const chatbotName = card.querySelector('.admin-message-sender')?.textContent?.trim() || 'this chatbot';
        // const clearedField = currentStatus === 'rejected' ? 'rejected_at' : 'deleted_at';
        const confirmed = await showChangeOfMindPopup(chatbotName);
        if (!confirmed) return;
    }

    // Step 2: validate chatbot params
    let validationData;
    try {
        const res  = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'validate_chatbot', id }),
        });
        validationData = await res.json();
    } catch (err) {
        showNotification('Validation request failed.', 'error');
        return;
    }

    if (!validationData.success) {
        showNotification(validationData.message || 'Validation error.', 'error');
        return;
    }

    // Step 3: show accept popup with validation results
    showAcceptPopup(id, validationData.chatbot.name, validationData.rules, validationData.valid);
}

function showAcceptPopup(id, chatbotName, rules, allPassed) {
    // Remove existing popup
    document.getElementById('sub-accept-popup')?.remove();

    const rulesHtml = rules.map(rule => {
        const icon   = rule.skip  ? '⏭️' : (rule.ok ? '✅' : '❌');
        const cls    = rule.skip  ? 'accept-rule-skip' : (rule.ok ? 'accept-rule-ok' : 'accept-rule-fail');
        return `<div class="accept-rule-item ${cls}">${icon} ${rule.label}</div>`;
    }).join('');

    const confirmDisabled = allPassed ? '' : ' disabled';
    const statusMsg = allPassed
        ? `<p class="accept-status-ok">✅ All requirements met. Ready to accept.</p>`
        : `<p class="accept-status-fail">❌ Some requirements are not met. Fix them before accepting.</p>`;

    const popup = document.createElement('div');
    popup.id    = 'sub-accept-popup';
    popup.className = 'sub-popup-overlay';
    popup.innerHTML = `
        <div class="sub-popup-box">
            <div class="sub-popup-header">
                <h3>Accept Chatbot</h3>
                <button class="sub-popup-close" onclick="closeAcceptPopup()">✕</button>
            </div>
            <div class="sub-popup-body">
                <p>Reviewing <strong>${escHtml(chatbotName)}</strong> for activation.</p>
                <div class="accept-rules-list">${rulesHtml}</div>
                ${statusMsg}
                <p class="accept-info-note">Accepting will set status to <strong>active</strong>, mark as verified, and send a confirmation email to the owner.</p>
            </div>
            <div class="sub-popup-footer">
                <button class="admin-cancel-btn" onclick="closeAcceptPopup()">Cancel</button>
                <button class="admin-accept-confirm-btn"${confirmDisabled}
                        onclick="confirmAccept('${escHtml(id)}', this)">
                    ✅ Confirm Accept
                </button>
            </div>
        </div>`;

    document.body.appendChild(popup);
    requestAnimationFrame(() => popup.classList.add('visible'));
}

function closeAcceptPopup() {
    const popup = document.getElementById('sub-accept-popup');
    if (popup) {
        popup.classList.remove('visible');
        setTimeout(() => popup.remove(), 200);
    }
}

async function confirmAccept(id, btn) {
    btn.disabled    = true;
    btn.textContent = 'Accepting...';

    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'accept', id }),
        });
        const result = await res.json();

        if (result.success) {
            closeAcceptPopup();
            updateCardStatus(id, 'active');
            showNotification('Chatbot accepted and email sent!', 'success');
            if (result.pendingCount !== undefined) {
                updateMainTabBadge('submissions', result.pendingCount);
            }
        } else {
            showNotification(result.message || 'Failed to accept chatbot.', 'error');
            btn.disabled    = false;
            btn.textContent = '✅ Confirm Accept';
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
        btn.disabled    = false;
        btn.textContent = '✅ Confirm Accept';
    }
}

// ============================================
// SUBMISSIONS — REVIEW
// ============================================

async function reviewSubmission(id, clickedBtn) {
    const card          = document.querySelector(`#submissions-tab .admin-message-card[data-id="${id}"]`);
    const currentStatus = card ? card.dataset.status : '';
    const newStatus     = currentStatus === 'reviewed' ? 'pending' : 'reviewed';
 
    // If chatbot is currently active → delist flow with popup
    if (currentStatus === 'active') {
        const chatbotName = card.querySelector('.admin-message-sender')?.textContent?.trim() || 'this chatbot';
        const reason = await showDelistPopup(chatbotName);
        if (reason === null) return; // null = cancelled, '' = no reason given (ok)
 
        clickedBtn.disabled = true;
 
        try {
            const res    = await fetch('../app/adminSubmissionActions.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'review', id, reason }),
            });
            const result = await res.json();
 
            if (result.success) {
                updateCardStatus(id, 'reviewed');
                showNotification('Chatbot delisted and marked as reviewed. Email sent.', 'success');
                if (result.pendingCount !== undefined) {
                    updateMainTabBadge('submissions', result.pendingCount);
                }
            } else {
                showNotification(result.message || 'Failed to delist chatbot.', 'error');
            }
        } catch (err) {
            showNotification('Something went wrong.', 'error');
        } finally {
            clickedBtn.disabled = false;
        }
        return;
    }
 
    // Normal flow (not active)
    clickedBtn.disabled = true;
 
    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'review', id, status: newStatus }),
        });
        const result = await res.json();
 
        if (result.success) {
            clickedBtn.classList.toggle('active', newStatus === 'reviewed');
 
            card.querySelectorAll('.submission-action-btn').forEach(b => {
                if (b !== clickedBtn) b.classList.remove('active');
            });
 
            const badge = card.querySelector('.admin-status-badge');
            if (badge) {
                badge.className   = `admin-status-badge submission-status-${newStatus}`;
                badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }
 
            showNotification(
                newStatus === 'reviewed' ? 'Marked as reviewed' : 'Moved back to pending',
                'success'
            );
 
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
    const card          = document.querySelector(`#submissions-tab .admin-message-card[data-id="${id}"]`);
    const currentStatus = card ? card.dataset.status : '';
 
    // Block: cannot reject an active chatbot
    if (currentStatus === 'active') {
        showNotification('Cannot reject an active chatbot. Use "Reviewed" or "Remove" to delist it first.', 'error');
        // Close the reject section
        const section = document.getElementById(`sub-reject-${id}`);
        if (section) section.style.display = 'none';
        return;
    }
 
    // "Change of mind" check for final states
    if (['removed'].includes(currentStatus)) {
        const chatbotName = card?.querySelector('.admin-message-sender')?.textContent?.trim() || 'this chatbot';
        const confirmed   = await showChangeOfMindPopup(chatbotName);
        if (!confirmed) return;
    }
 
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
            body:    JSON.stringify({ action: 'reject', id, reason }),
        });
        const result = await res.json();
 
        if (result.success) {
            updateCardStatus(id, 'rejected');
            section.style.display = 'none';
            textarea.value        = '';
            showNotification('Rejection sent successfully.', 'success');
            if (result.pendingCount !== undefined) {
                updateMainTabBadge('submissions', result.pendingCount);
            }
        } else {
            showNotification(result.message || 'Failed to reject submission.', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Send Rejection';
    }
}

// ============================================
// SUBMISSIONS — REMOVE
// ============================================

async function removeSubmission(id, clickedBtn) {
    const card          = document.querySelector(`#submissions-tab .admin-message-card[data-id="${id}"]`);
    const currentStatus = card ? card.dataset.status : '';
    const chatbotName   = card?.querySelector('.admin-message-sender')?.textContent?.trim() || 'this chatbot';
 
    // If currently active → delist popup with required reason
    if (currentStatus === 'active') {
        const reason = await showDelistPopup(chatbotName);
        if (reason === null) return; // null = cancelled, '' = no reason given (ok)
 
        clickedBtn.disabled = true;
 
        try {
            const res    = await fetch('../app/adminSubmissionActions.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'remove', id, reason }),
            });
            const result = await res.json();
 
            if (result.success) {
                updateCardStatus(id, 'removed');
                showNotification('Chatbot removed and delisted. Email sent.', 'success');
                if (result.pendingCount !== undefined) {
                    updateMainTabBadge('submissions', result.pendingCount);
                }
            } else {
                showNotification(result.message || 'Failed to remove chatbot.', 'error');
            }
        } catch (err) {
            showNotification('Something went wrong.', 'error');
        } finally {
            clickedBtn.disabled = false;
        }
        return;
    }
 
    // For non-active states → standard "change of mind" confirm popup
    if (['active', 'rejected'].includes(currentStatus)) {
        const confirmed = await showChangeOfMindPopup(chatbotName);
        if (!confirmed) return;
    } else {
        const confirmed = await showChangeOfMindPopup(chatbotName, true);
        if (!confirmed) return;
    }
 
    clickedBtn.disabled = true;
 
    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'remove', id }),
        });
        const result = await res.json();
 
        if (result.success) {
            updateCardStatus(id, 'removed');
            showNotification('Chatbot removed.', 'success');
            if (result.pendingCount !== undefined) {
                updateMainTabBadge('submissions', result.pendingCount);
            }
        } else {
            showNotification(result.message || 'Failed to remove chatbot.', 'error');
        }
    } catch (err) {
        showNotification('Something went wrong.', 'error');
    } finally {
        clickedBtn.disabled = false;
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
            body:    JSON.stringify({ action: 'save_notes', id, notes }),
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

// ============================================
// HELPER: "Change of mind" confirmation popup
// ============================================

function showChangeOfMindPopup(chatbotName, isRemove = false) {
    return new Promise((resolve) => {
        document.getElementById('sub-change-popup')?.remove();

        // let fieldsNote = '';
        // if (clearedFields) {
        //     fieldsNote = `<p class="change-popup-fields">The following timestamps will be cleared: <strong>${clearedFields}</strong></p>`;
        // }

        const actionText = isRemove
            ? `Are you sure you want to <strong>remove</strong>`
            : `Are you sure you want to change the status of`;

        const popup = document.createElement('div');
        popup.id    = 'sub-change-popup';
        popup.className = 'sub-popup-overlay';
        popup.innerHTML = `
            <div class="sub-popup-box sub-popup-box--warning">
                <div class="sub-popup-header">
                    <h3>Confirm Status Change</h3>
                    <button class="sub-popup-close" onclick="resolveChangePopup(false)">✕</button>
                </div>
                <div class="sub-popup-body">
                    <p>${actionText} <strong>${escHtml(chatbotName)}</strong>?</p>
                    
                </div>
                <div class="sub-popup-footer">
                    <button class="admin-cancel-btn" onclick="resolveChangePopup(false)">Cancel</button>
                    <button class="admin-confirm-change-btn" onclick="resolveChangePopup(true)">
                        Confirm
                    </button>
                </div>
            </div>`;

        popup._resolve = resolve;
        document.body.appendChild(popup);
        requestAnimationFrame(() => popup.classList.add('visible'));
    });
}

function resolveChangePopup(confirmed) {
    const popup = document.getElementById('sub-change-popup');
    if (!popup) return;
    const resolve = popup._resolve;
    popup.classList.remove('visible');
    setTimeout(() => popup.remove(), 200);
    if (resolve) resolve(confirmed);
}

// ============================================
// HELPER: delist 
// ============================================

function showDelistPopup(chatbotName) {
    return new Promise((resolve) => {
        document.getElementById('sub-delist-popup')?.remove();
 
        const popup = document.createElement('div');
        popup.id        = 'sub-delist-popup';
        popup.className = 'sub-popup-overlay';
        popup.innerHTML = `
            <div class="sub-popup-box sub-popup-box--warning">
                <div class="sub-popup-header">
                    <h3>Delist Chatbot</h3>
                    <button class="sub-popup-close" onclick="resolveDelistPopup(false)">✕</button>
                </div>
                <div class="sub-popup-body">
                    <p>You are about to delist <strong>${escHtml(chatbotName)}</strong> from ChatbotScout.</p>
                    <p>The owner will receive a <strong>Chatbot Delisted</strong> email with your reason.</p>
                    <label style="display:block;margin-top:1rem;font-size:1.3rem;font-family: outfit-semibold, sans-serif;color:#374151;">
                        Reason <span style="font-family: outfit-medium, sans-serif;color:#6B7280;">(optional — will be sent to the user)</span>
                    </label>
                    <textarea
                        id="sub-delist-reason"
                        class="admin-textarea"
                        placeholder="e.g. The chatbot is no longer publicly accessible..."
                        style="margin-top:0.5rem;"
                        rows="4"
                        maxlength="2000"
                    ></textarea>
                </div>
                <div class="sub-popup-footer">
                    <button class="admin-cancel-btn" onclick="resolveDelistPopup(false)">Cancel</button>
                    <button class="admin-confirm-change-btn" onclick="resolveDelistPopup(true)">
                        Confirm &amp; Send Email
                    </button>
                </div>
            </div>`;
 
        popup._resolve = resolve;
        document.body.appendChild(popup);
        requestAnimationFrame(() => popup.classList.add('visible'));
 
        // Focus textarea
        setTimeout(() => document.getElementById('sub-delist-reason')?.focus(), 150);
    });
}
 
function resolveDelistPopup(confirmed) {
    const popup = document.getElementById('sub-delist-popup');
    if (!popup) return;
 
    const resolve = popup._resolve;
    popup.classList.remove('visible');
    setTimeout(() => popup.remove(), 200);
 
    if (resolve) {
        if (confirmed) {
            // reason je teraz voliteľný — posielame aj prázdny string
            const reason = document.getElementById('sub-delist-reason')?.value?.trim() ?? '';
            resolve(reason !== undefined ? reason : '');
        } else {
            resolve(null); // null = cancelled
        }
    }
}

// ============================================
// HELPER: Update card status in DOM
// ============================================

function updateCardStatus(id, newStatus) {
    const card = document.querySelector(`#submissions-tab .admin-message-card[data-id="${id}"]`);
    if (!card) return;

    card.dataset.status = newStatus;

    // Update badge
    const badge = card.querySelector('.admin-status-badge');
    if (badge) {
        badge.className   = `admin-status-badge submission-status-${newStatus}`;
        badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    }

    // Update active state on buttons
    card.querySelectorAll('.submission-action-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.action === newStatus);
    });
}

// ============================================
// HELPER: escHtml (fallback if not defined globally)
// ============================================

if (typeof escHtml === 'undefined') {
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}