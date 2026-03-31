<?php
/**
 * AJAX Endpoint - Get Admin Data
 *
 * Returns paginated messages or reviews with counts for admin refresh.
 * Supports same filters as admin.php (status, review_status, helpful).
 * Supports limit/offset for "Show more" pagination.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db   = Database::getInstance()->getConnection();
$type = $_GET['type'] ?? 'messages';

if (!in_array($type, ['messages', 'reviews'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

// Pagination params
$limit  = max(1, min(100, (int)($_GET['limit']  ?? 10)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

// ============================================
// MESSAGES
// ============================================

if ($type === 'messages') {

    $allowedStatuses = ['all', 'new', 'replied', 'resolved', 'spam'];
    $filterStatus    = $_GET['status'] ?? 'all';
    $filterHelpful   = isset($_GET['helpful']);

    if (!in_array($filterStatus, $allowedStatuses, true)) {
        $filterStatus = 'all';
    }

    $whereParts = [];
    $msgParams  = [];

    if ($filterStatus !== 'all') {
        $whereParts[]        = 'status = :status';
        $msgParams['status'] = $filterStatus;
    }
    if ($filterHelpful) {
        $whereParts[] = 'helpful = 1';
    }

    $msgWhere = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM contact_messages $msgWhere");
    $countStmt->execute($msgParams);
    $total = (int)$countStmt->fetchColumn();

    // Paginated items
    $msgStmt = $db->prepare("
        SELECT id, first_name, last_name, email, subject, message,
               status, helpful, response, internal_notes, ip_address,
               created_at, first_responded_at, resolved_at
        FROM contact_messages
        $msgWhere
        ORDER BY CASE status WHEN 'new' THEN 0 ELSE 1 END, created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($msgParams as $k => $v) $msgStmt->bindValue($k, $v);
    $msgStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $msgStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $msgStmt->execute();
    $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for JS
    foreach ($messages as &$msg) {
        $msg['created_at_formatted']         = date('d M Y, H:i', strtotime($msg['created_at']));
        $msg['first_responded_at_formatted'] = $msg['first_responded_at']
            ? date('d M Y, H:i', strtotime($msg['first_responded_at'])) : null;
        $msg['resolved_at_formatted']        = $msg['resolved_at']
            ? date('d M Y, H:i', strtotime($msg['resolved_at'])) : null;
        $msg['helpful'] = (bool)$msg['helpful'];
    }
    unset($msg);

    // Counts per status
    $msgCountStmt = $db->query("SELECT status, COUNT(*) as cnt FROM contact_messages GROUP BY status");
    $msgCounts    = ['all' => 0, 'new' => 0, 'replied' => 0, 'resolved' => 0, 'spam' => 0];
    foreach ($msgCountStmt->fetchAll() as $row) {
        if (isset($msgCounts[$row['status']])) {
            $msgCounts[$row['status']] = (int)$row['cnt'];
        }
        $msgCounts['all'] += (int)$row['cnt'];
    }
    $msgCounts['helpful'] = (int)$db->query(
        "SELECT COUNT(*) FROM contact_messages WHERE helpful = 1"
    )->fetchColumn();

    echo json_encode([
        'success'  => true,
        'messages' => $messages,
        'counts'   => $msgCounts,
        'total'    => $total,
        'hasMore'  => ($offset + $limit) < $total,
        'offset'   => $offset,
    ]);
    exit;
}

// ============================================
// REVIEWS
// ============================================

if ($type === 'reviews') {

    $allowedReviewStatuses = ['all', 'pending', 'approved', 'rejected', 'flagged', 'removed'];
    $filterReviewStatus    = $_GET['review_status'] ?? 'all';
    if (!in_array($filterReviewStatus, $allowedReviewStatuses, true)) {
        $filterReviewStatus = 'all';
    }

    $reviewWhere  = $filterReviewStatus !== 'all' ? 'WHERE r.status = :status' : '';
    $reviewParams = $filterReviewStatus !== 'all' ? ['status' => $filterReviewStatus] : [];

    // Total count
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM reviews r
        JOIN users u    ON r.user_id    = u.id
        JOIN chatbots c ON r.chatbot_id = c.id
        $reviewWhere
    ");
    $countStmt->execute($reviewParams);
    $total = (int)$countStmt->fetchColumn();

    // Paginated items
    $reviewStmt = $db->prepare("
        SELECT
            r.id, r.rating, r.title, r.review_text,
            r.status, r.moderation_notes,
            r.created_at, r.moderated_at, r.user_id,
            u.first_name, u.last_name, u.email, u.is_review_blocked,
            c.name AS chatbot_name
        FROM reviews r
        JOIN users u    ON r.user_id    = u.id
        JOIN chatbots c ON r.chatbot_id = c.id
        $reviewWhere
        ORDER BY
            CASE r.status WHEN 'pending' THEN 0 ELSE 1 END,
            r.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($reviewParams as $k => $v) $reviewStmt->bindValue($k, $v);
    $reviewStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $reviewStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $reviewStmt->execute();
    $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for JS
    foreach ($reviews as &$review) {
        $review['created_at_formatted']   = date('d M Y, H:i', strtotime($review['created_at']));
        $review['moderated_at_formatted'] = $review['moderated_at']
            ? date('d M Y, H:i', strtotime($review['moderated_at'])) : null;
        $review['rating'] = (int)$review['rating'];
    }
    unset($review);

    // Counts per status
    $reviewCountStmt = $db->query("SELECT status, COUNT(*) as cnt FROM reviews GROUP BY status");
    $reviewCounts    = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'flagged' => 0, 'removed' => 0];
    foreach ($reviewCountStmt->fetchAll() as $row) {
        if (isset($reviewCounts[$row['status']])) {
            $reviewCounts[$row['status']] = (int)$row['cnt'];
        }
        $reviewCounts['all'] += (int)$row['cnt'];
    }

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'counts'  => $reviewCounts,
        'total'   => $total,
        'hasMore' => ($offset + $limit) < $total,
        'offset'  => $offset,
    ]);
    exit;
}