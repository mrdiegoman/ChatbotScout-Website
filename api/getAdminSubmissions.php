<?php
/**
 * API: Get Admin Submissions (paginated)
 * Used by admin_refresh.js for load more + refresh
 */

require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();

$allowedStatuses = ['all', 'pending', 'active', 'rejected'];
$subStatus       = $_GET['sub_status'] ?? 'pending';
if (!in_array($subStatus, $allowedStatuses, true)) {
    $subStatus = 'pending';
}

$limit  = min((int)($_GET['limit']  ?? 10), 50);
$offset = max((int)($_GET['offset'] ?? 0),   0);

$where  = $subStatus !== 'all'
    ? "WHERE c.status = :status AND c.submitted_at IS NOT NULL"
    : "WHERE c.status IN ('pending','active','rejected') AND c.submitted_at IS NOT NULL";
$params = $subStatus !== 'all' ? ['status' => $subStatus] : [];

// Total count
$totalStmt = $db->prepare("SELECT COUNT(*) FROM chatbots c LEFT JOIN users u ON c.owner_user_id = u.id $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

// Fetch page
$fetchParams          = $params;
$fetchParams['limit'] = $limit;
$fetchParams['off']   = $offset;

$stmt = $db->prepare("
    SELECT
        c.id, c.name, c.website_url, c.logo_url,
        c.status, c.submitted_at, c.access_note, c.admin_notes,
        u.first_name, u.last_name, u.email AS user_email
    FROM chatbots c
    LEFT JOIN users u ON c.owner_user_id = u.id
    $where
    ORDER BY
        CASE c.status WHEN 'pending' THEN 0 ELSE 1 END,
        c.submitted_at DESC
    LIMIT :limit OFFSET :off
");
$stmt->execute($fetchParams);
$rows = $stmt->fetchAll();

// Format dates for JS
foreach ($rows as &$row) {
    $row['submitted_at_formatted'] = date('d M Y, H:i', strtotime($row['submitted_at']));
}
unset($row);

// Counts per status
$countStmt = $db->query("
    SELECT status, COUNT(*) as cnt
    FROM chatbots
    WHERE status IN ('pending','active','rejected')
    AND submitted_at IS NOT NULL
    GROUP BY status
");
$counts = ['all' => 0, 'pending' => 0, 'active' => 0, 'rejected' => 0];
foreach ($countStmt->fetchAll() as $c) {
    if (isset($counts[$c['status']])) {
        $counts[$c['status']] = (int)$c['cnt'];
    }
    $counts['all'] += (int)$c['cnt'];
}

echo json_encode([
    'success'     => true,
    'submissions' => $rows,
    'total'       => $total,
    'hasMore'     => ($offset + $limit) < $total,
    'counts'      => $counts,
]);