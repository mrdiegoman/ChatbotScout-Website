<?php
/**
 * AJAX Endpoint - Visitor Identification
 *
 * Runs only after Statistics consent. The first-party visitor_id cookie is the
 * canonical identity; fingerprinting is a consented helper signal, not the
 * source of truth.
 */

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

if (!hasStatisticsConsent()) {
    echo json_encode(['success' => true, 'action' => 'no_consent']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_payload']);
    exit;
}

$fingerprint = strtolower(trim((string)($data['fingerprint'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_fingerprint']);
    exit;
}

$cleanString = static function ($value, int $maxLength): ?string {
    if (!is_string($value)) {
        return null;
    }

    $value = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '');
    if ($value === '') {
        return null;
    }

    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
};

$visitContext = [
    'landing_page' => $cleanString($data['landing_page'] ?? null, 1000),
    'referrer_url' => $cleanString($data['referrer_url'] ?? null, 1000),
    'utm_source'   => $cleanString($data['utm_source'] ?? null, 255),
    'utm_medium'   => $cleanString($data['utm_medium'] ?? null, 255),
    'utm_campaign' => $cleanString($data['utm_campaign'] ?? null, 255),
    'utm_term'     => $cleanString($data['utm_term'] ?? null, 255),
    'utm_content'  => $cleanString($data['utm_content'] ?? null, 255),
];

$appConfig          = require __DIR__ . '/../../config/app.php';
$cookieVisitorIdRaw = $_COOKIE[$appConfig['visitor']['cookie_name']] ?? null;
$storedVisitorIdRaw = $cleanString($data['stored_visitor_id'] ?? null, 36);

try {
    $visitor = new Visitor();

    $cookieVisitorId = is_string($cookieVisitorIdRaw)
        ? $visitor->getExisting($cookieVisitorIdRaw)
        : null;

    if ($cookieVisitorIdRaw && $cookieVisitorId === null) {
        $visitor->clearCookie();
    }

    // Cookie wins. If the same fingerprint was previously attached to another
    // visitor, merge that duplicate into the current cookie visitor.
    if ($cookieVisitorId !== null) {
        $fingerprintVisitorId = $visitor->findByFingerprint($fingerprint);
        $action = 'match';

        if ($fingerprintVisitorId !== null && $fingerprintVisitorId !== $cookieVisitorId) {
            $visitor->mergeVisitors($fingerprintVisitorId, $cookieVisitorId);
            $action = 'merged';
        }

        $visitor->recordFingerprint($cookieVisitorId, $fingerprint);
        $visitor->setCookie($cookieVisitorId);
        $visitor->deleteOrphanedEmpty($cookieVisitorId);

        echo json_encode([
            'success'    => true,
            'action'     => $action,
            'visitor_id' => $cookieVisitorId,
        ]);
        exit;
    }

    // If the cookie is missing but consented localStorage still has the visitor
    // ID, restore that server-side HttpOnly cookie.
    if ($storedVisitorIdRaw) {
        $storedVisitorId = $visitor->getExisting($storedVisitorIdRaw);
        if ($storedVisitorId !== null) {
            $visitor->recordFingerprint($storedVisitorId, $fingerprint);
            $visitor->setCookie($storedVisitorId);
            $visitor->deleteOrphanedEmpty($storedVisitorId);

            echo json_encode([
                'success'    => true,
                'action'     => 'restored_from_storage',
                'visitor_id' => $storedVisitorId,
            ]);
            exit;
        }
    }

    // Logged-in users may already have a consented visitor linked to their
    // account. Prefer that over creating a fresh anonymous visitor.
    if (!empty($_SESSION['user']['id'])) {
        $linkedVisitorId = $visitor->findLatestForUser((string)$_SESSION['user']['id']);
        if ($linkedVisitorId !== null) {
            $visitor->recordFingerprint($linkedVisitorId, $fingerprint);
            $visitor->setCookie($linkedVisitorId);
            $visitor->deleteOrphanedEmpty($linkedVisitorId);

            echo json_encode([
                'success'    => true,
                'action'     => 'restored_from_user',
                'visitor_id' => $linkedVisitorId,
            ]);
            exit;
        }
    }

    // Final fallback: only after Statistics consent, restore by known
    // fingerprint. If no signal matches, create one new visitor.
    $fingerprintVisitorId = $visitor->findByFingerprint($fingerprint);
    if ($fingerprintVisitorId !== null) {
        $visitor->recordFingerprint($fingerprintVisitorId, $fingerprint);
        $visitor->setCookie($fingerprintVisitorId);
        $visitor->deleteOrphanedEmpty($fingerprintVisitorId);

        echo json_encode([
            'success'    => true,
            'action'     => 'restored_from_fingerprint',
            'visitor_id' => $fingerprintVisitorId,
        ]);
        exit;
    }

    $visitorId = $visitor->createWithFingerprint($fingerprint, $visitContext);
    $visitor->deleteOrphanedEmpty($visitorId);

    echo json_encode([
        'success'    => true,
        'action'     => 'created',
        'visitor_id' => $visitorId,
    ]);
} catch (Throwable $e) {
    error_log('identify error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}
