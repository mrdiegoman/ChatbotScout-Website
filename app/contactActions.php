<?php
/**
 * Contact Form Actions Handler
 *
 * Handles contact form submission:
 * - Rate limiting (5 messages per IP)
 * - Server-side validation
 * - DB insert into contact_messages
 * - Confirmation email to user
 * - PRG redirect with session flash message
 */

require '../inc/bootstrap.php';

$contactUrl = $appConfig['base_url'] . '/contact.php';


// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $contactUrl);
    exit;
}

$db = Database::getInstance()->getConnection();

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$email     = trim($_POST['email']      ?? '');
$subject   = trim($_POST['subject']    ?? '');
$message   = trim($_POST['message']    ?? '');
$ipAddress = $_SERVER['REMOTE_ADDR']   ?? null;

// ============================================
// RATE LIMITING — 5 messages per IP
// ============================================

$rateLimiter = new RateLimiter();

if (!$rateLimiter->check('contact_form', $ipAddress)) {
    $remaining = $rateLimiter->getRemainingLockoutTime('contact_form', $ipAddress);

    if ($remaining >= 60) {
        $minutes = ceil($remaining / 60);
        $_SESSION['contact_error'] = "Too many messages sent. Please try again in {$minutes} minutes.";
    } else {
        $_SESSION['contact_error'] = "Too many messages sent. Please try again in {$remaining} seconds.";
    }

    $_SESSION['contact_old_input'] = compact('firstName', 'lastName', 'email', 'subject', 'message');
    header('Location: ' . $contactUrl);
    exit;
}

// ============================================
// VALIDATION
// ============================================

$errors = [];

if (empty($firstName) || strlen($firstName) < 2) {
    $errors[] = 'First name must be at least 2 characters.';
} elseif (strlen($firstName) > 50) {
    $errors[] = 'First name cannot exceed 50 characters.';
}

if (empty($lastName) || strlen($lastName) < 2) {
    $errors[] = 'Last name must be at least 2 characters.';
} elseif (strlen($lastName) > 50) {
    $errors[] = 'Last name cannot exceed 50 characters.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
} elseif (strlen($email) > 255) {
    $errors[] = 'Email address is too long.';
}

$allowedSubjects = [
    'general_question',
    'affiliate_inquiry',
    'partnership_request',
    'report_issue',
    'feedback',
    'press_media',
];

if (empty($subject) || !in_array($subject, $allowedSubjects, true)) {
    $errors[] = 'Please select a valid subject topic.';
}

if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters.';
} elseif (strlen($message) > 400) {
    $errors[] = 'Message cannot exceed 400 characters.';
}

if (!empty($errors)) {
    $rateLimiter->recordFailedAttempt('contact_form', $ipAddress);
    $_SESSION['contact_errors']   = $errors;
    $_SESSION['contact_old_input'] = compact('firstName', 'lastName', 'email', 'subject', 'message');
    header('Location: ' . $contactUrl);
    exit;
}

// ============================================
// SUBJECT LABEL MAP
// ============================================

$subjectLabels = [
    'general_question'    => 'General Question',
    'affiliate_inquiry'   => 'Affiliate Inquiry',
    'partnership_request' => 'Partnership Request',
    'report_issue'        => 'Report an Issue',
    'feedback'            => 'Feedback / Suggestion',
    'press_media'         => 'Press / Media',
];

$subjectLabel = $subjectLabels[$subject] ?? $subject;

// ============================================
// DB INSERT
// ============================================

try {
    $messageId = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $userId = $isLoggedIn ? $user['id'] : null;

    $stmt = $db->prepare("
        INSERT INTO contact_messages
            (id, first_name, last_name, email, subject, message, user_id, status, ip_address)
        VALUES
            (:id, :first_name, :last_name, :email, :subject, :message, :user_id, 'new', :ip_address)
    ");

    $stmt->execute([
        'id'         => $messageId,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'subject'    => $subjectLabel,
        'message'    => $message,
        'user_id'    => $userId,
        'ip_address' => $ipAddress,
    ]);

    // ============================================
    // CONFIRMATION EMAIL TO USER
    // ============================================

    $emailService = new Email();
    $emailService->sendContactConfirmation($email, $firstName, $subjectLabel);

    // ============================================
    // SUCCESS
    // ============================================

    $rateLimiter->reset('contact_form', $ipAddress);
    $_SESSION['contact_success'] = "Your message has been sent! We'll get back to you within a few business days.";
    header('Location: ' . $contactUrl);
    exit;

} catch (PDOException $e) {
    error_log('Contact form DB error: ' . $e->getMessage());
    $_SESSION['contact_error']    = 'Something went wrong. Please try again later.';
    $_SESSION['contact_old_input'] = compact('firstName', 'lastName', 'email', 'subject', 'message');
    header('Location: ' . $contactUrl);
    exit;
}