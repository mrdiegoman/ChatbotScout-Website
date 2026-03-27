<?php
require '../inc/bootstrap.php';
require '../app/auth.php';

// Ak je už prihlásený, presmeruj na index
if ($isLoggedIn) {
    header('Location: index.php');
    exit;
}

// ✅ Získaj errors zo session (PRG pattern)
$errors = $_SESSION['signup_errors'] ?? [];
$oldInput = $_SESSION['signup_input'] ?? [];

// ✅ Vymaž zo session (single-use)
unset($_SESSION['signup_errors']);
unset($_SESSION['signup_input']);

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken('signUp.php');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $termsAccepted = isset($_POST['terms_accepted']);
    $marketingEmails = isset($_POST['marketing_emails']);
    
    $errors = [];

    try {
        // Inicializácia User triedy
        $userService = new User();
        
        // Registrácia usera
        $userData = $userService->register($email, $password, $firstName, $lastName, $termsAccepted, $marketingEmails);
        
        if (isset($userData['errors']) && !empty($userData['errors'])) {
            $errors = $userData['errors'];
            
            // ✅ PRG: Ulož do session a redirect
            $_SESSION['signup_errors'] = $errors;
            $_SESSION['signup_input'] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'terms_accepted' => $termsAccepted,
                'marketing_emails' => $marketingEmails,
                // Heslo NEUCHOVÁVAJ v session (security)
            ];
            
            header('Location: signUp.php');
            exit;

        } else {
            // ✅ Úspešná registrácia → redirect
            header('Location: verificationSent.php?email=' . urlencode($userData['email']));
            exit;
        }
        
    } catch (Exception $e) {
        $errors['system'] = [$e->getMessage()];
        
        // ✅ PRG: Ulož do session a redirect
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_input'] = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'terms_accepted' => $termsAccepted,
            'marketing_emails' => $marketingEmails,
        ];
        
        header('Location: signUp.php');
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
        <h2 class="auth-title">Create an account</h2>
        
        <!-- Error Message -->
        <?php if (isset($errors['system'])): ?>
            <div class="message error"><?php echo htmlspecialchars($errors['system'][0]); ?></div>
        <?php endif; ?>
        
        <!-- Verification Sent Message -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'verification_sent'): ?>
            <div class="message success">
                ✉️ <strong>Check your email!</strong><br>
                We've sent you a verification link. Please click it to activate your account.
            </div>
        <?php endif; ?>
        
        <!-- Google Sign Up Button -->
        <a href="googleAuth.php" class="google-btn">
            <svg class="google-icon" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Sign up with Google
        </a>
        <p class="google-terms-text">
            By continuing, you agree to our
            <a href="termsAndConditions.php">Terms & Conditions</a> and
            <a href="privacyPolicy.php">Privacy Policy</a>.
        </p>
        
        <!-- Divider -->
        <div class="auth-divider-container">
            <div class="auth-divider-line"></div>
            <div class="auth-divider">or continue with</div>
            <div class="auth-divider-line"></div>
        </div>
        
        <!-- Registration Form -->
        <form method="POST" action="signUp.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="name-fields">
                <!-- First Name Field -->
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        value="<?php echo htmlspecialchars($oldInput['first_name'] ?? ''); ?>"
                        class="<?php echo !empty($errors['first_name']) ? 'error' : ''; ?>"
                    >
                    <?php if (!empty($errors['first_name'])): ?>
                        <?php foreach ($errors['first_name'] as $error): ?>
                            <small class="error-message">
                                <?php echo htmlspecialchars($error); ?>
                            </small>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Last Name Field -->
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        autocomplete="family-name"
                        value="<?php echo htmlspecialchars($oldInput['last_name'] ?? ''); ?>"
                        class="<?php echo !empty($errors['last_name']) ? 'error' : ''; ?>"
                    >
                    <?php if (!empty($errors['last_name'])): ?>
                        <?php foreach ($errors['last_name'] as $error): ?>
                            <small class="error-message">
                                <?php echo htmlspecialchars($error); ?>
                            </small>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
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
                        autocomplete="new-password"
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
                <?php else: ?>
                    <small class="password-hint">Minimum 8 characters</small>
                <?php endif; ?>
            </div>
            
            <!-- Terms of Service Checkbox (Required) -->
            <div class="checkbox-container">
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        id="terms_accepted" 
                        name="terms_accepted" 
                        <?php echo !empty($oldInput['terms_accepted']) ? 'checked' : ''; ?>
                    >
                    <label for="terms_accepted">
                        By signing up, you agree to our <a href="termsAndConditions.php" target="_blank">Terms of Service</a> and <a href="privacyPolicy.php" target="_blank">Privacy Policy</a>.
                    </label>
                </div>
                <?php if (!empty($errors['terms'])): ?>
                    <?php foreach ($errors['terms'] as $error): ?>
                        <small class="error-message">
                            <?php echo htmlspecialchars($error); ?>
                        </small>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Marketing Emails Checkbox (Optional) -->
            <div class="checkbox-group">
                <input 
                    type="checkbox" 
                    id="marketing_emails" 
                    name="marketing_emails"
                    <?php echo !empty($oldInput['marketing_emails']) ? 'checked' : ''; ?>
                >
                <label for="marketing_emails">
                    I'd like to receive product updates and marketing emails.
                </label>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="submit-btn">Create Account</button>
        </form>
        
        <!-- Footer - Link to Sign In -->
        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>
</div>

<!-- Password Toggle Script -->
<script src="js/passwordToggle.js"></script>
<!--  Live Validation Script -->
<script src="js/authValidation.js"></script>

<?php
require '../inc/footer.php';
?>