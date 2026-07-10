<?php
require '../inc/bootstrap.php';

$rawEmail = $_SESSION['pending_verification_email'] ?? '';
$email = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
$hasPendingEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
$clientIp = ClientIpResolver::current();
$userService = new User();
$expectsJson = str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($expectsJson ? '' : 'verification-sent');

    $result = $hasPendingEmail
        ? $userService->resendVerificationEmail($email, $clientIp)
        : [
            'success' => false,
            'message' => 'The verification request is no longer available. Please sign in again.',
            'remainingSeconds' => 0,
        ];

    if ($expectsJson) {
        header('Content-Type: application/json');
        if (!$result['success'] && !empty($result['remainingSeconds'])) {
            http_response_code(429);
        }
        echo json_encode($result);
        exit;
    }

    $_SESSION['verification_resend_notice'] = [
        'type' => $result['success'] ? 'success' : 'error',
        'message' => $result['message'],
    ];
    header('Location: verification-sent', true, 303);
    exit;
}

$resendState = $hasPendingEmail
    ? $userService->getVerificationResendState($email, $clientIp)
    : [
        'exists' => false,
        'verified' => false,
        'remainingSeconds' => 0,
        'canResend' => false,
    ];
$remainingSeconds = max(0, (int)$resendState['remainingSeconds']);
$cooldownUntil = time() + $remainingSeconds;
$notice = $_SESSION['verification_resend_notice'] ?? null;
unset($_SESSION['verification_resend_notice']);

require '../inc/header.php';
?>

<main class="verification-sent-page">
    <section class="verification-panel" aria-labelledby="verification-title">
        <div class="verification-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="20" height="16" x="2" y="4" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
        </div>

        <h1 id="verification-title">Check your email</h1>

        <?php if ($hasPendingEmail): ?>
            <p class="verification-lead">
                We sent a verification link to
                <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>
            </p>
            <p class="verification-copy">Can't find it? Check your spam folder.</p>
        <?php else: ?>
            <p class="verification-lead">This verification request has expired. Sign in again to continue.</p>
        <?php endif; ?>

        <?php if (is_array($notice) && !empty($notice['message'])): ?>
            <div class="verification-notice verification-notice--<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?>" role="status">
                <?php echo htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="verification-sent"
              class="verification-resend-form"
              data-server-now="<?php echo time(); ?>"
              data-cooldown-until="<?php echo $cooldownUntil; ?>"
              data-resend-available="<?php echo ($hasPendingEmail && !$resendState['verified']) ? 'true' : 'false'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit"
                    class="verification-primary-action btn-resend<?php echo $remainingSeconds > 0 ? ' countdown-active' : ''; ?>"
                    data-default-label="Resend verification email"
                    aria-describedby="verification-resend-status"
                    <?php echo (!$hasPendingEmail || $resendState['verified']) ? 'disabled' : ''; ?>>
                <?php echo $remainingSeconds > 0
                    ? 'Resend in ' . $remainingSeconds . 's'
                    : 'Resend verification email'; ?>
            </button>
            <p id="verification-resend-status" class="verification-resend-status" aria-live="polite"><?php
                echo $resendState['verified'] ? 'This email address is already verified.' : '';
            ?></p>
            <noscript>
                <p class="verification-noscript">Refresh the page when the countdown ends.</p>
            </noscript>
        </form>

        <a href="login" class="verification-back-link">Back to sign in</a>
    </section>
</main>

<script src="js/resendEmail.js?v=<?= filemtime(__DIR__ . '/js/resendEmail.js') ?>" defer></script>

<?php
require '../inc/footer.php';
?>
