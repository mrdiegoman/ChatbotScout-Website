<?php
/**
 * API: Get User Chatbots with active listing subscriptions
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Get chatbots that have an active listing subscription
    $stmt = $db->prepare("
        SELECT DISTINCT
            c.id,
            c.name,
            c.logo_url,
            c.website_url,
            c.status AS chatbot_status,
            s.id        AS subscription_id,
            s.status    AS subscription_status,
            s.end_date  AS subscription_end_date,
            lp.name     AS plan_name
        FROM chatbots c
        JOIN subscriptions s  ON s.chatbot_id = c.id
        JOIN listing_plans lp ON s.listing_plan_id = lp.id
        WHERE c.owner_user_id     = :user_id
          AND c.deleted_at        IS NULL
          AND s.subscription_type = 'listing'
          AND s.status            = 'active'
        ORDER BY c.name ASC
    ");
    $stmt->execute(['user_id' => $user['id']]);
    $chatbots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'chatbots' => $chatbots,
        'hasListing' => count($chatbots) > 0
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load chatbots'
    ]);
}