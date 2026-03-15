<?php
/**
 * Admin Submission Actions
 * Actions: validate_chatbot | accept | review | reject | remove | save_notes
 */

require '../inc/bootstrap.php';
require '../app/guard.php';

requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$id     = trim($input['id'] ?? '');

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Missing chatbot ID.']);
    exit;
}

$db        = Database::getInstance()->getConnection();
$appConfig = require '../config/app.php';

// ── Helper: get pending count ──────────────────────────────────────────────────
function getPendingCount(PDO $db): int {
    return (int)$db->query("
        SELECT COUNT(*) FROM chatbots
        WHERE status = 'pending' AND submitted_at IS NOT NULL
    ")->fetchColumn();
}

// ── Helper: validate chatbot readiness ────────────────────────────────────────
function validateChatbot(PDO $db, string $id): array {
    // Fetch chatbot data
    $stmt = $db->prepare("
        SELECT c.description, c.logo_url, c.affiliate_link,
               c.starting_price, c.pricing_model
        FROM chatbots c
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $chatbot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chatbot) {
        return ['valid' => false, 'rules' => [], 'error' => 'Chatbot not found.'];
    }

    // Features count
    $featStmt = $db->prepare("
        SELECT COUNT(*) FROM chatbot_features
        WHERE chatbot_id = :id
          AND (boolean_value = 1 OR text_value IS NOT NULL)
    ");
    $featStmt->execute(['id' => $id]);
    $featureCount = (int)$featStmt->fetchColumn();

    // Integrations count
    $intStmt = $db->prepare("
        SELECT COUNT(*) FROM chatbot_integrations
        WHERE chatbot_id = :id AND is_supported = 1
    ");
    $intStmt->execute(['id' => $id]);
    $integrationCount = (int)$intStmt->fetchColumn();

    $isFree = $chatbot['pricing_model'] === 'free';

    // Validate starting_price: if not free, must be > 0
    $priceOk = $isFree
        ? true
        : ($chatbot['starting_price'] !== null && (float)$chatbot['starting_price'] > 0.00);

    $rules = [
        [
            'label' => 'Description is set',
            'ok'    => !empty($chatbot['description']),
        ],
        [
            'label' => 'Logo is uploaded',
            'ok'    => !empty($chatbot['logo_url']),
        ],
        [
            'label' => 'Affiliate link is set',
            'ok'    => !empty($chatbot['affiliate_link']),
        ],
        [
            'label' => $isFree
                ? 'Starting price (free — skipped)'
                : 'Starting price is set (> 0.00)',
            'ok'    => $priceOk,
            'skip'  => $isFree,
        ],
        [
            'label' => 'At least 1 feature',
            'ok'    => $featureCount >= 1,
        ],
        [
            'label' => 'At least 1 integration',
            'ok'    => $integrationCount >= 1,
        ],
    ];

    $allPassed = array_reduce($rules, function (bool $carry, array $rule): bool {
        return $carry && ($rule['ok'] || ($rule['skip'] ?? false));
    }, true);

    return [
        'valid' => $allPassed,
        'rules' => $rules,
    ];
}

// ── Fetch chatbot + owner ──────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT c.id, c.name, c.website_url, c.status, c.is_verified,
           c.approved_at, c.rejected_at, c.deleted_at, c.last_verified_at, c.verified_at,
           u.email AS user_email, u.first_name, u.last_name
    FROM chatbots c
    JOIN users u ON c.owner_user_id = u.id
    WHERE c.id = :id AND c.submitted_at IS NOT NULL
");
$stmt->execute(['id' => $id]);
$chatbot = $stmt->fetch();

if (!$chatbot) {
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

// ── ACTIONS ────────────────────────────────────────────────────────────────────

switch ($action) {

    // ── VALIDATE CHATBOT (pre-accept check, no DB change) ─────────────────────
    case 'validate_chatbot':
        $result = validateChatbot($db, $id);
        echo json_encode([
            'success'    => true,
            'valid'      => $result['valid'],
            'rules'      => $result['rules'],
            'chatbot'    => [
                'id'   => $chatbot['id'],
                'name' => $chatbot['name'],
            ],
        ]);
        break;

    // ── ACCEPT → status=active, is_verified=1, approved_at, verified_at, email ─
    case 'accept':
        $validation = validateChatbot($db, $id);
        if (!$validation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => 'Chatbot does not meet all requirements.',
                'rules'   => $validation['rules'],
            ]);
            break;
        }

        $db->prepare("
            UPDATE chatbots SET
                status           = 'active',
                is_verified      = 1,
                approved_at      = NOW(),
                verified_at      = NOW(),
                last_verified_at = NOW(),
                rejected_at      = NULL,
                deleted_at       = NULL
            WHERE id = :id
        ")->execute(['id' => $id]);

        AuditLog::log('chatbot', $id, 'approved',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'active', 'is_verified' => true]
        );

        $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';
        try {
            $email = new Email();
            $email->sendChatbotApproved(
                $chatbot['user_email'],
                $userName,
                $chatbot['name']
            );
        } catch (Exception $e) {
            error_log('Accept email error: ' . $e->getMessage());
        }

        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot accepted and is now active.',
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── REVIEW → status=reviewed, is_verified=1, approved_at ─────────────────
    case 'review':
        // Block: if currently active, require a delist reason
        if ($chatbot['status'] === 'active') {
            $reason = trim($input['reason'] ?? '');
 
            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Delist reason is required when moving an active chatbot to reviewed.']);
                exit;
            }
 
            if (mb_strlen($reason) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
                exit;
            }
 
            $db->prepare("
                UPDATE chatbots SET
                    status      = 'reviewed',
                    is_verified = 0,
                    approved_at = NULL,
                    verified_at = NULL,
                    rejected_at = NULL,
                    deleted_at  = NULL
                WHERE id = :id
            ")->execute(['id' => $id]);
 
            AuditLog::log('chatbot', $id, 'updated',
                ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
                ['status' => 'reviewed', 'is_verified' => false, 'delist_reason' => $reason]
            );
 
            $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';
            try {
                $email = new Email();
                $email->sendChatbotDelisted(
                    $chatbot['user_email'],
                    $userName,
                    $chatbot['name'],
                    $reason
                );
            } catch (Exception $e) {
                error_log('Delist email error (review): ' . $e->getMessage());
            }
 
            echo json_encode([
                'success'      => true,
                'message'      => 'Chatbot delisted and moved to reviewed.',
                'pendingCount' => getPendingCount($db),
            ]);
            break;
        }
 
        // Normal flow (not active)
        $db->prepare("
            UPDATE chatbots SET
                status      = 'reviewed',
                is_verified = 1,
                approved_at = NULL,
                verified_at = NOW(),
                rejected_at = NULL,
                deleted_at  = NULL
            WHERE id = :id
        ")->execute(['id' => $id]);
 
        AuditLog::log('chatbot', $id, 'approved',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'reviewed', 'is_verified' => true]
        );
 
        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot marked as reviewed.',
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── REJECT → is_verified=0, rejected_at, clears approved/deleted/verified ──
    case 'reject':
        // Block: cannot reject an active chatbot directly
        if ($chatbot['status'] === 'active') {
            echo json_encode(['success' => false, 'message' => 'Cannot reject an active chatbot. Use "Reviewed" or "Remove" to delist it first.']);
            exit;
        }
 
        $reason = trim($input['reason'] ?? '');
 
        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
            exit;
        }
 
        if (mb_strlen($reason) > 2000) {
            echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
            exit;
        }
 
        $db->prepare("
            UPDATE chatbots SET
                status      = 'rejected',
                is_verified = 0,
                rejected_at = NOW(),
                approved_at = NULL,
                verified_at = NULL,
                deleted_at  = NULL,
                rejection_reason = :reason
            WHERE id = :id
        ")->execute(['id' => $id, 'reason' => $reason]);
 
        AuditLog::log('chatbot', $id, 'rejected',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'rejected', 'is_verified' => false, 'rejection_reason' => $reason]
        );
 
        $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';
        try {
            $email = new Email();
            $email->sendChatbotRejected(
                $chatbot['user_email'],
                $userName,
                $chatbot['name'],
                $reason
            );
        } catch (Exception $e) {
            error_log('Reject email error: ' . $e->getMessage());
        }
 
        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot rejected.',
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── REMOVE → status=removed, is_verified=0, deleted_at, clears others ─────
    case 'remove':
        $wasActive = $chatbot['status'] === 'active';
 
        if ($wasActive) {
            $reason = trim($input['reason'] ?? '');
 
            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Delist reason is required when removing an active chatbot.']);
                exit;
            }
 
            if (mb_strlen($reason) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Reason is too long (max 2000 characters).']);
                exit;
            }
        }
 
        $db->prepare("
            UPDATE chatbots SET
                status      = 'removed',
                is_verified = 0,
                deleted_at  = NOW(),
                approved_at = NULL,
                verified_at = NULL,
                rejected_at = NULL
            WHERE id = :id
        ")->execute(['id' => $id]);
 
        AuditLog::log('chatbot', $id, 'deleted',
            ['status' => $chatbot['status'], 'is_verified' => (bool)$chatbot['is_verified']],
            ['status' => 'removed', 'is_verified' => false]
        );
 
        if ($wasActive) {
            $userName = trim($chatbot['first_name'] . ' ' . $chatbot['last_name']) ?: 'there';
            try {
                $email = new Email();
                $email->sendChatbotDelisted(
                    $chatbot['user_email'],
                    $userName,
                    $chatbot['name'],
                    $reason
                );
            } catch (Exception $e) {
                error_log('Delist email error (remove): ' . $e->getMessage());
            }
        }
 
        echo json_encode([
            'success'      => true,
            'message'      => 'Chatbot removed.' . ($wasActive ? ' Delist email sent.' : ''),
            'pendingCount' => getPendingCount($db),
        ]);
        break;

    // ── SAVE NOTES ────────────────────────────────────────────────────────────
    case 'save_notes':
        $notes = trim($input['notes'] ?? '');

        if (mb_strlen($notes) > 5000) {
            echo json_encode(['success' => false, 'message' => 'Notes are too long (max 5000 characters).']);
            exit;
        }

        $db->prepare("
            UPDATE chatbots SET admin_notes = :notes WHERE id = :id
        ")->execute(['notes' => $notes ?: null, 'id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Notes saved.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}