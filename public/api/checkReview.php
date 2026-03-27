<?php
/**
 * AJAX Endpoint - Check Review
 *
 * Skontroluje či prihlásený user už dal review pre daný chatbot.
 * Vracia: { has_reviewed: true/false }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Neprihlásený user → nevie mať review
if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode(['success' => true, 'has_reviewed' => false]);
    exit;
}

$chatbotId = trim($_GET['chatbot_id'] ?? '');

if (empty($chatbotId)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot ID.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT id FROM `reviews`
        WHERE chatbot_id = :chatbot_id AND user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        ':chatbot_id' => $chatbotId,
        ':user_id'    => $user['id'],
    ]);

    $exists = (bool)$stmt->fetch();

    echo json_encode(['success' => true, 'has_reviewed' => $exists]);

} catch (Exception $e) {
    error_log('checkReview error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}