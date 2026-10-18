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

$data  = json_decode(file_get_contents('php://input'), true);
$data  = is_array($data) ? $data : [];
$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email cannot be empty']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}
if (mb_strlen($email) > 254) {
    echo json_encode(['success' => false, 'message' => 'Email is too long']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Duplicate check
    $check = $db->prepare("SELECT id FROM admin_notification_emails WHERE email = :email");
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This email is already in the list']);
        exit;
    }

    $id   = generateUUID();
    $stmt = $db->prepare("INSERT INTO admin_notification_emails (id, email) VALUES (:id, :email)");
    $stmt->execute([':id' => $id, ':email' => $email]);

    AuditLog::log('admin_notification_email', $id, 'created', null, ['email' => $email]);

    echo json_encode([
        'success' => true,
        'email'   => ['id' => $id, 'email' => $email],
    ]);

} catch (Exception $e) {
    error_log('addAdminNotificationEmail error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
