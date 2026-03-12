<?php
/**
 * AJAX Endpoint - Track Click
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$chatbotId = $data['chatbot_id'] ?? '';
$sourcePage = $data['source_page'] ?? null;

if (empty($chatbotId)) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Update click_count
    $db->prepare("
        UPDATE chatbots
        SET click_count = click_count + 1, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND status = 'active' AND deleted_at IS NULL
    ")->execute([':id' => $chatbotId]);

    // Zaznamenaj do chatbot_clicks ak má visitor
    if (!empty($visitorId)) {
        $clickId = sprintf('%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );

        $db->prepare("
            INSERT INTO chatbot_clicks (id, chatbot_id, visitor_id, source_page, clicked_at)
            VALUES (:id, :chatbot_id, :visitor_id, :source_page, NOW())
        ")->execute([
            'id'          => $clickId,
            'chatbot_id'  => $chatbotId,
            'visitor_id'  => $visitorId,
            'source_page' => $sourcePage,
        ]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('recordVisit error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}