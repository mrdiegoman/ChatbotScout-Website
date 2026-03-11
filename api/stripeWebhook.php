<?php
/**
 * Stripe Webhook Handler
 *
 * Handles:
 *  - payment_intent.succeeded
 *  - payment_intent.payment_failed
 *  - payment_intent.canceled
 *
 * Nastav webhook URL v Stripe dashboarde:
 * https://dashboard.stripe.com/webhooks
 * URL: https://yourdomain.com/api/stripeWebhook.php
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

// ── Helper: resolve subscription_id + payment_id from metadata or DB ─────────
function resolveIds(PDO $db, object $pi): array {
    $subscriptionId = $pi->metadata->subscription_id ?? null;
    $paymentId      = $pi->metadata->payment_id      ?? null;

    if (!$subscriptionId || !$paymentId) {
        $stmt = $db->prepare("
            SELECT id AS payment_id, subscription_id
            FROM payments
            WHERE stripe_payment_intent_id = :pi_id
              AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute(['pi_id' => $pi->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $subscriptionId = $row['subscription_id'];
            $paymentId      = $row['payment_id'];
        }
    }

    return [$subscriptionId, $paymentId];
}

// ── Handle events ─────────────────────────────────────────────────────────────
switch ($event->type) {

    // ── Payment succeeded ─────────────────────────────────────────────────────
    case 'payment_intent.succeeded':
        $pi = $event->data->object;
        [$subscriptionId, $paymentId] = resolveIds($db, $pi);

        if (!$subscriptionId || !$paymentId) {
            error_log("Stripe webhook: payment_intent.succeeded missing IDs, pi={$pi->id}");
            break;
        }

        // Get charge details (receipt_url, invoice_url) from latest charge
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

            $db->prepare("
                UPDATE subscriptions
                SET status                 = 'active',
                    stripe_subscription_id = :pi_id,
                    updated_at             = NOW()
                WHERE id     = :id
                  AND status IN ('pending', 'past_due')
            ")->execute(['pi_id' => $pi->id, 'id' => $subscriptionId]);

            $db->prepare("
                UPDATE payments
                SET status                   = 'succeeded',
                    succeeded_at             = NOW(),
                    failed_at                = NULL,
                    failure_code             = NULL,
                    failure_message          = NULL,
                    stripe_payment_intent_id = :pi_id,
                    stripe_charge_id         = :charge_id,
                    receipt_url              = :receipt_url,
                    updated_at               = NOW()
                WHERE id = :id
            ")->execute([
                'pi_id'       => $pi->id,
                'charge_id'   => $chargeId,
                'receipt_url' => $receiptUrl,
                'id'          => $paymentId,
            ]);

            $db->commit();
            error_log("Stripe: succeeded — subscription {$subscriptionId} activated");

            AuditLog::log('payment', $paymentId, 'updated',
                ['status' => 'pending'],
                ['status' => 'succeeded', 'stripe_payment_intent_id' => $pi->id],
                $pi->metadata->user_id ?? null
            );
            AuditLog::log('subscription', $subscriptionId, 'updated',
                ['status' => 'pending'],
                ['status' => 'active'],
                $pi->metadata->user_id ?? null
            );

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
        [$subscriptionId, $paymentId] = resolveIds($db, $pi);

        if (!$subscriptionId || !$paymentId) {
            error_log("Stripe webhook: payment_intent.payment_failed missing IDs, pi={$pi->id}");
            break;
        }

        $failureCode    = $pi->last_payment_error->code    ?? 'unknown';
        $failureMessage = $pi->last_payment_error->message ?? 'Payment failed';

        try {
            $db->prepare("
                UPDATE subscriptions
                SET status     = 'past_due',
                    updated_at = NOW()
                WHERE id     = :id
                  AND status IN ('pending', 'active')
            ")->execute(['id' => $subscriptionId]);

            $db->prepare("
                UPDATE payments
                SET status                   = 'failed',
                    failed_at                = NOW(),
                    failure_code             = :failure_code,
                    failure_message          = :failure_message,
                    stripe_payment_intent_id = :pi_id,
                    updated_at               = NOW()
                WHERE id = :id
            ")->execute([
                'failure_code'    => $failureCode,
                'failure_message' => $failureMessage,
                'pi_id'           => $pi->id,
                'id'              => $paymentId,
            ]);

            error_log("Stripe: payment_failed — subscription {$subscriptionId} set to past_due");

            AuditLog::log('payment', $paymentId, 'updated',
                ['status' => 'pending'],
                ['status' => 'failed', 'failure_code' => $failureCode, 'failure_message' => $failureMessage],
                $pi->metadata->user_id ?? null
            );
            AuditLog::log('subscription', $subscriptionId, 'updated',
                ['status' => 'active'],
                ['status' => 'past_due'],
                $pi->metadata->user_id ?? null
            );

        } catch (Exception $e) {
            error_log("Stripe webhook DB error (failed): " . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;

    // ── Payment canceled ──────────────────────────────────────────────────────
    case 'payment_intent.canceled':
        $pi = $event->data->object;
        [$subscriptionId, $paymentId] = resolveIds($db, $pi);

        if (!$subscriptionId || !$paymentId) {
            error_log("Stripe webhook: payment_intent.canceled missing IDs, pi={$pi->id}");
            break;
        }

        $cancelReason = $pi->cancellation_reason ?? 'Payment canceled';

        try {
            $db->prepare("
                UPDATE subscriptions
                SET status              = 'cancelled',
                    cancelled_at        = NOW(),
                    cancellation_reason = :reason,
                    updated_at          = NOW()
                WHERE id     = :id
                  AND status = 'pending'
            ")->execute(['reason' => $cancelReason, 'id' => $subscriptionId]);

            $db->prepare("
                UPDATE payments
                SET status                   = 'cancelled',
                    failed_at                = NOW(),
                    failure_code             = 'payment_canceled',
                    failure_message          = :reason,
                    stripe_payment_intent_id = :pi_id,
                    updated_at               = NOW()
                WHERE id = :id
            ")->execute([
                'reason' => $cancelReason,
                'pi_id'  => $pi->id,
                'id'     => $paymentId,
            ]);

            error_log("Stripe: canceled — subscription {$subscriptionId} cancelled");

            AuditLog::log('payment', $paymentId, 'updated',
                ['status' => 'pending'],
                ['status' => 'cancelled', 'cancellation_reason' => $cancelReason],
                $pi->metadata->user_id ?? null
            );
            AuditLog::log('subscription', $subscriptionId, 'updated',
                ['status' => 'pending'],
                ['status' => 'cancelled'],
                $pi->metadata->user_id ?? null
            );

        } catch (Exception $e) {
            error_log("Stripe webhook DB error (canceled): " . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);