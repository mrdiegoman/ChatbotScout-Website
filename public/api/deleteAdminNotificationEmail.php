<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

validateCsrfToken();

$data = json_decode(file_get_contents('php://input'), true);
$data = is_array($data) ? $data : [];
$id   = trim($data['id'] ?? '');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM admin_notification_emails WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('deleteAdminNotificationEmail error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
