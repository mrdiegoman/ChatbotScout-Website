<?php
/**
 * AJAX Endpoint - Block / Unblock User
 *
 * Block:
 * - Nastaví is_review_blocked alebo is_submission_blocked = TRUE
 * - Zmení VŠETKY pending reviews / submissions daného usera na 'removed'
 *
 * Unblock:
 * - Nastaví is_review_blocked alebo is_submission_blocked = FALSE
 *
 * POST JSON: {
 *   user_id:  "uuid",
 *   type:     "review" | "submission",
 *   unblock:  true   // voliteľné — ak chýba alebo false → block
 * }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');

// Iba admin
if (!$isLoggedIn || empty($user['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$userId  = trim($data['user_id'] ?? '');
$type    = trim($data['type']    ?? '');
$unblock = !empty($data['unblock']);

if (empty($userId) || !in_array($type, ['review', 'submission'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Overiť že user existuje
    $userStmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $userStmt->execute([':id' => $userId]);
    if (!$userStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $db->beginTransaction();

    if ($type === 'review') {
        $flag = 'is_review_blocked';

        if ($unblock) {
            $db->prepare("UPDATE users SET is_review_blocked = FALSE WHERE id = :id")
               ->execute([':id' => $userId]);
        } else {
            $db->prepare("UPDATE users SET is_review_blocked = TRUE WHERE id = :id")
               ->execute([':id' => $userId]);

            $db->prepare("
                UPDATE reviews
                SET status = 'removed', moderated_at = NOW(), moderated_by_user_id = :admin_id
                WHERE user_id = :user_id AND status = 'pending'
            ")->execute([':admin_id' => $user['id'], ':user_id' => $userId]);
        }

    } else {
        $flag = 'is_submission_blocked';

        if ($unblock) {
            $db->prepare("UPDATE users SET is_submission_blocked = FALSE WHERE id = :id")
               ->execute([':id' => $userId]);
        } else {
            $db->prepare("UPDATE users SET is_submission_blocked = TRUE WHERE id = :id")
               ->execute([':id' => $userId]);

            $db->prepare("
                UPDATE chatbots
                SET status = 'removed', deleted_at = NOW()
                WHERE owner_user_id = :user_id AND status = 'pending'
            ")->execute([':user_id' => $userId]);
        }
    }

    $db->commit();

    AuditLog::log('user', $userId, 'updated',
        [$flag => $unblock],
        [$flag => !$unblock]
    );

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('blockUser error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}