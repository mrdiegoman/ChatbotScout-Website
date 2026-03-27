<?php
require '../inc/bootstrap.php';

// Získaj token z URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php');
    exit;
}

$success = false;
$error = '';

try {
    $userService = new User();
    $userService->verifyEmail($token);
    
    $success = true;
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimiter->reset('resend_verification', $clientIp, $email);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

require '../inc/header.php';
?>

<div class="auth-page-wrapper">
    <div class="auth-container">
        <!-- Logo and Brand -->
        <div class="auth-header">
            <img src="img/ChatbotScout_logo.png" alt="ChatbotScout Logo">
            <h1>ChatbotScout</h1>
        </div>
        
        <?php if ($success): ?>
            <!-- Success State -->
            <div style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
                <h2 class="auth-title" style="color: #4CAF50;">Email Verified!</h2>
                <p style="color: #666; margin: 20px 0; font-size: 16px;">
                    Your email has been successfully verified. You can now log in to your account.
                </p>
                <a href="login.php?verified=success" class="submit-btn" style="display: inline-block; text-decoration: none; margin-top: 20px;">
                    Go to Login
                </a>
            </div>
        <?php else: ?>
            <!-- Error State -->
            <div style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 64px; margin-bottom: 20px;">❌</div>
                <h2 class="auth-title" style="color: #f44336;">Verification Failed</h2>
                <div class="message error" style="margin: 20px 0;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <p style="color: #666; margin: 20px 0; font-size: 14px;">
                    The verification link may have expired or is invalid.
                </p>
                <div style="margin-top: 30px;">
                    <a href="login.php" style="color: #2196F3; text-decoration: none; font-weight: 600;">
                        Back to Login
                    </a>
                    <span style="margin: 0 10px; color: #ccc;">|</span>
                    <a href="signUp.php" style="color: #2196F3; text-decoration: none; font-weight: 600;">
                        Create New Account
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require '../inc/footer.php';
?>