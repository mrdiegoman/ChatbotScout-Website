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
// STATE — offset pre každú sekciu
// ============================================

const adminState = {
    messages:    { offset: 0, hasMore: false, total: 0 },
    reviews:     { offset: 0, hasMore: false, total: 0 },
    submissions: { offset: 0, hasMore: false, total: 0 },
};

const PAGE_SIZE = 10;

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

    const ipBlock       = msg.ip_address                  ? `<span>IP: ${escHtml(msg.ip_address)}</span>` : '';
    const respBlock     = msg.first_responded_at_formatted ? `<span>First responded: ${escHtml(msg.first_responded_at_formatted)}</span>` : '';
    const resolvedBlock = msg.resolved_at_formatted        ? `<span>Resolved: ${escHtml(msg.resolved_at_formatted)}</span>` : '';
    const hasMeta       = ipBlock || respBlock || resolvedBlock;

    // FIX #2: data-status pridaný na kartu
    return `
    <div class="admin-message-card ${isHelpful ? 'is-helpful' : ''}" data-id="${id}" data-status="${status}">
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

    // FIX #2: data-status pridaný na kartu
    return `
    <div class="admin-message-card admin-review-card" data-id="${id}" data-status="${status}">
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
// SUBMISSION CARD BUILDER
// ============================================

function buildSubmissionCard(sub) {
    const id          = escHtml(sub.id);
    const name        = escHtml(sub.name);
    const status      = escHtml(sub.status);
    const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

    const logoHtml = sub.logo_url
        ? `<img src="../${escHtml(sub.logo_url)}" alt="logo" class="admin-submission-logo">`
        : `<div class="admin-submission-logo admin-submission-logo--placeholder">🤖</div>`;

    const accessNoteBlock = sub.access_note
        ? `<div class="admin-message-body"><strong>Access note:</strong><br>${nlToBr(sub.access_note)}</div>` : '';

    const adminNotesBlock = sub.admin_notes
        ? `<div class="admin-existing-response"><strong>Admin notes:</strong><p>${nlToBr(sub.admin_notes)}</p></div>` : '';

    const submissionStatusBtns = { reviewed: 'Approve', rejected: 'Reject' };
    const statusBtns = Object.entries(submissionStatusBtns).map(([s, label]) => {
        const active = sub.status === s ? ' active' : '';
        return `<button class="admin-status-btn${active}" data-status="${s}"
                    onclick="updateSubmissionStatus('${id}', '${s}', this)">${label}</button>`;
    }).join('');

    // FIX #2: data-status pridaný na kartu
    return `
    <div class="admin-message-card admin-submission-card" data-id="${id}" data-status="${status}">
        <div class="admin-message-card-header">
            <div class="admin-message-card-header-left">
                <div class="admin-message-sender-row">
                    ${logoHtml}
                    <span class="admin-message-sender">${name}</span>
                </div>
                <span class="admin-message-email">
                    Submitted by ${escHtml(sub.first_name + ' ' + sub.last_name)} (${escHtml(sub.user_email)})
                </span>
            </div>
            <div class="admin-message-card-header-right">
                <span class="admin-status-badge submission-status-${status}">${statusLabel}</span>
                <span class="admin-message-date">${escHtml(sub.submitted_at_formatted)}</span>
            </div>
        </div>

        <div class="admin-message-subject">
            🌐 <a href="${escHtml(sub.website_url)}" target="_blank" rel="noopener">${escHtml(sub.website_url)}</a>
        </div>

        ${accessNoteBlock}
        ${adminNotesBlock}

        <div class="admin-message-actions">
            <div class="admin-status-actions">${statusBtns}</div>
            <div class="admin-toggle-actions">
                <button class="admin-toggle-btn" onclick="toggleSection(this, 'sub-notes-${id}')">📝 Notes</button>
            </div>
        </div>

        <div class="admin-notes-section" id="sub-notes-${id}" style="display:none;">
            <label>Admin Notes <span class="admin-reply-hint">(internal only)</span></label>
            <textarea class="admin-textarea" placeholder="Add notes about this submission...">${escHtml(sub.admin_notes || '')}</textarea>
            <button class="admin-save-btn" onclick="saveSubmissionNotes('${id}', this)">Save Notes</button>
        </div>
    </div>`;
}

