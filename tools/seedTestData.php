<?php
/**
 * TEST SEED: Create payment (pending) + PaymentIntent pre Stripe testovanie
 *
 * Spusti z koreňového adresára projektu:
 *   php tools/seedTestData.php
 *
 * Flow:
 *  - Seed vytvorí payment (pending) v DB + PaymentIntent na Stripe
 *  - stripe trigger pošle webhook s metadata (payment_id, promotion_id atď.)
 *  - Webhook: UPDATE payment → succeeded + INSERT subscription → active
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
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── Helper: run stripe trigger ────────────────────────────────────────────────
// stripe trigger vytvára NOVÝ PaymentIntent – id sa nedá overridovať.
// Preto odovzdávame všetky potrebné hodnoty cez metadata override.
function runTrigger(string $event, array $metadata): void {
    $overrides = '';
    foreach ($metadata as $key => $value) {
        $overrides .= " --override \"payment_intent:metadata[{$key}]={$value}\"";
    }

    $cmd = "stripe trigger {$event}{$overrides}";

    echo "\nRunning: stripe trigger {$event} (s metadata overrides)\n";
    echo str_repeat("-", 50) . "\n";

    exec($cmd, $output, $exitCode);
    foreach ($output as $line) {
        echo $line . "\n";
    }

    echo $exitCode === 0 ? "\n✅ Trigger sent!\n" : "\n❌ Trigger failed (exit code: {$exitCode})\n";
}

// ── Helper: check DB result ───────────────────────────────────────────────────
function checkDB(PDO $db, string $paymentId): void {
    echo "\n--- DB Result ---\n";

    $pay = $db->prepare("
        SELECT status, succeeded_at, failed_at, failure_code,
               failure_message, receipt_url, stripe_charge_id, subscription_id
        FROM payments WHERE id = :id
    ");
    $pay->execute(['id' => $paymentId]);
    $payRow = $pay->fetch(PDO::FETCH_ASSOC);

    if (!$payRow) {
        echo "payments: NOT FOUND\n";
        echo str_repeat("-", 50) . "\n";
        return;
    }

    echo "payments.status            = " . $payRow['status'] . "\n";
    echo "payments.succeeded_at      = " . ($payRow['succeeded_at']     ?? 'NULL') . "\n";
    echo "payments.failed_at         = " . ($payRow['failed_at']        ?? 'NULL') . "\n";
    echo "payments.failure_code      = " . ($payRow['failure_code']     ?? 'NULL') . "\n";
    echo "payments.failure_message   = " . ($payRow['failure_message']  ?? 'NULL') . "\n";
    echo "payments.receipt_url       = " . ($payRow['receipt_url']      ?? 'NULL') . "\n";
    echo "payments.stripe_charge_id  = " . ($payRow['stripe_charge_id'] ?? 'NULL') . "\n";

    if ($payRow['subscription_id']) {
        $sub = $db->prepare("SELECT status, cancelled_at FROM subscriptions WHERE id = :id");
        $sub->execute(['id' => $payRow['subscription_id']]);
        $subRow = $sub->fetch(PDO::FETCH_ASSOC);
        echo "subscriptions.status       = " . ($subRow['status']       ?? 'NOT FOUND') . "\n";
        echo "subscriptions.cancelled_at = " . ($subRow['cancelled_at'] ?? 'NULL') . "\n";
    } else {
        echo "subscriptions: NOT CREATED YET\n";
    }

    echo str_repeat("-", 50) . "\n";
}

// ── Helper: reset DB ──────────────────────────────────────────────────────────
function resetDB(PDO $db, string $paymentId): void {
    $pay = $db->prepare("SELECT subscription_id FROM payments WHERE id = :id");
    $pay->execute(['id' => $paymentId]);
    $payRow = $pay->fetch(PDO::FETCH_ASSOC);

    if (!empty($payRow['subscription_id'])) {
        $db->prepare("DELETE FROM subscriptions WHERE id = :id")
           ->execute(['id' => $payRow['subscription_id']]);
    }

    $db->prepare("
        UPDATE payments
        SET status           = 'pending',
            subscription_id  = NULL,
            succeeded_at     = NULL,
            failed_at        = NULL,
            failure_code     = NULL,
            failure_message  = NULL,
            receipt_url      = NULL,
            stripe_charge_id = NULL,
            updated_at       = NOW()
        WHERE id = :id
    ")->execute(['id' => $paymentId]);

    echo "✅ DB reset – payment → pending, subscription vymazaná.\n";
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
$chatbot = $db->query("SELECT id, name FROM chatbots WHERE deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
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

// ── Vytvor payment (pending) v DB ─────────────────────────────────────────────
$paymentId  = seedUUID();
$finalPrice = floatval($promotion['price']) * (1 - floatval($promotion['discount_percentage']) / 100);

$db->prepare("
    INSERT INTO payments (
        id, user_id, chatbot_id,
        amount, currency_id, payment_method, status,
        stripe_customer_id, created_at, updated_at
    ) VALUES (
        :id, :user_id, :chatbot_id,
        :amount, :currency_id, 'stripe', 'pending',
        :stripe_customer_id, NOW(), NOW()
    )
")->execute([
    'id'                 => $paymentId,
    'user_id'            => $user['id'],
    'chatbot_id'         => $chatbot['id'],
    'amount'             => $finalPrice,
    'currency_id'        => $promotion['currency_id'],
    'stripe_customer_id' => $stripeCustomerId,
]);

// ── Vytvor Stripe PaymentIntent ───────────────────────────────────────────────
$amountCents = (int) round($finalPrice * 100);
$currency    = strtolower($promotion['currency_code']);

$paymentIntent = \Stripe\PaymentIntent::create([
    'amount'                    => $amountCents,
    'currency'                  => $currency,
    'customer'                  => $stripeCustomerId,
    'setup_future_usage'        => 'off_session',
    'automatic_payment_methods' => ['enabled' => true],
    'metadata'                  => [
        'user_id'       => $user['id'],
        'chatbot_id'    => $chatbot['id'],
        'promotion_id'  => $promotion['id'],
        'promotion_id2' => '',
        'payment_id'    => $paymentId,
        'payment_id2'   => '',
        'amount'        => $finalPrice,
        'amount2'       => 0,
        'period_type'   => $promotion['billing_period_type'],
        'duration_days' => $promotion['duration_days'],
        'currency_id'   => $promotion['currency_id'],
        'test_seed'     => 'true',
    ],
]);

// Ulož stripe_payment_intent_id do payment záznamu
$db->prepare("UPDATE payments SET stripe_payment_intent_id = :pi_id WHERE id = :id")
   ->execute(['pi_id' => $paymentIntent->id, 'id' => $paymentId]);

echo "\n✅ Payment (pending): {$paymentId}\n";
echo "✅ PaymentIntent:     {$paymentIntent->id}\n";
echo "   (Subscription sa vytvorí až po succeeded webhookU)\n";

// Metadata pre stripe trigger overrides
$triggerMetadata = [
    'user_id'       => $user['id'],
    'chatbot_id'    => $chatbot['id'],
    'promotion_id'  => $promotion['id'],
    'promotion_id2' => '',
    'payment_id'    => $paymentId,
    'payment_id2'   => '',
    'amount'        => $finalPrice,
    'amount2'       => 0,
    'period_type'   => $promotion['billing_period_type'],
    'duration_days' => $promotion['duration_days'],
    'currency_id'   => $promotion['currency_id'],
];

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
    echo "  r) Reset DB (payment → pending, subscription vymazaná)\n";
    echo "  0) Koniec\n";
    echo "=================================================\n";
    echo "Vyber (0-7, r): ";

    $choice = trim(fgets(STDIN));

    switch ($choice) {
        case '1':
            runTrigger('payment_intent.succeeded', $triggerMetadata);
            sleep(2);
            checkDB($db, $paymentId);
            break;

        case '2':
            runTrigger('payment_intent.payment_failed', $triggerMetadata);
            sleep(2);
            checkDB($db, $paymentId);
            break;

        case '3':
            runTrigger('payment_intent.canceled', $triggerMetadata);
            sleep(2);
            checkDB($db, $paymentId);
            break;

        case '4':
            echo "\n>>> Test 1/3: Successful payment\n";
            runTrigger('payment_intent.succeeded', $triggerMetadata);
            sleep(2);
            checkDB($db, $paymentId);

            echo "\nResetting DB...\n";
            resetDB($db, $paymentId);

            echo "\n>>> Test 2/3: Failed payment\n";
            runTrigger('payment_intent.payment_failed', $triggerMetadata);
            sleep(2);
            checkDB($db, $paymentId);

            echo "\nResetting DB...\n";
            resetDB($db, $paymentId);

            echo "\n>>> Test 3/3: Canceled payment\n";
            runTrigger('payment_intent.canceled', $triggerMetadata);
            sleep(2);
            checkDB($db, $paymentId);
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
            resetDB($db, $paymentId);
            break;

        case '0':
            echo "\n=================================================\n";
            echo " IDs pre kontrolu\n";
            echo "=================================================\n";
            echo " payment_id:   {$paymentId}\n";
            echo " stripe_pi_id: {$paymentIntent->id}\n";
            echo "=================================================\n";
            exit(0);

        default:
            echo "Neplatna volba, skus znova.\n";
    }
}