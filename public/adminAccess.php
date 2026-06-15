<?php
require '../inc/bootstrap.php';
require 'app/guard.php';

$entry = trim((string)($_GET['entry'] ?? ''), " \t\n\r\0\x0B/");

if (!AdminAuth::pathMatches($entry)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

if (AdminAuth::isAuthenticated()) {
    header('Location: admin');
    exit;
}

$loginPath = AdminAuth::loginUrl();
$error = $_SESSION['admin_login_error'] ?? '';
$oldInput = $_SESSION['admin_login_input'] ?? [];
unset($_SESSION['admin_login_error'], $_SESSION['admin_login_input']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($loginPath);

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $totpCode = trim((string)($_POST['totp_code'] ?? ''));

    $result = AdminAuth::authenticate($username, $password, $totpCode);

    if (!empty($result['success'])) {
        header('Location: admin');
        exit;
    }

    $_SESSION['admin_login_error'] = $result['message'] ?? 'Unable to sign in.';
    $_SESSION['admin_login_input'] = ['username' => $username];
    header('Location: ' . $loginPath);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Operations Access - ChatbotScout</title>
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-32x32.png">
    <link rel="stylesheet" href="css/reset.css?v=<?= filemtime(__DIR__ . '/css/reset.css') ?>">
    <link rel="stylesheet" href="css/adminAccess.css?v=<?= filemtime(__DIR__ . '/css/adminAccess.css') ?>">
</head>
<body class="ops-access-page">
    <main class="ops-access-shell">
        <section class="ops-access-card" aria-labelledby="ops-access-title">
            <a href="./" class="ops-access-brand" aria-label="Go to ChatbotScout homepage">
                <img src="img/ChatbotScout_logo.png" alt="ChatbotScout">
            </a>

            <div class="ops-access-heading">
                <h1 id="ops-access-title">Operations Access</h1>
                <p>Sign in with your dedicated credentials and authenticator code.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="ops-access-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($loginPath, ENT_QUOTES, 'UTF-8') ?>" class="ops-access-form" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label class="ops-access-field" for="username">
                    <span>Username</span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars($oldInput['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="username"
                        inputmode="text"
                        spellcheck="false"
                        required
                    >
                </label>

                <label class="ops-access-field" for="password">
                    <span>Password</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <label class="ops-access-field" for="totp_code">
                    <span>Authenticator code</span>
                    <input
                        type="text"
                        id="totp_code"
                        name="totp_code"
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        pattern="[0-9 ]{6,}"
                        maxlength="12"
                        placeholder="000000"
                    >
                </label>

                <button type="submit" class="ops-access-submit">Continue</button>
            </form>
        </section>
    </main>
</body>
</html>
