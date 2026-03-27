<?php
/**
 * AJAX Endpoint - Track Click
 */

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$chatbotId  = $data['chatbot_id'] ?? '';
$sourcePage = $data['source_page'] ?? null;

if (empty($chatbotId)) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Update click_count (always - no consent needed, just a number)
    $db->prepare("
        UPDATE chatbots
        SET click_count = click_count + 1, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND status = 'active' AND deleted_at IS NULL
    ")->execute([':id' => $chatbotId]);

    // $visitorId comes from bootstrap.php
    // It is NULL if the user declined cookies or has not consented yet
    // In that case we skip the detailed chatbot_clicks tracking entirely
    $cookieVisitorId = $visitorId ?? null;

    // Fallback for logged-in users without a visitor cookie:
    // Find visitor_id via visitor_user_links by user_id from session
    // Only runs if consent was explicitly accepted — never for declined users
    $cookieConsent = $_COOKIE['cookie_consent'] ?? null;
    if (empty($cookieVisitorId) && $cookieConsent === 'accepted' && !empty($_SESSION['user']['id'])) {
        $stmt = $db->prepare("
            SELECT visitor_id
            FROM visitor_user_links
            WHERE user_id = :user_id
            ORDER BY converted_at DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $_SESSION['user']['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $cookieVisitorId = $row['visitor_id'];
        }
    }

    if (!empty($cookieVisitorId)) {

        // Check if a record exists for this visitor+chatbot within the last 30 days
        $stmt = $db->prepare("
            SELECT id
            FROM chatbot_clicks
            WHERE visitor_id = :visitor_id
              AND chatbot_id = :chatbot_id
              AND clicked_at >= NOW() - INTERVAL 30 DAY
            LIMIT 1
        ");
        $stmt->execute([
            'visitor_id' => $cookieVisitorId,
            'chatbot_id' => $chatbotId,
        ]);

        $recentClick = $stmt->fetch(PDO::FETCH_ASSOC);

        // Only insert if no recent click exists in the last 30 days
        if (!$recentClick) {
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
                'visitor_id'  => $cookieVisitorId,
                'source_page' => $sourcePage,
            ]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('recordVisit error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}