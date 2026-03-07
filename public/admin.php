<?php
require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

$db = Database::getInstance()->getConnection();

// ============================================
// ACTIVE TAB
// ============================================

$activeTab   = $_GET['tab'] ?? 'messages';
$allowedTabs = ['messages', 'reviews', 'submissions', 'settings'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'messages';
}

// ============================================
// MESSAGES — filters + fetch
// ============================================

$allowedStatuses = ['all', 'new', 'replied', 'resolved', 'spam'];
$filterStatus    = $_GET['status']  ?? 'all';
$filterHelpful   = isset($_GET['helpful']);

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}

$whereParts = [];
$msgParams  = [];

if ($filterStatus !== 'all') {
    $whereParts[]         = 'status = :status';
    $msgParams['status']  = $filterStatus;
}
if ($filterHelpful) {
    $whereParts[] = 'helpful = 1';
}

$msgWhere = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Total count pre messages
    $msgTotalStmt = $db->prepare("SELECT COUNT(*) FROM contact_messages $msgWhere");
    $msgTotalStmt->execute($msgParams);
    $msgTotal   = (int)$msgTotalStmt->fetchColumn();
    $msgHasMore = $msgTotal > 10;

    $msgStmt = $db->prepare("
        SELECT id, first_name, last_name, email, subject, message,
               status, helpful, response, internal_notes, ip_address,
               created_at, first_responded_at, resolved_at
        FROM contact_messages
        $msgWhere
        ORDER BY CASE status WHEN 'new' THEN 0 ELSE 1 END, created_at DESC
        LIMIT 10
    ");
    $msgStmt->execute($msgParams);
    $messages = $msgStmt->fetchAll();

// Message counts
$msgCountStmt = $db->query("SELECT status, COUNT(*) as cnt FROM contact_messages GROUP BY status");
$msgCounts    = ['all' => 0, 'new' => 0, 'replied' => 0, 'resolved' => 0, 'spam' => 0];
foreach ($msgCountStmt->fetchAll() as $row) {
    $msgCounts[$row['status']] = (int)$row['cnt'];
    $msgCounts['all']         += (int)$row['cnt'];
}
$helpfulCount = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE helpful = 1")->fetchColumn();

// ============================================
// REVIEWS — filters + fetch
// ============================================

$allowedReviewStatuses = ['all', 'pending', 'active', 'rejected', 'flagged', 'removed'];
$filterReviewStatus    = $_GET['review_status'] ?? 'all';
if (!in_array($filterReviewStatus, $allowedReviewStatuses, true)) {
    $filterReviewStatus = 'all';
}

$reviewWhere  = $filterReviewStatus !== 'all' ? 'WHERE r.status = :status' : '';
$reviewParams = $filterReviewStatus !== 'all' ? ['status' => $filterReviewStatus] : [];

// Total count pre reviews
    $reviewTotalStmt = $db->prepare("
        SELECT COUNT(*) FROM reviews r
        JOIN users u    ON r.user_id    = u.id
        JOIN chatbots c ON r.chatbot_id = c.id
        $reviewWhere
    ");
    $reviewTotalStmt->execute($reviewParams);
    $reviewTotal   = (int)$reviewTotalStmt->fetchColumn();
    $reviewHasMore = $reviewTotal > 10;

    $reviewStmt = $db->prepare("
        SELECT
            r.id, r.rating, r.title, r.review_text,
            r.status, r.moderation_notes,
            r.created_at, r.moderated_at,
            u.first_name, u.last_name, u.email,
            c.name AS chatbot_name
        FROM reviews r
        JOIN users u    ON r.user_id    = u.id
        JOIN chatbots c ON r.chatbot_id = c.id
        $reviewWhere
        ORDER BY
            CASE r.status WHEN 'pending' THEN 0 ELSE 1 END,
            r.created_at DESC
        LIMIT 10
    ");
    $reviewStmt->execute($reviewParams);
    $reviews = $reviewStmt->fetchAll();

// Review counts per status
$reviewCountStmt = $db->query("SELECT status, COUNT(*) as cnt FROM reviews GROUP BY status");
$reviewCounts    = ['all' => 0, 'pending' => 0, 'active' => 0, 'rejected' => 0, 'flagged' => 0, 'removed' => 0];
foreach ($reviewCountStmt->fetchAll() as $row) {
    if (isset($reviewCounts[$row['status']])) {
        $reviewCounts[$row['status']] = (int)$row['cnt'];
    }
    $reviewCounts['all'] += (int)$row['cnt'];
}

// ============================================
// SUBMISSIONS — filters + fetch
// ============================================

$allowedSubStatuses = ['all', 'pending', 'active', 'rejected'];
$filterSubStatus    = $_GET['sub_status'] ?? 'pending';
if (!in_array($filterSubStatus, $allowedSubStatuses, true)) {
    $filterSubStatus = 'pending';
}

$subWhere  = $filterSubStatus !== 'all' ? 'WHERE c.status = :status' : "WHERE c.status IN ('pending','active','rejected')";
$subParams = $filterSubStatus !== 'all' ? ['status' => $filterSubStatus] : [];

// Total count
$subTotalStmt = $db->prepare("
    SELECT COUNT(*) FROM chatbots c
    LEFT JOIN users u ON c.owner_user_id = u.id
    $subWhere
    AND c.submitted_at IS NOT NULL
");
$subTotalStmt->execute($subParams);
$subTotal   = (int)$subTotalStmt->fetchColumn();
$subHasMore = $subTotal > 10;

$subStmt = $db->prepare("
    SELECT
        c.id, c.name, c.website_url, c.logo_url,
        c.status, c.submitted_at, c.access_note, c.admin_notes,
        u.first_name, u.last_name, u.email AS user_email
    FROM chatbots c
    LEFT JOIN users u ON c.owner_user_id = u.id
    $subWhere
    AND c.submitted_at IS NOT NULL
    ORDER BY
        CASE c.status WHEN 'pending' THEN 0 ELSE 1 END,
        c.submitted_at DESC
    LIMIT 10
");
$subStmt->execute($subParams);
$submissions = $subStmt->fetchAll();

// Submission counts per status
$subCountStmt = $db->query("
    SELECT status, COUNT(*) as cnt
    FROM chatbots
    WHERE status IN ('pending','active','rejected')
    AND submitted_at IS NOT NULL
    GROUP BY status
");
$subCounts = ['all' => 0, 'pending' => 0, 'active' => 0, 'rejected' => 0];
foreach ($subCountStmt->fetchAll() as $row) {
    if (isset($subCounts[$row['status']])) {
        $subCounts[$row['status']] = (int)$row['cnt'];
    }
    $subCounts['all'] += (int)$row['cnt'];
}

require '../inc/header.php';
?>

<link rel="stylesheet" href="../css/admin.css">
<link rel="stylesheet" href="../css/admin_refresh.css">
<link rel="stylesheet" href="../css/admin_settings.css">

<style>
.admin-submission-logo {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.5rem;
    object-fit: contain;
    background: #f3f4f6;
    flex-shrink: 0;
}
.admin-submission-logo--placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border: 1.5px solid #e5e7eb;
}
.submission-status-pending  { background: #FEF3C7; color: #92400E; }
.submission-status-active   { background: #D1FAE5; color: #065F46; }
.submission-status-rejected { background: #FEE2E2; color: #991B1B; }
</style>

<div class="admin-wrapper">

    <div class="admin-header">
        <h1>Admin Panel</h1>
        <p>Manage contact messages, user reviews and chatbot submissions</p>
    </div>

    <!-- Main tab navigation -->
    <div class="admin-main-tabs">
        <button class="admin-main-tab-btn <?= $activeTab === 'messages' ? 'active' : '' ?>"
                data-tab="messages">
            Messages
            <?php if ($msgCounts['new'] > 0): ?>
                <span class="admin-main-tab-count"><?= $msgCounts['new'] ?></span>
            <?php endif; ?>
            <span class="admin-refresh-btn" id="refreshMessagesBtn"
                  onclick="event.stopPropagation(); refreshMessages(this)" title="Refresh messages">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                    <path d="M3.51 9a9 9 0 0114.36-3.36L23 10M1 14l5.13 4.36A9 9 0 0020.49 15"/>
                </svg>
            </span>
        </button>
        <button class="admin-main-tab-btn <?= $activeTab === 'reviews' ? 'active' : '' ?>"
                data-tab="reviews">
            Reviews
            <?php if ($reviewCounts['pending'] > 0): ?>
                <span class="admin-main-tab-count"><?= $reviewCounts['pending'] ?></span>
            <?php endif; ?>
            <span class="admin-refresh-btn" id="refreshReviewsBtn"
                  onclick="event.stopPropagation(); refreshReviews(this)" title="Refresh reviews">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                    <path d="M3.51 9a9 9 0 0114.36-3.36L23 10M1 14l5.13 4.36A9 9 0 0020.49 15"/>
                </svg>
            </span>
        </button>
        <button class="admin-main-tab-btn <?= $activeTab === 'submissions' ? 'active' : '' ?>"
                data-tab="submissions">
            Submissions
            <?php if ($subCounts['pending'] > 0): ?>
                <span class="admin-main-tab-count"><?= $subCounts['pending'] ?></span>
            <?php endif; ?>
            <span class="admin-refresh-btn" id="refreshSubmissionsBtn"
                  onclick="event.stopPropagation(); refreshSubmissions(this)" title="Refresh submissions">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                    <path d="M3.51 9a9 9 0 0114.36-3.36L23 10M1 14l5.13 4.36A9 9 0 0020.49 15"/>
                </svg>
            </span>
        </button>
        <button class="admin-main-tab-btn <?= $activeTab === 'settings' ? 'active' : '' ?>"
                data-tab="settings">
            ⚙️ Settings
        </button>
    </div>

    <!-- ============================================ -->
    <!-- TAB: MESSAGES                               -->
    <!-- ============================================ -->

    <div class="admin-tab-pane <?= $activeTab === 'messages' ? 'active' : '' ?>" id="messages-tab">

        <!-- Status filter tabs -->
        <div class="admin-tabs">
            <?php foreach (['all' => 'All', 'new' => 'New', 'replied' => 'Replied', 'resolved' => 'Resolved', 'spam' => 'Spam'] as $key => $label): ?>
                <a href="?tab=messages&status=<?= $key ?>"
                   class="admin-tab <?= $filterStatus === $key && !$filterHelpful ? 'active' : '' ?>">
                    <?= $label ?>
                    <span class="admin-tab-badge <?= $key === 'new' ? 'badge-new' : '' ?>">
                        <?= $msgCounts[$key] ?>
                    </span>
                </a>
            <?php endforeach; ?>
            <a href="?tab=messages&helpful"
               class="admin-tab admin-tab-helpful <?= $filterHelpful ? 'active' : '' ?>">
                ⭐ Helpful
                <span class="admin-tab-badge badge-helpful"><?= $helpfulCount ?></span>
            </a>
        </div>

        <div class="admin-messages"
            data-total="<?= $msgTotal ?>"
            data-has-more="<?= $msgHasMore ? 'true' : 'false' ?>">

            <!-- JUMP TO OLDEST — navrchu sekcie -->
            <?php if ($msgTotal > 10): ?>
            <div class="admin-jump-to-oldest-bar">
                <button id="messagesScrollOldestBtn"
                        class="admin-scroll-oldest-btn"
                        onclick="scrollToOldest('messages')">
                    ↓ Jump to oldest
                </button>
            </div>
            <?php endif; ?>
            <?php if (empty($messages)): ?>
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p>No messages found</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php $msgId = htmlspecialchars($msg['id']); ?>
                    <div class="admin-message-card <?= $msg['helpful'] ? 'is-helpful' : '' ?>" data-id="<?= $msgId ?>">

                        <div class="admin-message-card-header">
                            <div class="admin-message-card-header-left">
                                <div class="admin-message-sender-row">
                                    <span class="admin-message-sender">
                                        <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                                    </span>
                                    <?php if ($msg['helpful']): ?>
                                        <span class="admin-helpful-badge">⭐ Helpful</span>
                                    <?php endif; ?>
                                </div>
                                <span class="admin-message-email"><?= htmlspecialchars($msg['email']) ?></span>
                            </div>
                            <div class="admin-message-card-header-right">
                                <span class="admin-status-badge status-<?= $msg['status'] ?>">
                                    <?= ucfirst($msg['status']) ?>
                                </span>
                                <span class="admin-message-date">
                                    <?= date('d M Y, H:i', strtotime($msg['created_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($msg['subject']): ?>
                            <div class="admin-message-subject">📌 <?= htmlspecialchars($msg['subject']) ?></div>
                        <?php endif; ?>

                        <div class="admin-message-body">
                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        </div>

                        <?php if ($msg['response']): ?>
                            <div class="admin-existing-response">
                                <strong>Your response:</strong>
                                <p><?= nl2br(htmlspecialchars($msg['response'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="admin-message-actions">
                            <div class="admin-status-actions">
                                <?php foreach (['new' => 'New', 'replied' => 'Replied', 'resolved' => 'Resolved', 'spam' => 'Spam'] as $s => $label): ?>
                                    <button class="admin-status-btn <?= $msg['status'] === $s ? 'active' : '' ?>"
                                            data-status="<?= $s ?>"
                                            onclick="updateStatus('<?= $msgId ?>', '<?= $s ?>', this)">
                                        <?= $label ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="admin-toggle-actions">
                                <button class="admin-helpful-btn <?= $msg['helpful'] ? 'active' : '' ?>"
                                        onclick="toggleHelpful('<?= $msgId ?>', this)">
                                    ⭐ Helpful
                                </button>
                                <button class="admin-toggle-btn" onclick="toggleSection(this, 'reply-<?= $msgId ?>')">✏️ Reply</button>
                                <button class="admin-toggle-btn" onclick="toggleSection(this, 'notes-<?= $msgId ?>')">📝 Notes</button>
                            </div>
                        </div>

                        <div class="admin-reply-section" id="reply-<?= $msgId ?>" style="display:none;">
                            <label>Reply to <?= htmlspecialchars($msg['first_name']) ?>
                                <span class="admin-reply-hint">(email will be sent to <?= htmlspecialchars($msg['email']) ?>)</span>
                            </label>
                            <textarea class="admin-textarea" placeholder="Write your reply here..."><?= htmlspecialchars($msg['response'] ?? '') ?></textarea>
                            <button class="admin-save-btn" onclick="sendReply('<?= $msgId ?>', this)">📨 Send Reply</button>
                        </div>

                        <div class="admin-notes-section" id="notes-<?= $msgId ?>" style="display:none;">
                            <label>Internal Notes <span class="admin-reply-hint">(not visible to user)</span></label>
                            <textarea class="admin-textarea" placeholder="Add internal notes here..."><?= htmlspecialchars($msg['internal_notes'] ?? '') ?></textarea>
                            <button class="admin-save-btn" onclick="saveNotes('<?= $msgId ?>', this)">Save Notes</button>
                        </div>

                        <div class="admin-message-meta">
                            <?php if ($msg['ip_address']): ?>
                                <span>IP: <?= htmlspecialchars($msg['ip_address']) ?></span>
                            <?php endif; ?>
                            <?php if ($msg['first_responded_at']): ?>
                                <span>First responded: <?= date('d M Y, H:i', strtotime($msg['first_responded_at'])) ?></span>
                            <?php endif; ?>
                            <?php if ($msg['resolved_at']): ?>
                                <span>Resolved: <?= date('d M Y, H:i', strtotime($msg['resolved_at'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- MESSAGES LOAD MORE BAR -->
            <div class="admin-load-more-bar" id="messagesLoadMoreBar" style="display:none;">
                <span class="admin-load-more-counter"></span>
                <button class="admin-show-more-btn" onclick="loadMoreMessages(this)" style="display:none;">
                    Show more
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: REVIEWS                                -->
    <!-- ============================================ -->

    <div class="admin-tab-pane <?= $activeTab === 'reviews' ? 'active' : '' ?>" id="reviews-tab">

        <!-- Review status filter tabs -->
        <div class="admin-tabs">
            <?php foreach ([
                'all'      => 'All',
                'pending'  => 'Pending',
                'active' => 'Approved',
                'rejected' => 'Rejected',
                'flagged'  => 'Flagged',
                'removed'  => 'Removed',
            ] as $key => $label): ?>
                <a href="?tab=reviews&review_status=<?= $key ?>"
                   class="admin-tab <?= $filterReviewStatus === $key ? 'active' : '' ?>">
                    <?= $label ?>
                    <span class="admin-tab-badge <?= $key === 'pending' ? 'badge-new' : '' ?>">
                        <?= $reviewCounts[$key] ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-messages"
            data-total="<?= $reviewTotal ?>"
            data-has-more="<?= $reviewHasMore ? 'true' : 'false' ?>">

            <!-- JUMP TO OLDEST — navrchu sekcie -->
            <?php if ($reviewTotal > 10): ?>
            <div class="admin-jump-to-oldest-bar">
                <button id="reviewsScrollOldestBtn"
                        class="admin-scroll-oldest-btn"
                        onclick="scrollToOldest('reviews')">
                    ↓ Jump to oldest
                </button>
            </div>
            <?php endif; ?>
            <?php if (empty($reviews)): ?>
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <p>No reviews found</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <?php $reviewId = htmlspecialchars($review['id']); ?>
                    <div class="admin-message-card admin-review-card" data-id="<?= $reviewId ?>">

                        <!-- Review card header -->
                        <div class="admin-message-card-header">
                            <div class="admin-message-card-header-left">
                                <div class="admin-message-sender-row">
                                    <span class="admin-message-sender">
                                        <?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?>
                                    </span>
                                    <!-- Star rating -->
                                    <span class="admin-review-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="<?= $i <= $review['rating'] ? 'star-filled' : 'star-empty' ?>">★</span>
                                        <?php endfor; ?>
                                        <span class="admin-review-rating-num"><?= $review['rating'] ?>/5</span>
                                    </span>
                                </div>
                                <span class="admin-message-email"><?= htmlspecialchars($review['email']) ?></span>
                            </div>
                            <div class="admin-message-card-header-right">
                                <span class="admin-status-badge review-status-<?= $review['status'] ?>">
                                    <?= ucfirst($review['status']) ?>
                                </span>
                                <span class="admin-message-date">
                                    <?= date('d M Y, H:i', strtotime($review['created_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Chatbot name -->
                        <div class="admin-message-subject">
                            🤖 <?= htmlspecialchars($review['chatbot_name']) ?>
                        </div>

                        <!-- Review title -->
                        <?php if ($review['title']): ?>
                            <div class="admin-review-title">"<?= htmlspecialchars($review['title']) ?>"</div>
                        <?php endif; ?>

                        <!-- Review text -->
                        <div class="admin-message-body">
                            <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                        </div>

                        <!-- Existing moderation notes -->
                        <?php if ($review['moderation_notes']): ?>
                            <div class="admin-existing-response">
                                <strong>Moderation notes:</strong>
                                <p><?= nl2br(htmlspecialchars($review['moderation_notes'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Review actions -->
                        <div class="admin-message-actions">
                            <div class="admin-status-actions">
                                <?php foreach ([
                                    'approved' => 'Approve',
                                    'rejected' => 'Reject',
                                    'flagged'  => 'Flag',
                                    'removed'  => 'Remove',
                                ] as $s => $label): ?>
                                    <button class="admin-status-btn review-status-action-btn <?= $review['status'] === $s ? 'active' : '' ?>"
                                            data-status="<?= $s ?>"
                                            onclick="updateReviewStatus('<?= $reviewId ?>', '<?= $s ?>', this)">
                                        <?= $label ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="admin-toggle-actions">
                                <button class="admin-toggle-btn" onclick="toggleSection(this, 'review-notes-<?= $reviewId ?>')">
                                    📝 Notes
                                </button>
                            </div>
                        </div>

                        <!-- Moderation notes section -->
                        <div class="admin-notes-section" id="review-notes-<?= $reviewId ?>" style="display:none;">
                            <label>Moderation Notes <span class="admin-reply-hint">(internal only)</span></label>
                            <textarea class="admin-textarea"
                                      placeholder="e.g. Rejected — contains spam links..."><?= htmlspecialchars($review['moderation_notes'] ?? '') ?></textarea>
                            <button class="admin-save-btn" onclick="saveReviewNotes('<?= $reviewId ?>', this)">Save Notes</button>
                        </div>

                        <!-- Moderated at -->
                        <?php if ($review['moderated_at']): ?>
                            <div class="admin-message-meta">
                                <span>Moderated: <?= date('d M Y, H:i', strtotime($review['moderated_at'])) ?></span>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- REVIEWS LOAD MORE BAR -->
            <div class="admin-load-more-bar" id="reviewsLoadMoreBar" style="display:none;">
                <span class="admin-load-more-counter"></span>
                <button class="admin-show-more-btn" onclick="loadMoreReviews(this)" style="display:none;">
                    Show more
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: SUBMISSIONS                            -->
    <!-- ============================================ -->

    <div class="admin-tab-pane <?= $activeTab === 'submissions' ? 'active' : '' ?>" id="submissions-tab">

        <!-- Status filter tabs -->
        <div class="admin-tabs">
            <?php foreach ([
                'all'      => 'All',
                'pending'  => 'Pending',
                'active' => 'Approved',
                'rejected' => 'Rejected',
            ] as $key => $label): ?>
                <a href="?tab=submissions&sub_status=<?= $key ?>"
                   class="admin-tab <?= $filterSubStatus === $key ? 'active' : '' ?>">
                    <?= $label ?>
                    <span class="admin-tab-badge <?= $key === 'pending' ? 'badge-new' : '' ?>">
                        <?= $subCounts[$key] ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-messages"
             data-total="<?= $subTotal ?>"
             data-has-more="<?= $subHasMore ? 'true' : 'false' ?>">

            <?php if ($subTotal > 10): ?>
            <div class="admin-jump-to-oldest-bar">
                <button id="submissionsScrollOldestBtn"
                        class="admin-scroll-oldest-btn"
                        onclick="scrollToOldestSubmissions()">
                    ↓ Jump to oldest
                </button>
            </div>
            <?php endif; ?>

            <?php if (empty($submissions)): ?>
                <div class="admin-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>No submissions found</p>
                </div>
            <?php else: ?>
                <?php foreach ($submissions as $sub): ?>
                    <?php $subId = htmlspecialchars($sub['id']); ?>
                    <div class="admin-message-card admin-submission-card" data-id="<?= $subId ?>">

                        <div class="admin-message-card-header">
                            <div class="admin-message-card-header-left">
                                <div class="admin-message-sender-row">
                                    <?php if ($sub['logo_url']): ?>
                                        <img src="../<?= htmlspecialchars($sub['logo_url']) ?>"
                                             alt="logo"
                                             class="admin-submission-logo">
                                    <?php else: ?>
                                        <div class="admin-submission-logo admin-submission-logo--placeholder">🤖</div>
                                    <?php endif; ?>
                                    <span class="admin-message-sender">
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </span>
                                </div>
                                <span class="admin-message-email">
                                    Submitted by <?= htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']) ?>
                                    · <a href="mailto:<?= htmlspecialchars($sub['user_email']) ?>"><?= htmlspecialchars($sub['user_email']) ?></a>
                                </span>
                            </div>
                            <div class="admin-message-card-header-right">
                                <span class="admin-status-badge submission-status-<?= $sub['status'] ?>">
                                    <?= ucfirst($sub['status']) ?>
                                </span>
                                <span class="admin-message-date">
                                    <?= date('d M Y, H:i', strtotime($sub['submitted_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Website URL -->
                        <div class="admin-message-subject">
                            🌐 <a href="<?= htmlspecialchars($sub['website_url']) ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($sub['website_url']) ?>
                            </a>
                        </div>

                        <!-- Access note -->
                        <?php if ($sub['access_note']): ?>
                            <div class="admin-message-body">
                                <strong>Access note:</strong><br>
                                <?= nl2br(htmlspecialchars($sub['access_note'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Existing admin notes -->
                        <?php if ($sub['admin_notes']): ?>
                            <div class="admin-existing-response">
                                <strong>Admin notes:</strong>
                                <p><?= nl2br(htmlspecialchars($sub['admin_notes'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="admin-message-actions">
                            <div class="admin-status-actions">
                                <button class="admin-status-btn submission-action-btn <?= $sub['status'] === 'active' ? 'active' : '' ?>"
                                        data-status="approved"
                                        onclick="approveSubmission('<?= $subId ?>', this)">
                                    ✅ Approve
                                </button>
                                <button class="admin-status-btn submission-action-btn <?= $sub['status'] === 'rejected' ? 'active' : '' ?>"
                                        data-status="rejected"
                                        onclick="toggleSection(this, 'sub-reject-<?= $subId ?>')">
                                    ❌ Reject
                                </button>
                            </div>
                            <div class="admin-toggle-actions">
                                <button class="admin-toggle-btn"
                                        onclick="toggleSection(this, 'sub-notes-<?= $subId ?>')">
                                    📝 Notes
                                </button>
                            </div>
                        </div>

                        <!-- Reject section -->
                        <div class="admin-reply-section" id="sub-reject-<?= $subId ?>" style="display:none;">
                            <label>Rejection reason
                                <span class="admin-reply-hint">(will be sent to user via email)</span>
                            </label>
                            <textarea class="admin-textarea"
                                      placeholder="e.g. The chatbot is not publicly accessible, the website appears to be inactive..."></textarea>
                            <button class="admin-save-btn admin-reject-btn"
                                    onclick="rejectSubmission('<?= $subId ?>', this)">
                                Send Rejection
                            </button>
                        </div>

                        <!-- Admin notes section -->
                        <div class="admin-notes-section" id="sub-notes-<?= $subId ?>" style="display:none;">
                            <label>Admin Notes <span class="admin-reply-hint">(internal only, not sent to user)</span></label>
                            <textarea class="admin-textarea"
                                      placeholder="e.g. Tested on 12.3, working fine. Missing pricing page..."><?= htmlspecialchars($sub['admin_notes'] ?? '') ?></textarea>
                            <button class="admin-save-btn"
                                    onclick="saveSubmissionNotes('<?= $subId ?>', this)">
                                Save Notes
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- SUBMISSIONS LOAD MORE BAR -->
            <div class="admin-load-more-bar" id="submissionsLoadMoreBar" style="display:none;">
                <span class="admin-load-more-counter"></span>
                <button class="admin-show-more-btn" onclick="loadMoreSubmissions(this)" style="display:none;">
                    Show more
                </button>
            </div>

        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: SETTINGS                               -->
    <!-- ============================================ -->

    <div class="admin-tab-pane <?= $activeTab === 'settings' ? 'active' : '' ?>" id="settings-tab">

        <div class="admin-settings-loading" id="settingsLoading" style="display:none;">
            <div class="admin-settings-spinner"></div>
        </div>

        <div id="settingsContent" style="display:none;">

            <div class="admin-settings-card">
                <div class="admin-settings-card-header">
                    <h2>Reply Email Settings</h2>
                    <p>Outgoing email address used when replying to contact messages. Keep this separate from your admin login email.</p>
                </div>

                <div class="admin-settings-form" id="adminSettingsForm">

                    <div class="admin-settings-field">
                        <label for="settingReplyEmail">Reply-from email <span class="admin-settings-required">*</span></label>
                        <input type="email"
                               id="settingReplyEmail"
                               maxlength="254"
                               placeholder="support@yourdomain.com"
                               autocomplete="off">
                        <span class="admin-settings-hint">Users will see this address in their inbox when you reply.</span>
                    </div>

                    <div class="admin-settings-field">
                        <label for="settingReplyName">Reply-from name</label>
                        <input type="text"
                               id="settingReplyName"
                               maxlength="100"
                               placeholder="ChatbotHub Support"
                               autocomplete="off">
                        <span class="admin-settings-hint">Display name shown alongside the email address.</span>
                    </div>

                    <div class="admin-settings-footer">
                        <span class="admin-settings-updated" id="settingsLastUpdated"></span>
                        <button class="admin-save-btn admin-settings-save-btn"
                                onclick="saveAdminSettings(this)">
                            Save Settings
                        </button>
                    </div>

                </div>
            </div>

        </div>

    </div>

</div><!-- end admin-wrapper -->

<script src="../js/admin.js" defer></script>
<script src="../js/admin_refresh.js" defer></script>

<?php require '../inc/footer.php'; ?>