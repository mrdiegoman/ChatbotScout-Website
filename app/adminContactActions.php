<?php
/**
 * Admin Contact Actions Handler
 *
 * AJAX endpoint for admin operations on contact messages:
 * - update_status   (new / replied / resolved / spam)
 * - send_reply      (validate, MX check, send email, save response)
 * - save_notes      (save internal notes)
 * - toggle_helpful  (toggle helpful boolean)
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
    $status  = $data['status'] ?? '';
    $allowed = ['new', 'replied', 'resolved', 'spam'];

    if (!in_array($status, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    try {
        if ($status === 'resolved') {
            $stmt = $db->prepare("UPDATE contact_messages SET status = :status, resolved_at = NOW() WHERE id = :id");
        } else {
            $stmt = $db->prepare("UPDATE contact_messages SET status = :status WHERE id = :id");
        }

        $stmt->execute(['status' => $status, 'id' => $id]);
        echo json_encode(['success' => true, 'status' => $status]);

    } catch (PDOException $e) {
        error_log('Admin status update error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// ============================================
// SEND REPLY
// ============================================

if ($action === 'send_reply') {
    $response = trim($data['response'] ?? '');

    // Empty check
    if (empty($response)) {
        echo json_encode(['success' => false, 'message' => 'Reply cannot be empty — not sent.']);
        exit;
    }

    try {
        // Fetch original message
        $stmt = $db->prepare("SELECT first_name, last_name, email, subject FROM contact_messages WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $msg = $stmt->fetch();

        if (!$msg) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            exit;
        }

        // ============================================
        // MX RECORD CHECK
        // ============================================

        $emailDomain = substr(strrchr($msg['email'], '@'), 1);
        $mxRecords   = [];
        $hasMx       = getmxrr($emailDomain, $mxRecords);

        if (!$hasMx) {
            echo json_encode([
                'success' => false,
                'message' => "Cannot send reply — email domain \"$emailDomain\" has no valid MX records."
            ]);
            exit;
        }

        // ============================================
        // SEND EMAIL
        // ============================================

        $emailService = new Email();
        $fullName     = $msg['first_name'] . ' ' . $msg['last_name'];
        $sent         = $emailService->sendAdminReply(
            $msg['email'],
            $fullName,
            $msg['subject'] ?? 'Your inquiry',
            $response
        );

        if (!$sent) {
            echo json_encode([
                'success' => false,
                'message' => 'Reply was not sent — email delivery failed. Check server mail logs.'
            ]);
            exit;
        }

        // ============================================
        // SAVE TO DB — only after successful send
        // ============================================

        $stmt = $db->prepare("
            UPDATE contact_messages
            SET
                response           = :response,
                status             = 'replied',
                first_responded_at = COALESCE(first_responded_at, NOW())
            WHERE id = :id
        ");
        $stmt->execute(['response' => $response, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Reply sent successfully!']);

    } catch (PDOException $e) {
        error_log('Admin send_reply DB error: ' . $e->getMessage());
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

// ============================================
// TOGGLE HELPFUL
// ============================================

if ($action === 'toggle_helpful') {
    try {
        $stmt = $db->prepare("SELECT helpful FROM contact_messages WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            exit;
        }

        $newValue = $row['helpful'] ? 0 : 1;

        $stmt = $db->prepare("UPDATE contact_messages SET helpful = :helpful WHERE id = :id");
        $stmt->execute(['helpful' => $newValue, 'id' => $id]);

        echo json_encode(['success' => true, 'helpful' => (bool)$newValue]);

    } catch (PDOException $e) {
        error_log('Admin toggle_helpful error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);