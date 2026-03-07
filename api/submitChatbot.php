<?php
/**
 * API: Submit Chatbot
 * Handles chatbot submission with logo upload
 * Sends confirmation email to user + notification email to admin
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a chatbot.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Input validation ─────────────────────────────────────────────────────────
$name       = trim($_POST['name']        ?? '');
$websiteUrl = trim($_POST['website_url'] ?? '');
$accessNote = trim($_POST['access_note'] ?? '');
$agreed     = $_POST['access_agreement'] ?? '';

$errors = [];

if (empty($name)) {
    $errors[] = 'Chatbot name is required.';
} elseif (mb_strlen($name) > 200) {
    $errors[] = 'Chatbot name cannot exceed 200 characters.';
}

if (empty($websiteUrl)) {
    $errors[] = 'Website URL is required.';
} elseif (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Please enter a valid website URL (e.g. https://example.com).';
}

if (empty($accessNote)) {
    $errors[] = 'Please describe how we can access your chatbot for testing.';
} elseif (mb_strlen($accessNote) > 1000) {
    $errors[] = 'Access note cannot exceed 1000 characters.';
}

if ($agreed !== '1') {
    $errors[] = 'You must agree to provide access for testing.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Logo upload ───────────────────────────────────────────────────────────────
$logoUrl = null;

if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file      = $_FILES['logo'];
    $maxSize   = 5 * 1024 * 1024; // 5MB
    $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $uploadDir = '../img/chatbot_logos/';

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Logo upload failed. Please try again.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Logo file is too large. Maximum size is 5MB.']);
        exit;
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please use JPG, PNG, WebP or GIF.']);
        exit;
    }

    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . generateUUID() . '.' . strtolower($ext);
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save logo. Please try again.']);
        exit;
    }

    $logoUrl = 'img/chatbot_logos/' . $filename;
}

// ── Save to DB ────────────────────────────────────────────────────────────────
try {
    $db = Database::getInstance()->getConnection();

    $chatbotId = generateUUID();

    $stmt = $db->prepare("
        INSERT INTO chatbots (
            id, owner_user_id, name, website_url, logo_url,
            status, submitted_at, access_note,
            starting_price, pricing_model, pricing_currency,
            has_free_trial, has_affiliate_program
        ) VALUES (
            :id, :owner_user_id, :name, :website_url, :logo_url,
            'pending', NOW(), :access_note,
            0.00, 'free', 'USD',
            0, 0
        )
    ");

    $stmt->execute([
        'id'            => $chatbotId,
        'owner_user_id' => $user['id'],
        'name'          => $name,
        'website_url'   => $websiteUrl,
        'logo_url'      => $logoUrl,
        'access_note'   => $accessNote,
    ]);

    // ── Send emails ───────────────────────────────────────────────────────────
    $emailService = new Email();
    $userName     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'there';

    // 1. Confirmation to user
    $emailService->sendChatbotSubmissionConfirmation(
        $user['email'],
        $userName,
        $name,
        $websiteUrl
    );

    // 2. Notification to admin
    $appConfig  = require '../config/app.php';
    $adminEmail = $appConfig['admin']['email'] ?? 'admin@chatbothub.com';
    $emailService->sendChatbotSubmissionAdminNotification(
        $adminEmail,
        $userName,
        $user['email'],
        $name,
        $websiteUrl,
        $accessNote,
        $chatbotId
    );

    echo json_encode([
        'success'    => true,
        'chatbot_id' => $chatbotId,
        'message'    => 'Your chatbot has been submitted successfully! We will review it and get back to you by email.'
    ]);

} catch (Exception $e) {
    // Clean up uploaded logo if DB insert failed
    if ($logoUrl && file_exists('../' . $logoUrl)) {
        unlink('../' . $logoUrl);
    }
    error_log('Submit chatbot error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}

// ── Helper ────────────────────────────────────────────────────────────────────
function generateUUID(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}