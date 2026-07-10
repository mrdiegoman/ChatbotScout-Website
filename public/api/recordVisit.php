<?php
/**
 * AJAX Endpoint - Track Click
 */

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

validateCsrfToken();

$data       = json_decode(file_get_contents('php://input'), true);
$data       = is_array($data) ? $data : [];
$chatbotId  = is_string($data['chatbot_id'] ?? null) ? trim($data['chatbot_id']) : '';
$sourcePage = is_string($data['source_page'] ?? null) ? trim($data['source_page']) : null;

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $chatbotId)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

if ($sourcePage !== null) {
    $sourcePage = preg_replace('/[\x00-\x1F\x7F]/', '', $sourcePage);
    $sourcePage = substr($sourcePage, 0, 1000);
    $sourcePage = $sourcePage === '' ? null : $sourcePage;
}

try {
    $db = Database::getInstance()->getConnection();

    $chatbotStmt = $db->prepare("
        SELECT id
        FROM chatbots
        WHERE id = :id AND status = 'active' AND deleted_at IS NULL
        LIMIT 1
    ");
    $chatbotStmt->execute([':id' => $chatbotId]);

    if (!$chatbotStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false]);
        exit;
    }

    $rateLimiter = new RateLimiter();
    $clientIp    = ClientIpResolver::current();

    if (!$rateLimiter->check('chatbot_click', $clientIp)) {
        http_response_code(429);
        echo json_encode(['success' => false]);
        exit;
    }

    if ($rateLimiter->recordAttempt('chatbot_click', $clientIp) > 0) {
        http_response_code(429);
        echo json_encode(['success' => false]);
        exit;
    }

    // Detailed per-visitor click analytics are optional Statistics tracking.
    $cookieVisitorId = null;

    if (hasStatisticsConsent()) {
        $cookieVisitorId = $visitorId ?? null;

        // Fallback for logged-in users whose visitor cookie has not reached this
        // request yet, but who already have a consented visitor link.
        if (empty($cookieVisitorId) && !empty($_SESSION['user']['id'])) {
            $visitor = new Visitor();
            $cookieVisitorId = $visitor->findLatestForUser((string)$_SESSION['user']['id']);
        }
    }

    $shouldIncrementAggregate = false;

    if (!empty($cookieVisitorId)) {

        // Check if a record exists for this visitor+chatbot within the last 30 days
        $stmt = $db->prepare("
            SELECT id
            FROM chatbot_clicks
            WHERE visitor_id = :visitor_id
              AND chatbot_id = :chatbot_id
              AND clicked_at >= NOW() - INTERVAL 30 DAY
            LIMIT 1
        ");
        $stmt->execute([
            'visitor_id' => $cookieVisitorId,
            'chatbot_id' => $chatbotId,
        ]);

        $recentClick = $stmt->fetch(PDO::FETCH_ASSOC);

        // Only insert if no recent click exists in the last 30 days
        if (!$recentClick) {
            $clickId = sprintf('%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6))
            );

            $db->prepare("
                INSERT INTO chatbot_clicks (id, chatbot_id, visitor_id, source_page, clicked_at)
                VALUES (:id, :chatbot_id, :visitor_id, :source_page, NOW())
            ")->execute([
                'id'          => $clickId,
                'chatbot_id'  => $chatbotId,
                'visitor_id'  => $cookieVisitorId,
                'source_page' => $sourcePage,
            ]);

            $shouldIncrementAggregate = true;
        }
    } else {
        $sessionKey    = 'counted_chatbot_clicks';
        $dedupeSeconds = 30 * 24 * 60 * 60;
        $maxSessionIds = 200;
        $now           = time();

        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }

        foreach ($_SESSION[$sessionKey] as $trackedChatbotId => $timestamp) {
            if (!is_int($timestamp) || ($now - $timestamp) > $dedupeSeconds) {
                unset($_SESSION[$sessionKey][$trackedChatbotId]);
            }
        }

        if (count($_SESSION[$sessionKey]) > $maxSessionIds) {
            asort($_SESSION[$sessionKey]);
            $_SESSION[$sessionKey] = array_slice($_SESSION[$sessionKey], -$maxSessionIds, null, true);
        }

        if (!isset($_SESSION[$sessionKey][$chatbotId])) {
            $_SESSION[$sessionKey][$chatbotId] = $now;
            $shouldIncrementAggregate = true;
        }
    }

    if ($shouldIncrementAggregate) {
        $db->prepare("
            UPDATE chatbots
            SET click_count = click_count + 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND status = 'active' AND deleted_at IS NULL
        ")->execute([':id' => $chatbotId]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('recordVisit error: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}
