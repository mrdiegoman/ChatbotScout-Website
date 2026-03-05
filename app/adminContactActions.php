<?php
/**
 * Admin Contact Actions Handler
 *
 * AJAX endpoint for admin operations on contact messages:
 * - update_status  (new / replied / resolved / spam)
 * - reply          (send reply, save response, mark as replied)
 * - save_notes     (save internal notes)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';
$id     = $data['id']     ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing message ID']);
    exit;
}

$db = Database::getInstance()->getConnection();

// ============================================
// UPDATE STATUS
// ============================================

if ($action === 'update_status') {
    $status = $data['status'] ?? '';
    $allowed = ['new', 'replied', 'resolved', 'spam'];

    if (!in_array($status, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    try {
        $resolvedAt = $status === 'resolved' ? 'NOW()' : 'resolved_at';
        $sql = $status === 'resolved'
            ? "UPDATE contact_messages SET status = :status, resolved_at = NOW() WHERE id = :id"
            : "UPDATE contact_messages SET status = :status WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $id]);

        echo json_encode(['success' => true, 'status' => $status]);
    } catch (PDOException $e) {
        error_log('Admin status update error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// ============================================
// REPLY
// ============================================

if ($action === 'reply') {
    $response = trim($data['response'] ?? '');

    if (empty($response)) {
        echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
        exit;
    }

    try {
        // Get the original message details for sending email
        $stmt = $db->prepare("SELECT first_name, last_name, email, subject FROM contact_messages WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $msg = $stmt->fetch();

        if (!$msg) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            exit;
        }

        // Update DB: save response, mark as replied, set first_responded_at if null
        $stmt = $db->prepare("
            UPDATE contact_messages
            SET
                response           = :response,
                status             = 'replied',
                first_responded_at = COALESCE(first_responded_at, NOW())
            WHERE id = :id
        ");
        $stmt->execute(['response' => $response, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Reply saved successfully']);

    } catch (PDOException $e) {
        error_log('Admin reply error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// ============================================
// SAVE INTERNAL NOTES
// ============================================

if ($action === 'save_notes') {
    $notes = trim($data['notes'] ?? '');

    try {
        $stmt = $db->prepare("UPDATE contact_messages SET internal_notes = :notes WHERE id = :id");
        $stmt->execute(['notes' => $notes ?: null, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Notes saved']);
    } catch (PDOException $e) {
        error_log('Admin notes error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);