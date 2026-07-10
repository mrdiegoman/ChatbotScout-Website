<?php
/**
 * Admin Settings Actions
 *
 * AJAX endpoint pre globálne nastavenia admina.
 * Akcie:
 *  - GET  → vráti všetky settings
 *  - POST → uloží reply_from_email + reply_from_name + noreply_email + noreply_name
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';
require 'guard.php';

requireRole('admin');

header('Content-Type: application/json');

$db     = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ============================================
// GET
// ============================================

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT `key`, `value`, `updated_at` FROM settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = [
                'value'      => $row['value'],
                'updated_at' => $row['updated_at']
                    ? date('d M Y, H:i', strtotime($row['updated_at']))
                    : null,
            ];
        }

        echo json_encode(['success' => true, 'settings' => $settings]);

    } catch (Exception $e) {
        error_log('adminSettingsActions GET error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

// ============================================
// SAVE (POST)
// ============================================

if ($method === 'POST') {
    validateCsrfToken();
    $data = json_decode(file_get_contents('php://input'), true);
    $data = is_array($data) ? $data : [];

    $replyEmail   = trim($data['reply_from_email'] ?? '');
    $replyName    = trim($data['reply_from_name']  ?? '');
    $noreplyEmail = trim($data['noreply_email']    ?? '');
    $noreplyName  = trim($data['noreply_name']     ?? '');

    // ── Validácia reply_from_email ──
    if (empty($replyEmail)) {
        echo json_encode(['success' => false, 'message' => 'Reply-from email cannot be empty']);
        exit;
    }
    if (!filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Reply-from email is invalid']);
        exit;
    }
    if (mb_strlen($replyEmail) > 254) {
        echo json_encode(['success' => false, 'message' => 'Reply-from email is too long']);
        exit;
    }

    // ── Validácia reply_from_name ──
    if (mb_strlen($replyName) > 100) {
        echo json_encode(['success' => false, 'message' => 'Reply-from name is too long (max 100 characters)']);
        exit;
    }

    // ── Validácia noreply_email ──
    if (empty($noreplyEmail)) {
        echo json_encode(['success' => false, 'message' => 'No-reply email cannot be empty']);
        exit;
    }
    if (!filter_var($noreplyEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'No-reply email is invalid']);
        exit;
    }
    if (mb_strlen($noreplyEmail) > 254) {
        echo json_encode(['success' => false, 'message' => 'No-reply email is too long']);
        exit;
    }

    // ── Validácia noreply_name ──
    if (mb_strlen($noreplyName) > 100) {
        echo json_encode(['success' => false, 'message' => 'No-reply name is too long (max 100 characters)']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO settings (`key`, `value`, `updated_at`)
            VALUES (:key, :value, NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
        ");

        $stmt->execute([':key' => 'reply_from_email', ':value' => $replyEmail]);
        $stmt->execute([':key' => 'reply_from_name',  ':value' => $replyName  ?: null]);
        $stmt->execute([':key' => 'noreply_email',    ':value' => $noreplyEmail]);
        $stmt->execute([':key' => 'noreply_name',     ':value' => $noreplyName ?: null]);

        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);

    } catch (Exception $e) {
        error_log('adminSettingsActions POST error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
