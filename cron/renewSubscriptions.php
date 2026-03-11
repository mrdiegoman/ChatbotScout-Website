<?php
/**
 * CRON: Renew Subscriptions (Auto-Renewal)
 *
 * Pre každú aktívnu subscription kde:
 *  - auto_renew = TRUE
 *  - end_date < NOW() + 1 day (tzn. expiruje do 24 hodín)
 *  - cancel_at_period_end = FALSE
 *
 * Vytvorí nový PaymentIntent (off-session) cez uložený Stripe customer.
 * Po úspešnej platbe webhook (payment_intent.succeeded) predĺži end_date.
 *
 * Spúšťaj raz denne:
 *   0 6 * * * php /path/to/cron/renewSubscriptions.php
 *
 * Na Windows (manuálne testovanie):
 *   php cron/renewSubscriptions.php
 */

define('BOOTSTRAP_LOADED', true);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

$db = Database::getInstance()->getConnection();

echo "[" . date('Y-m-d H:i:s') . "] renewSubscriptions started\n";

// ── Helper: generate UUID v4 ────────────────────────────────────────────────
function generateRenewUUID(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

try {
    // Find subscriptions expiring within next 24 hours with auto_renew = TRUE
    $stmt = $db->prepare("
        SELECT
            s.id              AS subscription_id,
            s.user_id,
            s.chatbot_id,
            s.stripe_customer_id,
            s.end_date,
            s.promotions_id,
            p.price,
            p.slot_type,
            p.discount_percentage,
            bp.type           AS billing_period_type,
            bp.duration_days,
            c.code            AS currency_code,
            u.email           AS user_email,
            u.stripe_customer_id AS user_stripe_customer_id
        FROM subscriptions s
        JOIN promotions      p   ON p.id  = s.promotions_id
        JOIN billing_periods bp  ON bp.id = p.billing_period_id
        JOIN currencies      c   ON c.id  = p.currency_id
        JOIN users           u   ON u.id  = s.user_id
        WHERE s.status              = 'active'
          AND s.auto_renew          = TRUE
          AND s.cancel_at_period_end = FALSE
          AND s.end_date            < DATE_ADD(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $renewals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($renewals)) {
        echo "No subscriptions due for renewal.\n";
        exit(0);
    }

    foreach ($renewals as $sub) {
        $subscriptionId   = $sub['subscription_id'];
        $stripeCustomerId = $sub['stripe_customer_id'] ?? $sub['user_stripe_customer_id'];

        echo "  Processing renewal for subscription {$subscriptionId}...\n";

        if (!$stripeCustomerId) {
            echo "  SKIP: No Stripe customer ID for subscription {$subscriptionId}\n";
            error_log("renewSubscriptions: No stripe_customer_id for subscription {$subscriptionId}");
            continue;
        }

        try {
            // Calculate renewal amount
            $finalPrice  = floatval($sub['price']) * (1 - (floatval($sub['discount_percentage']) / 100));
            $amountCents = (int) round($finalPrice * 100);
            $currency    = strtolower($sub['currency_code']);

            // Calculate new end_date based on billing period
            $newEndDate = match($sub['billing_period_type']) {
                'daily'   => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 day')),
                'weekly'  => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 week')),
                'monthly' => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 month')),
                'yearly'  => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 year')),
                default   => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 month')),
            };

            // Create new payment record
            $newPaymentId = generateRenewUUID();
            $db->prepare("
                INSERT INTO payments (
                    id, user_id, subscription_id, chatbot_id,
                    amount, currency_id, payment_method, status
                )
                SELECT
                    :new_payment_id,
                    :user_id,
                    :subscription_id,
                    :chatbot_id,
                    :amount,
                    p.currency_id,
                    'stripe',
                    'pending'
                FROM promotions p
                WHERE p.id = :promotions_id
            ")->execute([
                'new_payment_id'  => $newPaymentId,
                'user_id'         => $sub['user_id'],
                'subscription_id' => $subscriptionId,
                'chatbot_id'      => $sub['chatbot_id'],
                'amount'          => $finalPrice,
                'promotions_id'   => $sub['promotions_id'],
            ]);

            // Create off-session PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount'               => $amountCents,
                'currency'             => $currency,
                'customer'             => $stripeCustomerId,
                'confirm'              => true,
                'off_session'          => true,
                'automatic_payment_methods' => [
                    'enabled'          => true,
                    'allow_redirects'  => 'never',
                ],
                'metadata' => [
                    'subscription_id' => $subscriptionId,
                    'payment_id'      => $newPaymentId,
                    'user_id'         => $sub['user_id'],
                    'renewal'         => 'true',
                    'new_end_date'    => $newEndDate,
                ],
            ]);

            // Save PaymentIntent ID to new payment record
            $db->prepare("
                UPDATE payments
                SET stripe_payment_intent_id = :pi_id,
                    stripe_customer_id       = :cid
                WHERE id = :payment_id
            ")->execute([
                'pi_id'      => $paymentIntent->id,
                'cid'        => $stripeCustomerId,
                'payment_id' => $newPaymentId,
            ]);

            // If payment already succeeded (instant confirmation)
            if ($paymentIntent->status === 'succeeded') {
                $db->prepare("
                    UPDATE subscriptions
                    SET end_date   = :new_end_date,
                        updated_at = NOW()
                    WHERE id = :id
                ")->execute([
                    'new_end_date' => $newEndDate,
                    'id'           => $subscriptionId,
                ]);

                $db->prepare("
                    UPDATE payments
                    SET status     = 'completed',
                        paid_at    = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ")->execute(['id' => $newPaymentId]);

                echo "  SUCCESS: Subscription {$subscriptionId} renewed until {$newEndDate}\n";
            } else {
                echo "  PENDING: PaymentIntent {$paymentIntent->id} created, awaiting webhook\n";
            }

        } catch (\Stripe\Exception\CardException $e) {
            // Card was declined
            $db->prepare("
                UPDATE subscriptions
                SET status     = 'past_due',
                    updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => $subscriptionId]);

            $db->prepare("
                UPDATE payments SET status = 'failed', updated_at = NOW() WHERE id = :id
            ")->execute(['id' => $newPaymentId ?? null]);

            error_log("renewSubscriptions: Card declined for subscription {$subscriptionId}: " . $e->getMessage());
            echo "  FAILED: Card declined for subscription {$subscriptionId}\n";

        } catch (Exception $e) {
            error_log("renewSubscriptions: Error for subscription {$subscriptionId}: " . $e->getMessage());
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "Done.\n";

} catch (Exception $e) {
    error_log("renewSubscriptions cron fatal error: " . $e->getMessage());
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}