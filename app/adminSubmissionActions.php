<?php
/**
 * Admin Submission Actions
 * Actions: approve | reject | save_notes
 */

require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$id     = trim($input['id'] ?? '');

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot ID.']);
    exit;
}

$db        = Database::getInstance()->getConnection();
$appConfig = require '../config/app.php';

// ── Helper: get pending count ──────────────────────────────────────────────────
function getPendingCount(PDO $db): int {
    return (int)$db->query("
        SELECT COUNT(*) FROM chatbots
        WHERE status = 'pending' AND submitted_at IS NOT NULL
    ")->fetchColumn();
}

// ── Fetch chatbot + owner ──────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT c.id, c.name, c.website_url, c.status,
           u.email AS user_email, u.first_name, u.last_name
    FROM chatbots c
    JOIN users u ON c.owner_user_id = u.id
    WHERE c.id = :id AND c.submitted_at IS NOT NULL
");
$stmt->execute(['id' => $id]);
$chatbot = $stmt->fetch();

if (!$chatbot) {
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

// ── ACTIONS ────────────────────────────────────────────────────────────────────

switch ($action) {

    // ── APPROVE ──────────────────────────────────────────────────────────────
    case 'approve':
        if ($chatbot['status'] === 'reviewed') {
            echo json_encode(['success' => true, 'message' => 'Already approved.', 'pendingCount' => getPendingCount($db)]);
            exit;
        }

        $db->prepare("
            UPDATE chatbots SET status = 'reviewed', approved_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $id]);

        $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';

        try {
            $email = new Email();
            $email->sendChatbotApproved(
                $chatbot['user_email'],
                $userName,
                $chatbot['name']
            );
        } catch (Exception $e) {
            error_log('Approve email error: ' . $e->getMessage());
        }

        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot approved.',
            'pendingCount' => getPendingCount($db)
        ]);
        break;

    // ── REJECT ────────────────────────────────────────────────────────────────
    case 'reject':
        $reason = trim($input['reason'] ?? '');

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
            exit;
        }

        if (mb_strlen($reason) > 2000) {
            echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
            exit;
        }

        $db->prepare("
            UPDATE chatbots SET status = 'rejected', rejected_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $id]);

        $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';

        try {
            $email = new Email();
            $email->sendChatbotRejected(
                $chatbot['user_email'],
                $userName,
                $chatbot['name'],
                $reason
            );
        } catch (Exception $e) {
            error_log('Reject email error: ' . $e->getMessage());
        }

        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot rejected.',
            'pendingCount' => getPendingCount($db)
        ]);
        break;

    // ── SAVE NOTES ────────────────────────────────────────────────────────────
    case 'save_notes':
        $notes = trim($input['notes'] ?? '');

        if (mb_strlen($notes) > 5000) {
            echo json_encode(['success' => false, 'message' => 'Notes are too long (max 5000 characters).']);
            exit;
        }

        $db->prepare("
            UPDATE chatbots SET admin_notes = :notes WHERE id = :id
        ")->execute(['notes' => $notes ?: null, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Notes saved.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}