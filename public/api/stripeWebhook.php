<?php
/**
 * Stripe Webhook Handler
 *
 * Flow:
 *  payment_intent.succeeded  → UPDATE payment (pending → succeeded) + INSERT subscription (active)
 *  payment_intent.failed     → UPDATE payment (pending → failed)
 *  payment_intent.canceled   → UPDATE payment (pending → cancelled)
 */

require '../../inc/bootstrap.php';
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

// ── Helper: UUID v4 ──────────────────────────────────────────────────────────
function generateWebhookUuid(): string {
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── Handle events ─────────────────────────────────────────────────────────────
switch ($event->type) {

    // ── Payment succeeded → update payment + vytvor subscription ─────────────
    case 'payment_intent.succeeded':
        $pi = $event->data->object;

        $userId       = $pi->metadata->user_id      ?? null;
        $chatbotId    = $pi->metadata->chatbot_id    ?? null;
        $promotionId  = $pi->metadata->promotion_id  ?? null;
        $promotionId2 = $pi->metadata->promotion_id2 ?? null;
        $paymentId    = $pi->metadata->payment_id    ?? null;
        $paymentId2   = $pi->metadata->payment_id2   ?? null;
        $amount       = $pi->metadata->amount        ?? null;
        $amount2      = $pi->metadata->amount2       ?? null;
        $periodType   = $pi->metadata->period_type   ?? 'monthly';
        $durationDays = $pi->metadata->duration_days ?? 30;
        $currencyId   = $pi->metadata->currency_id   ?? null;

        if (!$userId || !$chatbotId || !$promotionId || !$paymentId) {
            error_log("Stripe webhook: payment_intent.succeeded missing metadata, pi={$pi->id}");
            break;
        }

        // ── Získaj receipt URL z charge ──────────────────────────────────────
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

            // ── Update payment 1 (pending → succeeded) ───────────────────────
            $db->prepare("
                UPDATE payments
                SET status                   = 'succeeded',
                    subscription_id          = :subscription_id,
                    stripe_charge_id         = :charge_id,
                    receipt_url              = :receipt_url,
                    succeeded_at             = NOW(),
                    updated_at               = NOW()
                WHERE id = :id
            ")->execute([
                'subscription_id' => $subscriptionId,
                'charge_id'       => $chargeId,
                'receipt_url'     => $receiptUrl,
                'id'              => $paymentId,
            ]);

            AuditLog::log('subscription', $subscriptionId, 'created',
                null,
                ['status' => 'active', 'chatbot_id' => $chatbotId, 'promotion_id' => $promotionId],
                $userId
            );
            AuditLog::log('payment', $paymentId, 'updated',
                ['status' => 'pending'],
                ['status' => 'succeeded', 'stripe_charge_id' => $chargeId],
                $userId
            );

            // ── Bundle: vytvor subscription 2 + update payment 2 ─────────────
            if ($promotionId2 && $paymentId2) {
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

                $db->prepare("
                    UPDATE payments
                    SET status          = 'succeeded',
                        subscription_id = :subscription_id,
                        stripe_charge_id = :charge_id,
                        receipt_url     = :receipt_url,
                        succeeded_at    = NOW(),
                        updated_at      = NOW()
                    WHERE id = :id
                ")->execute([
                    'subscription_id' => $subscriptionId2,
                    'charge_id'       => $chargeId,
                    'receipt_url'     => $receiptUrl,
                    'id'              => $paymentId2,
                ]);

                AuditLog::log('subscription', $subscriptionId2, 'created',
                    null,
                    ['status' => 'active', 'chatbot_id' => $chatbotId, 'promotion_id' => $promotionId2],
                    $userId
                );
                AuditLog::log('payment', $paymentId2, 'updated',
                    ['status' => 'pending'],
                    ['status' => 'succeeded'],
                    $userId
                );
            }

            $db->commit();
            error_log("Stripe: succeeded — subscription {$subscriptionId} created (active), payment {$paymentId} updated (succeeded)");

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Stripe webhook DB error (succeeded): " . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;

    // ── Payment failed → update payment pending → failed ─────────────────────
    case 'payment_intent.payment_failed':
        $pi = $event->data->object;

        $paymentId   = $pi->metadata->payment_id  ?? null;
        $paymentId2  = $pi->metadata->payment_id2 ?? null;
        $userId      = $pi->metadata->user_id     ?? null;

        $failureCode    = $pi->last_payment_error->code    ?? 'unknown';
        $failureMessage = $pi->last_payment_error->message ?? 'Payment failed';

        // Fallback: nájdi podľa stripe_payment_intent_id
        if (!$paymentId) {
            $row = $db->prepare("SELECT id FROM payments WHERE stripe_payment_intent_id = :pi_id AND status = 'pending' LIMIT 1");
            $row->execute(['pi_id' => $pi->id]);
            $found = $row->fetch(PDO::FETCH_ASSOC);
            $paymentId = $found['id'] ?? null;
        }

        if ($paymentId) {
            $db->prepare("
                UPDATE payments
                SET status          = 'failed',
                    failed_at       = NOW(),
                    failure_code    = :code,
                    failure_message = :message,
                    updated_at      = NOW()
                WHERE id = :id
            ")->execute([
                'code'    => $failureCode,
                'message' => $failureMessage,
                'id'      => $paymentId,
            ]);

            AuditLog::log('payment', $paymentId, 'updated',
                ['status' => 'pending'],
                ['status' => 'failed', 'failure_code' => $failureCode],
                $userId
            );
        }

        if ($paymentId2) {
            $db->prepare("
                UPDATE payments
                SET status          = 'failed',
                    failed_at       = NOW(),
                    failure_code    = :code,
                    failure_message = :message,
                    updated_at      = NOW()
                WHERE id = :id
            ")->execute([
                'code'    => $failureCode,
                'message' => $failureMessage,
                'id'      => $paymentId2,
            ]);
        }

        error_log("Stripe: payment_failed — payment {$paymentId}, code={$failureCode}");
        break;

    // ── Payment canceled → update payment pending → cancelled ────────────────
    case 'payment_intent.canceled':
        $pi = $event->data->object;

        $paymentId  = $pi->metadata->payment_id  ?? null;
        $paymentId2 = $pi->metadata->payment_id2 ?? null;
        $userId     = $pi->metadata->user_id     ?? null;

        $cancelReason = $pi->cancellation_reason ?? 'canceled';

        // Fallback: nájdi podľa stripe_payment_intent_id
        if (!$paymentId) {
            $row = $db->prepare("SELECT id FROM payments WHERE stripe_payment_intent_id = :pi_id AND status = 'pending' LIMIT 1");
            $row->execute(['pi_id' => $pi->id]);
            $found = $row->fetch(PDO::FETCH_ASSOC);
            $paymentId = $found['id'] ?? null;
        }

        if ($paymentId) {
            $db->prepare("
                UPDATE payments
                SET status          = 'cancelled',
                    failed_at       = NOW(),
                    failure_code    = 'payment_canceled',
                    failure_message = :reason,
                    updated_at      = NOW()
                WHERE id = :id
            ")->execute(['reason' => $cancelReason, 'id' => $paymentId]);

            AuditLog::log('payment', $paymentId, 'updated',
                ['status' => 'pending'],
                ['status' => 'cancelled', 'cancellation_reason' => $cancelReason],
                $userId
            );
        }

        if ($paymentId2) {
            $db->prepare("
                UPDATE payments
                SET status          = 'cancelled',
                    failed_at       = NOW(),
                    failure_code    = 'payment_canceled',
                    failure_message = :reason,
                    updated_at      = NOW()
                WHERE id = :id
            ")->execute(['reason' => $cancelReason, 'id' => $paymentId2]);
        }

        error_log("Stripe: canceled — payment {$paymentId}, reason={$cancelReason}");
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);