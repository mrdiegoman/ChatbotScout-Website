// ============================================
// HELPERS
// ============================================

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function nlToBr(str) {
    if (!str) return '';
    return escHtml(str).replace(/\n/g, '<br>');
}

// ============================================
// MESSAGE CARD BUILDER
// ============================================

function buildMessageCard(msg) {
    const id          = escHtml(msg.id);
    const name        = escHtml(msg.first_name + ' ' + msg.last_name);
    const email       = escHtml(msg.email);
    const status      = escHtml(msg.status);
    const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
    const isHelpful   = msg.helpful;

    const helpfulBadge  = isHelpful ? `<span class="admin-helpful-badge">⭐ Helpful</span>` : '';
    const subjectBlock  = msg.subject
        ? `<div class="admin-message-subject">📌 ${escHtml(msg.subject)}</div>` : '';
    const responseBlock = msg.response
        ? `<div class="admin-existing-response"><strong>Your response:</strong><p>${nlToBr(msg.response)}</p></div>` : '';

    const statusBtns = ['new', 'replied', 'resolved', 'spam'].map(s => {
        const label  = s.charAt(0).toUpperCase() + s.slice(1);
        const active = msg.status === s ? ' active' : '';
        return `<button class="admin-status-btn${active}" data-status="${s}"
                    onclick="updateStatus('${id}', '${s}', this)">${label}</button>`;
    }).join('');

    const helpfulActive = isHelpful ? ' active' : '';

    const ipBlock  = msg.ip_address
        ? `<span>IP: ${escHtml(msg.ip_address)}</span>` : '';
    const respBlock = msg.first_responded_at_formatted
        ? `<span>First responded: ${escHtml(msg.first_responded_at_formatted)}</span>` : '';
    const resolvedBlock = msg.resolved_at_formatted
        ? `<span>Resolved: ${escHtml(msg.resolved_at_formatted)}</span>` : '';
    const hasMeta  = ipBlock || respBlock || resolvedBlock;

    return `
    <div class="admin-message-card ${isHelpful ? 'is-helpful' : ''}" data-id="${id}">
        <div class="admin-message-card-header">
            <div class="admin-message-card-header-left">
                <div class="admin-message-sender-row">
                    <span class="admin-message-sender">${name}</span>
                    ${helpfulBadge}
                </div>
                <span class="admin-message-email">${email}</span>
            </div>
            <div class="admin-message-card-header-right">
                <span class="admin-status-badge status-${status}">${statusLabel}</span>
                <span class="admin-message-date">${escHtml(msg.created_at_formatted)}</span>
            </div>
        </div>

        ${subjectBlock}

        <div class="admin-message-body">${nlToBr(msg.message)}</div>

        ${responseBlock}

        <div class="admin-message-actions">
            <div class="admin-status-actions">${statusBtns}</div>
            <div class="admin-toggle-actions">
                <button class="admin-helpful-btn${helpfulActive}" onclick="toggleHelpful('${id}', this)">⭐ Helpful</button>
                <button class="admin-toggle-btn" onclick="toggleSection(this, 'reply-${id}')">✏️ Reply</button>
                <button class="admin-toggle-btn" onclick="toggleSection(this, 'notes-${id}')">📝 Notes</button>
            </div>
        </div>

        <div class="admin-reply-section" id="reply-${id}" style="display:none;">
            <label>Reply to ${escHtml(msg.first_name)}
                <span class="admin-reply-hint">(email will be sent to ${email})</span>
            </label>
            <textarea class="admin-textarea" placeholder="Write your reply here...">${escHtml(msg.response || '')}</textarea>
            <button class="admin-save-btn" onclick="sendReply('${id}', this)">📨 Send Reply</button>
        </div>

        <div class="admin-notes-section" id="notes-${id}" style="display:none;">
            <label>Internal Notes <span class="admin-reply-hint">(not visible to user)</span></label>
            <textarea class="admin-textarea" placeholder="Add internal notes here...">${escHtml(msg.internal_notes || '')}</textarea>
            <button class="admin-save-btn" onclick="saveNotes('${id}', this)">Save Notes</button>
        </div>

        ${hasMeta ? `<div class="admin-message-meta">${ipBlock}${respBlock}${resolvedBlock}</div>` : ''}
    </div>`;
}

