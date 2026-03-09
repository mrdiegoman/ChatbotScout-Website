<?php
/**
 * Stripe Webhook Handler
 * Handles: payment_intent.succeeded, payment_intent.payment_failed
 *
 * Nastav webhook URL v Stripe dashboarde:
 * https://dashboard.stripe.com/webhooks
 * URL: https://yourdomain.com/api/stripeWebhook.php
 * Events: payment_intent.succeeded, payment_intent.payment_failed
 */

require '../inc/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

// Read raw payload
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
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

// ── Handle events ─────────────────────────────────────────────────────────────
switch ($event->type) {

    // ── Payment succeeded ─────────────────────────────────────────────────────
    case 'payment_intent.succeeded':
        $pi            = $event->data->object;
        $subscriptionId = $pi->metadata->subscription_id ?? null;
        $paymentId      = $pi->metadata->payment_id      ?? null;

        if (!$subscriptionId || !$paymentId) break;

        try {
            $db->beginTransaction();

            // Activate subscription
            $db->prepare("
                UPDATE subscriptions
                SET status            = 'active',
                    stripe_subscription_id = :pi_id,
                    updated_at        = NOW()
                WHERE id = :id AND status = 'pending'
            ")->execute([
                'pi_id' => $pi->id,
                'id'    => $subscriptionId
            ]);

            // Update payment to completed
            $db->prepare("
                UPDATE payments
                SET status     = 'completed',
                    paid_at    = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => $paymentId]);

            $db->commit();

            error_log("Stripe: Payment succeeded for subscription {$subscriptionId}");

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Stripe webhook DB error: " . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;

    // ── Payment failed ────────────────────────────────────────────────────────
    case 'payment_intent.payment_failed':
        $pi            = $event->data->object;
        $subscriptionId = $pi->metadata->subscription_id ?? null;
        $paymentId      = $pi->metadata->payment_id      ?? null;

        if (!$subscriptionId || !$paymentId) break;

        try {
            $db->prepare("
                UPDATE subscriptions
                SET status = 'past_due', updated_at = NOW()
                WHERE id = :id AND status = 'pending'
            ")->execute(['id' => $subscriptionId]);

            $db->prepare("
                UPDATE payments
                SET status = 'failed', updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => $paymentId]);

            error_log("Stripe: Payment failed for subscription {$subscriptionId}");

        } catch (Exception $e) {
            error_log("Stripe webhook DB error (failed): " . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
