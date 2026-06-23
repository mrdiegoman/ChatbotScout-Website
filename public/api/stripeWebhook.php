<?php
/**
 * Stripe Webhook Handler
 *
 * Stripe Billing is the billing source of truth:
 * - invoice.paid / invoice.payment_succeeded activates local promotion rows
 * - invoice.payment_failed marks related records as past due/failed
 * - customer.subscription.* syncs cancellation and renewal state
 */

require __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../inc/stripePaymentFinalizer.php';

$stripeConfig = require __DIR__ . '/../../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
if (!empty($stripeConfig['api_version'])) {
    \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
}

$payload = file_get_contents('php://input');
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

try {
    switch ($event->type) {
        case 'invoice.paid':
        case 'invoice.payment_succeeded':
            finalizeStripePaidInvoice($db, $event->data->object);
            break;

        case 'invoice.payment_failed':
            markStripeInvoicePaymentFailed($db, $event->data->object);
            break;

        case 'invoice.voided':
            markStripeInvoiceCancelled($db, $event->data->object);
            break;

        case 'invoice.marked_uncollectible':
            markStripeInvoicePaymentFailed($db, $event->data->object);
            break;

        case 'payment_intent.processing':
            markStripePaymentIntentProcessing($db, $event->data->object);
            break;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            syncStripeSubscriptionStatus($db, $event->data->object);
            break;
    }
} catch (Exception $e) {
    error_log("Stripe webhook error ({$event->type}): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['received' => false]);
    exit;
}

http_response_code(200);
echo json_encode(['received' => true]);
