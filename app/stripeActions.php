<?php
/**
 * Stripe Actions
 * Handles: create_payment_intent
 */

if (!defined('BOOTSTRAP_LOADED')) {
    http_response_code(403);
    exit;
}

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

    $input          = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = trim($input['subscription_id'] ?? '');

    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Missing subscription ID']);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Load subscription + promotion + payment
        $stmt = $db->prepare("
            SELECT
                s.id            AS subscription_id,
                s.user_id,
                s.chatbot_id,
                p.price,
                p.discount_percentage,
                p.slot_type,
                bp.type         AS billing_period_type,
                c.code          AS currency_code,
                pay.id          AS payment_id,
                pay.amount      AS payment_amount,
                u.email         AS user_email,
                u.first_name,
                u.last_name,
                u.stripe_customer_id
            FROM subscriptions s
            JOIN promotions     p   ON p.id  = s.promotions_id
            JOIN billing_periods bp ON bp.id = p.billing_period_id
            JOIN currencies      c  ON c.id  = p.currency_id
            JOIN payments        pay ON pay.subscription_id = s.id
            JOIN users           u   ON u.id = s.user_id
            WHERE s.id       = :subscription_id
              AND s.user_id  = :user_id
              AND s.status   = 'pending'
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'user_id'         => $user['id']
        ]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            echo json_encode(['success' => false, 'message' => 'Subscription not found or already processed']);
            exit;
        }

        // ── Get or create Stripe Customer ────────────────────────────────────
        $stripeCustomerId = $sub['stripe_customer_id'];

        if (!$stripeCustomerId) {
            $customer = \Stripe\Customer::create([
                'email' => $sub['user_email'],
                'name'  => trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? '')),
                'metadata' => [
                    'user_id' => $sub['user_id']
                ]
            ]);
            $stripeCustomerId = $customer->id;

            // Save to users table
            $db->prepare("UPDATE users SET stripe_customer_id = :cid WHERE id = :uid")
               ->execute(['cid' => $stripeCustomerId, 'uid' => $sub['user_id']]);
        }

        // ── Create PaymentIntent ─────────────────────────────────────────────
        $amountCents = (int) round(floatval($sub['payment_amount']) * 100);
        $currency    = strtolower($sub['currency_code']);

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount'               => $amountCents,
            'currency'             => $currency,
            'customer'             => $stripeCustomerId,
            'setup_future_usage'   => 'off_session', // pre auto-renewal
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'subscription_id' => $subscriptionId,
                'payment_id'      => $sub['payment_id'],
                'user_id'         => $sub['user_id'],
                'slot_type'       => $sub['slot_type'],
                'billing_period'  => $sub['billing_period_type'],
            ]
        ]);

        // Save stripe_payment_intent_id to payments
        $db->prepare("
            UPDATE payments
            SET stripe_payment_intent_id = :pi_id,
                stripe_customer_id       = :cid
            WHERE id = :payment_id
        ")->execute([
            'pi_id'      => $paymentIntent->id,
            'cid'        => $stripeCustomerId,
            'payment_id' => $sub['payment_id']
        ]);

        // Save stripe_customer_id to subscription
        $db->prepare("
            UPDATE subscriptions
            SET stripe_customer_id = :cid
            WHERE id = :sid
        ")->execute([
            'cid' => $stripeCustomerId,
            'sid' => $subscriptionId
        ]);

        echo json_encode([
            'success'       => true,
            'client_secret' => $paymentIntent->client_secret,
            'payment_id'    => $sub['payment_id'],
            'amount'        => $sub['payment_amount'],
            'currency'      => $sub['currency_code'],
            'slot_type'     => $sub['slot_type'],
            'billing_period'=> $sub['billing_period_type'],
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

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
