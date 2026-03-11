<?php
/**
 * TEST SEED: Create test subscription + PaymentIntent for Stripe testing
 *
 * Spusti z koreňového adresára projektu:
 *   php tools/seedTestData.php
 *
 * !! LEN NA LOKÁLNE TESTOVANIE - NIKDY NA PRODUKCII !!
 */

define('BOOTSTRAP_LOADED', true);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

$db = Database::getInstance()->getConnection();

// ── Helper: UUID ─────────────────────────────────────────────────────────────
function seedUUID(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ── Helper: run stripe trigger ────────────────────────────────────────────────
// Stripe CLI override format: --override "fixtureName:field[key]=value"
// This sets the metadata on the PaymentIntent fixture Stripe creates internally.
// The webhook handler will receive these metadata values in the event.
function runTrigger(string $event, string $piId, string $subscriptionId, string $paymentId): void {
    $cmd = "stripe trigger {$event}"
         . " --override \"payment_intent:metadata[subscription_id]={$subscriptionId}\""
         . " --override \"payment_intent:metadata[payment_id]={$paymentId}\"";

    echo "\nRunning: {$cmd}\n";
    echo str_repeat("-", 50) . "\n";

    exec($cmd, $output, $exitCode);
    foreach ($output as $line) {
        echo $line . "\n";
    }

    if ($exitCode === 0) {
        echo "\n✅ Trigger sent!\n";
    } else {
        echo "\n❌ Trigger failed (exit code: {$exitCode})\n";
    }
}

// ── Helper: check DB result ───────────────────────────────────────────────────
function checkDB(PDO $db, string $subscriptionId, string $paymentId): void {
    echo "\n--- DB Result ---\n";

    $sub = $db->prepare("SELECT status, cancelled_at FROM subscriptions WHERE id = :id");
    $sub->execute(['id' => $subscriptionId]);
    $subRow = $sub->fetch(PDO::FETCH_ASSOC);
    echo "subscriptions.status       = " . ($subRow['status']       ?? 'NOT FOUND') . "\n";
    echo "subscriptions.cancelled_at = " . ($subRow['cancelled_at'] ?? 'NULL') . "\n";

    $pay = $db->prepare("
        SELECT status, succeeded_at, failed_at, failure_code, failure_message, receipt_url, stripe_charge_id
        FROM payments WHERE id = :id
    ");
    $pay->execute(['id' => $paymentId]);
    $payRow = $pay->fetch(PDO::FETCH_ASSOC);
    echo "payments.status            = " . ($payRow['status']          ?? 'NOT FOUND') . "\n";
    echo "payments.succeeded_at      = " . ($payRow['succeeded_at']    ?? 'NULL') . "\n";
    echo "payments.failed_at         = " . ($payRow['failed_at']       ?? 'NULL') . "\n";
    echo "payments.failure_code      = " . ($payRow['failure_code']    ?? 'NULL') . "\n";
    echo "payments.failure_message   = " . ($payRow['failure_message'] ?? 'NULL') . "\n";
    echo "payments.receipt_url       = " . ($payRow['receipt_url']     ?? 'NULL') . "\n";
    echo "payments.stripe_charge_id  = " . ($payRow['stripe_charge_id'] ?? 'NULL') . "\n";
    echo str_repeat("-", 50) . "\n";
}

// ── Helper: reset DB to pending ───────────────────────────────────────────────
function resetDB(PDO $db, string $subscriptionId, string $paymentId): void {
    $db->prepare("
        UPDATE subscriptions
        SET status = 'pending', cancelled_at = NULL
        WHERE id = :id
    ")->execute(['id' => $subscriptionId]);

    $db->prepare("
        UPDATE payments
        SET status = 'pending', succeeded_at = NULL, failed_at = NULL, failure_code = NULL
        WHERE id = :id
    ")->execute(['id' => $paymentId]);

    echo "DB reset to 'pending'.\n";
}

// ─────────────────────────────────────────────────────────────────────────────

echo "\n=================================================\n";
echo "  Stripe Test Seed\n";
echo "=================================================\n\n";

// ── Load test user ────────────────────────────────────────────────────────────
$user = $db->query("SELECT id, email, first_name, last_name, stripe_customer_id FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) die("ERROR: No users found in DB.\n");
echo "User:    {$user['email']} ({$user['id']})\n";

// ── Load test chatbot ─────────────────────────────────────────────────────────
$chatbot = $db->query("SELECT id, name FROM chatbots LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$chatbot) die("ERROR: No chatbots found in DB.\n");
echo "Chatbot: {$chatbot['name']} ({$chatbot['id']})\n";

// ── Load a promotion ──────────────────────────────────────────────────────────
$promotion = $db->query("
    SELECT p.id, p.price, p.discount_percentage, p.slot_type, p.currency_id,
           bp.type AS billing_period_type, bp.duration_days,
           c.code  AS currency_code
    FROM promotions p
    JOIN billing_periods bp ON bp.id = p.billing_period_id
    JOIN currencies      c  ON c.id  = p.currency_id
    WHERE p.is_active = 1
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
if (!$promotion) die("ERROR: No active promotions found in DB.\n");
echo "Promo:   {$promotion['slot_type']} — {$promotion['price']} {$promotion['currency_code']}\n\n";

// ── Get or create Stripe Customer ─────────────────────────────────────────────
$stripeCustomerId = $user['stripe_customer_id'];
if (!$stripeCustomerId) {
    $customer = \Stripe\Customer::create([
        'email'    => $user['email'],
        'name'     => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'metadata' => ['user_id' => $user['id']],
    ]);
    $stripeCustomerId = $customer->id;
    $db->prepare("UPDATE users SET stripe_customer_id = :cid WHERE id = :uid")
       ->execute(['cid' => $stripeCustomerId, 'uid' => $user['id']]);
    echo "Created Stripe Customer: {$stripeCustomerId}\n";
} else {
    echo "Stripe Customer: {$stripeCustomerId}\n";
}

// ── Create subscription in DB ─────────────────────────────────────────────────
$subscriptionId = seedUUID();
$startDate      = date('Y-m-d H:i:s');
$endDate        = match($promotion['billing_period_type']) {
    'daily'   => date('Y-m-d H:i:s', strtotime("+{$promotion['duration_days']} days")),
    'weekly'  => date('Y-m-d H:i:s', strtotime("+{$promotion['duration_days']} days")),
    'monthly' => date('Y-m-d H:i:s', strtotime('+1 month')),
    'yearly'  => date('Y-m-d H:i:s', strtotime('+1 year')),
    default   => date('Y-m-d H:i:s', strtotime('+1 month')),
};

$db->prepare("
    INSERT INTO subscriptions (
        id, user_id, chatbot_id, subscription_type,
        promotions_id, status, start_date, end_date,
        stripe_customer_id, auto_renew
    ) VALUES (
        :id, :user_id, :chatbot_id, 'promotion',
        :promotions_id, 'pending', :start_date, :end_date,
        :stripe_customer_id, 1
    )
")->execute([
    'id'                 => $subscriptionId,
    'user_id'            => $user['id'],
    'chatbot_id'         => $chatbot['id'],
    'promotions_id'      => $promotion['id'],
    'start_date'         => $startDate,
    'end_date'           => $endDate,
    'stripe_customer_id' => $stripeCustomerId,
]);

// ── Create payment in DB ──────────────────────────────────────────────────────
$paymentId  = seedUUID();
$finalPrice = floatval($promotion['price']) * (1 - (floatval($promotion['discount_percentage']) / 100));

$db->prepare("
    INSERT INTO payments (
        id, user_id, subscription_id, chatbot_id,
        amount, currency_id, payment_method, status, stripe_customer_id
    ) VALUES (
        :id, :user_id, :subscription_id, :chatbot_id,
        :amount, :currency_id, 'stripe', 'pending', :stripe_customer_id
    )
")->execute([
    'id'                 => $paymentId,
    'user_id'            => $user['id'],
    'subscription_id'    => $subscriptionId,
    'chatbot_id'         => $chatbot['id'],
    'amount'             => $finalPrice,
    'currency_id'        => $promotion['currency_id'],
    'stripe_customer_id' => $stripeCustomerId,
]);

// ── Create Stripe PaymentIntent ───────────────────────────────────────────────
$amountCents   = (int) round($finalPrice * 100);
$currency      = strtolower($promotion['currency_code']);
$paymentIntent = \Stripe\PaymentIntent::create([
    'amount'                    => $amountCents,
    'currency'                  => $currency,
    'customer'                  => $stripeCustomerId,
    'setup_future_usage'        => 'off_session',
    'automatic_payment_methods' => ['enabled' => true],
    'metadata' => [
        'subscription_id' => $subscriptionId,
        'payment_id'      => $paymentId,
        'user_id'         => $user['id'],
        'slot_type'       => $promotion['slot_type'],
        'billing_period'  => $promotion['billing_period_type'],
        'test_seed'       => 'true',
    ],
]);

$db->prepare("UPDATE payments SET stripe_payment_intent_id = :pi_id WHERE id = :pid")
   ->execute(['pi_id' => $paymentIntent->id, 'pid' => $paymentId]);

echo "\nCreated subscription:  {$subscriptionId}\n";
echo "Created payment:       {$paymentId}\n";
echo "Created PaymentIntent: {$paymentIntent->id}\n";

// ── Interactive menu ──────────────────────────────────────────────────────────
while (true) {
    echo "\n=================================================\n";
    echo "  Co chces testovat?\n";
    echo "=================================================\n";
    echo "  1) Successful payment  (payment_intent.succeeded)\n";
    echo "  2) Failed payment      (payment_intent.payment_failed)\n";
    echo "  3) Canceled payment    (payment_intent.canceled)\n";
    echo "  4) Vsetky 3 za sebou\n";
    echo "  5) Cron - cancelAtPeriodEnd\n";
    echo "  6) Cron - expireSubscriptions\n";
    echo "  7) Cron - renewSubscriptions\n";
    echo "  r) Reset DB na 'pending'\n";
    echo "  0) Koniec\n";
    echo "=================================================\n";
    echo "Vyber (0-7, r): ";

    $choice = trim(fgets(STDIN));

    switch ($choice) {
        case '1':
            runTrigger('payment_intent.succeeded', $paymentIntent->id, $subscriptionId, $paymentId);
            sleep(1);
            checkDB($db, $subscriptionId, $paymentId);
            break;

        case '2':
            runTrigger('payment_intent.payment_failed', $paymentIntent->id, $subscriptionId, $paymentId);
            sleep(1);
            checkDB($db, $subscriptionId, $paymentId);
            break;

        case '3':
            runTrigger('payment_intent.canceled', $paymentIntent->id, $subscriptionId, $paymentId);
            sleep(1);
            checkDB($db, $subscriptionId, $paymentId);
            break;

        case '4':
            echo "\n>>> Test 1/3: Successful payment\n";
            runTrigger('payment_intent.succeeded', $paymentIntent->id, $subscriptionId, $paymentId);
            sleep(1);
            checkDB($db, $subscriptionId, $paymentId);

            echo "\nResetting DB...\n";
            resetDB($db, $subscriptionId, $paymentId);

            echo "\n>>> Test 2/3: Failed payment\n";
            runTrigger('payment_intent.payment_failed', $paymentIntent->id, $subscriptionId, $paymentId);
            sleep(1);
            checkDB($db, $subscriptionId, $paymentId);

            echo "\nResetting DB...\n";
            resetDB($db, $subscriptionId, $paymentId);

            echo "\n>>> Test 3/3: Canceled payment\n";
            runTrigger('payment_intent.canceled', $paymentIntent->id, $subscriptionId, $paymentId);
            sleep(1);
            checkDB($db, $subscriptionId, $paymentId);
            break;

        case '5':
            echo "\nRunning cron: cancelAtPeriodEnd\n";
            exec('php ' . __DIR__ . '/../cron/cancelAtPeriodEnd.php', $out);
            echo implode("\n", $out) . "\n";
            $out = [];
            break;

        case '6':
            echo "\nRunning cron: expireSubscriptions\n";
            exec('php ' . __DIR__ . '/../cron/expireSubscriptions.php', $out);
            echo implode("\n", $out) . "\n";
            $out = [];
            break;

        case '7':
            echo "\nRunning cron: renewSubscriptions\n";
            exec('php ' . __DIR__ . '/../cron/renewSubscriptions.php', $out);
            echo implode("\n", $out) . "\n";
            $out = [];
            break;

        case 'r':
            resetDB($db, $subscriptionId, $paymentId);
            break;

        case '0':
            echo "\n=================================================\n";
            echo " IDs pre kontrolu v DB\n";
            echo "=================================================\n";
            echo " subscription_id: {$subscriptionId}\n";
            echo " payment_id:      {$paymentId}\n";
            echo " stripe_pi_id:    {$paymentIntent->id}\n";
            echo "=================================================\n";
            exit(0);

        default:
            echo "Neplatna volba, skus znova.\n";
    }
}