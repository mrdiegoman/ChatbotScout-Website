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
        const res    = await fetch('app/adminContactActions.php', {
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
        const res    = await fetch('app/adminContactActions.php', {
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
        const res    = await fetch('app/adminContactActions.php', {
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
        const res    = await fetch('app/adminContactActions.php', {
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
        const res    = await fetch('app/adminReviewActions.php', {
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
        const res    = await fetch('app/adminReviewActions.php', {
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
        const res  = await fetch('app/adminSettingsActions.php', { method: 'GET' });
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
        const res  = await fetch('app/adminSettingsActions.php', {
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
        const res  = await fetch('api/getAdminNotificationEmails.php', { method: 'GET' });
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
        const res  = await fetch('api/addAdminNotificationEmail.php', {
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
        const res  = await fetch('api/deleteAdminNotificationEmail.php', {
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
    return;
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
// SUBMISSIONS — ADMIN-CREATED CHATBOTS
// ============================================

function openAdminChatbotCreatePopup() {
    document.getElementById('admin-chatbot-create-popup')?.remove();

    const popup = document.createElement('div');
    popup.id = 'admin-chatbot-create-popup';
    popup.className = 'sub-popup-overlay';
    popup.innerHTML = `
        <div class="sub-popup-box admin-chatbot-create-box">
            <div class="sub-popup-header">
                <h3>Add chatbot</h3>
                <button type="button" class="modal-close sub-popup-close" onclick="closeAdminChatbotCreatePopup()" aria-label="Close">&times;</button>
            </div>
            <form id="admin-chatbot-create-form" onsubmit="createAdminChatbot(event)">
                <div class="sub-popup-body">
                    <div class="admin-create-chatbot-grid">
                        <label class="admin-create-chatbot-field">
                            <span>Name</span>
                            <input name="name" type="text" maxlength="200" required>
                        </label>
                        <label class="admin-create-chatbot-field">
                            <span>Website URL</span>
                            <input name="website_url" type="url" maxlength="500" placeholder="https://example.com" required>
                        </label>
                        <label class="admin-create-chatbot-field">
                            <span>Logo URL or path</span>
                            <input name="logo_url" type="text" maxlength="500" placeholder="img/websiteLogos/logo.png">
                        </label>
                        <label class="admin-create-chatbot-field admin-create-chatbot-field--wide">
                            <span>Admin notes</span>
                            <textarea name="admin_notes" maxlength="5000" rows="4"></textarea>
                        </label>
                    </div>
                </div>
                <div class="sub-popup-footer">
                    <button type="button" class="admin-cancel-btn" onclick="closeAdminChatbotCreatePopup()">Cancel</button>
                    <button type="submit" class="admin-save-btn" data-admin-create-save>Add chatbot</button>
                </div>
            </form>
        </div>`;

    document.body.appendChild(popup);
    requestAnimationFrame(() => popup.classList.add('visible'));
}

function closeAdminChatbotCreatePopup() {
    const popup = document.getElementById('admin-chatbot-create-popup');
    if (!popup) return;

    popup.classList.remove('visible');
    setTimeout(() => popup.remove(), 200);
}

async function createAdminChatbot(event) {
    event.preventDefault();

    const form = document.getElementById('admin-chatbot-create-form');
    if (!form) return;

    const saveBtn = form.querySelector('[data-admin-create-save]');
    const payload = {
        action: 'create_admin_chatbot',
        name: form.elements['name'].value.trim(),
        website_url: form.elements['website_url'].value.trim(),
        logo_url: form.elements['logo_url'].value.trim(),
        admin_notes: form.elements['admin_notes'].value.trim(),
    };

    if (!payload.name || !payload.website_url) {
        showNotification('Name and website URL are required.', 'error');
        return;
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Adding...';
    }

    try {
        const res = await fetch('app/adminSubmissionActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await res.json();

        if (!result.success) {
            showNotification(result.message || 'Could not add chatbot.', 'error');
            return;
        }

        closeAdminChatbotCreatePopup();
        showNotification(result.message || 'Chatbot added.', 'success');
        refreshSubmissionsAfterAdminChange(result.counts);
    } catch (err) {
        console.error(err);
        showNotification('Could not add chatbot.', 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Add chatbot';
        }
    }
}

async function searchAccountForChatbot(chatbotId, btn) {
    const section = document.getElementById(`sub-assign-${chatbotId}`);
    const input = section?.querySelector('.admin-assign-email-input');
    const resultEl = document.getElementById(`sub-assign-result-${chatbotId}`);
    const email = input?.value.trim() || '';

    if (!email) {
        showNotification('Enter an email address first.', 'error');
        input?.focus();
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Searching...';
    }
    if (resultEl) {
        resultEl.innerHTML = '';
    }

    try {
        const res = await fetch('app/adminSubmissionActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'search_user_by_email', id: chatbotId, email }),
        });
        const result = await res.json();

        if (!result.success) {
            showNotification(result.message || 'Search failed.', 'error');
            return;
        }

        if (!result.found || !result.user) {
            if (resultEl) {
                resultEl.innerHTML = `<div class="admin-assign-empty">No account found for ${escapeHtml(email)}.</div>`;
            }
            return;
        }

        const user = result.user;
        const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'User';
        if (resultEl) {
            resultEl.innerHTML = `
                <div class="admin-assign-user-card">
                    <div>
                        <strong>${escapeHtml(fullName)}</strong>
                        <span>${escapeHtml(user.email)}</span>
                    </div>
                    <button type="button"
                            class="admin-save-btn admin-assign-add-btn"
                            onclick="assignChatbotToAccount('${escapeAttr(chatbotId)}', '${escapeAttr(user.id)}', this)">
                        Add
                    </button>
                </div>`;
        }
    } catch (err) {
        console.error(err);
        showNotification('Search failed.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Search';
        }
    }
}

async function assignChatbotToAccount(chatbotId, userId, btn) {
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Adding...';
    }

    try {
        const res = await fetch('app/adminSubmissionActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'assign_chatbot_owner', id: chatbotId, user_id: userId }),
        });
        const result = await res.json();

        if (!result.success) {
            showNotification(result.message || 'Could not assign account.', 'error');
            return;
        }

        showNotification('Chatbot assigned to account.', 'success');
        refreshSubmissionsAfterAdminChange(result.counts);
    } catch (err) {
        console.error(err);
        showNotification('Could not assign account.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add';
        }
    }
}

function refreshSubmissionsAfterAdminChange(counts = null) {
    if (counts && typeof updateMainTabBadge === 'function') {
        updateMainTabBadge('submissions', counts.pending ?? 0);
    }

    const refreshBtn = document.getElementById('refreshSubmissionsBtn');
    if (typeof refreshSubmissions === 'function' && refreshBtn) {
        refreshSubmissions(refreshBtn);
    }
}

// ============================================
// SUBMISSIONS — MARK REVIEWED (toggle, no email)
// ============================================

async function approveSubmission(id, clickedBtn) {
    clickedBtn.disabled = true;

    const card      = clickedBtn.closest('.admin-message-card');
    const isActive  = clickedBtn.classList.contains('active');
    const newStatus = isActive ? 'pending' : 'reviewed';

    try {
        const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res  = await fetch('app/adminSubmissionActions.php', {
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
    const accountNotAssigned = rules.some(rule => rule.code === 'account_assigned' && !rule.ok);
    const statusMsg = allPassed
        ? `<p class="accept-status-ok">✅ All requirements met. Ready to accept.</p>`
        : (accountNotAssigned
            ? `<p class="accept-status-fail">❌ Account is not assigned yet. Assign this chatbot to an existing account before accepting.</p>`
            : `<p class="accept-status-fail">❌ Some requirements are not met. Fix them before accepting.</p>`);

    const popup = document.createElement('div');
    popup.id    = 'sub-accept-popup';
    popup.className = 'sub-popup-overlay';
    popup.innerHTML = `
        <div class="sub-popup-box">
            <div class="sub-popup-header">
                <h3>Accept Chatbot</h3>
                <button type="button" class="modal-close sub-popup-close" onclick="closeAcceptPopup()" aria-label="Close">&times;</button>
            </div>
            <div class="sub-popup-body">
                <p>Reviewing <strong>${escHtml(chatbotName)}</strong> for activation.</p>
                <div class="accept-rules-list">${rulesHtml}</div>
                ${statusMsg}
                <p class="accept-info-note">Accepting will set status to <strong>active</strong>, mark as verified, and send a confirmation email to the owner.</p>
            </div>
            <div class="sub-popup-footer">
                <button class="admin-cancel-btn" onclick="closeAcceptPopup()">Cancel</button>
                <button class="admin-accept-edit-btn"
                        onclick="openSubmissionEditFromAccept('${escapeAttr(id)}')">
                    Edit Listing
                </button>
                <button class="admin-accept-confirm-btn"${confirmDisabled}
                        onclick="confirmAccept('${escapeAttr(id)}', this)">
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

function openSubmissionEditFromAccept(id) {
    closeAcceptPopup();
    setTimeout(() => openSubmissionEditPopup(id), 220);
}

async function confirmAccept(id, btn) {
    btn.disabled    = true;
    btn.textContent = 'Accepting...';

    try {
        const res    = await fetch('app/adminSubmissionActions.php', {
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
            const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res    = await fetch('app/adminSubmissionActions.php', {
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
            const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res    = await fetch('app/adminSubmissionActions.php', {
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
        const res    = await fetch('app/adminSubmissionActions.php', {
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
                    <button type="button" class="modal-close sub-popup-close" onclick="resolveChangePopup(false)" aria-label="Close">&times;</button>
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
                    <button type="button" class="modal-close sub-popup-close" onclick="resolveDelistPopup(false)" aria-label="Close">&times;</button>
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
// SUBMISSIONS - EDIT LISTING POPUP
// ============================================

const SUBMISSION_PRICING_MODELS = [
    ['free', 'Free'],
    ['freemium', 'Freemium'],
    ['recurring', 'Recurring'],
    ['one-time', 'One-time'],
    ['usage-based', 'Usage-based'],
    ['custom', 'Custom'],
];

const SUBMISSION_CURRENCIES = ['USD', 'EUR', 'GBP'];

function submissionEditAssetSrc(path) {
    if (!path) return '';
    const value = String(path);
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('/') || value.startsWith('data:') || value.startsWith('../')) {
        return value;
    }
    if (value.startsWith('public/')) {
        return `../${value}`;
    }
    return value;
}

function checkedAttr(value) {
    return value ? ' checked' : '';
}

function selectedAttr(current, value) {
    return String(current || '') === value ? ' selected' : '';
}

function normalizeSubmissionEditName(name) {
    return String(name || '').trim().toLowerCase().replace(/\s+/g, ' ');
}

function buildSubmissionEditOption(kind, item, selected) {
    const isFeature = kind === 'feature';
    const checkClass = isFeature ? 'sub-edit-feature-check' : 'sub-edit-integration-check';
    const usageCount = Number(item.usage_count || 0);
    const badge = isFeature && item.advanced
        ? '<span class="sub-edit-option-badge">Advanced</span>'
        : '';
    const deleteTitle = usageCount > 0
        ? `Used by ${usageCount} chatbot(s). Remove it from all chatbots before deleting.`
        : 'Delete this unused feature';
    const deleteClass = usageCount > 0 ? ' sub-edit-delete-feature-btn--locked' : '';
    const deleteAction = usageCount > 0
        ? `event.preventDefault(); event.stopPropagation(); showNotification('This feature is used by ${usageCount} chatbot(s).', 'info')`
        : `deleteSubmissionEditFeature('${escapeAttr(item.id)}', this, event)`;
    const deleteButton = isFeature
        ? `<button type="button"
                   class="sub-edit-delete-feature-btn${deleteClass}"
                   title="${escapeAttr(deleteTitle)}"
                   aria-disabled="${usageCount > 0 ? 'true' : 'false'}"
                   onclick="${deleteAction}">
               Delete
           </button>`
        : '';
    const selectedClass = selected ? ' sub-edit-option--selected' : '';

    return `
        <div class="sub-edit-option${selectedClass}" data-name-key="${escapeAttr(normalizeSubmissionEditName(item.name))}">
            <input type="checkbox"
                   class="${checkClass}"
                   data-id="${escapeAttr(item.id)}"
                   onchange="handleSubmissionEditOptionToggle(this)"
                   ${checkedAttr(selected)}>
            <span class="sub-edit-option-name">${escapeHtml(item.name)}</span>
            ${badge}
            ${deleteButton}
        </div>`;
}

function buildSubmissionEditSelector(kind, title, items, selectedIds) {
    const isFeature = kind === 'features';
    const selectedSet = new Set(selectedIds || []);
    const rows = (items || []).map(item => {
        return buildSubmissionEditOption(isFeature ? 'feature' : 'integration', item, selectedSet.has(item.id));
    }).join('');

    return `
        <section class="sub-edit-picker">
            <div class="sub-edit-picker-head">
                <h4>${title}</h4>
                <span id="sub-edit-${kind}-count">0 selected</span>
            </div>
            <input type="search"
                   id="sub-edit-${kind}-search"
                   class="sub-edit-search"
                   data-sub-edit-search="${kind}"
                   placeholder="Search ${title.toLowerCase()}...">
            <div class="sub-edit-create-row">
                <input type="text"
                       id="sub-edit-new-${isFeature ? 'feature' : 'integration'}"
                       maxlength="100"
                       placeholder="Create new ${isFeature ? 'feature' : 'integration'}">
                ${isFeature ? `
                    <div class="sub-edit-advanced-toggle">
                        <span>Advanced</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="sub-edit-new-feature-advanced">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>` : ''}
                <button type="button" onclick="addSubmissionEditItem('${kind}')">Add</button>
            </div>
            <div class="sub-edit-options" id="sub-edit-${kind}-list">
                ${rows || '<div class="sub-edit-empty-option">No items yet.</div>'}
            </div>
        </section>`;
}

function buildSubmissionValidationSummary(validation) {
    const rules = validation?.rules || [];
    if (!rules.length) return '';

    const rulesHtml = rules.map(rule => {
        const cls = rule.skip ? 'accept-rule-skip' : (rule.ok ? 'accept-rule-ok' : 'accept-rule-fail');
        const icon = rule.skip ? '-' : (rule.ok ? 'OK' : '!');
        return `<div class="accept-rule-item ${cls}">${icon} ${escapeHtml(rule.label)}</div>`;
    }).join('');

    return `
        <section class="sub-edit-validation">
            <h4>Accept requirements</h4>
            <div class="accept-rules-list">${rulesHtml}</div>
        </section>`;
}

async function openSubmissionEditPopup(id) {
    document.getElementById('sub-edit-popup')?.remove();

    const popup = document.createElement('div');
    popup.id = 'sub-edit-popup';
    popup.className = 'sub-popup-overlay';
    popup.innerHTML = `
        <div class="sub-popup-box sub-edit-popup-box">
            <div class="sub-popup-header">
                <h3>Edit Listing</h3>
                <button type="button" class="modal-close sub-popup-close" onclick="closeSubmissionEditPopup()" aria-label="Close">&times;</button>
            </div>
            <div class="sub-popup-body sub-edit-popup-body">
                <div class="sub-edit-loading">Loading listing details...</div>
            </div>
        </div>`;

    document.body.appendChild(popup);
    requestAnimationFrame(() => popup.classList.add('visible'));

    try {
        const res = await fetch('app/adminSubmissionActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_edit_data', id }),
        });
        const result = await res.json();

        if (!result.success) {
            showNotification(result.message || 'Could not load listing details.', 'error');
            closeSubmissionEditPopup();
            return;
        }

        renderSubmissionEditPopup(result.data);
    } catch (err) {
        console.error(err);
        showNotification('Could not load listing details.', 'error');
        closeSubmissionEditPopup();
    }
}

function renderSubmissionEditPopup(data) {
    const popup = document.getElementById('sub-edit-popup');
    if (!popup) return;

    const c = data.chatbot || {};
    const pricingOptions = SUBMISSION_PRICING_MODELS.map(([value, label]) => {
        return `<option value="${value}"${selectedAttr(c.pricing_model, value)}>${label}</option>`;
    }).join('');
    const currencyOptions = SUBMISSION_CURRENCIES.map(currency => {
        return `<option value="${currency}"${selectedAttr(c.pricing_currency || 'USD', currency)}>${currency}</option>`;
    }).join('');
    const commissionOptions = [
        ['', 'Not set'],
        ['percentage', 'Percentage'],
        ['flat', 'Flat'],
        ['cpa', 'CPA'],
    ].map(([value, label]) => {
        return `<option value="${value}"${selectedAttr(c.affiliate_commission_type || '', value)}>${label}</option>`;
    }).join('');

    popup.innerHTML = `
        <div class="sub-popup-box sub-edit-popup-box">
            <div class="sub-popup-header">
                <h3>Edit Listing</h3>
                <button type="button" class="modal-close sub-popup-close" onclick="closeSubmissionEditPopup()" aria-label="Close">&times;</button>
            </div>
            <form id="sub-edit-form" data-chatbot-id="${escapeAttr(c.id)}" onsubmit="saveSubmissionEdit(event, '${escapeAttr(c.id)}')">
                <div class="sub-popup-body sub-edit-popup-body">
                    <section class="sub-edit-section">
                        <h4>Core details</h4>
                        <div class="sub-edit-grid">
                            <label class="sub-edit-field">
                                <span>Name</span>
                                <input name="name" type="text" maxlength="200" required value="${escapeAttr(c.name || '')}">
                            </label>
                            <label class="sub-edit-field">
                                <span>Website URL</span>
                                <input name="website_url" type="url" maxlength="500" required value="${escapeAttr(c.website_url || '')}">
                            </label>
                            <label class="sub-edit-field sub-edit-field--wide">
                                <span>Description</span>
                                <textarea name="description" maxlength="150" rows="3">${escapeHtml(c.description || '')}</textarea>
                            </label>
                            <label class="sub-edit-field">
                                <span>Logo URL or path</span>
                                <input name="logo_url" type="text" maxlength="500" placeholder="img/websiteLogos/ChatbotScout_logo.png or https://example.com/logo.png" value="${escapeAttr(c.logo_url || '')}">
                            </label>
                            <label class="sub-edit-field">
                                <span>Pricing URL</span>
                                <input name="pricing_url" type="url" maxlength="500" value="${escapeAttr(c.pricing_url || '')}">
                            </label>
                        </div>
                    </section>

                    <section class="sub-edit-section">
                        <h4>Affiliate</h4>
                        <div class="sub-edit-grid">
                            <div class="sub-edit-toggle-field">
                                <span>Affiliate program enabled</span>
                                <label class="toggle-switch">
                                    <input name="has_affiliate_program" type="checkbox"${checkedAttr(c.has_affiliate_program)}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <label class="sub-edit-field sub-edit-field--wide">
                                <span>Outbound / affiliate link</span>
                                <input name="affiliate_link" type="url" maxlength="1000" value="${escapeAttr(c.affiliate_link || '')}">
                            </label>
                            <label class="sub-edit-field">
                                <span>Commission rate</span>
                                <input name="affiliate_commission_rate" type="number" min="0" max="999.99" step="0.01" value="${escapeAttr(c.affiliate_commission_rate || '')}">
                            </label>
                            <label class="sub-edit-field">
                                <span>Commission type</span>
                                <select name="affiliate_commission_type">${commissionOptions}</select>
                            </label>
                            <label class="sub-edit-field">
                                <span>Cookie duration days</span>
                                <input name="affiliate_cookie_duration" type="number" min="0" max="3650" step="1" value="${escapeAttr(c.affiliate_cookie_duration || '')}">
                            </label>
                        </div>
                    </section>

                    <section class="sub-edit-section">
                        <h4>Pricing</h4>
                        <div class="sub-edit-grid">
                            <label class="sub-edit-field">
                                <span>Pricing model</span>
                                <select name="pricing_model">${pricingOptions}</select>
                            </label>
                            <label class="sub-edit-field">
                                <span>Starting price</span>
                                <input name="starting_price" type="number" min="0" step="0.01" value="${escapeAttr(c.starting_price || '0.00')}">
                            </label>
                            <label class="sub-edit-field">
                                <span>Currency</span>
                                <select name="pricing_currency">${currencyOptions}</select>
                            </label>
                            <div class="sub-edit-toggle-field">
                                <span>Free trial available</span>
                                <label class="toggle-switch">
                                    <input name="has_free_trial" type="checkbox" data-free-trial-toggle${checkedAttr(c.has_free_trial)}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <label class="sub-edit-field">
                                <span>Free trial days</span>
                                <input name="free_trial_days" type="number" min="1" max="3650" step="1" data-free-trial-days${c.has_free_trial ? ' required aria-required="true"' : ''} value="${escapeAttr(c.free_trial_days || '')}">
                            </label>
                        </div>
                    </section>

                    <div class="sub-edit-pickers">
                        ${buildSubmissionEditSelector('features', 'Features', data.features || [], data.selectedFeatureIds || [])}
                        ${buildSubmissionEditSelector('integrations', 'Integrations', data.integrations || [], data.selectedIntegrationIds || [])}
                    </div>

                    ${buildSubmissionValidationSummary(data.validation)}
                </div>
                <div class="sub-popup-footer">
                    <button type="submit" class="admin-save-btn" data-sub-edit-save>Save Listing</button>
                </div>
            </form>
        </div>`;

    updateSubmissionEditCounts();
    bindSubmissionEditSearchHandlers();
    bindSubmissionEditOptionHandlers();
    bindSubmissionFreeTrialControls();
}

function closeSubmissionEditPopup() {
    const popup = document.getElementById('sub-edit-popup');
    if (!popup) return;

    popup.classList.remove('visible');
    setTimeout(() => popup.remove(), 200);
}

function filterSubmissionEditList(kind, value) {
    const list = document.getElementById(`sub-edit-${kind}-list`);
    if (!list) return;

    const term = normalizeSubmissionEditName(value);
    list.querySelectorAll('.sub-edit-option').forEach(option => {
        const nameKey = option.dataset.nameKey || normalizeSubmissionEditName(option.textContent);
        option.style.display = term === '' || nameKey.includes(term) ? '' : 'none';
    });
}

function bindSubmissionEditSearchHandlers() {
    document.querySelectorAll('[data-sub-edit-search]').forEach(input => {
        if (input.dataset.bound === '1') return;

        input.dataset.bound = '1';
        input.addEventListener('input', () => {
            filterSubmissionEditList(input.dataset.subEditSearch, input.value);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });
}

function bindSubmissionEditOptionHandlers() {
    const form = document.getElementById('sub-edit-form');
    if (!form || form.dataset.optionHandlersBound === '1') return;

    form.dataset.optionHandlersBound = '1';
    form.addEventListener('click', event => {
        const option = event.target.closest('.sub-edit-option');
        if (!option || !form.contains(option)) return;

        if (event.target.closest('button, input, label, a, select, textarea')) {
            return;
        }

        const checkbox = option.querySelector('input[type="checkbox"]');
        if (!checkbox || checkbox.disabled) return;

        checkbox.checked = !checkbox.checked;
        handleSubmissionEditOptionToggle(checkbox);
    });
}

function syncSubmissionFreeTrialDaysState(form) {
    const trialToggle = form?.elements?.['has_free_trial'];
    const trialDays = form?.elements?.['free_trial_days'];
    if (!trialToggle || !trialDays) return;

    const isRequired = trialToggle.checked;
    trialDays.required = isRequired;
    trialDays.setAttribute('aria-required', isRequired ? 'true' : 'false');

    if (!isRequired) {
        trialDays.setCustomValidity('');
    }
}

function bindSubmissionFreeTrialControls() {
    const form = document.getElementById('sub-edit-form');
    const trialToggle = form?.elements?.['has_free_trial'];
    const trialDays = form?.elements?.['free_trial_days'];
    if (!form || !trialToggle || !trialDays || trialToggle.dataset.freeTrialBound === '1') return;

    trialToggle.dataset.freeTrialBound = '1';
    syncSubmissionFreeTrialDaysState(form);

    trialToggle.addEventListener('change', () => {
        syncSubmissionFreeTrialDaysState(form);
        if (trialToggle.checked && !trialDays.value.trim()) {
            trialDays.focus();
        }
    });

    trialDays.addEventListener('input', () => {
        trialDays.setCustomValidity('');
    });
}

function validateSubmissionFreeTrialDays(form) {
    const trialToggle = form?.elements?.['has_free_trial'];
    const trialDays = form?.elements?.['free_trial_days'];
    if (!trialToggle || !trialDays) return true;

    syncSubmissionFreeTrialDaysState(form);

    if (!trialToggle.checked) {
        return true;
    }

    const value = trialDays.value.trim();
    const days = Number(value);
    if (value === '' || !Number.isFinite(days) || days < 1) {
        trialDays.setCustomValidity('Enter the number of free trial days.');
        trialDays.reportValidity();
        trialDays.focus();
        return false;
    }

    trialDays.setCustomValidity('');
    return true;
}

function handleSubmissionEditOptionToggle(checkbox) {
    const option = checkbox.closest('.sub-edit-option');
    if (option) {
        option.classList.toggle('sub-edit-option--selected', checkbox.checked);
        option.setAttribute('aria-checked', checkbox.checked ? 'true' : 'false');
    }
    updateSubmissionEditCounts();
}

function addSubmissionEditItem(kind) {
    const isFeature = kind === 'features';
    const input = document.getElementById(isFeature ? 'sub-edit-new-feature' : 'sub-edit-new-integration');
    const list = document.getElementById(`sub-edit-${kind}-list`);
    if (!input || !list) return;

    const name = input.value.trim();
    if (!name) {
        showNotification(`Enter a ${isFeature ? 'feature' : 'integration'} name first.`, 'error');
        return;
    }
    if (name.length > 100) {
        showNotification('Name can be max 100 characters.', 'error');
        return;
    }

    const key = normalizeSubmissionEditName(name);
    const existing = Array.from(list.querySelectorAll('.sub-edit-option'))
        .find(option => option.dataset.nameKey === key);

    if (existing) {
        const checkbox = existing.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = true;
        existing.hidden = false;
        existing.style.display = '';
        existing.scrollIntoView({ block: 'nearest' });
        updateSubmissionEditCounts();
        input.value = '';
        return;
    }

    list.querySelector('.sub-edit-empty-option')?.remove();

    const option = document.createElement('div');
    option.className = 'sub-edit-option sub-edit-option--new';
    option.dataset.nameKey = key;
    option.setAttribute('aria-checked', 'true');

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = true;
    checkbox.className = isFeature ? 'sub-edit-feature-check' : 'sub-edit-integration-check';
    checkbox.dataset.newName = name;
    checkbox.addEventListener('change', () => handleSubmissionEditOptionToggle(checkbox));

    if (isFeature) {
        const advanced = document.getElementById('sub-edit-new-feature-advanced')?.checked || false;
        checkbox.dataset.advanced = advanced ? '1' : '0';
    }

    const text = document.createElement('span');
    text.className = 'sub-edit-option-name';
    text.textContent = name;

    const badge = document.createElement('span');
    badge.className = 'sub-edit-option-badge sub-edit-option-badge--new';
    badge.textContent = 'New';

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'sub-edit-delete-item-btn';
    deleteBtn.title = `Remove this new ${isFeature ? 'feature' : 'integration'}`;
    deleteBtn.textContent = 'Delete';
    deleteBtn.addEventListener('click', event => deleteSubmissionEditNewItem(deleteBtn, event));

    option.append(checkbox, text, badge);
    if (isFeature && checkbox.dataset.advanced === '1') {
        const advancedBadge = document.createElement('span');
        advancedBadge.className = 'sub-edit-option-badge';
        advancedBadge.textContent = 'Advanced';
        option.appendChild(advancedBadge);
    }
    option.appendChild(deleteBtn);

    list.prepend(option);
    input.value = '';
    if (isFeature) {
        const advancedInput = document.getElementById('sub-edit-new-feature-advanced');
        if (advancedInput) advancedInput.checked = false;
    }
    updateSubmissionEditCounts();
}

function updateSubmissionEditCounts() {
    document.querySelectorAll('.sub-edit-option input[type="checkbox"]').forEach(checkbox => {
        const option = checkbox.closest('.sub-edit-option');
        if (!option) return;

        option.classList.toggle('sub-edit-option--selected', checkbox.checked);
        option.setAttribute('aria-checked', checkbox.checked ? 'true' : 'false');
    });

    const featureCount = document.querySelectorAll('#sub-edit-features-list .sub-edit-feature-check:checked').length;
    const integrationCount = document.querySelectorAll('#sub-edit-integrations-list .sub-edit-integration-check:checked').length;

    const featureCounter = document.getElementById('sub-edit-features-count');
    const integrationCounter = document.getElementById('sub-edit-integrations-count');

    if (featureCounter) featureCounter.textContent = `${featureCount} selected`;
    if (integrationCounter) integrationCounter.textContent = `${integrationCount} selected`;
}

async function deleteSubmissionEditFeature(featureId, btn, event) {
    event?.preventDefault();
    event?.stopPropagation();

    if (btn.disabled) return;

    const option = btn.closest('.sub-edit-option');
    const featureName = option?.querySelector('.sub-edit-option-name')?.textContent?.trim() || 'this feature';
    const form = document.getElementById('sub-edit-form');
    const chatbotId = form?.dataset.chatbotId || '';

    if (!chatbotId) {
        showNotification('Could not find the current chatbot.', 'error');
        return;
    }

    if (!confirm(`Delete feature "${featureName}"?`)) {
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Deleting...';

    try {
        const res = await fetch('app/adminSubmissionActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_feature', id: chatbotId, feature_id: featureId }),
        });
        const result = await res.json();

        if (!result.success) {
            showNotification(result.message || 'Could not delete feature.', 'error');
            btn.disabled = false;
            btn.textContent = 'Delete';
            return;
        }

        option?.remove();
        updateSubmissionEditCounts();
        showNotification('Feature deleted.', 'success');
    } catch (err) {
        console.error(err);
        showNotification('Could not delete feature.', 'error');
        btn.disabled = false;
        btn.textContent = 'Delete';
    }
}

function deleteSubmissionEditNewItem(btn, event) {
    event?.preventDefault();
    event?.stopPropagation();

    const option = btn.closest('.sub-edit-option');
    if (!option) return;

    const itemName = option.querySelector('.sub-edit-option-name')?.textContent?.trim() || 'this item';
    if (!confirm(`Remove "${itemName}" from this edit?`)) {
        return;
    }

    option.remove();
    updateSubmissionEditCounts();
}

async function saveSubmissionEdit(event, id) {
    event.preventDefault();

    const form = document.getElementById('sub-edit-form');
    if (!form) return;

    if (!validateSubmissionFreeTrialDays(form)) {
        return;
    }

    const saveBtn = form.querySelector('[data-sub-edit-save]');
    const featureIds = [];
    const integrationIds = [];
    const newFeatures = [];
    const newIntegrations = [];

    form.querySelectorAll('.sub-edit-feature-check:checked').forEach(checkbox => {
        if (checkbox.dataset.id) {
            featureIds.push(checkbox.dataset.id);
        } else if (checkbox.dataset.newName) {
            newFeatures.push({
                name: checkbox.dataset.newName,
                advanced: checkbox.dataset.advanced === '1',
            });
        }
    });

    form.querySelectorAll('.sub-edit-integration-check:checked').forEach(checkbox => {
        if (checkbox.dataset.id) {
            integrationIds.push(checkbox.dataset.id);
        } else if (checkbox.dataset.newName) {
            newIntegrations.push({ name: checkbox.dataset.newName });
        }
    });

    const payload = {
        action: 'save_edit_data',
        id,
        fields: {
            name: form.elements['name'].value.trim(),
            website_url: form.elements['website_url'].value.trim(),
            description: form.elements['description'].value.trim(),
            logo_url: form.elements['logo_url'].value.trim(),
            pricing_url: form.elements['pricing_url'].value.trim(),
            has_affiliate_program: form.elements['has_affiliate_program'].checked,
            affiliate_link: form.elements['affiliate_link'].value.trim(),
            affiliate_commission_rate: form.elements['affiliate_commission_rate'].value.trim(),
            affiliate_commission_type: form.elements['affiliate_commission_type'].value,
            affiliate_cookie_duration: form.elements['affiliate_cookie_duration'].value.trim(),
            pricing_model: form.elements['pricing_model'].value,
            starting_price: form.elements['starting_price'].value.trim(),
            pricing_currency: form.elements['pricing_currency'].value,
            has_free_trial: form.elements['has_free_trial'].checked,
            free_trial_days: form.elements['free_trial_days'].value.trim(),
        },
        feature_ids: featureIds,
        integration_ids: integrationIds,
        new_features: newFeatures,
        new_integrations: newIntegrations,
    };

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }

    try {
        const res = await fetch('app/adminSubmissionActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await res.json();

        if (!result.success) {
            showNotification(result.message || 'Could not save listing details.', 'error');
            return;
        }

        const body = document.querySelector('#sub-edit-popup .sub-edit-popup-body');
        const scrollTop = body ? body.scrollTop : 0;

        updateSubmissionCardAfterEdit(id, result.data.chatbot);
        renderSubmissionEditPopup(result.data);

        requestAnimationFrame(() => {
            const nextBody = document.querySelector('#sub-edit-popup .sub-edit-popup-body');
            if (nextBody) nextBody.scrollTop = scrollTop;
        });

        const ready = !!result.data.validation?.valid;
        showNotification(
            ready ? 'Listing saved.' : 'Listing saved. Some accept requirements are still missing.',
            ready ? 'success' : 'info'
        );
    } catch (err) {
        console.error(err);
        showNotification('Could not save listing details.', 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Listing';
        }
    }
}

function updateSubmissionCardAfterEdit(id, chatbot) {
    const card = document.querySelector(`#submissions-tab .admin-message-card[data-id="${id}"]`);
    if (!card || !chatbot) return;

    const nameEl = card.querySelector('.admin-message-sender');
    if (nameEl) nameEl.textContent = chatbot.name || '';

    const linkEl = card.querySelector('.admin-message-subject a');
    if (linkEl) {
        linkEl.href = chatbot.website_url || '#';
        linkEl.textContent = chatbot.website_url || '';
    }

    const row = card.querySelector('.admin-message-sender-row');
    const currentLogo = row?.querySelector('.admin-submission-logo');
    if (!row || !currentLogo) return;

    let nextLogo;
    if (chatbot.logo_url) {
        nextLogo = document.createElement('img');
        nextLogo.src = submissionEditAssetSrc(chatbot.logo_url);
        nextLogo.alt = 'logo';
        nextLogo.className = 'admin-submission-logo';
    } else {
        nextLogo = document.createElement('div');
        nextLogo.className = 'admin-submission-logo admin-submission-logo--placeholder';
        nextLogo.textContent = 'Bot';
    }

    currentLogo.replaceWith(nextLogo);
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
