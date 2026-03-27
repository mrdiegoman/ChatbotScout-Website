<?php
/**
 * API: Get User Chatbots
 * Returns chatbots owned by the logged-in user that are active (reviewed/active status).
 * Listing is now managed manually via affiliate deal — no listing_plans join needed.
 */

require '../../inc/bootstrap.php';
require '../../app/auth.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Get all active/reviewed chatbots owned by this user
    $stmt = $db->prepare("
        SELECT
            c.id,
            c.name,
            c.logo_url,
            c.website_url,
            c.status AS chatbot_status
        FROM chatbots c
        WHERE c.owner_user_id = :user_id
          AND c.deleted_at    IS NULL
          AND c.status        IN ('active')
        ORDER BY c.name ASC
    ");
    $stmt->execute(['user_id' => $user['id']]);
    $chatbots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'chatbots'   => $chatbots,
        'hasListing' => count($chatbots) > 0
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load chatbots'
    ]);
}