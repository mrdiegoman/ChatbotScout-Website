<?php
/**
 * Public, paginated list of approved reviews for one active chatbot.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, private');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$chatbotId = trim($_GET['chatbot'] ?? '');
if (!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $chatbotId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid chatbot ID']);
    exit;
}

$rating = 0;
if (isset($_GET['rating']) && $_GET['rating'] !== '') {
    $rating = filter_var($_GET['rating'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5],
    ]);
    if ($rating === false) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid rating filter']);
        exit;
    }
}

$offset = max(0, min(10000, (int)($_GET['offset'] ?? 0)));
$limit = 10;

try {
    $db = Database::getInstance()->getConnection();

    $where = "\n        r.chatbot_id = :chatbot_id\n        AND r.status = 'approved'\n        AND EXISTS (\n            SELECT 1\n            FROM chatbots c\n            WHERE c.id = r.chatbot_id\n              AND c.status = 'active'\n              AND c.deleted_at IS NULL\n        )\n    ";
    $params = ['chatbot_id' => $chatbotId];

    if ($rating) {
        $where .= ' AND r.rating = :rating';
        $params['rating'] = $rating;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM reviews r WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $reviewStmt = $db->prepare("\n        SELECT\n            r.id, r.rating, r.review_text, r.created_at,\n            u.first_name, u.last_name, u.profile_picture_url\n        FROM reviews r\n        JOIN users u ON u.id = r.user_id\n        WHERE {$where}\n        ORDER BY r.created_at DESC, r.id DESC\n        LIMIT :limit OFFSET :offset\n    ");

    foreach ($params as $name => $value) {
        $reviewStmt->bindValue(':' . $name, $value);
    }
    $reviewStmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
    $reviewStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $reviewStmt->execute();
    $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($reviews) > $limit;
    $reviews = array_slice($reviews, 0, $limit);

    foreach ($reviews as &$review) {
        $review['rating'] = (int)$review['rating'];
        $review['reviewer_name'] = trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')) ?: 'ChatbotScout user';
        $review['created_at_formatted'] = date('M j, Y', strtotime($review['created_at']));
        $review['profile_picture_url'] = localProfilePictureUrl($review['profile_picture_url'] ?? null);
        unset($review['first_name'], $review['last_name']);
    }
    unset($review);

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'total' => $total,
        'has_more' => $hasMore,
    ]);
} catch (PDOException $e) {
    error_log('getChatbotReviews error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load reviews']);
}
