<?php
/**
 * Profile Actions Handler
 * 
 * AJAX endpoint pre profile operations
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';
require '../app/auth.php';

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

// ✅ Exceptions - actions ktoré NEVYŽADUJÚ prihlásenie
$publicActions = ['resend_verification'];

// Must be logged in (okrem public actions)
if (!in_array($action, $publicActions) && !$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// ============================================
// RESEND VERIFICATION EMAIL (na začiatok - public action)
// ============================================

if ($action === 'resend_verification') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = $data['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }
    
    // ✅ 60-second timer check (session-based)
    $lastSentKey = 'last_verification_sent_' . md5($email);
    $lastSentTime = $_SESSION[$lastSentKey] ?? 0;
    $timeSinceLastSend = time() - $lastSentTime;
    
    if ($timeSinceLastSend < 60) {
        $remainingSeconds = 60 - $timeSinceLastSend;
        echo json_encode([
            'success' => false, 
            'message' => "Please wait {$remainingSeconds} more seconds before resending.",
            'remainingSeconds' => $remainingSeconds
        ]);
        exit;
    }
    
    // ✅ Rate limiting check
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$rateLimiter->check('resend_verification', $clientIp)) {
        $remaining = $rateLimiter->getRemainingLockoutTime('resend_verification', $clientIp);
        if ($remaining >= 60) {
            $minutes = ceil($remaining / 60);
            echo json_encode([
                'success' => false, 
                'message' => "Too many requests. Please try again in {$minutes} minutes."
            ]);
        }
        elseif ($remaining > 0) {
                echo json_encode([
                'success' => false, 
                'message' => "Too many requests. Please try again in {$remaining} seconds."
            ]);
        }
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Skontroluj user
    $stmt = $db->prepare("
        SELECT id, is_email_verified, first_name, last_name 
        FROM users 
        WHERE email = :email
    ");
    $stmt->execute(['email' => $email]);
    $userCheck = $stmt->fetch();
    
    if (!$userCheck) {
        // Security: Vráť success aj keď user neexistuje
        $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
        echo json_encode(['success' => true, 'message' => 'If email exists, verification link was sent']);
        exit;
    }
    
    if ($userCheck['is_email_verified']) {
        // ✅ Record failed attempt
        $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
        echo json_encode(['success' => false, 'message' => 'Email is already verified']);
        exit;
    }
    
    // Generuj nový token
    $appConfig = require __DIR__ . '/../config/app.php';
    $newToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + $appConfig['email_verification']['token_lifetime']);
    
    // Ulož do DB
    $stmt = $db->prepare("
        UPDATE users 
        SET email_verification_token = :token,
            email_verification_expires_at = :expires
        WHERE id = :id
    ");
    $stmt->execute([
        'token' => $newToken,
        'expires' => $expiresAt,
        'id' => $userCheck['id']
    ]);
    
    // Odošli cez Email class
    $emailService = new Email();
    $fullName = $userCheck['first_name'] . ' ' . $userCheck['last_name'];
    $emailService->sendVerification($email, $fullName, $newToken);
    $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
    
    // ✅ Ulož timestamp do session (pre 60-sec timer)
    $_SESSION[$lastSentKey] = time();
    
    // ✅ Reset rate limiter (úspešné odoslanie)
    // $rateLimiter->reset('resend_verification', $clientIp);
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification email has been resent successfully'
    ]);
    
    exit;
}

// ============================================
// UPLOAD AVATAR
// ============================================

if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
        exit;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
    $uploadDir = __DIR__ . '/../img/avatars/';
    $uploadPath = $uploadDir . $filename;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    try {
        $image = null;
        switch ($file['type']) {
            case 'image/jpeg': $image = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png': $image = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $image = imagecreatefromwebp($file['tmp_name']); break;
        }
        
        if ($image === false) throw new Exception('Failed to process image');
        
        $width = imagesx($image);
        $height = imagesy($image);
        $size = 400;
        $thumb = imagecreatetruecolor($size, $size);
        
        if ($file['type'] === 'image/png' || $file['type'] === 'image/webp') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        
        $minDimension = min($width, $height);
        $srcX = ($width - $minDimension) / 2;
        $srcY = ($height - $minDimension) / 2;
        
        imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $size, $size, $minDimension, $minDimension);
        
        switch ($file['type']) {
            case 'image/jpeg': imagejpeg($thumb, $uploadPath, 90); break;
            case 'image/png': imagepng($thumb, $uploadPath, 9); break;
            case 'image/webp': imagewebp($thumb, $uploadPath, 90); break;
        }
        
        imagedestroy($image);
        imagedestroy($thumb);
        
        if ($user['profile_picture_url'] && strpos($user['profile_picture_url'], 'default-avatar') === false) {
            $oldPath = __DIR__ . '/' . $user['profile_picture_url'];
            error_log($oldPath);
            error_log(file_exists($oldPath) ? 'EXISTS' : 'NOT EXISTS');
            if (file_exists($oldPath)) unlink($oldPath);
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
        $newUrl = '../img/avatars/' . $filename;
        $stmt->execute(['url' => $newUrl, 'id' => $user['id']]);
        
        $_SESSION['user']['profile_picture_url'] = $newUrl;

        AuditLog::log('user', $user['id'], 'updated',
            ['profile_picture_url' => $user['profile_picture_url']],
            ['profile_picture_url' => $newUrl]
        );

        echo json_encode(['success' => true, 'url' => $newUrl]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}

// ============================================
// UPDATE NAME
// ============================================

if ($action === 'update_name') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    
    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['success' => false, 'message' => 'Both names are required']);
        exit;
    }
    
    if (mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50) {
        echo json_encode(['success' => false, 'message' => 'Names too long (max 50 characters)']);
        exit;
    }
    
    // if (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2) {
    //     echo json_encode(['success' => false, 'message' => 'Names too short (min 2 characters)']);
    //     exit;
    // }
    
    $firstName = mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8');
    $lastName = mb_convert_case($lastName, MB_CASE_TITLE, 'UTF-8');
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE users SET first_name = :first, last_name = :last WHERE id = :id");
    $stmt->execute(['first' => $firstName, 'last' => $lastName, 'id' => $user['id']]);
    
    $_SESSION['user']['first_name'] = $firstName;
    $_SESSION['user']['last_name'] = $lastName;

    AuditLog::log('user', $user['id'], 'updated',
        ['first_name' => $user['first_name'], 'last_name' => $user['last_name']],
        ['first_name' => $firstName, 'last_name' => $lastName]
    );

    echo json_encode(['success' => true, 'firstName' => $firstName, 'lastName' => $lastName]);
    exit;
}

// ============================================
// CHANGE PASSWORD
// ============================================

if ($action === 'change_password') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $data['currentPassword'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute(['id' => $user['id']]);
    $userData = $stmt->fetch();
    
    if (!password_verify($currentPassword, $userData['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $stmt->execute(['hash' => $newHash, 'id' => $user['id']]);

    AuditLog::log('user', $user['id'], 'updated',
        ['password' => '***'],
        ['password' => '***']
    );

    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// UPDATE MARKETING EMAILS
// ============================================

if ($action === 'update_marketing_emails') {
    $data = json_decode(file_get_contents('php://input'), true);
    $enabled = $data['enabled'] ?? false;
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("UPDATE users SET marketing_emails = :enabled WHERE id = :id");
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $user['id']]);
        $_SESSION['user']['marketing_emails'] = $enabled;
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
    
    exit;
}

// ============================================
// STRIPE CUSTOMER PORTAL
// ============================================

if ($action === 'stripe_customer_portal') {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT stripe_customer_id FROM subscriptions WHERE user_id = :user_id AND status = 'active' LIMIT 1");
    $stmt->execute(['user_id' => $user['id']]);
    $sub = $stmt->fetch();
    
    if (!$sub || !$sub['stripe_customer_id']) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Stripe integration not yet configured']);
    }
    
    exit;
}

// ============================================
// CANCEL SUBSCRIPTION
// ============================================

if ($action === 'cancel_subscription') {
    $data = json_decode(file_get_contents('php://input'), true);
    $cancelType = $data['cancelType'] ?? 'at-period-end';
    $reason = $data['reason'] ?? null;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, end_date FROM subscriptions WHERE user_id = :user_id AND status = 'active' LIMIT 1");
    $stmt->execute(['user_id' => $user['id']]);
    $sub = $stmt->fetch();
    
    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
        exit;
    }
    
    if ($cancelType === 'immediately') {
        $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(), end_date = NOW(), cancellation_reason = :reason WHERE id = :id");
    } else {
        $stmt = $db->prepare("UPDATE subscriptions SET cancel_at_period_end = TRUE, auto_renew = FALSE, cancelled_at = NOW(), cancellation_reason = :reason WHERE id = :id");
    }
    
    $stmt->execute(['reason' => $reason, 'id' => $sub['id']]);

    AuditLog::log('subscription', $sub['id'], 'updated',
        ['status' => 'active', 'cancel_at_period_end' => false],
        ['status' => $cancelType === 'immediately' ? 'cancelled' : 'active',
         'cancel_at_period_end' => $cancelType !== 'immediately',
         'cancellation_reason' => $reason]
    );

    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// DELETE ACCOUNT — GDPR compliant
// Anonymizuje osobné údaje namiesto soft delete.
// Zachová len účtovné záznamy (payments) pre právne účely.
// ============================================
 
if ($action === 'delete_account') {
    $db = Database::getInstance()->getConnection();
 
    try {
        $db->beginTransaction();
 
        $userId    = $user['id'];
        $anonymousEmail = 'deleted_' . $userId . '@deleted.invalid';
 
        // 1. Anonymizuj osobné údaje v users tabuľke
        $db->prepare("
            UPDATE users SET
                email                          = :email,
                password_hash                  = '',
                google_id                      = NULL,
                first_name                     = 'Deleted',
                last_name                      = 'User',
                profile_picture_url            = NULL,
                bio                            = NULL,
                website_url                    = NULL,
                marketing_emails               = 0,
                email_verification_token       = NULL,
                email_verification_expires_at  = NULL,
                password_reset_token           = NULL,
                password_reset_expires_at      = NULL,
                status                         = 'disabled',
                disabled_at                    = NOW()
            WHERE id = :id
        ")->execute(['email' => $anonymousEmail, 'id' => $userId]);
 
        // 2. Zruš aktívne subscripcie
        $db->prepare("
            UPDATE subscriptions
            SET status              = 'cancelled',
                cancelled_at        = NOW(),
                end_date            = NOW(),
                cancellation_reason = 'Account deleted by user'
            WHERE user_id = :user_id AND status = 'active'
        ")->execute(['user_id' => $userId]);
 
        // 3. Vymaž visitor_id cookie a visitor_user_links (tracking dáta)
        $db->prepare("
            DELETE FROM visitor_user_links WHERE user_id = :user_id
        ")->execute(['user_id' => $userId]);
 
        // 4. Anonymizuj IP adresu v contact_messages (osobný údaj)
        $db->prepare("
            UPDATE contact_messages
            SET ip_address = NULL,
                first_name = 'Deleted',
                last_name  = 'User'
            WHERE user_id = :user_id
        ")->execute(['user_id' => $userId]);
 
        // 5. Anonymizuj IP v audit_log pre tohto usera
        $db->prepare("
            UPDATE audit_log
            SET ip_address = NULL,
                user_agent = NULL
            WHERE user_id = :user_id
        ")->execute(['user_id' => $userId]);
 
        $db->commit();
 
    } catch (Exception $e) {
        $db->rollBack();
        error_log('delete_account error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Deletion failed. Please try again.']);
        exit;
    }
 
    // Vymaž visitor_id cookie z browsera
    $appConfig = require __DIR__ . '/../config/app.php';
    setcookie($appConfig['visitor']['cookie_name'], '', time() - 3600, '/');
    setcookie('cookie_consent', '', time() - 3600, '/');
 
    logout();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>