// ============================================
// LOAD MORE BAR — update visibility + counter
// ============================================

function updateLoadMoreBar(section) {
    const state   = adminState[section];
    const barId   = section === 'messages'    ? 'messagesLoadMoreBar'
                  : section === 'reviews'     ? 'reviewsLoadMoreBar'
                  : 'submissionsLoadMoreBar';
    const bar     = document.getElementById(barId);
    if (!bar) return;

    const showMoreBtn   = bar.querySelector('.admin-show-more-btn');
    const counterEl     = bar.querySelector('.admin-load-more-counter');

    const containerSel  = section === 'messages'    ? '#messages-tab .admin-messages'
                        : section === 'reviews'     ? '#reviews-tab .admin-messages'
                        : '#submissions-tab .admin-messages';
    const container     = document.querySelector(containerSel);
    const cardCount     = container ? container.querySelectorAll('.admin-message-card').length : 0;

    bar.style.display = cardCount > 0 ? 'flex' : 'none';

    if (showMoreBtn) {
        showMoreBtn.style.display = state.hasMore ? 'inline-flex' : 'none';
    }

    if (counterEl) {
        counterEl.textContent = `Showing ${cardCount} of ${state.total}`;
    }
}

// ============================================
// SCROLL HELPER — scrollne na kartu s offsetom od vrchu (30% viewportu)
// ============================================

function scrollToCard(card) {
    const cardTop    = card.getBoundingClientRect().top + window.scrollY;
    const offset     = window.innerHeight * 0.30;
    window.scrollTo({ top: cardTop - offset, behavior: 'smooth' });
}



async function scrollToOldest(section) {
    const btnId = section === 'messages' ? '#messagesScrollOldestBtn' : '#reviewsScrollOldestBtn';
    const btn   = document.querySelector(btnId);
    if (btn) { btn.disabled = true; btn.textContent = 'Loading...'; }

    try {
        // 1. Opýtaj sa API na ID najstaršieho new/pending záznamu
        const res  = await fetch(`../api/getOldestNewItem.php?type=${section}`);
        const data = await res.json();

        if (!data.success) throw new Error(data.message);

        if (!data.id) {
            showNotification('No new items found', 'info');
            return;
        }

        const targetId = data.id;

        // 2. Skontroluj či karta už je v DOM
        const containerSel = section === 'messages'
            ? '#messages-tab .admin-messages'
            : '#reviews-tab .admin-messages';

        let targetCard = document.querySelector(`${containerSel} .admin-message-card[data-id="${targetId}"]`);

        // 3. Načítavaj chunky len kým sa karta neobjaví v DOM
        while (!targetCard && adminState[section].hasMore) {
            await loadAllChunk(section);
            targetCard = document.querySelector(`${containerSel} .admin-message-card[data-id="${targetId}"]`);
        }

        // 4. Scrollni na kartu
        if (targetCard) {
            scrollToCard(targetCard);
        } else {
            showNotification('No new items found', 'info');
        }

    } catch (err) {
        console.error(err);
        showNotification('Failed to jump to oldest', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = '↓ Jump to oldest'; }
    }
}

// Interný helper — načíta jeden chunk bez tlačidla
async function loadAllChunk(section) {
    const state = adminState[section];

    let url;
    if (section === 'messages') {
        const activeSubTab = document.querySelector('#messages-tab .admin-tabs .admin-tab.active');
        let status = 'all', helpful = '';
        if (activeSubTab) {
            const href = activeSubTab.getAttribute('href') || '';
            if (href.includes('helpful')) { helpful = '&helpful'; }
            else { const m = href.match(/status=([^&]+)/); status = m ? m[1] : 'all'; }
        }
        url = `../api/getAdminData.php?type=messages&status=${encodeURIComponent(status)}${helpful}&limit=50&offset=${state.offset}`;
    } else {
        const activeSubTab = document.querySelector('#reviews-tab .admin-tabs .admin-tab.active');
        let reviewStatus = 'all';
        if (activeSubTab) {
            const href = activeSubTab.getAttribute('href') || '';
            const m = href.match(/review_status=([^&]+)/);
            reviewStatus = m ? m[1] : 'all';
        }
        url = `../api/getAdminData.php?type=reviews&review_status=${encodeURIComponent(reviewStatus)}&limit=50&offset=${state.offset}`;
    }

    const res  = await fetch(url);
    const data = await res.json();
    if (!data.success) throw new Error(data.message);

    const barId = section === 'messages' ? 'messagesLoadMoreBar' : 'reviewsLoadMoreBar';
    const bar   = document.getElementById(barId);
    const items = section === 'messages' ? data.messages : data.reviews;
    const html  = section === 'messages'
        ? items.map(buildMessageCard).join('')
        : items.map(buildReviewCard).join('');

    bar.insertAdjacentHTML('beforebegin', html);

    state.offset  += items.length;
    state.hasMore  = data.hasMore;
    state.total    = data.total;

    updateLoadMoreBar(section);
}

