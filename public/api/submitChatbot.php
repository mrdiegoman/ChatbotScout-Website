<?php
/**
 * API: Submit Chatbot — Affiliate Partnership Request
 * Saves to DB + sends emails (user confirmation + admin notification)
 */

require '../../inc/bootstrap.php';
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

validateCsrfToken();

// ============================================
// KONTROLA BLOKÁCIE
// ============================================

if (!empty($user['is_submission_blocked'])) {
    echo json_encode(['success' => false, 'message' => 'You are blocked from submitting chatbots.', 'blocked' => true]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Input ────────────────────────────────────────────────────────────────────
$contactName  = trim($input['contact_name']  ?? '');
$companyName  = trim($input['company_name']  ?? '');
$contactEmail = trim($input['contact_email'] ?? '');
$chatbotName  = trim($input['name']          ?? '');
$websiteUrl   = trim($input['website_url']   ?? '');
$message      = trim($input['message']       ?? '');

// ── Validation ───────────────────────────────────────────────────────────────
$errors = [];

if (empty($contactName)) {
    $errors[] = 'Your name is required.';
} elseif (mb_strlen($contactName) > 200) {
    $errors[] = 'Name cannot exceed 200 characters.';
}

if (empty($companyName)) {
    $errors[] = 'Company / product name is required.';
} elseif (mb_strlen($companyName) > 200) {
    $errors[] = 'Company name cannot exceed 200 characters.';
}

if (empty($contactEmail)) {
    $errors[] = 'Contact email is required.';
} elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($chatbotName)) {
    $errors[] = 'Chatbot name is required.';
} elseif (mb_strlen($chatbotName) > 200) {
    $errors[] = 'Chatbot name cannot exceed 200 characters.';
}

if (empty($websiteUrl)) {
    $errors[] = 'Chatbot website URL is required.';
} elseif (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Please enter a valid URL (e.g. https://example.com).';
}

if (empty($message)) {
    $errors[] = 'Please tell us about your chatbot.';
} elseif (mb_strlen($message) > 2000) {
    $errors[] = 'Message cannot exceed 2000 characters.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Save to DB ────────────────────────────────────────────────────────────────
try {
    $db = Database::getInstance()->getConnection();

    $chatbotId = generateUUID();

    // Store company name + contact email + message in access_note
    $accessNote = "Company: {$companyName}\nContact email: {$contactEmail}\n\n{$message}";

    $stmt = $db->prepare("
        INSERT INTO chatbots (
            id, owner_user_id, name, website_url,
            status, submitted_at, access_note,
            starting_price, pricing_model, pricing_currency,
            has_free_trial, has_affiliate_program
        ) VALUES (
            :id, :owner_user_id, :name, :website_url,
            'pending', NOW(), :access_note,
            0.00, 'free', 'USD',
            0, 0
        )
    ");

    $stmt->execute([
        'id'            => $chatbotId,
        'owner_user_id' => $user['id'],
        'name'          => $chatbotName,
        'website_url'   => $websiteUrl,
        'access_note'   => $accessNote,
    ]);

    // ── Send emails ───────────────────────────────────────────────────────────
    $emailService = new Email();
 
    $settingsStmt = $db->query("
        SELECT `key`, `value` FROM settings
        WHERE `key` IN ('noreply_email', 'reply_from_name')
    ");
    $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $noreplyEmail = $settingsRows['noreply_email']   ?? '';
    $fromName     = $settingsRows['reply_from_name'] ?? '';

    if (empty($noreplyEmail)) {
        error_log('[WARNING] noreply_email not configured. Set it in Admin Panel → Settings.');
        $noreplyEmail = 'noreply@chatbotscout.com';
    }

    $adminEmailsStmt = $db->query("SELECT email FROM admin_notification_emails ORDER BY created_at ASC");
    $adminEmails     = $adminEmailsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($adminEmails)) {
        $appConfig   = require '../../config/app.php';
        $adminEmails = [$appConfig['admin']['email'] ?? 'admin@chatbotscout.com'];
    }

    // 1. Confirmation to user
    $emailService->sendChatbotSubmissionConfirmation(
        $contactEmail,
        $contactName,
        $chatbotName,
        $websiteUrl
    );

    // 2. Notification to all admin emails
    foreach ($adminEmails as $adminEmail) {
        $emailService->sendChatbotSubmissionAdminNotification(
            $adminEmail,
            $contactName,
            $contactEmail,
            $chatbotName,
            $websiteUrl,
            $message,
            $chatbotId,
            $companyName,
            $noreplyEmail,
            $fromName
        );
    }

    echo json_encode([
        'success'    => true,
        'chatbot_id' => $chatbotId,
        'message'    => 'Your partnership request has been submitted! We will review it and get back to you by email.'
    ]);

} catch (Exception $e) {
    error_log('Submit chatbot error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}

