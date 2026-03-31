<?php
/**
 * API Endpoint — Get Oldest New/Pending Item ID
 *
 * Returns the ID of the oldest item with status 'new' (messages)
 * or 'pending' (reviews / submissions), so the JS can load only
 * as many chunks as needed to reach that card.
 *
 * GET params:
 *   type = messages | reviews | submissions
 *
 * Response:
 *   { success: true, id: "uuid" }   — found
 *   { success: true, id: null }     — none found
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

$type    = $_GET['type'] ?? '';
$allowed = ['messages', 'reviews', 'submissions'];

if (!in_array($type, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    if ($type === 'messages') {
        $stmt = $db->query("
            SELECT id FROM contact_messages
            WHERE status = 'new'
            ORDER BY created_at ASC
            LIMIT 1
        ");
    } elseif ($type === 'reviews') {
        $stmt = $db->query("
            SELECT id FROM reviews
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 1
        ");
    } else {
        // submissions
        $stmt = $db->query("
            SELECT id FROM chatbots
            WHERE status = 'pending'
              AND submitted_at IS NOT NULL
            ORDER BY submitted_at ASC
            LIMIT 1
        ");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'id'      => $row ? $row['id'] : null,
    ]);

} catch (Exception $e) {
    error_log('getOldestNewItem error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
