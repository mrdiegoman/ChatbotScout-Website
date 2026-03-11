<?php
/**
 * Stripe Webhook Handler
 *
 * ZMENA: payment_intent.succeeded teraz vytvára payment + subscription naraz ako active.
 * Subscription sa nevytvára vopred ako pending.
 *
 * Handles:
 *  - payment_intent.succeeded      → vytvorí payment (succeeded) + subscription (active)
 *  - payment_intent.payment_failed → zaloguje chybu
 *  - payment_intent.canceled       → zaloguje zrušenie
 */

require '../inc/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $stripeConfig['webhook_secret']
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

$db = Database::getInstance()->getConnection();

// ── Helper: generate UUID v4 ─────────────────────────────────────────────────
function generateWebhookUuid(): string {
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── Handle events ─────────────────────────────────────────────────────────────
switch ($event->type) {

    // ── Payment succeeded → vytvor payment + subscription ────────────────────
    case 'payment_intent.succeeded':
        $pi = $event->data->object;

        $userId       = $pi->metadata->user_id      ?? null;
        $chatbotId    = $pi->metadata->chatbot_id    ?? null;
        $promotionId  = $pi->metadata->promotion_id  ?? null;
        $promotionId2 = $pi->metadata->promotion_id2 ?? null;
        $amount       = $pi->metadata->amount        ?? null;
        $amount2      = $pi->metadata->amount2       ?? null;
        $periodType   = $pi->metadata->period_type   ?? 'monthly';
        $durationDays = $pi->metadata->duration_days ?? 30;
        $currencyId   = $pi->metadata->currency_id   ?? null;

        if (!$userId || !$chatbotId || !$promotionId) {
            error_log("Stripe webhook: payment_intent.succeeded missing metadata, pi={$pi->id}");
            break;
        }

        // Získaj receipt URL z charge
        $receiptUrl = null;
        $chargeId   = null;
        if (!empty($pi->latest_charge)) {
            try {
                $charge     = \Stripe\Charge::retrieve($pi->latest_charge);
                $receiptUrl = $charge->receipt_url ?? null;
                $chargeId   = $charge->id          ?? null;
            } catch (Exception $e) {
                error_log("Stripe: Could not retrieve charge: " . $e->getMessage());
            }
        }

        try {
            $db->beginTransaction();

            // ── Vypočítaj dátumy subscription ────────────────────────────────
            $startDate = date('Y-m-d H:i:s');
            $endDate   = match($periodType) {
                'daily'   => date('Y-m-d H:i:s', strtotime("+{$durationDays} days")),
                'weekly'  => date('Y-m-d H:i:s', strtotime('+1 week')),
                'monthly' => date('Y-m-d H:i:s', strtotime('+1 month')),
                'yearly'  => date('Y-m-d H:i:s', strtotime('+1 year')),
                default   => date('Y-m-d H:i:s', strtotime('+1 month')),
            };

            // ── Vytvor subscription 1 (active) ───────────────────────────────
            $subscriptionId = generateWebhookUuid();
            $db->prepare("
                INSERT INTO subscriptions (
                    id, user_id, chatbot_id, subscription_type,
                    promotions_id, status, start_date, end_date, auto_renew,
                    stripe_customer_id, created_at, updated_at
                ) VALUES (
                    :id, :user_id, :chatbot_id, 'promotion',
                    :promotions_id, 'active', :start_date, :end_date, 1,
                    :stripe_customer_id, NOW(), NOW()
                )
            ")->execute([
                'id'                 => $subscriptionId,
                'user_id'            => $userId,
                'chatbot_id'         => $chatbotId,
                'promotions_id'      => $promotionId,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'stripe_customer_id' => $pi->customer ?? null,
            ]);

            // ── Vytvor payment 1 (succeeded) ─────────────────────────────────
            $paymentId = generateWebhookUuid();
            $db->prepare("
                INSERT INTO payments (
                    id, user_id, subscription_id, chatbot_id,
                    amount, currency_id, payment_method, status,
                    stripe_payment_intent_id, stripe_charge_id, stripe_customer_id,
                    receipt_url, succeeded_at, created_at, updated_at
                ) VALUES (
                    :id, :user_id, :subscription_id, :chatbot_id,
                    :amount, :currency_id, 'stripe', 'succeeded',
                    :pi_id, :charge_id, :stripe_customer_id,
                    :receipt_url, NOW(), NOW(), NOW()
                )
            ")->execute([
                'id'                 => $paymentId,
                'user_id'            => $userId,
                'subscription_id'    => $subscriptionId,
                'chatbot_id'         => $chatbotId,
                'amount'             => $amount,
                'currency_id'        => $currencyId,
                'pi_id'              => $pi->id,
                'charge_id'          => $chargeId,
                'stripe_customer_id' => $pi->customer ?? null,
                'receipt_url'        => $receiptUrl,
            ]);

            // Audit log
            AuditLog::log('subscription', $subscriptionId, 'created',
                null,
                ['status' => 'active', 'chatbot_id' => $chatbotId, 'promotion_id' => $promotionId],
                $userId
            );
            AuditLog::log('payment', $paymentId, 'created',
                null,
                ['status' => 'succeeded', 'amount' => $amount, 'stripe_payment_intent_id' => $pi->id],
                $userId
            );

            // ── Bundle: vytvor subscription 2 + payment 2 ────────────────────
            if ($promotionId2) {
                $subscriptionId2 = generateWebhookUuid();
                $db->prepare("
                    INSERT INTO subscriptions (
                        id, user_id, chatbot_id, subscription_type,
                        promotions_id, status, start_date, end_date, auto_renew,
                        stripe_customer_id, created_at, updated_at
                    ) VALUES (
                        :id, :user_id, :chatbot_id, 'promotion',
                        :promotions_id, 'active', :start_date, :end_date, 1,
                        :stripe_customer_id, NOW(), NOW()
                    )
                ")->execute([
                    'id'                 => $subscriptionId2,
                    'user_id'            => $userId,
                    'chatbot_id'         => $chatbotId,
                    'promotions_id'      => $promotionId2,
                    'start_date'         => $startDate,
                    'end_date'           => $endDate,
                    'stripe_customer_id' => $pi->customer ?? null,
                ]);

                $paymentId2 = generateWebhookUuid();
                $db->prepare("
                    INSERT INTO payments (
                        id, user_id, subscription_id, chatbot_id,
                        amount, currency_id, payment_method, status,
                        stripe_payment_intent_id, stripe_charge_id, stripe_customer_id,
                        receipt_url, succeeded_at, created_at, updated_at
                    ) VALUES (
                        :id, :user_id, :subscription_id, :chatbot_id,
                        :amount, :currency_id, 'stripe', 'succeeded',
                        :pi_id, :charge_id, :stripe_customer_id,
                        :receipt_url, NOW(), NOW(), NOW()
                    )
                ")->execute([
                    'id'                 => $paymentId2,
                    'user_id'            => $userId,
                    'subscription_id'    => $subscriptionId2,
                    'chatbot_id'         => $chatbotId,
                    'amount'             => $amount2,
                    'currency_id'        => $currencyId,
                    'pi_id'              => $pi->id,
                    'charge_id'          => $chargeId,
                    'stripe_customer_id' => $pi->customer ?? null,
                    'receipt_url'        => $receiptUrl,
                ]);

                AuditLog::log('subscription', $subscriptionId2, 'created',
                    null,
                    ['status' => 'active', 'chatbot_id' => $chatbotId, 'promotion_id' => $promotionId2],
                    $userId
                );
                AuditLog::log('payment', $paymentId2, 'created',
                    null,
                    ['status' => 'succeeded', 'amount' => $amount2, 'stripe_payment_intent_id' => $pi->id],
                    $userId
                );
            }

            $db->commit();
            error_log("Stripe: succeeded — subscription {$subscriptionId} created as active for user {$userId}");

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Stripe webhook DB error (succeeded): " . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;

    // ── Payment failed ────────────────────────────────────────────────────────
    case 'payment_intent.payment_failed':
        $pi = $event->data->object;

        $userId      = $pi->metadata->user_id     ?? null;
        $chatbotId   = $pi->metadata->chatbot_id  ?? null;
        $promotionId = $pi->metadata->promotion_id ?? null;

        $failureCode    = $pi->last_payment_error->code    ?? 'unknown';
        $failureMessage = $pi->last_payment_error->message ?? 'Payment failed';

        error_log("Stripe: payment_failed — user={$userId}, chatbot={$chatbotId}, code={$failureCode}");

        // Subscription sa nevytvorila (správne správanie)
        // Zalogujeme len do audit_log pre prehľad
        if ($userId && $promotionId) {
            AuditLog::log('payment', $pi->id, 'updated',
                ['status' => 'pending'],
                ['status' => 'failed', 'failure_code' => $failureCode, 'failure_message' => $failureMessage],
                $userId
            );
        }
        break;

    // ── Payment canceled ──────────────────────────────────────────────────────
    case 'payment_intent.canceled':
        $pi = $event->data->object;

        $userId      = $pi->metadata->user_id     ?? null;
        $promotionId = $pi->metadata->promotion_id ?? null;

        $cancelReason = $pi->cancellation_reason ?? 'Payment canceled';

        error_log("Stripe: canceled — pi={$pi->id}, reason={$cancelReason}");

        if ($userId && $promotionId) {
            AuditLog::log('payment', $pi->id, 'updated',
                ['status' => 'pending'],
                ['status' => 'cancelled', 'cancellation_reason' => $cancelReason],
                $userId
            );
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);