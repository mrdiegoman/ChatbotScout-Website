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

try {
    // Find subscriptions expiring within next 24 hours with auto_renew = TRUE
    $stmt = $db->prepare("
        SELECT
            s.id              AS subscription_id,
            s.user_id,
            s.chatbot_id,
            s.stripe_customer_id,
            s.start_date,
            s.end_date,
            s.promotions_id,
            p.price,
            p.slot_type,
            p.discount_percentage,
            bp.type           AS billing_period_type,
            bp.duration_days,
            c.code            AS currency_code,
            u.email           AS user_email,
            u.stripe_customer_id AS user_stripe_customer_id,
            (
                SELECT pay.metadata
                FROM payments pay
                WHERE pay.subscription_id = s.id
                  AND pay.status = 'succeeded'
                ORDER BY COALESCE(pay.succeeded_at, pay.created_at) DESC
                LIMIT 1
            ) AS latest_payment_metadata
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
            // Calculate renewal amount from the actual subscription period, not only the base promotion row.
            $billingPeriodType = promotionBillingPeriodFromDates(
                $sub['start_date'] ?? null,
                $sub['end_date'] ?? null,
                $sub['billing_period_type'] ?? 'monthly'
            );
            $latestPaymentMetadata = json_decode((string) ($sub['latest_payment_metadata'] ?? ''), true);
            $isBundleRenewal = is_array($latestPaymentMetadata) && !empty($latestPaymentMetadata['is_bundle']);
            $finalPrice = $isBundleRenewal
                ? promotionBundleLineTotalPrice(floatval($sub['price']), $billingPeriodType)
                : promotionSingleTotalPrice(
                    floatval($sub['price']),
                    floatval($sub['discount_percentage']),
                    $billingPeriodType
                );
            $amountCents = (int) round($finalPrice * 100);
            $currency    = strtolower($sub['currency_code']);

            // Calculate new end_date based on billing period
            $newEndDate = match($billingPeriodType) {
                'daily'   => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 day')),
                'weekly'  => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 week')),
                'monthly' => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 month')),
                'yearly'  => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 year')),
                default   => date('Y-m-d H:i:s', strtotime($sub['end_date'] . ' +1 month')),
            };

            // Create new payment record
            $newPaymentId = generateUUID();
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
                    'period_type'     => $billingPeriodType,
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
                    SET status          = 'succeeded',
                        succeeded_at    = NOW(),
                        updated_at      = NOW()
                    WHERE id = :id
                ")->execute(['id' => $newPaymentId]);

                AuditLog::log('subscription', $subscriptionId, 'updated',
                    ['end_date' => $sub['end_date'], 'status' => 'active'],
                    ['end_date' => $newEndDate, 'status' => 'active', 'renewal' => true],
                    $sub['user_id']
                );
                AuditLog::log('payment', $newPaymentId, 'created',
                    null,
                    ['subscription_id' => $subscriptionId, 'status' => 'succeeded', 'renewal' => true],
                    $sub['user_id']
                );

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

            AuditLog::log('subscription', $subscriptionId, 'updated',
                ['status' => 'active'],
                ['status' => 'past_due', 'renewal_failed' => true, 'reason' => 'card_declined'],
                $sub['user_id']
            );

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
