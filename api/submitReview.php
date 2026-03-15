<?php
/**
 * AJAX Endpoint - Submit Review
 *
 * Uloží hodnotenie od prihláseného usera do tabuľky `reviews`.
 * Status je 'pending' – admin musí schváliť.
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

// Musí byť prihlásený
if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a review.']);
    exit;
}

// ============================================
// KONTROLA BLOKÁCIE
// ============================================

if (!empty($user['is_review_blocked'])) {
    echo json_encode(['success' => false, 'message' => 'You are blocked from submitting reviews.', 'blocked' => true]);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$chatbotId  = trim($data['chatbot_id'] ?? '');
$rating     = (int)($data['rating'] ?? 0);
$title      = trim($data['title'] ?? '');
$reviewText = trim($data['review_text'] ?? '');

// ============================================
// VALIDATION
// ============================================

if (empty($chatbotId)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot ID.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}

if (empty($reviewText)) {
    echo json_encode(['success' => false, 'message' => 'Review message cannot be empty.']);
    exit;
}

if (mb_strlen($title) > 200) {
    echo json_encode(['success' => false, 'message' => 'Title is too long (max 200 characters).']);
    exit;
}

if (mb_strlen($reviewText) > 5000) {
    echo json_encode(['success' => false, 'message' => 'Review text is too long (max 5000 characters).']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Skontroluj či chatbot existuje
    $checkStmt = $db->prepare("
        SELECT id FROM `chatbots`
        WHERE id = :id AND status = 'active' AND deleted_at IS NULL
    ");
    $checkStmt->execute([':id' => $chatbotId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Chatbot not found.']);
        exit;
    }

    // Skontroluj či user už nerecenzoval tento chatbot
    $existsStmt = $db->prepare("
        SELECT id FROM `reviews`
        WHERE chatbot_id = :chatbot_id AND user_id = :user_id
    ");
    $existsStmt->execute([
        ':chatbot_id' => $chatbotId,
        ':user_id'    => $user['id'],
    ]);
    if ($existsStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted a review for this chatbot.']);
        exit;
    }

    // Generuj UUID
    $reviewId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $insertStmt = $db->prepare("
        INSERT INTO `reviews`
            (`id`, `chatbot_id`, `user_id`, `rating`, `title`, `review_text`, `status`)
        VALUES
            (:id, :chatbot_id, :user_id, :rating, :title, :review_text, 'pending')
    ");
    $insertStmt->execute([
        ':id'          => $reviewId,
        ':chatbot_id'  => $chatbotId,
        ':user_id'     => $user['id'],
        ':rating'      => $rating,
        ':title'       => $title ?: null,
        ':review_text' => $reviewText,
    ]);

    AuditLog::log('review', $reviewId, 'created',
        null,
        ['chatbot_id' => $chatbotId, 'rating' => $rating, 'status' => 'pending']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your review has been submitted and is pending approval.',
    ]);

} catch (Exception $e) {
    error_log('submitReview error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}