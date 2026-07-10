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
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

validateCsrfToken();

$appConfig = require __DIR__ . '/../../config/app.php';

$cookieName = $appConfig['visitor']['cookie_name'];

// Vymaž HttpOnly visitor_id cookie. Historické analytické záznamy v DB nemažeme
// automaticky; revocation zastaví ďalšie identifikované tracking requesty.
expireAppCookie($cookieName, '/', true, (bool)$appConfig['visitor']['cookie_secure'], 'Lax');

echo json_encode(['success' => true]);
