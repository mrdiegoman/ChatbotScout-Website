<?php
/**
 * API: Revoke Consent Cookies
 *
 * Vymaže server-side cookies ktoré JS nemôže zmazať (httponly).
 * Volá sa z Consent.js pri rejectAll() alebo savePreferences() kde statistics = false.
 */

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

validateCsrfToken();

$appConfig = require __DIR__ . '/../../config/app.php';

$cookieName = $appConfig['visitor']['cookie_name'];

// Vymaž HttpOnly visitor_id cookie. Historické analytické záznamy v DB nemažeme
// automaticky; revocation zastaví ďalšie identifikované tracking requesty.
setcookie($cookieName, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => (bool)$appConfig['visitor']['cookie_secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);

unset($_COOKIE[$cookieName]);

echo json_encode(['success' => true]);
