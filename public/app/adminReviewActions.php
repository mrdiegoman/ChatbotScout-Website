<?php
/**
 * Admin Review Actions Handler
 *
 * AJAX endpoint pre moderáciu reviews:
 * - update_status   (approved / rejected / flagged / removed)
 * - save_notes      (moderation notes)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';
require '../../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

validateCsrfToken();

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';
$id     = $data['id']     ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing review ID']);
    exit;
}

$db = Database::getInstance()->getConnection();

// ============================================
// UPDATE STATUS
// ============================================

if ($action === 'update_status') {
    $status  = $data['status'] ?? '';
    $allowed = ['approved', 'rejected', 'flagged', 'removed'];

    if (!in_array($status, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE reviews
            SET
                status               = :status,
                moderated_by_user_id = :admin_id,
                moderated_at         = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'status'   => $status,
            'admin_id' => $user['id'],
            'id'       => $id,
        ]);

        // Mapovanie statusu na audit akciu
        $auditAction = match($status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            default    => 'updated',
        };

        AuditLog::log('review', $id, $auditAction,
            null,
            ['status' => $status, 'moderated_by' => $user['id']]
        );

        echo json_encode(['success' => true, 'status' => $status]);

    } catch (PDOException $e) {
        error_log('Admin review status error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// ============================================
// SAVE MODERATION NOTES
// ============================================

if ($action === 'save_notes') {
    $notes = trim($data['notes'] ?? '');

    try {
        $stmt = $db->prepare("UPDATE reviews SET moderation_notes = :notes WHERE id = :id");
        $stmt->execute(['notes' => $notes ?: null, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Notes saved']);

    } catch (PDOException $e) {
        error_log('Admin review notes error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);