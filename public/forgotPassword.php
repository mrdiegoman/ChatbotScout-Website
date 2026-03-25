<?php
require '../inc/bootstrap.php';
require '../app/auth.php';

// Ak je už prihlásený, presmeruj na index
if ($isLoggedIn) {
    header('Location: index.php');
    exit;
}

// ✅ Získaj errors/success zo session (PRG pattern)
$error = $_SESSION['forgot_errors'] ?? null;
$success = $_SESSION['forgot_success'] ?? null;
$oldInput = $_SESSION['forgot_input'] ?? [];

// ✅ Vymaž zo session (single-use)
unset($_SESSION['forgot_errors']);
unset($_SESSION['forgot_success']);
unset($_SESSION['forgot_input']);

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken('forgotPassword.php');
    $email = trim($_POST['email'] ?? '');
    
    // ✅ Rate limiting check
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$rateLimiter->check('password_reset', $clientIp)) {
        $remaining = $rateLimiter->getRemainingLockoutTime('password_reset', $clientIp);
        if ($remaining >= 60) {
            $minutes = ceil($remaining / 60);
            $error = "Too many password reset attempts. Please try again in {$minutes} minutes.";
        } else {
            $error = "Too many password reset attempts. Please try again in {$remaining} seconds.";
        }
        
        // ✅ PRG: Ulož do session a redirect
        $_SESSION['forgot_errors'] = $error;
        $_SESSION['forgot_input'] = ['email' => $email];
        header('Location: forgotPassword.php');
        exit;
        
    } else {
        // Validácia
        if (empty($email)) {
            $error = "Please enter your email address.";
            // ✅ Record failed attempt
            $rateLimiter->recordFailedAttempt('password_reset', $clientIp);
            
            // ✅ PRG: Ulož do session a redirect
            $_SESSION['forgot_errors'] = $error;
            $_SESSION['forgot_input'] = ['email' => $email];
            header('Location: forgotPassword.php');
            exit;
            
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            // ✅ Record failed attempt
            $rateLimiter->recordFailedAttempt('password_reset', $clientIp);
            
            // ✅ PRG: Ulož do session a redirect
            $_SESSION['forgot_errors'] = $error;
            $_SESSION['forgot_input'] = ['email' => $email];
            header('Location: forgotPassword.php');
            exit;
            
        } else {
            try {
                // Inicializácia User triedy
                $userService = new User();
                
                // Odoslanie reset emailu
                $userService->sendPasswordReset($email);
                
                // ✅ Reset rate limiter (úspešný request)
                // $rateLimiter->reset('password_reset', $clientIp, $email);
                
                // Vždy zobraz success message (aj ak email neexistuje)
                $success = "If the email address exists in our system, you will receive a password reset link shortly.";
                
                // ✅ PRG: Ulož do session a redirect
                $_SESSION['forgot_success'] = $success;
                header('Location: forgotPassword.php');
                exit;
                
            } catch (Exception $e) {
                $error = $e->getMessage();
                // ✅ Record failed attempt
                $rateLimiter->recordFailedAttempt('password_reset', $clientIp, $email);
                
                // ✅ PRG: Ulož do session a redirect
                $_SESSION['forgot_errors'] = $error;
                $_SESSION['forgot_input'] = ['email' => $email];
                header('Location: forgotPassword.php');
                exit;
            }
        }
    }
}

require '../inc/header.php';
?>

<div class="auth-page-wrapper">
    <div class="auth-container">
        <!-- Logo and Brand -->
        <div class="auth-header">
            <img src="../img/ChatbotScout_logo.png" alt="ChatbotScout Logo">
            <h1>ChatbotScout</h1>
        </div>
        
        <!-- Page Title -->
        <h2 class="auth-title">Reset your password</h2>
        
        <!-- Success Message -->
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Password Reset Form -->
        <form method="POST" action="forgotPassword.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Email Field -->
            <div class="form-group">
                <label for="email">Email address</label>
                <input 
                    id="email" 
                    name="email" 
                    autocomplete="email"
                    value="<?php echo htmlspecialchars($oldInput['email'] ?? ''); ?>"
                    placeholder="Enter your email"
                >
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="submit-btn">Send reset link</button>
        </form>
        
        <!-- Footer - Back to login -->
        <div class="auth-footer">
            Remember your password? <a href="login.php">Sign In</a>
        </div>
    </div>
</div>

<?php
require '../inc/footer.php';
?>