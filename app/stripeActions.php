<?php
/**
 * Stripe Actions
 * Handles: create_payment_intent
 *
 * ZMENA: Neprijíma subscription_id.
 * Prijíma chatbot_id + promotion_id, vytvorí PaymentIntent s metadatami.
 * Subscription + payment sa vytvárajú AŽ v stripeWebhook.php po succeeded.
 */

require '../inc/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

// ── Create Payment Intent ─────────────────────────────────────────────────────
if ($action === 'create_payment_intent') {

    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $input        = json_decode(file_get_contents('php://input'), true);
    $chatbotId    = trim($input['chatbot_id']    ?? '');
    $promotionId  = trim($input['promotion_id']  ?? '');
    $promotionId2 = trim($input['promotion_id2'] ?? ''); // Bundle (optional)

    if (!$chatbotId || !$promotionId) {
        echo json_encode(['success' => false, 'message' => 'Missing chatbot_id or promotion_id']);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();

        // ── Verify chatbot belongs to user ───────────────────────────────────
        $chatbotStmt = $db->prepare("
            SELECT id, name
            FROM chatbots
            WHERE id             = :chatbot_id
            AND owner_user_id  = :user_id
            AND deleted_at     IS NULL
            LIMIT 1
        ");
        $chatbotStmt->execute([
            'chatbot_id' => $chatbotId,
            'user_id'    => $user['id'],
        ]);

        if (!$chatbotStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid chatbot']);
            exit;
        }

        // Stripe customer je na users tabuľke
        $userStmt = $db->prepare("
            SELECT id, email, first_name, last_name, stripe_customer_id
            FROM users
            WHERE id = :user_id
        ");
        $userStmt->execute(['user_id' => $user['id']]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // ── Load promotion 1 ─────────────────────────────────────────────────
        $promoStmt = $db->prepare("
            SELECT p.*, bp.type AS period_type, bp.duration_days,
                   c.code AS currency_code, c.id AS currency_id
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

        $finalPrice = floatval($promo['price']) * (1 - floatval($promo['discount_percentage']) / 100);
        $totalPrice = $finalPrice;
        $currency   = strtolower($promo['currency_code']);

        // ── Load promotion 2 (bundle) ────────────────────────────────────────
        $promo2     = null;
        $finalPrice2 = 0;
        if ($promotionId2) {
            $promoStmt2 = $db->prepare("
                SELECT p.*, bp.type AS period_type, bp.duration_days,
                       c.code AS currency_code, c.id AS currency_id
                FROM promotions p
                JOIN billing_periods bp ON p.billing_period_id = bp.id
                JOIN currencies      c  ON p.currency_id       = c.id
                WHERE p.id        = :promotion_id
                  AND p.is_active = 1
                  AND p.deleted_at IS NULL
            ");
            $promoStmt2->execute(['promotion_id' => $promotionId2]);
            $promo2 = $promoStmt2->fetch(PDO::FETCH_ASSOC);
            if ($promo2) {
                $finalPrice2 = floatval($promo2['price']) * (1 - floatval($promo2['discount_percentage']) / 100);
                $totalPrice += $finalPrice2;
            }
        }

        $amountCents = (int) round($totalPrice * 100);

        // ── Get or create Stripe Customer ────────────────────────────────────
        $stripeCustomerId = $userData['stripe_customer_id'];

        if (!$stripeCustomerId) {
            $customer = \Stripe\Customer::create([
                'email' => $userData['email'],
                'name'  => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
                'metadata' => ['user_id' => $userData['id']],
            ]);
            $stripeCustomerId = $customer->id;

            $db->prepare("UPDATE users SET stripe_customer_id = :cid WHERE id = :uid")
               ->execute(['cid' => $stripeCustomerId, 'uid' => $userData['id']]);
        }

        // ── Create PaymentIntent ─────────────────────────────────────────────
        // Všetky potrebné info ukladáme do metadata – webhook ich použije na vytvorenie subscription + payment
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount'             => $amountCents,
            'currency'           => $currency,
            'customer'           => $stripeCustomerId,
            'setup_future_usage' => 'off_session',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata'           => [
                'user_id'         => $userData['id'],
                'chatbot_id'      => $chatbotId,
                'promotion_id'    => $promotionId,
                'promotion_id2'   => $promotionId2 ?: '',
                'amount'          => $finalPrice,
                'amount2'         => $finalPrice2,
                'period_type'     => $promo['period_type'],
                'duration_days'   => $promo['duration_days'],
                'currency_id'     => $promo['currency_id'],
            ],
        ]);

        echo json_encode([
            'success'       => true,
            'client_secret' => $paymentIntent->client_secret,
            'amount'        => $totalPrice,
            'currency'      => $promo['currency_code'],
        ]);

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log('stripeActions error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);