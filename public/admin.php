<?php
require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

$db = Database::getInstance()->getConnection();

// ============================================
// FILTERS
// ============================================

$allowedStatuses = ['all', 'new', 'replied', 'resolved', 'spam'];
$filterStatus    = $_GET['status']  ?? 'all';
$filterHelpful   = isset($_GET['helpful']); // ?helpful flag

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}

// ============================================
// FETCH MESSAGES
// ============================================

$whereParts = [];
$params     = [];

if ($filterStatus !== 'all') {
    $whereParts[] = 'status = :status';
    $params['status'] = $filterStatus;
}

if ($filterHelpful) {
    $whereParts[] = 'helpful = 1';
}

$whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $db->prepare("
    SELECT
        id, first_name, last_name, email, subject, message,
        status, helpful, response, internal_notes, ip_address,
        created_at, first_responded_at, resolved_at
    FROM contact_messages
    $whereClause
    ORDER BY
        CASE status WHEN 'new' THEN 0 ELSE 1 END,
        created_at DESC
");

$stmt->execute($params);
$messages = $stmt->fetchAll();

// ============================================
// COUNTS PER STATUS
// ============================================

$countStmt = $db->query("SELECT status, COUNT(*) as cnt FROM contact_messages GROUP BY status");
$rawCounts = $countStmt->fetchAll();

$counts = ['all' => 0, 'new' => 0, 'replied' => 0, 'resolved' => 0, 'spam' => 0];
foreach ($rawCounts as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

// Helpful count
$helpfulCount = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE helpful = 1")->fetchColumn();

require '../inc/header.php';
?>

<link rel="stylesheet" href="../css/admin.css">

<div class="admin-wrapper">

    <div class="admin-header">
        <h1>Contact Messages</h1>
        <p>Manage and respond to incoming contact form submissions</p>
    </div>

    <!-- Status filter tabs -->
    <div class="admin-tabs">
        <?php foreach (['all' => 'All', 'new' => 'New', 'replied' => 'Replied', 'resolved' => 'Resolved', 'spam' => 'Spam'] as $key => $label): ?>
            <a href="?status=<?= $key ?>" class="admin-tab <?= $filterStatus === $key && !$filterHelpful ? 'active' : '' ?>">
                <?= $label ?>
                <span class="admin-tab-badge <?= $key === 'new' ? 'badge-new' : '' ?>">
                    <?= $counts[$key] ?>
                </span>
            </a>
        <?php endforeach; ?>
        <a href="?helpful" class="admin-tab admin-tab-helpful <?= $filterHelpful ? 'active' : '' ?>">
            ⭐ Helpful
            <span class="admin-tab-badge badge-helpful">
                <?= $helpfulCount ?>
            </span>
        </a>
    </div>

    <!-- Messages list -->
    <div class="admin-messages">
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

                    <!-- Card header -->
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
                            <span class="admin-message-email">
                                <?= htmlspecialchars($msg['email']) ?>
                            </span>
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

                    <!-- Subject -->
                    <?php if ($msg['subject']): ?>
                        <div class="admin-message-subject">
                            📌 <?= htmlspecialchars($msg['subject']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Message body -->
                    <div class="admin-message-body">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </div>

                    <!-- Existing response -->
                    <?php if ($msg['response']): ?>
                        <div class="admin-existing-response">
                            <strong>Your response:</strong>
                            <p><?= nl2br(htmlspecialchars($msg['response'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Actions bar -->
                    <div class="admin-message-actions">

                        <!-- Status buttons -->
                        <div class="admin-status-actions">
                            <?php foreach (['new' => 'New', 'replied' => 'Replied', 'resolved' => 'Resolved', 'spam' => 'Spam'] as $s => $label): ?>
                                <button
                                    class="admin-status-btn <?= $msg['status'] === $s ? 'active' : '' ?>"
                                    data-status="<?= $s ?>"
                                    onclick="updateStatus('<?= $msgId ?>', '<?= $s ?>', this)">
                                    <?= $label ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Toggle buttons -->
                        <div class="admin-toggle-actions">
                            <button
                                class="admin-helpful-btn <?= $msg['helpful'] ? 'active' : '' ?>"
                                onclick="toggleHelpful('<?= $msgId ?>', this)"
                                title="Mark as helpful">
                                ⭐ Helpful
                            </button>
                            <button class="admin-toggle-btn" onclick="toggleSection(this, 'reply-<?= $msgId ?>')">
                                ✏️ Reply
                            </button>
                            <button class="admin-toggle-btn" onclick="toggleSection(this, 'notes-<?= $msgId ?>')">
                                📝 Notes
                            </button>
                        </div>
                    </div>

                    <!-- Reply section -->
                    <div class="admin-reply-section" id="reply-<?= $msgId ?>" style="display:none;">
                        <label>Reply to <?= htmlspecialchars($msg['first_name']) ?>
                            <span class="admin-reply-hint">(email will be sent to <?= htmlspecialchars($msg['email']) ?>)</span>
                        </label>
                        <textarea
                            class="admin-textarea"
                            placeholder="Write your reply here..."><?= htmlspecialchars($msg['response'] ?? '') ?></textarea>
                        <button class="admin-save-btn" onclick="sendReply('<?= $msgId ?>', this)">
                            📨 Send Reply
                        </button>
                    </div>

                    <!-- Notes section -->
                    <div class="admin-notes-section" id="notes-<?= $msgId ?>" style="display:none;">
                        <label>Internal Notes <span class="admin-reply-hint">(not visible to user)</span></label>
                        <textarea
                            class="admin-textarea"
                            placeholder="Add internal notes here..."><?= htmlspecialchars($msg['internal_notes'] ?? '') ?></textarea>
                        <button class="admin-save-btn" onclick="saveNotes('<?= $msgId ?>', this)">
                            Save Notes
                        </button>
                    </div>

                    <!-- Meta info -->
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
    </div>
</div>

<script src="../js/admin.js" defer></script>

<?php require '../inc/footer.php'; ?>