// ============================================
// LOAD MORE MESSAGES
// ============================================

async function loadMoreMessages(btn) {
    btn.disabled    = true;
    btn.textContent = 'Loading...';

    const state = adminState.messages;

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

    const url = `../api/getAdminData.php?type=messages&status=${encodeURIComponent(status)}${helpful}&limit=${PAGE_SIZE}&offset=${state.offset}`;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const bar = document.getElementById('messagesLoadMoreBar');
        bar.insertAdjacentHTML('beforebegin', data.messages.map(buildMessageCard).join(''));

        state.offset  += data.messages.length;
        state.hasMore = data.hasMore;
        state.total   = data.total;

        updateLoadMoreBar('messages');
    } catch (err) {
        console.error(err);
        showNotification('Failed to load more messages', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Show more';
    }
}

// ============================================
// LOAD MORE REVIEWS
// ============================================

async function loadMoreReviews(btn) {
    btn.disabled    = true;
    btn.textContent = 'Loading...';

    const state = adminState.reviews;

    const activeSubTab = document.querySelector('#reviews-tab .admin-tabs .admin-tab.active');
    let reviewStatus = 'all';
    if (activeSubTab) {
        const href  = activeSubTab.getAttribute('href') || '';
        const match = href.match(/review_status=([^&]+)/);
        reviewStatus = match ? match[1] : 'all';
    }

    const url = `../api/getAdminData.php?type=reviews&review_status=${encodeURIComponent(reviewStatus)}&limit=${PAGE_SIZE}&offset=${state.offset}`;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const bar = document.getElementById('reviewsLoadMoreBar');
        bar.insertAdjacentHTML('beforebegin', data.reviews.map(buildReviewCard).join(''));

        state.offset  += data.reviews.length;
        state.hasMore = data.hasMore;
        state.total   = data.total;

        updateLoadMoreBar('reviews');
    } catch (err) {
        console.error(err);
        showNotification('Failed to load more reviews', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Show more';
    }
}

