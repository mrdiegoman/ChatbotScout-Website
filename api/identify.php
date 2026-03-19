<?php
/**
 * AJAX Endpoint - FingerprintJS Visitor Identification
 *
 * Spúšťa sa po každom načítaní stránky (cez JS).
 * Zabezpečuje že visitor má správnu cookie aj po jej vymazaní.
 *
 * Logika:
 *  - Má cookie + fingerprint sa zhoduje → nič (OK)
 *  - Má cookie + fingerprint patrí inému staršiemu visitoru → obnov cookie na starého visitora
 *  - Má cookie + fingerprint nikde v DB → ulož fingerprint na aktuálneho visitora
 *  - Nemá cookie (edge case) → nič, bootstrap sa postará pri ďalšom page loade
 *  - Statistics consent nie je udelený → okamžite zastav, nič nerob
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

// ============================================
// GDPR: Statistics consent check
// Supports both v2 (JSON) and legacy (string) cookie
// ============================================

function hasStatisticsConsent(): bool
{
    // Check new v2 cookie first
    $v2 = $_COOKIE['cookie_consent_v2'] ?? null;
    if ($v2) {
        $consent = json_decode($v2, true);
        if (is_array($consent)) {
            return !empty($consent['statistics']);
        }
    }

    // Fallback: legacy cookie
    return ($_COOKIE['cookie_consent'] ?? null) === 'accepted';
}

if (!hasStatisticsConsent()) {
    echo json_encode(['success' => true, 'action' => 'no_consent']);
    exit;
}

$data        = json_decode(file_get_contents('php://input'), true);
$fingerprint = trim($data['fingerprint'] ?? '');

if (empty($fingerprint)) {
    echo json_encode(['success' => false, 'reason' => 'no_fingerprint']);
    exit;
}

$appConfig       = require __DIR__ . '/../config/app.php';
$cookieVisitorId = $_COOKIE[$appConfig['visitor']['cookie_name']] ?? null;

// Ak nie je cookie, nemáme čo robiť - bootstrap to vyrieši pri ďalšom page loade
if (empty($cookieVisitorId)) {
    echo json_encode(['success' => true, 'action' => 'no_cookie']);
    exit;
}

try {
    $visitor = new Visitor();

    // Hľadaj visitora podľa FingerprintJS fingerprintu
    $fingerprintVisitorId = $visitor->findByFingerprint($fingerprint);

    if ($fingerprintVisitorId !== null) {

        if ($cookieVisitorId === $fingerprintVisitorId) {
            // Visitor sa zhoduje s cookie aj fingerprintom - všetko OK
            echo json_encode(['success' => true, 'action' => 'match', 'visitor_id' => $cookieVisitorId]);

        } else {
            // Fingerprint patrí inému (staršiemu) visitoru.
            // Bootstrap vytvoril nového prázdneho visitora - vymažeme ho (orphaned).
            // Potom obnovíme cookie na pôvodného visitora.
            $visitor->deleteIfEmpty($cookieVisitorId);
            $visitor->setCookie($fingerprintVisitorId);
            // Vrátime správne visitor_id aby JS mohol okamžite aktualizovať cookie
            echo json_encode(['success' => true, 'action' => 'restored', 'visitor_id' => $fingerprintVisitorId]);
        }

    } else {
        // Fingerprint nebol nájdený v DB - aktualizuj fingerprint na aktuálnom visitorovi.
        $visitor->updateFingerprint($cookieVisitorId, $fingerprint);
        echo json_encode(['success' => true, 'action' => 'updated', 'visitor_id' => $cookieVisitorId]);
    }

    // Cleanup orphaned visitorov s prázdnym fingerprintom staršších ako 5 minút
    $visitor->deleteOrphanedEmpty($cookieVisitorId);

} catch (Exception $e) {
    error_log('identify error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}