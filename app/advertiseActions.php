<?php
/**
 * Advertise Actions
 * Handles promotion subscription creation and Stripe redirect
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

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

    // ─── Create promotion subscription ──────────────────────────────────────
    case 'create_promotion':
        $input = json_decode(file_get_contents('php://input'), true);

        $chatbotId    = trim($input['chatbot_id']    ?? '');
        $promotionId  = trim($input['promotion_id']  ?? '');

        if (!$chatbotId || !$promotionId) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        try {
            $db = Database::getInstance()->getConnection();

            // ── Verify chatbot belongs to user and has active listing ────────
            $chatbotStmt = $db->prepare("
                SELECT c.id
                FROM chatbots c
                JOIN subscriptions s ON s.chatbot_id = c.id
                WHERE c.id                = :chatbot_id
                  AND c.owner_user_id     = :user_id
                  AND c.deleted_at        IS NULL
                  AND s.subscription_type = 'listing'
                  AND s.status            = 'active'
                LIMIT 1
            ");
            $chatbotStmt->execute([
                'chatbot_id' => $chatbotId,
                'user_id'    => $user['id']
            ]);

            if (!$chatbotStmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid chatbot or no active listing subscription'
                ]);
                exit;
            }

            // ── Load promotion plan ──────────────────────────────────────────
            $promoStmt = $db->prepare("
                SELECT p.*, bp.type AS period_type, bp.duration AS period_duration,
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
                WHERE s.user_id            = :user_id
                  AND s.chatbot_id         = :chatbot_id
                  AND s.subscription_type  = 'promotion'
                  AND s.status             = 'active'
                  AND pr.slot_type         = :slot_type
                LIMIT 1
            ");
            $existingStmt->execute([
                'user_id'   => $user['id'],
                'chatbot_id'=> $chatbotId,
                'slot_type' => $promo['slot_type']
            ]);

            if ($existingStmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You already have an active promotion for this slot'
                ]);
                exit;
            }

            // ── Calculate dates ──────────────────────────────────────────────
            $startDate = date('Y-m-d H:i:s');
            $endDate   = match($promo['period_type']) {
                'daily'   => date('Y-m-d H:i:s', strtotime("+{$promo['period_duration']} days")),
                'weekly'  => date('Y-m-d H:i:s', strtotime("+{$promo['period_duration']} weeks")),
                'monthly' => date('Y-m-d H:i:s', strtotime("+{$promo['period_duration']} months")),
                'yearly'  => date('Y-m-d H:i:s', strtotime("+{$promo['period_duration']} years")),
                default   => date('Y-m-d H:i:s', strtotime('+1 month'))
            };

            $db->beginTransaction();

            try {
                // ── Create subscription ──────────────────────────────────────
                $subscriptionId = generateUUID();
                $subStmt = $db->prepare("
                    INSERT INTO subscriptions (
                        id, user_id, chatbot_id, subscription_type,
                        promotions_id, status, start_date, end_date, auto_renew
                    ) VALUES (
                        :id, :user_id, :chatbot_id, 'promotion',
                        :promotions_id, 'pending', :start_date, :end_date, 1
                    )
                ");
                $subStmt->execute([
                    'id'            => $subscriptionId,
                    'user_id'       => $user['id'],
                    'chatbot_id'    => $chatbotId,
                    'promotions_id' => $promotionId,
                    'start_date'    => $startDate,
                    'end_date'      => $endDate
                ]);

                // ── Create payment record ────────────────────────────────────
                $finalPrice = $promo['price'] * (1 - ($promo['discount_percentage'] / 100));
                $paymentId  = generateUUID();
                $payStmt = $db->prepare("
                    INSERT INTO payments (
                        id, user_id, subscription_id, chatbot_id,
                        amount, currency_id, payment_method, status
                    ) VALUES (
                        :id, :user_id, :subscription_id, :chatbot_id,
                        :amount, :currency_id, 'stripe', 'pending'
                    )
                ");
                $payStmt->execute([
                    'id'              => $paymentId,
                    'user_id'         => $user['id'],
                    'subscription_id' => $subscriptionId,
                    'chatbot_id'      => $chatbotId,
                    'amount'          => $finalPrice,
                    'currency_id'     => $promo['currency_id']
                ]);

                $db->commit();

                // ── TODO: Replace with real Stripe Checkout Session ──────────
                // $stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
                // $session = $stripe->checkout->sessions->create([...]);
                // $stripeUrl = $session->url;

                $stripeUrl = 'https://checkout.stripe.com/placeholder?subscription_id=' . $subscriptionId;

                echo json_encode([
                    'success'         => true,
                    'subscription_id' => $subscriptionId,
                    'payment_id'      => $paymentId,
                    'redirect_url'    => $stripeUrl
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create promotion']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ── Helper: generate UUID v4 ────────────────────────────────────────────────
function generateUUID(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}