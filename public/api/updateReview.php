<?php
/**
 * AJAX Endpoint - Update Review (user)
 *
 * Akcie:
 * - edit   → update rating/text, status = pending, moderation_notes = null
 * - delete → zmazanie review (iba vlastné)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

validateCsrfToken();

$data   = json_decode(file_get_contents('php://input'), true);
$data   = is_array($data) ? $data : [];
$action = $data['action'] ?? '';
$id     = trim($data['id'] ?? '');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit;
}

// ============================================
// KONTROLA BLOKÁCIE (iba pri edit akcii)
// ============================================

if ($action === 'edit' && !empty($user['is_review_blocked'])) {
    echo json_encode(['success' => false, 'message' => 'You are blocked from editing reviews.', 'blocked' => true]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Overiť že review patrí tomuto userovi
    $checkStmt = $db->prepare("SELECT id, status FROM reviews WHERE id = :id AND user_id = :user_id");
    $checkStmt->execute([':id' => $id, ':user_id' => $user['id']]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found or access denied']);
        exit;
    }

    // ============================================
    // EDIT
    // ============================================

    if ($action === 'edit') {
        $rating     = (int)($data['rating']     ?? 0);
        $reviewText = trim($data['review_text'] ?? '');

        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
            exit;
        }
        if (mb_strlen($reviewText) > 5000) {
            echo json_encode(['success' => false, 'message' => 'Review text is too long (max 5000 characters)']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE reviews
            SET
                rating            = :rating,
                title             = NULL,
                review_text       = :review_text,
                status            = 'pending',
                moderation_notes  = NULL,
                moderated_at      = NULL,
                moderated_by_user_id = NULL
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([
            ':rating'      => $rating,
            ':review_text' => $reviewText !== '' ? $reviewText : null,
            ':id'          => $id,
            ':user_id'     => $user['id'],
        ]);

        AuditLog::log('review', $id, 'updated',
            ['status' => $existing['status']],
            ['rating' => $rating, 'status' => 'pending']
        );

        echo json_encode(['success' => true, 'message' => 'Review updated successfully.']);
        exit;
    }

    // ============================================
    // DELETE
    // ============================================

    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM reviews WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $user['id']]);

        AuditLog::log('review', $id, 'deleted', ['status' => $existing['status']], null);

        echo json_encode(['success' => true, 'message' => 'Review deleted successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (Exception $e) {
    error_log('updateReview error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
