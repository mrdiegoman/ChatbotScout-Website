<?php
/**
 * AJAX Endpoint - Track Click
 *
 * Increments click_count on a chatbot when user clicks "Go to Website".
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$chatbotId  = $data['chatbot_id'] ?? '';

if (empty($chatbotId)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot_id']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        UPDATE `chatbots`
        SET `click_count` = `click_count` + 1,
            `updated_at`  = CURRENT_TIMESTAMP
        WHERE `id` = :id
          AND `status` = 'active'
          AND `deleted_at` IS NULL
    ");
    $stmt->execute([':id' => $chatbotId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('trackClick error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}