<?php
require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

$db = Database::getInstance()->getConnection();

// ============================================
// FILTERS
// ============================================

$allowedStatuses = ['all', 'new', 'replied', 'resolved', 'spam'];
$filterStatus    = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'all';
}

// ============================================
// FETCH MESSAGES
// ============================================

$whereClause = $filterStatus !== 'all' ? 'WHERE status = :status' : '';

$stmt = $db->prepare("
    SELECT
        id, first_name, last_name, email, subject, message,
        status, response, internal_notes, ip_address,
        created_at, first_responded_at, resolved_at
    FROM contact_messages
    $whereClause
    ORDER BY
        CASE status WHEN 'new' THEN 0 ELSE 1 END,
        created_at DESC
");

if ($filterStatus !== 'all') {
    $stmt->execute(['status' => $filterStatus]);
} else {
    $stmt->execute();
}

$messages = $stmt->fetchAll();

// ============================================
// COUNTS PER STATUS
// ============================================

$countStmt = $db->query("
    SELECT status, COUNT(*) as cnt
    FROM contact_messages
    GROUP BY status
");
$rawCounts = $countStmt->fetchAll();

$counts = ['all' => 0, 'new' => 0, 'replied' => 0, 'resolved' => 0, 'spam' => 0];
foreach ($rawCounts as $row) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

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
            <a href="?status=<?= $key ?>"
               class="admin-tab <?= $filterStatus === $key ? 'active' : '' ?>">
                <?= $label ?>
                <span class="admin-tab-badge <?= $key === 'new' ? 'badge-new' : '' ?>">
                    <?= $counts[$key] ?>
                </span>
            </a>
        <?php endforeach; ?>
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
                <div class="admin-message-card" data-id="<?= htmlspecialchars($msg['id']) ?>">

                    <!-- Card header -->
                    <div class="admin-message-card-header">
                        <div class="admin-message-card-header-left">
                            <span class="admin-message-sender">
                                <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                            </span>
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

                    <!-- Existing response (if any) -->
                    <?php if ($msg['response']): ?>
                        <div class="admin-existing-response">
                            <strong>Your response:</strong>
                            <p><?= nl2br(htmlspecialchars($msg['response'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="admin-message-actions">

                        <!-- Status buttons -->
                        <div class="admin-status-actions">
                            <?php foreach (['new' => 'New', 'replied' => 'Replied', 'resolved' => 'Resolved', 'spam' => 'Spam'] as $s => $label): ?>
                                <button
                                    class="admin-status-btn <?= $msg['status'] === $s ? 'active' : '' ?>"
                                    data-status="<?= $s ?>"
                                    onclick="updateStatus('<?= htmlspecialchars($msg['id']) ?>', '<?= $s ?>', this)">
                                    <?= $label ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Toggle reply / notes -->
                        <div class="admin-toggle-actions">
                            <button class="admin-toggle-btn" onclick="toggleSection(this, 'reply-<?= htmlspecialchars($msg['id']) ?>')">
                                ✏️ Reply
                            </button>
                            <button class="admin-toggle-btn" onclick="toggleSection(this, 'notes-<?= htmlspecialchars($msg['id']) ?>')">
                                📝 Notes
                            </button>
                        </div>
                    </div>

                    <!-- Reply section -->
                    <div class="admin-reply-section" id="reply-<?= htmlspecialchars($msg['id']) ?>" style="display:none;">
                        <label>Reply to <?= htmlspecialchars($msg['first_name']) ?>:</label>
                        <textarea
                            class="admin-textarea"
                            placeholder="Write your reply here..."><?= htmlspecialchars($msg['response'] ?? '') ?></textarea>
                        <button class="admin-save-btn" onclick="saveReply('<?= htmlspecialchars($msg['id']) ?>', this)">
                            Save Reply
                        </button>
                    </div>

                    <!-- Internal notes section -->
                    <div class="admin-notes-section" id="notes-<?= htmlspecialchars($msg['id']) ?>" style="display:none;">
                        <label>Internal Notes (not visible to user):</label>
                        <textarea
                            class="admin-textarea"
                            placeholder="Add internal notes here..."><?= htmlspecialchars($msg['internal_notes'] ?? '') ?></textarea>
                        <button class="admin-save-btn" onclick="saveNotes('<?= htmlspecialchars($msg['id']) ?>', this)">
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