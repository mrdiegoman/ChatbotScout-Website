<?php
/**
 * Advertise Actions
 * Handles promotion validation and Stripe redirect
 *
 * ZMENA: Subscription + payment sa vytvárajú AŽ po úspešnej platbe (webhook).
 * Tu len validujeme a vrátime promotion details pre checkout.
 */

require '../../inc/bootstrap.php';
require __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
}

$action = $_GET['action'] ?? '';

function repairScheduledPromotionPeriods(PDO $db, string $userId): void
{
    $stmt = $db->prepare("
        UPDATE subscriptions scheduled
        JOIN (
            SELECT
                user_id,
                chatbot_id,
                stripe_subscription_id,
                MAX(end_date) AS max_end_date
            FROM subscriptions
            WHERE user_id = :repair_group_user_id
              AND subscription_type = 'promotion'
              AND stripe_subscription_id IS NOT NULL
              AND status IN ('active', 'trialing', 'past_due', 'pending')
              AND end_date >= NOW()
            GROUP BY user_id, chatbot_id, stripe_subscription_id
        ) active_group
          ON active_group.user_id = scheduled.user_id
         AND active_group.chatbot_id = scheduled.chatbot_id
         AND active_group.stripe_subscription_id = scheduled.stripe_subscription_id
        SET scheduled.end_date = active_group.max_end_date,
            scheduled.updated_at = NOW()
        WHERE scheduled.user_id = :repair_user_id
          AND scheduled.subscription_type = 'promotion'
          AND scheduled.cancel_at_period_end = TRUE
          AND scheduled.auto_renew = FALSE
          AND scheduled.stripe_subscription_id IS NOT NULL
          AND scheduled.end_date < active_group.max_end_date
    ");
    $stmt->execute([
        'repair_group_user_id' => $userId,
        'repair_user_id' => $userId,
    ]);
}

switch ($action) {

    // ─── Check if user has active listing ───────────────────────────────────
    case 'check_listing':
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM subscriptions s
                JOIN chatbots c ON c.id = s.chatbot_id
                WHERE c.owner_user_id     = :user_id
                  AND s.subscription_type = 'listing'
                  AND s.status            = 'active'
            ");
            $stmt->execute(['user_id' => $user['id']]);
            $count = (int) $stmt->fetchColumn();

            echo json_encode(['success' => true, 'hasListing' => $count > 0]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error checking listing']);
        }
        break;

    // ─── Validate promotion + return details for checkout ───────────────────
    case 'create_promotion':
        $input       = json_decode(file_get_contents('php://input'), true);
        $chatbotId   = trim($input['chatbot_id']   ?? '');
        $promotionId = trim($input['promotion_id'] ?? '');

        if (!$chatbotId || !$promotionId) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        try {
            $db = Database::getInstance()->getConnection();

            // ── Verify chatbot belongs to user and is active ─────────────────
            repairScheduledPromotionPeriods($db, $user['id']);

            $chatbotStmt = $db->prepare("
                SELECT c.id
                FROM chatbots c
                WHERE c.id           = :chatbot_id
                  AND c.owner_user_id = :user_id
                  AND c.deleted_at   IS NULL
                  AND c.status       = 'active'
                LIMIT 1
            ");
            $chatbotStmt->execute([
                'chatbot_id' => $chatbotId,
                'user_id'    => $user['id'],
            ]);

            if (!$chatbotStmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid chatbot or no active listing subscription',
                ]);
                exit;
            }

            // ── Load promotion plan ──────────────────────────────────────────
            $promoStmt = $db->prepare("
                SELECT p.*, bp.type AS period_type, bp.duration_days AS period_duration,
                       c.id AS currency_id
                FROM promotions p
                JOIN billing_periods bp ON p.billing_period_id = bp.id
                JOIN currencies      c  ON p.currency_id       = c.id
                WHERE p.id        = :promotion_id
                  AND p.is_active = 1
                  AND p.deleted_at IS NULL
            ");
            $promoStmt->execute(['promotion_id' => $promotionId]);
            $promo = $promoStmt->fetch(PDO::FETCH_ASSOC);

            if (!$promo) {
                echo json_encode(['success' => false, 'message' => 'Promotion plan not found']);
                exit;
            }

            // ── Check for existing active promotion of same slot_type ────────
            $existingStmt = $db->prepare("
                SELECT s.id
                FROM subscriptions s
                JOIN promotions pr ON s.promotions_id = pr.id
                WHERE s.user_id           = :user_id
                  AND s.chatbot_id        = :chatbot_id
                  AND s.subscription_type = 'promotion'
                  AND s.end_date          >= NOW()
                  AND s.status IN ('active', 'trialing', 'past_due')
                  AND pr.slot_type        = :slot_type
                LIMIT 1
            ");
            $existingStmt->execute([
                'user_id'    => $user['id'],
                'chatbot_id' => $chatbotId,
                'slot_type'  => $promo['slot_type'],
            ]);

            if ($existingStmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You already have an active promotion for this slot',
                ]);
                exit;
            }

            // ── Všetko OK – vráť len IDs pre checkout ────────────────────────
            // Subscription + payment sa vytvoria až po úspešnej platbe (webhook)
            echo json_encode([
                'success'      => true,
                'chatbot_id'   => $chatbotId,
                'promotion_id' => $promotionId,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            error_log('advertise error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to validate promotion']);
        }
        break;
    
    // ─── Check active promotions for chatbot ────────────────────────────────
    case 'check_active_promotions':
        $chatbotId = trim($_GET['chatbot_id'] ?? '');
 
        if (!$chatbotId) {
            echo json_encode(['success' => false, 'message' => 'Missing chatbot_id']);
            break;
        }
 
        try {
            $db = Database::getInstance()->getConnection();
 
            repairScheduledPromotionPeriods($db, $user['id']);

            $stmt = $db->prepare("
                SELECT
                    s.id AS subscription_id,
                    s.stripe_subscription_id,
                    s.status,
                    s.start_date,
                    s.end_date,
                    s.cancel_at_period_end,
                    pr.id AS promotion_id,
                    pr.slot_type,
                    bp.type AS plan_billing_period,
                    (
                        SELECT COALESCE(
                            JSON_UNQUOTE(JSON_EXTRACT(pay.metadata, '$.billing_period')),
                            JSON_UNQUOTE(JSON_EXTRACT(pay.metadata, '$.period_type'))
                        )
                        FROM payments pay
                        WHERE pay.subscription_id = s.id
                          AND pay.status = 'succeeded'
                        ORDER BY COALESCE(pay.succeeded_at, pay.created_at) DESC
                        LIMIT 1
                    ) AS latest_payment_billing_period
                FROM subscriptions s
                JOIN promotions pr ON s.promotions_id = pr.id
                JOIN billing_periods bp ON pr.billing_period_id = bp.id
                WHERE s.user_id           = :user_id
                  AND s.chatbot_id        = :chatbot_id
                  AND s.subscription_type = 'promotion'
                  AND s.end_date          >= NOW()
                  AND s.status IN ('active', 'trialing', 'past_due')
            ");
            $stmt->execute([
                'user_id'    => $user['id'],
                'chatbot_id' => $chatbotId,
            ]);
 
            $activePromotions = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $planBillingPeriod = promotionNormalizeBillingPeriod($row['plan_billing_period'] ?? 'monthly');
                $paymentBillingPeriod = promotionNormalizeBillingPeriod($row['latest_payment_billing_period'] ?? '', '');
                $dateBillingPeriod = promotionBillingPeriodFromDates(
                    $row['start_date'] ?? null,
                    $row['end_date'] ?? null,
                    $planBillingPeriod
                );

                $row['billing_period'] = $paymentBillingPeriod
                    ?: (in_array($dateBillingPeriod, ['monthly', 'yearly'], true) ? $dateBillingPeriod : $planBillingPeriod);
                $activePromotions[] = $row;
            }

            $activeSlots = array_values(array_unique(array_column($activePromotions, 'slot_type')));
 
            echo json_encode([
                'success'           => true,
                'active_slots'      => $activeSlots, // napr. ["Homepage"] alebo ["Homepage", "Chatbots page"]
                'active_promotions' => $activePromotions,
            ]);
 
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error checking promotions']);
        }
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