// ============================================
// REFRESH MESSAGES — reset na offset 0
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

    const url = `../api/getAdminData.php?type=messages&status=${encodeURIComponent(status)}${helpful}&limit=${PAGE_SIZE}&offset=0`;

    btn.classList.add('spinning');
    btn.disabled = true;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) { showNotification(data.message || 'Refresh failed', 'error'); return; }

        const container = document.querySelector('#messages-tab .admin-messages');

        // FIX #1: Vymaž iba karty a empty state, nie celý container (zachová load-more bar)
        container.querySelectorAll('.admin-message-card, .admin-empty').forEach(el => el.remove());

        const bar = document.getElementById('messagesLoadMoreBar');
        if (data.messages.length === 0) {
            bar.insertAdjacentHTML('beforebegin', `
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p>No messages found</p>
                </div>`);
        } else {
            bar.insertAdjacentHTML('beforebegin', data.messages.map(buildMessageCard).join(''));
        }

        // Reset state
        adminState.messages.offset  = data.messages.length;
        adminState.messages.hasMore = data.hasMore;
        adminState.messages.total   = data.total;
        updateLoadMoreBar('messages');

        // Update badges
        updateMainTabBadge('messages', data.counts.new);
        document.querySelectorAll('#messages-tab .admin-tabs .admin-tab').forEach(tab => {
            const href  = tab.getAttribute('href') || '';
            const badge = tab.querySelector('.admin-tab-badge');
            if (!badge) return;
            if (tab.classList.contains('admin-tab-helpful')) {
                badge.textContent = data.counts.helpful ?? 0;
            } else {
                const match = href.match(/status=([^&]+)/);
                badge.textContent = data.counts[match ? match[1] : 'all'] ?? 0;
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
// REFRESH REVIEWS — reset na offset 0
// ============================================

async function refreshReviews(btn) {
    const activeSubTab = document.querySelector('#reviews-tab .admin-tabs .admin-tab.active');
    let reviewStatus = 'all';
    if (activeSubTab) {
        const href  = activeSubTab.getAttribute('href') || '';
        const match = href.match(/review_status=([^&]+)/);
        reviewStatus = match ? match[1] : 'all';
    }

    const url = `../api/getAdminData.php?type=reviews&review_status=${encodeURIComponent(reviewStatus)}&limit=${PAGE_SIZE}&offset=0`;

    btn.classList.add('spinning');
    btn.disabled = true;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) { showNotification(data.message || 'Refresh failed', 'error'); return; }

        const container = document.querySelector('#reviews-tab .admin-messages');

        // FIX #1: Vymaž iba karty a empty state, nie celý container (zachová load-more bar)
        container.querySelectorAll('.admin-message-card, .admin-empty').forEach(el => el.remove());

        const bar = document.getElementById('reviewsLoadMoreBar');
        if (data.reviews.length === 0) {
            bar.insertAdjacentHTML('beforebegin', `
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <p>No reviews found</p>
                </div>`);
        } else {
            bar.insertAdjacentHTML('beforebegin', data.reviews.map(buildReviewCard).join(''));
        }

        // Reset state
        adminState.reviews.offset  = data.reviews.length;
        adminState.reviews.hasMore = data.hasMore;
        adminState.reviews.total   = data.total;
        updateLoadMoreBar('reviews');

        // Update badges
        updateMainTabBadge('reviews', data.counts.pending);
        document.querySelectorAll('#reviews-tab .admin-tabs .admin-tab').forEach(tab => {
            const href  = tab.getAttribute('href') || '';
            const badge = tab.querySelector('.admin-tab-badge');
            if (!badge) return;
            const match = href.match(/review_status=([^&]+)/);
            badge.textContent = data.counts[match ? match[1] : 'all'] ?? 0;
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
// LOAD MORE SUBMISSIONS
// ============================================

async function loadMoreSubmissions(btn) {
    btn.disabled    = true;
    btn.textContent = 'Loading...';

    const state = adminState.submissions;

    const activeTab = document.querySelector('#submissions-tab .admin-tabs .admin-tab.active');
    let subStatus   = 'pending';
    if (activeTab) {
        const href  = activeTab.getAttribute('href') || '';
        const match = href.match(/sub_status=([^&]+)/);
        subStatus   = match ? match[1] : 'pending';
    }

    const url = `../api/getAdminSubmissions.php?sub_status=${encodeURIComponent(subStatus)}&limit=${PAGE_SIZE}&offset=${state.offset}`;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const bar = document.getElementById('submissionsLoadMoreBar');
        bar.insertAdjacentHTML('beforebegin', data.submissions.map(buildSubmissionCard).join(''));

        state.offset  += data.submissions.length;
        state.hasMore  = data.hasMore;
        state.total    = data.total;

        updateLoadMoreBar('submissions');
    } catch (err) {
        console.error(err);
        showNotification('Failed to load more submissions', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Show more';
    }
}

// ============================================
// SCROLL TO OLDEST — SUBMISSIONS (smart)
// ============================================

async function scrollToOldestSubmissions() {
    const btn = document.getElementById('submissionsScrollOldestBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Loading...'; }

    try {
        // 1. Opýtaj sa API na ID najstaršieho pending submission
        const res  = await fetch('../api/getOldestNewItem.php?type=submissions');
        const data = await res.json();

        if (!data.success) throw new Error(data.message);

        if (!data.id) {
            showNotification('No new items found', 'info');
            return;
        }

        const targetId = data.id;

        // 2. Skontroluj či karta už je v DOM
        let targetCard = document.querySelector(
            `#submissions-tab .admin-messages .admin-message-card[data-id="${targetId}"]`
        );

        // 3. Načítavaj chunky len kým sa karta neobjaví
        while (!targetCard && adminState.submissions.hasMore) {
            const state     = adminState.submissions;
            const activeTab = document.querySelector('#submissions-tab .admin-tabs .admin-tab.active');
            let subStatus   = 'pending';
            if (activeTab) {
                const href  = activeTab.getAttribute('href') || '';
                const match = href.match(/sub_status=([^&]+)/);
                subStatus   = match ? match[1] : 'pending';
            }
            const url      = `../api/getAdminSubmissions.php?sub_status=${encodeURIComponent(subStatus)}&limit=50&offset=${state.offset}`;
            const chunkRes = await fetch(url);
            const chunkData = await chunkRes.json();
            if (!chunkData.success) throw new Error(chunkData.message);

            const bar = document.getElementById('submissionsLoadMoreBar');
            bar.insertAdjacentHTML('beforebegin', chunkData.submissions.map(buildSubmissionCard).join(''));
            state.offset  += chunkData.submissions.length;
            state.hasMore  = chunkData.hasMore;
            state.total    = chunkData.total;
            updateLoadMoreBar('submissions');

            targetCard = document.querySelector(
                `#submissions-tab .admin-messages .admin-message-card[data-id="${targetId}"]`
            );
        }

        // 4. Scrollni na kartu
        if (targetCard) {
            scrollToCard(targetCard);
        } else {
            showNotification('No new items found', 'info');
        }

    } catch (err) {
        console.error(err);
        showNotification('Failed to jump to oldest', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = '↓ Jump to oldest'; }
    }
}

// ============================================
// REFRESH SUBMISSIONS
// ============================================

async function refreshSubmissions(btn) {
    const activeTab = document.querySelector('#submissions-tab .admin-tabs .admin-tab.active');
    let subStatus   = 'pending';
    if (activeTab) {
        const href  = activeTab.getAttribute('href') || '';
        const match = href.match(/sub_status=([^&]+)/);
        subStatus   = match ? match[1] : 'pending';
    }

    const url = `../api/getAdminSubmissions.php?sub_status=${encodeURIComponent(subStatus)}&limit=${PAGE_SIZE}&offset=0`;

    btn.classList.add('spinning');
    btn.disabled = true;

    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Refresh failed');

        const container = document.querySelector('#submissions-tab .admin-messages');

        // FIX #1: Vymaž iba karty a empty state, nie celý container (zachová load-more bar)
        container.querySelectorAll('.admin-message-card, .admin-empty').forEach(el => el.remove());

        const bar = document.getElementById('submissionsLoadMoreBar');
        if (data.submissions.length === 0) {
            bar.insertAdjacentHTML('beforebegin', `
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>No submissions found</p>
                </div>`);
        } else {
            bar.insertAdjacentHTML('beforebegin', data.submissions.map(buildSubmissionCard).join(''));
        }

        adminState.submissions.offset  = data.submissions.length;
        adminState.submissions.hasMore = data.hasMore;
        adminState.submissions.total   = data.total;
        updateLoadMoreBar('submissions');

        updateMainTabBadge('submissions', data.counts.pending ?? 0);
        document.querySelectorAll('#submissions-tab .admin-tabs .admin-tab').forEach(tab => {
            const href  = tab.getAttribute('href') || '';
            const badge = tab.querySelector('.admin-tab-badge');
            if (!badge) return;
            const match = href.match(/sub_status=([^&]+)/);
            badge.textContent = data.counts[match ? match[1] : 'all'] ?? 0;
        });

        showNotification('Submissions refreshed', 'success');
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

// ============================================
// INIT — nastav state z PHP-rendered kariet
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    ['messages', 'reviews', 'submissions'].forEach(section => {
        const selectorMap = {
            messages:    '#messages-tab .admin-messages .admin-message-card',
            reviews:     '#reviews-tab .admin-messages .admin-message-card',
            submissions: '#submissions-tab .admin-messages .admin-message-card',
        };
        const containerMap = {
            messages:    '#messages-tab .admin-messages',
            reviews:     '#reviews-tab .admin-messages',
            submissions: '#submissions-tab .admin-messages',
        };
        const cards     = document.querySelectorAll(selectorMap[section]);
        const container = document.querySelector(containerMap[section]);
        if (container) {
            adminState[section].offset  = cards.length;
            adminState[section].total   = parseInt(container.dataset.total  || cards.length, 10);
            adminState[section].hasMore = container.dataset.hasMore === 'true';
        }
        updateLoadMoreBar(section);
    });
});