<?php
/**
 * AJAX Endpoint - FingerprintJS Visitor Identification
 *
 * Spúšťa sa po každom načítaní stránky (cez JS).
 * Zabezpečuje že visitor má správnu cookie aj po jej vymazaní.
 *
 * Logika:
 *  - Nemá cookie + má fingerprint → vytvor nového visitora s fingerprintom hneď
 *  - Má cookie + fingerprint sa zhoduje → nič (OK)
 *  - Má cookie + fingerprint patrí inému staršiemu visitoru → obnov cookie na starého visitora
 *  - Má cookie + fingerprint nikde v DB → ulož fingerprint na aktuálneho visitora
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

// function hasStatisticsConsent(): bool
// {
//     $v2 = $_COOKIE['cookie_consent_v2'] ?? null;
//     if ($v2) {
//         $consent = json_decode($v2, true);
//         if (is_array($consent)) {
//             return !empty($consent['statistics']);
//         }
//     }

//     return ($_COOKIE['cookie_consent'] ?? null) === 'accepted';
// }

if (!hasStatisticsConsent()) {
    echo json_encode(['success' => true, 'action' => 'no_consent']);
    exit;
}

$data        = json_decode(file_get_contents('php://input'), true);
$fingerprint = trim($data['fingerprint'] ?? '');

if (empty($fingerprint) || mb_strlen($fingerprint) > 64 || !preg_match('/^[a-f0-9]+$/i', $fingerprint)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_fingerprint']);
    exit;
}

$appConfig       = require __DIR__ . '/../config/app.php';
$cookieVisitorId = $_COOKIE[$appConfig['visitor']['cookie_name']] ?? null;

// ============================================
// Nemá cookie — vytvor nového visitora s fingerprintom hneď
// ============================================

if (empty($cookieVisitorId)) {
    try {
        $visitor   = new Visitor();
        $visitorId = $visitor->createWithFingerprint($fingerprint);
        echo json_encode(['success' => true, 'action' => 'created', 'visitor_id' => $visitorId]);
    } catch (Exception $e) {
        error_log('identify createWithFingerprint error: ' . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// ============================================
// Má cookie — spracuj existujúceho visitora
// ============================================

try {
    $visitor = new Visitor();

    $fingerprintVisitorId = $visitor->findByFingerprint($fingerprint);

    if ($fingerprintVisitorId !== null) {

        if ($cookieVisitorId === $fingerprintVisitorId) {
            // Visitor sa zhoduje s cookie aj fingerprintom — všetko OK
            echo json_encode(['success' => true, 'action' => 'match', 'visitor_id' => $cookieVisitorId]);

        } else {
            // Fingerprint patrí inému (staršiemu) visitoru.
            // Vymažeme aktuálneho prázdneho visitora a obnovíme cookie na pôvodného.
            $visitor->deleteIfEmpty($cookieVisitorId);
            $visitor->setCookie($fingerprintVisitorId);
            echo json_encode(['success' => true, 'action' => 'restored', 'visitor_id' => $fingerprintVisitorId]);
        }

    } else {
        // Fingerprint nebol nájdený v DB — aktualizuj fingerprint na aktuálnom visitorovi
        $visitor->updateFingerprint($cookieVisitorId, $fingerprint);
        echo json_encode(['success' => true, 'action' => 'updated', 'visitor_id' => $cookieVisitorId]);
    }

    // Cleanup orphaned visitorov s prázdnym fingerprintom starších ako 5 minút
    $visitor->deleteOrphanedEmpty($cookieVisitorId);

} catch (Exception $e) {
    error_log('identify error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}