<?php
/**
 * API: Get Admin Submissions (paginated)
 * Used by admin_refresh.js for load more + refresh
 */

require '../../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();

$allowedStatuses = ['all', 'pending', 'reviewed', 'active', 'rejected', 'removed'];
$subStatus       = $_GET['sub_status'] ?? 'pending';
if (!in_array($subStatus, $allowedStatuses, true)) {
    $subStatus = 'pending';
}

$limit  = min((int)($_GET['limit']  ?? 10), 50);
$offset = max((int)($_GET['offset'] ?? 0),   0);

$allStatuses = "('pending','reviewed','active','rejected','removed')";
$visibleCondition = "(c.submitted_at IS NOT NULL OR c.created_source = 'admin')";

$where  = $subStatus !== 'all'
    ? "WHERE c.status = :status AND $visibleCondition"
    : "WHERE c.status IN $allStatuses AND $visibleCondition";
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
        c.owner_user_id, c.created_by_admin_id, c.created_source, c.created_at,
        c.status, c.is_verified,
        c.submitted_at, c.approved_at, c.rejected_at, c.deleted_at, c.verified_at,
        c.access_note, c.admin_notes,
        u.first_name, u.last_name, u.email AS user_email,
        u.is_submission_blocked
    FROM chatbots c
    LEFT JOIN users u ON c.owner_user_id = u.id
    $where
    ORDER BY
        CASE c.status WHEN 'pending' THEN 0 ELSE 1 END,
        COALESCE(c.submitted_at, c.created_at) DESC
    LIMIT :limit OFFSET :off
");
$stmt->execute($fetchParams);
$rows = $stmt->fetchAll();

// Format dates for JS
foreach ($rows as &$row) {
    $row['submitted_at_formatted'] = $row['submitted_at']
        ? date('d M Y, H:i', strtotime($row['submitted_at'])) : null;
    $row['created_at_formatted'] = $row['created_at']
        ? date('d M Y, H:i', strtotime($row['created_at'])) : null;
    $row['display_date_formatted'] = $row['submitted_at_formatted'] ?: $row['created_at_formatted'];
    $row['approved_at_formatted']  = $row['approved_at']
        ? date('d M Y, H:i', strtotime($row['approved_at'])) : null;
    $row['rejected_at_formatted']  = $row['rejected_at']
        ? date('d M Y, H:i', strtotime($row['rejected_at'])) : null;
    $row['deleted_at_formatted']   = $row['deleted_at']
        ? date('d M Y, H:i', strtotime($row['deleted_at'])) : null;
    $row['verified_at_formatted']  = $row['verified_at']
        ? date('d M Y, H:i', strtotime($row['verified_at'])) : null;
    $row['is_verified']            = (bool)$row['is_verified'];
    $row['is_submission_blocked']  = (bool)$row['is_submission_blocked'];
    $row['is_admin_added']         = ($row['created_source'] ?? '') === 'admin' && empty($row['owner_user_id']);
}
unset($row);

// Counts per status
$countStmt = $db->query("
    SELECT status, COUNT(*) as cnt
    FROM chatbots c
    WHERE c.status IN ('pending','reviewed','active','rejected','removed')
    AND $visibleCondition
    GROUP BY status
");
$counts = ['all' => 0, 'pending' => 0, 'reviewed' => 0, 'active' => 0, 'rejected' => 0, 'removed' => 0];
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
