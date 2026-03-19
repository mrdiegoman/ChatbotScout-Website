<?php
/**
 * API: Revoke Consent Cookies
 *
 * Vymaže server-side cookies ktoré JS nemôže zmazať (httponly).
 * Volá sa z Consent.js pri rejectAll() alebo savePreferences() kde statistics = false.
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$appConfig = require __DIR__ . '/../config/app.php';

// Vymaž visitor_id cookie
setcookie(
    $appConfig['visitor']['cookie_name'],
    '',
    time() - 3600,
    '/',
    '',
    false,
    true
);

echo json_encode(['success' => true]);