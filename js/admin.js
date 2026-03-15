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
                        onclick="confirmAccept('${id}', this)">
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

    // "Change of mind" check for final states
    if (['active', 'rejected', 'removed'].includes(currentStatus)) {
        const chatbotName   = card.querySelector('.admin-message-sender')?.textContent?.trim() || 'this chatbot';
        // const clearedFields = currentStatus === 'active'   ? 'approved_at, verified_at, last_verified_at'
        //                     : currentStatus === 'rejected' ? 'rejected_at'
        //                     : 'deleted_at';
        const confirmed = await showChangeOfMindPopup(chatbotName);
        if (!confirmed) return;
    }

    clickedBtn.disabled = true;

    try {
        const res    = await fetch('../app/adminSubmissionActions.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'review', id }),
        });
        const result = await res.json();

        if (result.success) {
            updateCardStatus(id, 'reviewed');
            showNotification('Marked as reviewed.', 'success');
            if (result.pendingCount !== undefined) {
                updateMainTabBadge('submissions', result.pendingCount);
            }
        } else {
            showNotification(result.message || 'Failed to update status.', 'error');
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

    // "Change of mind" check for final states
    if (['active', 'removed'].includes(currentStatus)) {
        const chatbotName   = card.querySelector('.admin-message-sender')?.textContent?.trim() || 'this chatbot';
        // const clearedFields = currentStatus === 'active' ? 'approved_at, verified_at, last_verified_at' : 'deleted_at';
        const confirmed = await showChangeOfMindPopup(chatbotName);
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

    // "Change of mind" check for final states
    if (['active', 'rejected'].includes(currentStatus)) {
        // const clearedFields = currentStatus === 'active'   ? 'approved_at, verified_at, last_verified_at'
        //                     : 'rejected_at';
        const confirmed = await showChangeOfMindPopup(chatbotName);
        if (!confirmed) return;
    } else {
        // For pending/reviewed → still confirm remove (it's destructive)
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