<?php
/**
 * Admin Settings Actions
 *
 * AJAX endpoint pre globálne nastavenia admina.
 * Akcie:
 *  - get  → vráti všetky settings
 *  - save → uloží reply_from_email + reply_from_name
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';
require '../app/guard.php';

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
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $replyEmail = trim($data['reply_from_email'] ?? '');
    $replyName  = trim($data['reply_from_name']  ?? '');

    // Validácia
    if (empty($replyEmail)) {
        echo json_encode(['success' => false, 'message' => 'Reply-from email cannot be empty']);
        exit;
    }

    if (!filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    if (mb_strlen($replyEmail) > 254) {
        echo json_encode(['success' => false, 'message' => 'Email address is too long']);
        exit;
    }

    if (mb_strlen($replyName) > 100) {
        echo json_encode(['success' => false, 'message' => 'Name is too long (max 100 characters)']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO settings (`key`, `value`, `updated_at`)
            VALUES (:key, :value, NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
        ");

        $stmt->execute([':key' => 'reply_from_email', ':value' => $replyEmail]);
        $stmt->execute([':key' => 'reply_from_name',  ':value' => $replyName ?: null]);

        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully',
        ]);

    } catch (Exception $e) {
        error_log('adminSettingsActions POST error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);