<?php
require '../inc/bootstrap.php';

// Získaj token z URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login');
    exit;
}

// ✅ Získaj errors zo session (PRG pattern)
$error = $_SESSION['reset_errors'] ?? null;

// ✅ Vymaž zo session (single-use)
unset($_SESSION['reset_errors']);

$userService = new User();
$tokenIsValid = $_SERVER['REQUEST_METHOD'] === 'POST'
    || $userService->isPasswordResetTokenValid($token);

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken('reset-password?token=' . urlencode($_GET['token'] ?? ''));
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please fill in all fields.";
        
        // ✅ PRG: Ulož do session a redirect
        $_SESSION['reset_errors'] = $error;
        header('Location: reset-password?token=' . urlencode($token));
        exit;
        
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
        
        // ✅ PRG: Ulož do session a redirect
        $_SESSION['reset_errors'] = $error;
        header('Location: reset-password?token=' . urlencode($token));
        exit;
        
    } elseif ($passwordErrors = PasswordPolicy::validate($newPassword)) {
        $error = implode(' ', $passwordErrors);
        
        // ✅ PRG: Ulož do session a redirect
        $_SESSION['reset_errors'] = $error;
        header('Location: reset-password?token=' . urlencode($token));
        exit;
        
    } else {
        try {
            $userService->resetPassword($token, $newPassword);
            
            // ✅ Úspešný reset - ulož success do session a presmeruj
            $_SESSION['login_success'] = 'Password reset successful! You can now log in.';
            header('Location: login');
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            
            // ✅ PRG: Ulož do session a redirect
            $_SESSION['reset_errors'] = $error;
            header('Location: reset-password?token=' . urlencode($token));
            exit;
        }
    }
}

require '../inc/header.php';
?>

<?php if (!$tokenIsValid): ?>
    <main class="verification-sent-page">
        <section class="verification-panel" aria-labelledby="reset-result-title">
            <div class="verification-icon verification-icon--error" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </div>
            <h1 id="reset-result-title">Reset link unavailable</h1>
            <p class="verification-lead">
                <?php echo htmlspecialchars($error ?: 'This password reset link is invalid, expired, or has already been used.', ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <a href="forgot-password" class="verification-primary-action">Request a new link</a>
            <a href="login" class="verification-back-link">Back to sign in</a>
        </section>
    </main>
<?php else: ?>
    <div class="auth-page-wrapper">
        <div class="auth-container">
            <!-- Logo and Brand -->
            <div class="auth-header">
                <img src="img/ChatbotScout_logo.png" alt="ChatbotScout Logo">
                <h1>ChatbotScout</h1>
            </div>

            <!-- Page Title -->
            <h2 class="auth-title">Reset Password</h2>
        
        <!-- Instructions -->
        <p class="auth-instruction">
            Enter your new password below.
        </p>
        
        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Reset Password Form -->
        <form method="POST" action="reset-password?token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <!-- New Password Field -->
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="password-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        autocomplete="new-password"
                    >
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="password-hint">
                    Your password must contain at least 8 characters, an uppercase letter, a lowercase letter, a number, and a special character (for example !, @, or #).
                </div>
            </div>
            
            <!-- Confirm Password Field -->
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-wrapper">
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        autocomplete="new-password"
                    >
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="submit-btn">Reset Password</button>
        </form>
        
        <!-- Footer - Back to Login -->
        <div class="auth-footer">
            <a href="login">Back to Sign In</a>
        </div>
        </div>
    </div>
<?php endif; ?>

<!-- Password Toggle Script -->
<script src="js/passwordToggle.js?v=<?= filemtime(__DIR__ . '/js/passwordToggle.js') ?>"></script>

<?php
require '../inc/footer.php';
?>
