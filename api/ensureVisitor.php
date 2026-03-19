<?php
/**
 * API: Ensure Visitor ID
 *
 * Vytvorí visitor_id cookie okamžite po udelení súhlasu — bez nutnosti refreshu.
 * Volá sa z Consent.js po acceptAll() / savePreferences() kde statistics = true.
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Ak visitor_id už existuje, nič nerobíme
$appConfig = require __DIR__ . '/../config/app.php';
if (!empty($_COOKIE[$appConfig['visitor']['cookie_name']])) {
    echo json_encode(['success' => true, 'visitor_id' => $_COOKIE[$appConfig['visitor']['cookie_name']]]);
    exit;
}

try {
    $visitor   = new Visitor();
    $utmParams = [
        'utm_source'   => $_GET['utm_source']   ?? null,
        'utm_medium'   => $_GET['utm_medium']   ?? null,
        'utm_campaign' => $_GET['utm_campaign'] ?? null,
        'utm_term'     => $_GET['utm_term']     ?? null,
        'utm_content'  => $_GET['utm_content']  ?? null,
    ];

    $visitorId = $visitor->getOrCreate($utmParams);

    echo json_encode(['success' => true, 'visitor_id' => $visitorId]);

} catch (Exception $e) {
    error_log('ensureVisitor error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}
