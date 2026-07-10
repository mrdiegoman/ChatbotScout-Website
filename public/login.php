<?php
require '../inc/bootstrap.php';
require 'app/auth.php';

// Ak je už prihlásený, presmeruj na index
if ($isLoggedIn) {
    header('Location: ./');
    exit;
}

// ✅ Získaj errors zo session (PRG pattern)
$errors = $_SESSION['login_errors'] ?? [];
$oldInput = $_SESSION['login_input'] ?? [];
$successMessage = $_SESSION['login_success'] ?? null;

// ✅ Vymaž zo session (single-use)
unset($_SESSION['login_errors']);
unset($_SESSION['login_input']);
unset($_SESSION['login_success']);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken('login.php');

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    $errors = [];

    $userService = new User();

    try {
        $userData = $userService->login($email, $password);

        if (isset($userData['errors']) && !empty($userData['errors'])) {
            $errors = $userData['errors'];
            
            // ✅ PRG: Ulož do session a redirect
            $_SESSION['login_errors'] = $errors;
            $_SESSION['login_input'] = [
                'email' => $email,
                // Heslo NEUCHOVÁVAJ v session (security)
            ];
            
            header('Location: login');
            exit;

        } elseif (!empty($userData['requires_email_verification'])) {
            $_SESSION['pending_verification_email'] = $userData['email'];
            header('Location: verification-sent');
            exit;

        } else {
            // ✅ Úspešný login
            login($userData, $rememberMe);
            header('Location: ./');
            exit;
        }

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        
        // ✅ Map errors to specific fields or general
        if (strpos($errorMessage, 'Too many login attempts') !== false ||
            strpos($errorMessage, 'suspended') !== false ||
            strpos($errorMessage, 'disabled') !== false ||
            strpos($errorMessage, 'verify your email') !== false ||
            strpos($errorMessage, 'Invalid email or password') !== false) {
            // Globálne errory - na vrchu
            $errors['general'] = $errorMessage;
        } else {
            // Ostatné errory - general fallback
            $errors['general'] = $errorMessage;
        }
        
        // ✅ PRG: Ulož do session a redirect
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_input'] = [
            'email' => $email,
        ];
        
        header('Location: login');
        exit;
    }
}

require '../inc/header.php';
?>

<div class="auth-page-wrapper">
    <div class="auth-container">
        <!-- Logo and Brand -->
        <!-- <div class="auth-header">
            <img src="img/ChatbotScout_logo.png" alt="ChatbotScout Logo">
            <h1>ChatbotScout</h1>
        </div> -->
        
        <!-- Page Title -->
        <h2 class="auth-title">Sign In</h2>
        
        <!-- ✅ General Error Message (na vrchu) -->
        <?php if (!empty($errors['general'])): ?>
            <div class="message error"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>
        
        <!-- ✅ Success Message (password reset, email verification) -->
        <?php if (!empty($successMessage)): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        
        <!-- Success Message (po verifikácii emailu) -->
        <?php if (isset($_GET['verified']) && $_GET['verified'] === 'success'): ?>
            <div class="message success message--with-icon">
                <span class="message__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="12" height="12" fill="white"><path d="M530.8 134.1C545.1 144.5 548.3 164.5 537.9 178.8L281.9 530.8C276.4 538.4 267.9 543.1 258.5 543.9C249.1 544.7 240 541.2 233.4 534.6L105.4 406.6C92.9 394.1 92.9 373.8 105.4 361.3C117.9 348.8 138.2 348.8 150.7 361.3L252.2 462.8L486.2 141.1C496.6 126.8 516.6 123.6 530.9 134z"/></svg>
                </span>
                Email verified successfully! You can now log in.
            </div>
        <?php endif; ?>
        
        <!-- Google Sign In Button  -->
        <a href="google-auth" class="google-btn">
            <svg class="google-icon" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Sign in with Google
        </a>
        <p class="google-terms-text">
            By continuing, you agree to our
            <a href="terms-and-conditions">Terms & Conditions</a> and
            <a href="privacy-policy">Privacy Policy</a>.
        </p>
        
        <!-- Divider -->
        <div class="auth-divider-container">
            <div class="auth-divider-line"></div>
            <div class="auth-divider">or continue with</div>
            <div class="auth-divider-line"></div>
        </div>
        
        <!-- Login Form -->
        <form method="POST" action="login" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Email Field -->
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="text"
                    id="email" 
                    name="email" 
                    autocomplete="email"
                    value="<?php echo htmlspecialchars($oldInput['email'] ?? ''); ?>"
                    class="<?php echo !empty($errors['email']) ? 'error' : ''; ?>"
                >
                <?php if (!empty($errors['email'])): ?>
                    <?php foreach ($errors['email'] as $error): ?>
                        <small class="error-message">
                            <?php echo htmlspecialchars($error); ?>
                        </small>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Password Field -->
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        autocomplete="current-password"
                        class="<?php echo !empty($errors['password']) ? 'error' : ''; ?>"
                    >
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <?php foreach ($errors['password'] as $error): ?>
                        <small class="error-message">
                            <?php echo htmlspecialchars($error); ?>
                        </small>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Remember Me Checkbox -->
            <div class="checkbox-group">
                <input 
                    type="checkbox" 
                    id="remember_me" 
                    name="remember_me"
                >
                <label for="remember_me">
                    Stay signed in
                </label>
            </div>
            
            <!-- Forgot Password Link -->
            <div class="forgot-password">
                <a href="forgot-password">Forgot password?</a>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="submit-btn">Sign In</button>
        </form>
        
        <!-- Footer - Link to Sign Up -->
        <div class="auth-footer">
            Don't have an account? <a href="sign-up">Create one now</a>
        </div>
    </div>
</div>

<!-- Password Toggle Script -->
<script src="js/passwordToggle.js?v=<?= filemtime(__DIR__ . '/js/passwordToggle.js') ?>"></script>
<!-- ✅ Live Validation Script -->
<script src="js/authValidation.js?v=<?= filemtime(__DIR__ . '/js/authValidation.js') ?>"></script>

<?php
require '../inc/footer.php';
?>
