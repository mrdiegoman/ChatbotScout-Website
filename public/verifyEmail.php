<?php
require '../inc/bootstrap.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login');
    exit;
}

$success = false;
$error = '';

try {
    $userService = new User();
    $userService->verifyEmail($token);
    unset($_SESSION['pending_verification_email']);
    $success = true;
} catch (Exception $e) {
    $error = $e->getMessage();
}

require '../inc/header.php';
?>

<main class="verification-sent-page">
    <section class="verification-panel" aria-labelledby="verification-result-title">
        <?php if ($success): ?>
            <div class="verification-icon verification-icon--success" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m5 12 4 4L19 6"/>
                </svg>
            </div>
            <h1 id="verification-result-title">Email verified</h1>
            <p class="verification-lead">Your email has been successfully verified. You can now sign in to your account.</p>
            <a href="login?verified=success" class="verification-primary-action">Go to sign in</a>
        <?php else: ?>
            <div class="verification-icon verification-icon--error" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </div>
            <h1 id="verification-result-title">Verification failed</h1>
            <p class="verification-lead">
                <?php echo htmlspecialchars($error ?: 'The verification link is invalid or has expired.', ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <a href="login" class="verification-primary-action">Back to sign in</a>
            <a href="sign-up" class="verification-back-link">Create a new account</a>
        <?php endif; ?>
    </section>
</main>

<?php
require '../inc/footer.php';
?>
