<?php
/**
 * AJAX Endpoint - Get User Reviews
 *
 * Vracia všetky reviews prihláseného usera + is_review_blocked flag.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT
            r.id,
            r.rating,
            r.title,
            r.review_text,
            r.status,
            r.moderation_notes,
            r.created_at,
            r.moderated_at,
            c.id   AS chatbot_id,
            c.name AS chatbot_name
        FROM reviews r
        JOIN chatbots c ON r.chatbot_id = c.id
        WHERE r.user_id = :user_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':user_id' => $user['id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reviews as &$review) {
        $review['rating']                   = (int)$review['rating'];
        $review['created_at_formatted']     = date('d M Y', strtotime($review['created_at']));
        $review['moderated_at_formatted']   = $review['moderated_at']
            ? date('d M Y', strtotime($review['moderated_at'])) : null;
    }
    unset($review);

    echo json_encode([
        'success'            => true,
        'reviews'            => $reviews,
        'is_review_blocked'  => (bool)($user['is_review_blocked'] ?? false),
    ]);

} catch (Exception $e) {
    error_log('getUserReviews error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}