<?php
/**
 * Profile Actions Handler
 * 
 * AJAX endpoint pre profile operations
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../../inc/stripePaymentFinalizer.php';

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

// ✅ Exceptions - actions ktoré NEVYŽADUJÚ prihlásenie
$publicActions = ['resend_verification'];

// Must be logged in (okrem public actions)
if (!in_array($action, $publicActions) && !$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $publicActions)) {
    validateCsrfToken();
}

function deleteLocalProfilePicture(?string $profilePictureUrl): void
{
    if (!$profilePictureUrl || strpos($profilePictureUrl, 'default-avatar') !== false) {
        return;
    }

    $path = parse_url($profilePictureUrl, PHP_URL_PATH) ?: $profilePictureUrl;
    $relativePath = ltrim(str_replace('\\', '/', $path), '/');

    if (!str_starts_with($relativePath, 'img/avatars/')) {
        return;
    }

    $avatarDir = realpath(__DIR__ . '/../img/avatars');
    $targetPath = realpath(__DIR__ . '/../' . $relativePath);

    if (!$avatarDir || !$targetPath || !is_file($targetPath)) {
        return;
    }

    if (strpos($targetPath, $avatarDir . DIRECTORY_SEPARATOR) !== 0) {
        return;
    }

    if (!unlink($targetPath)) {
        throw new RuntimeException('Failed to delete profile picture file');
    }
}

function profileReturnUrl(string $tab = 'profile'): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $publicPath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

    return $scheme . '://' . $host . $publicPath . '/profile.php?tab=' . rawurlencode($tab);
}

function configureStripeForProfileActions(): void
{
    $stripeConfig = require __DIR__ . '/../../config/stripe.php';
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    if (!empty($stripeConfig['api_version'])) {
        \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
    }
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
    $appConfig = require __DIR__ . '/../../config/app.php';
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
            $oldPath = __DIR__ . '/../' . $user['profile_picture_url'];
            error_log($oldPath);
            error_log(file_exists($oldPath) ? 'EXISTS' : 'NOT EXISTS');
            if (file_exists($oldPath)) unlink($oldPath);
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
        $newUrl = 'img/avatars/' . $filename;
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
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');

    $db = Database::getInstance()->getConnection();
    if ($subscriptionId) {
        $stmt = $db->prepare("
            SELECT stripe_customer_id, stripe_subscription_id
            FROM subscriptions
            WHERE id = :subscription_id
              AND user_id = :user_id
              AND status IN ('active', 'trialing', 'past_due')
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'user_id' => $user['id'],
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT stripe_customer_id, stripe_subscription_id
            FROM subscriptions
            WHERE user_id = :user_id
              AND status IN ('active', 'trialing', 'past_due')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $user['id']]);
    }
    $sub = $stmt->fetch();
    
    if (!$sub || !$sub['stripe_customer_id']) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
    } else {
        try {
            configureStripeForProfileActions();

            $portalSession = \Stripe\BillingPortal\Session::create([
                'customer' => $sub['stripe_customer_id'],
                'return_url' => profileReturnUrl('profile'),
            ]);

            echo json_encode(['success' => true, 'url' => $portalSession->url]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe customer portal error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Stripe customer portal error: ' . $e->getMessage(),
            ]);
        }
    }
    
    exit;
}

// ============================================
// RENEW SUBSCRIPTION
// ============================================

if ($action === 'renew_subscription') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');

    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Missing subscription']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, status, cancel_at_period_end, stripe_subscription_id
        FROM subscriptions
        WHERE id = :subscription_id
          AND user_id = :user_id
          AND status IN ('active', 'trialing', 'past_due')
        LIMIT 1
    ");
    $stmt->execute([
        'subscription_id' => $subscriptionId,
        'user_id' => $user['id'],
    ]);
    $sub = $stmt->fetch();

    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
        exit;
    }

    if (empty($sub['stripe_subscription_id'])) {
        echo json_encode(['success' => false, 'message' => 'Stripe subscription was not found']);
        exit;
    }

    try {
        configureStripeForProfileActions();

        $stripeSubscription = \Stripe\Subscription::retrieve($sub['stripe_subscription_id']);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');

        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            syncStripeSubscriptionStatus($db, $stripeSubscription);
            echo json_encode(['success' => false, 'message' => 'This subscription has already ended and cannot be renewed.']);
            exit;
        }

        if (empty($stripeSubscription->cancel_at_period_end) && empty($sub['cancel_at_period_end'])) {
            syncStripeSubscriptionStatus($db, $stripeSubscription);
            echo json_encode(['success' => true, 'message' => 'Subscription is already renewed.']);
            exit;
        }

        $stripeSubscription = \Stripe\Subscription::update($sub['stripe_subscription_id'], [
            'cancel_at_period_end' => false,
        ]);

        syncStripeSubscriptionStatus($db, $stripeSubscription);

        $db->prepare("
            UPDATE subscriptions
            SET cancel_at_period_end = FALSE,
                auto_renew = CASE
                    WHEN status IN ('active', 'trialing') THEN TRUE
                    ELSE auto_renew
                END,
                cancelled_at = NULL,
                cancellation_reason = NULL,
                updated_at = NOW()
            WHERE stripe_subscription_id = :stripe_subscription_id
              AND user_id = :user_id
        ")->execute([
            'stripe_subscription_id' => $sub['stripe_subscription_id'],
            'user_id' => $user['id'],
        ]);

        AuditLog::log('subscription', $sub['id'], 'updated',
            ['cancel_at_period_end' => true],
            ['cancel_at_period_end' => false, 'auto_renew' => true]
        );

        echo json_encode(['success' => true, 'message' => 'Subscription renewed.']);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe renew subscription error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Stripe could not renew this subscription: ' . $e->getMessage(),
        ]);
    }

    exit;
}

// ============================================
// CANCEL SUBSCRIPTION
// ============================================

if ($action === 'cancel_subscription') {
    $data = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');
    $cancelType = $data['cancelType'] ?? 'at-period-end';
    $reason = $data['reason'] ?? null;

    if (!in_array($cancelType, ['at-period-end', 'immediately'], true)) {
        $cancelType = 'at-period-end';
    }

    $reason = is_string($reason) ? trim($reason) : null;
    if ($reason === '') {
        $reason = null;
    }

    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Missing subscription']);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, status, end_date, cancel_at_period_end, stripe_subscription_id
        FROM subscriptions
        WHERE id = :subscription_id
          AND user_id = :user_id
          AND status IN ('active', 'trialing', 'past_due')
        LIMIT 1
    ");
    $stmt->execute([
        'subscription_id' => $subscriptionId,
        'user_id' => $user['id'],
    ]);
    $sub = $stmt->fetch();
    
    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
        exit;
    }

    if (!empty($sub['cancel_at_period_end']) && $cancelType !== 'immediately') {
        echo json_encode(['success' => true, 'message' => 'Subscription is already scheduled for cancellation.']);
        exit;
    }

    if (empty($sub['stripe_subscription_id'])) {
        echo json_encode(['success' => false, 'message' => 'Stripe subscription was not found']);
        exit;
    }

    try {
        configureStripeForProfileActions();

        $stripeSubscription = \Stripe\Subscription::retrieve($sub['stripe_subscription_id']);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');

        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            syncStripeSubscriptionStatus($db, $stripeSubscription);

            $db->prepare("
                UPDATE subscriptions
                SET status = 'cancelled',
                    auto_renew = FALSE,
                    cancel_at_period_end = FALSE,
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancellation_reason = COALESCE(:reason, cancellation_reason),
                    updated_at = NOW()
                WHERE stripe_subscription_id = :stripe_subscription_id
                  AND user_id = :user_id
            ")->execute([
                'reason' => $reason,
                'stripe_subscription_id' => $sub['stripe_subscription_id'],
                'user_id' => $user['id'],
            ]);

            echo json_encode(['success' => true, 'message' => 'Subscription was already cancelled in Stripe.']);
            exit;
        }

        if ($cancelType === 'immediately') {
            $stripeSubscription = $stripeSubscription->cancel();
        } else {
            if (!empty($stripeSubscription->cancel_at_period_end)) {
                syncStripeSubscriptionStatus($db, $stripeSubscription);
                echo json_encode(['success' => true, 'message' => 'Subscription is already scheduled for cancellation.']);
                exit;
            }

            $stripeSubscription = \Stripe\Subscription::update($sub['stripe_subscription_id'], [
                'cancel_at_period_end' => true,
            ]);
        }

        syncStripeSubscriptionStatus($db, $stripeSubscription);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe cancel subscription error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Stripe could not cancel this subscription: ' . $e->getMessage(),
        ]);
        exit;
    }
    
    if ($cancelType === 'immediately') {
        $stmt = $db->prepare("
            UPDATE subscriptions
            SET status = 'cancelled',
                auto_renew = FALSE,
                cancelled_at = NOW(),
                end_date = NOW(),
                cancellation_reason = :reason
            WHERE stripe_subscription_id = :stripe_subscription_id
              AND user_id = :user_id
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE subscriptions
            SET cancel_at_period_end = TRUE,
                auto_renew = FALSE,
                cancelled_at = NOW(),
                cancellation_reason = :reason
            WHERE stripe_subscription_id = :stripe_subscription_id
              AND user_id = :user_id
        ");
    }
    
    $stmt->execute([
        'reason' => $reason,
        'stripe_subscription_id' => $sub['stripe_subscription_id'],
        'user_id' => $user['id'],
    ]);

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
        $userId    = $user['id'];
        $anonymousEmail = 'deleted_' . $userId . '@deleted.invalid';

        $stripeSubStmt = $db->prepare("
            SELECT DISTINCT stripe_subscription_id
            FROM subscriptions
            WHERE user_id = :user_id
              AND status IN ('active', 'trialing', 'past_due')
              AND stripe_subscription_id IS NOT NULL
        ");
        $stripeSubStmt->execute(['user_id' => $userId]);
        $stripeSubscriptionIds = $stripeSubStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($stripeSubscriptionIds)) {
            configureStripeForProfileActions();
            foreach ($stripeSubscriptionIds as $stripeSubscriptionId) {
                $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                $stripeSubscription->cancel([
                    'cancellation_details' => [
                        'comment' => 'Account deleted by user',
                    ],
                ]);
            }
        }

        $db->beginTransaction();

        $pictureStmt = $db->prepare("
            SELECT profile_picture_url
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $pictureStmt->execute(['id' => $userId]);
        $profilePictureUrl = $pictureStmt->fetchColumn();

        deleteLocalProfilePicture($profilePictureUrl ?: null);
 
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('delete_account error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Deletion failed. Please try again.']);
        exit;
    }
 
    // Vymaž visitor_id cookie z browsera
    $appConfig = require __DIR__ . '/../../config/app.php';
    setcookie($appConfig['visitor']['cookie_name'], '', time() - 3600, '/');
    setcookie('cookie_consent', '', time() - 3600, '/');
 
    logout();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