// ============================================
// REVIEW CARD BUILDER
// ============================================

function buildReviewCard(review) {
    const id          = escHtml(review.id);
    const name        = escHtml(review.first_name + ' ' + review.last_name);
    const email       = escHtml(review.email);
    const status      = escHtml(review.status);
    const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
    const rating      = parseInt(review.rating, 10);

    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<span class="${i <= rating ? 'star-filled' : 'star-empty'}">★</span>`;
    }

    const titleBlock = review.title
        ? `<div class="admin-review-title">"${escHtml(review.title)}"</div>` : '';
    const notesBlock = review.moderation_notes
        ? `<div class="admin-existing-response"><strong>Moderation notes:</strong><p>${nlToBr(review.moderation_notes)}</p></div>` : '';

    const reviewStatusBtns = { approved: 'Approve', rejected: 'Reject', flagged: 'Flag', removed: 'Remove' };
    const statusBtns = Object.entries(reviewStatusBtns).map(([s, label]) => {
        const active = review.status === s ? ' active' : '';
        return `<button class="admin-status-btn review-status-action-btn${active}" data-status="${s}"
                    onclick="updateReviewStatus('${id}', '${s}', this)">${label}</button>`;
    }).join('');

    const moderatedBlock = review.moderated_at_formatted
        ? `<div class="admin-message-meta"><span>Moderated: ${escHtml(review.moderated_at_formatted)}</span></div>` : '';

    return `
    <div class="admin-message-card admin-review-card" data-id="${id}">
        <div class="admin-message-card-header">
            <div class="admin-message-card-header-left">
                <div class="admin-message-sender-row">
                    <span class="admin-message-sender">${name}</span>
                    <span class="admin-review-stars">
                        ${stars}
                        <span class="admin-review-rating-num">${rating}/5</span>
                    </span>
                </div>
                <span class="admin-message-email">${email}</span>
            </div>
            <div class="admin-message-card-header-right">
                <span class="admin-status-badge review-status-${status}">${statusLabel}</span>
                <span class="admin-message-date">${escHtml(review.created_at_formatted)}</span>
            </div>
        </div>

        <div class="admin-message-subject">🤖 ${escHtml(review.chatbot_name)}</div>

        ${titleBlock}

        <div class="admin-message-body">${nlToBr(review.review_text)}</div>

        ${notesBlock}

        <div class="admin-message-actions">
            <div class="admin-status-actions">${statusBtns}</div>
            <div class="admin-toggle-actions">
                <button class="admin-toggle-btn" onclick="toggleSection(this, 'review-notes-${id}')">📝 Notes</button>
            </div>
        </div>

        <div class="admin-notes-section" id="review-notes-${id}" style="display:none;">
            <label>Moderation Notes <span class="admin-reply-hint">(internal only)</span></label>
            <textarea class="admin-textarea"
                      placeholder="e.g. Rejected — contains spam links...">${escHtml(review.moderation_notes || '')}</textarea>
            <button class="admin-save-btn" onclick="saveReviewNotes('${id}', this)">Save Notes</button>
        </div>

        ${moderatedBlock}
    </div>`;
}

// ============================================
// REFRESH MESSAGES
// ============================================

async function refreshMessages(btn) {
    const activeSubTab = document.querySelector('#messages-tab .admin-tabs .admin-tab.active');
    let status  = 'all';
    let helpful = '';
    if (activeSubTab) {
        const href = activeSubTab.getAttribute('href') || '';
        if (href.includes('helpful')) {
            helpful = '&helpful';
        } else {
            const match = href.match(/status=([^&]+)/);
            status = match ? match[1] : 'all';
        }
    }
    const url = `../api/getAdminData.php?type=messages&status=${encodeURIComponent(status)}${helpful}`;

    btn.classList.add('spinning');
    btn.disabled = true;

    try {
        const res    = await fetch(url);
        const data   = await res.json();

        if (!data.success) {
            showNotification(data.message || 'Refresh failed', 'error');
            return;
        }

        // Rebuild cards
        const container = document.querySelector('#messages-tab .admin-messages');
        if (data.messages.length === 0) {
            container.innerHTML = `
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p>No messages found</p>
                </div>`;
        } else {
            container.innerHTML = data.messages.map(buildMessageCard).join('');
        }

        // Update main tab badge
        updateMainTabBadge('messages', data.counts.new);

        // Update sub-tab badges
        const subBadges = document.querySelectorAll('#messages-tab .admin-tabs .admin-tab');
        subBadges.forEach(tab => {
            const href   = tab.getAttribute('href') || '';
            const badge  = tab.querySelector('.admin-tab-badge');
            if (!badge) return;

            if (tab.classList.contains('admin-tab-helpful')) {
                badge.textContent = data.counts.helpful ?? 0;
            } else {
                const match = href.match(/status=([^&]+)/);
                const key   = match ? match[1] : 'all';
                badge.textContent = data.counts[key] ?? 0;
            }
        });

        showNotification('Messages refreshed', 'success');
    } catch (err) {
        console.error(err);
        showNotification('Refresh failed', 'error');
    } finally {
        btn.classList.remove('spinning');
        btn.disabled = false;
    }
}

// ============================================
// REFRESH REVIEWS
// ============================================

async function refreshReviews(btn) {
    const activeSubTab = document.querySelector('#reviews-tab .admin-tabs .admin-tab.active');
    let reviewStatus = 'all';
    if (activeSubTab) {
        const href  = activeSubTab.getAttribute('href') || '';
        const match = href.match(/review_status=([^&]+)/);
        reviewStatus = match ? match[1] : 'all';
    }
    const url = `../api/getAdminData.php?type=reviews&review_status=${encodeURIComponent(reviewStatus)}`;

    btn.classList.add('spinning');
    btn.disabled = true;

    try {
        const res  = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            showNotification(data.message || 'Refresh failed', 'error');
            return;
        }

        // Rebuild cards
        const container = document.querySelector('#reviews-tab .admin-messages');
        if (data.reviews.length === 0) {
            container.innerHTML = `
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <p>No reviews found</p>
                </div>`;
        } else {
            container.innerHTML = data.reviews.map(buildReviewCard).join('');
        }

        // Update main tab badge
        updateMainTabBadge('reviews', data.counts.pending);

        // Update sub-tab badges
        const subBadges = document.querySelectorAll('#reviews-tab .admin-tabs .admin-tab');
        subBadges.forEach(tab => {
            const href  = tab.getAttribute('href') || '';
            const badge = tab.querySelector('.admin-tab-badge');
            if (!badge) return;
            const match = href.match(/review_status=([^&]+)/);
            const key   = match ? match[1] : 'all';
            badge.textContent = data.counts[key] ?? 0;
        });

        showNotification('Reviews refreshed', 'success');
    } catch (err) {
        console.error(err);
        showNotification('Refresh failed', 'error');
    } finally {
        btn.classList.remove('spinning');
        btn.disabled = false;
    }
}

// ============================================
// UPDATE MAIN TAB BADGE
// ============================================

function updateMainTabBadge(tab, count) {
    const tabBtn = document.querySelector(`.admin-main-tab-btn[data-tab="${tab}"]`);
    if (!tabBtn) return;

    let badge = tabBtn.querySelector('.admin-main-tab-count');
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'admin-main-tab-count';
            tabBtn.appendChild(badge);
        }
        badge.textContent = count;
    } else {
        if (badge) badge.remove();
    }
}