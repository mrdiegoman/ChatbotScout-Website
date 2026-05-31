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
                <div style="display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;background: #00E53A;border-radius:50%;margin-bottom:20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="42" height="42" fill="white"><path d="M530.8 134.1C545.1 144.5 548.3 164.5 537.9 178.8L281.9 530.8C276.4 538.4 267.9 543.1 258.5 543.9C249.1 544.7 240 541.2 233.4 534.6L105.4 406.6C92.9 394.1 92.9 373.8 105.4 361.3C117.9 348.8 138.2 348.8 150.7 361.3L252.2 462.8L486.2 141.1C496.6 126.8 516.6 123.6 530.9 134z"/></svg>
                </div>
                <h2 class="auth-title" style="color: #00E53A;">Email Verified!</h2>
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