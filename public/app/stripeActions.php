<?php
/**
 * Stripe Actions
 * Creates Stripe Billing subscriptions for promotion purchases.
 */

require '../../inc/bootstrap.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
if (!empty($stripeConfig['api_version'])) {
    \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
}

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

const STRIPE_CHECKOUT_REUSE_MINUTES = 60;

if ($action === 'create_payment_intent') {
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $chatbotId = trim($input['chatbot_id'] ?? '');
    $promotionId = trim($input['promotion_id'] ?? '');
    $promotionId2 = trim($input['promotion_id2'] ?? '');
    $requestedBillingPeriod = trim($input['billing_period'] ?? '');

    if (!$chatbotId || !$promotionId) {
        echo json_encode(['success' => false, 'message' => 'Missing chatbot_id or promotion_id']);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();

        $chatbotStmt = $db->prepare("
            SELECT id, name
            FROM chatbots
            WHERE id = :chatbot_id
              AND owner_user_id = :user_id
              AND deleted_at IS NULL
              AND status = 'active'
            LIMIT 1
        ");
        $chatbotStmt->execute([
            'chatbot_id' => $chatbotId,
            'user_id' => $user['id'],
        ]);
        $chatbot = $chatbotStmt->fetch(PDO::FETCH_ASSOC);

        if (!$chatbot) {
            echo json_encode(['success' => false, 'message' => 'Invalid chatbot or inactive listing']);
            exit;
        }

        $userStmt = $db->prepare("
            SELECT id, email, first_name, last_name, stripe_customer_id
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $userStmt->execute(['user_id' => $user['id']]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userData) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $promo = loadPromotionForStripe($db, $promotionId);
        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Promotion plan not found']);
            exit;
        }

        $billingPeriod = in_array($requestedBillingPeriod, ['monthly', 'yearly'], true)
            ? $requestedBillingPeriod
            : $promo['period_type'];
        $durationDays = $billingPeriod === 'yearly' ? 365 : (int) $promo['duration_days'];
        $interval = $billingPeriod === 'yearly' ? 'year' : 'month';
        $currency = strtolower($promo['currency_code']);

        $promo2 = null;
        if ($promotionId2) {
            $promo2 = loadPromotionForStripe($db, $promotionId2);
            if (!$promo2 || $promo2['currency_id'] !== $promo['currency_id']) {
                echo json_encode(['success' => false, 'message' => 'Invalid bundle promotion combination']);
                exit;
            }
        }

        $selectedPromotions = [$promo];
        if ($promo2) {
            $selectedPromotions[] = $promo2;
        }

        foreach ($selectedPromotions as $selectedPromo) {
            if (chatbotHasActivePromotionSlot($db, $userData['id'], $chatbotId, $selectedPromo['slot_type'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This chatbot already has ' . stripePromotionLabel($selectedPromo['slot_type']) . ' active.',
                ]);
                exit;
            }
        }

        $stripeCustomerId = $userData['stripe_customer_id'];
        if (!$stripeCustomerId) {
            $customer = \Stripe\Customer::create([
                'email' => $userData['email'],
                'name' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
                'metadata' => ['user_id' => $userData['id']],
            ]);
            $stripeCustomerId = $customer->id;

            $db->prepare("
                UPDATE users
                SET stripe_customer_id = :stripe_customer_id
                WHERE id = :user_id
            ")->execute([
                'stripe_customer_id' => $stripeCustomerId,
                'user_id' => $userData['id'],
            ]);
        }

        $lines = [];
        foreach ($selectedPromotions as $selectedPromo) {
            $lineAmount = $promo2
                ? promotionBundleLineTotalPrice((float) $selectedPromo['price'], $billingPeriod)
                : promotionSingleTotalPrice(
                    (float) $selectedPromo['price'],
                    (float) $selectedPromo['discount_percentage'],
                    $billingPeriod
                );

            $lines[] = [
                'promotion' => $selectedPromo,
                'amount' => $lineAmount,
                'payment_id' => generateUUID(),
                'subscription_id' => generateUUID(),
            ];
        }

        $totalPrice = array_sum(array_column($lines, 'amount'));
        $promotionIds = array_map(static fn ($line) => $line['promotion']['id'], $lines);

        $reusableCheckout = findReusableStripeCheckout(
            $db,
            $userData['id'],
            $chatbotId,
            $promotionIds,
            $billingPeriod,
            $totalPrice
        );

        if ($reusableCheckout) {
            echo json_encode([
                'success' => true,
                'client_secret' => $reusableCheckout['client_secret'],
                'amount' => $reusableCheckout['amount'],
                'currency' => $promo['currency_code'],
                'stripe_subscription_id' => $reusableCheckout['stripe_subscription_id'],
                'stripe_invoice_id' => $reusableCheckout['stripe_invoice_id'],
                'reused' => true,
            ]);
            exit;
        }

        $stripeItems = [];

        foreach ($lines as $line) {
            $productId = getOrCreateStripePromotionProduct($line['promotion']);
            $stripeItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product' => $productId,
                    'unit_amount' => stripeAmountToCents((float) $line['amount']),
                    'recurring' => ['interval' => $interval],
                    'tax_behavior' => 'exclusive',
                ],
                'quantity' => 1,
                'metadata' => [
                    'promotion_id' => $line['promotion']['id'],
                    'payment_id' => $line['payment_id'],
                    'subscription_id' => $line['subscription_id'],
                    'slot_type' => $line['promotion']['slot_type'],
                ],
            ];
        }

        $paymentIds = array_column($lines, 'payment_id');
        $subscriptionIds = array_column($lines, 'subscription_id');
        $lineAmounts = array_map(static fn ($line) => number_format((float) $line['amount'], 2, '.', ''), $lines);

        foreach ($lines as $line) {
            $paymentMetadata = json_encode([
                'billing_period' => $billingPeriod,
                'period_type' => $billingPeriod,
                'duration_days' => $durationDays,
                'promotion_id' => $line['promotion']['id'],
                'line_amount' => $line['amount'],
                'total_amount' => $totalPrice,
                'is_bundle' => count($lines) > 1,
            ]);

            $db->prepare("
                INSERT INTO payments (
                    id, user_id, chatbot_id,
                    amount, currency_id, payment_method, status,
                    metadata, created_at, updated_at
                ) VALUES (
                    :id, :user_id, :chatbot_id,
                    :amount, :currency_id, 'stripe', 'pending',
                    :metadata, NOW(), NOW()
                )
            ")->execute([
                'id' => $line['payment_id'],
                'user_id' => $userData['id'],
                'chatbot_id' => $chatbotId,
                'amount' => $line['amount'],
                'currency_id' => $promo['currency_id'],
                'metadata' => $paymentMetadata,
            ]);
        }

        $stripeSubscription = \Stripe\Subscription::create([
            'customer' => $stripeCustomerId,
            'items' => $stripeItems,
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'metadata' => [
                'user_id' => $userData['id'],
                'chatbot_id' => $chatbotId,
                'chatbot_name' => $chatbot['name'],
                'promotion_ids' => implode(',', $promotionIds),
                'payment_ids' => implode(',', $paymentIds),
                'subscription_ids' => implode(',', $subscriptionIds),
                'line_amounts' => implode(',', $lineAmounts),
                'total_amount' => number_format((float) $totalPrice, 2, '.', ''),
                'period_type' => $billingPeriod,
                'duration_days' => (string) $durationDays,
                'currency_id' => $promo['currency_id'],
                'is_bundle' => count($lines) > 1 ? '1' : '0',
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        $latestInvoice = $stripeSubscription->latest_invoice ?? null;
        $paymentIntent = $latestInvoice ? ($latestInvoice->payment_intent ?? null) : null;
        $clientSecret = $paymentIntent ? ($paymentIntent->client_secret ?? null) : null;

        if (!$clientSecret && $latestInvoice && isset($latestInvoice->confirmation_secret->client_secret)) {
            $clientSecret = $latestInvoice->confirmation_secret->client_secret;
        }

        if (!$clientSecret) {
            throw new RuntimeException('Stripe did not return a payment client secret.');
        }

        $stripeInvoiceId = $latestInvoice ? ($latestInvoice->id ?? null) : null;
        $invoiceUrl = $latestInvoice
            ? (($latestInvoice->hosted_invoice_url ?? null) ?: ($latestInvoice->invoice_pdf ?? null))
            : null;
        $stripePaymentIntentId = $paymentIntent ? ($paymentIntent->id ?? null) : null;

        foreach ($lines as $line) {
            $db->prepare("
                UPDATE payments
                SET stripe_payment_intent_id = :stripe_payment_intent_id,
                    stripe_invoice_id = :stripe_invoice_id,
                    invoice_url = :invoice_url,
                    metadata = JSON_SET(
                        metadata,
                        '$.stripe_subscription_id', :stripe_subscription_id,
                        '$.stripe_invoice_id', :stripe_invoice_id_json
                    ),
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'stripe_invoice_id' => $stripeInvoiceId,
                'invoice_url' => $invoiceUrl,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_invoice_id_json' => $stripeInvoiceId,
                'id' => $line['payment_id'],
            ]);
        }

        AuditLog::log('payment', $paymentIds[0], 'created',
            null,
            [
                'status' => 'pending',
                'amount' => $totalPrice,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_invoice_id' => $stripeInvoiceId,
            ],
            $userData['id']
        );

        echo json_encode([
            'success' => true,
            'client_secret' => $clientSecret,
            'amount' => $totalPrice,
            'currency' => $promo['currency_code'],
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe subscription error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log('stripeActions error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }

    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

function loadPromotionForStripe(PDO $db, string $promotionId): ?array {
    $stmt = $db->prepare("
        SELECT p.*, bp.type AS period_type, bp.duration_days,
               c.code AS currency_code, c.id AS currency_id
        FROM promotions p
        JOIN billing_periods bp ON p.billing_period_id = bp.id
        JOIN currencies c ON p.currency_id = c.id
        WHERE p.id = :promotion_id
          AND p.is_active = 1
          AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute(['promotion_id' => $promotionId]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    return $promotion ?: null;
}

function chatbotHasActivePromotionSlot(PDO $db, string $userId, string $chatbotId, string $slotType): bool {
    $stmt = $db->prepare("
        SELECT s.id
        FROM subscriptions s
        JOIN promotions p ON p.id = s.promotions_id
        WHERE s.user_id = :user_id
          AND s.chatbot_id = :chatbot_id
          AND s.subscription_type = 'promotion'
          AND s.status IN ('active', 'trialing', 'past_due')
          AND p.slot_type = :slot_type
        LIMIT 1
    ");
    $stmt->execute([
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
        'slot_type' => $slotType,
    ]);

    return (bool) $stmt->fetchColumn();
}

function stripePromotionLabel(string $slotType): string {
    return match ($slotType) {
        'Homepage' => 'Homepage Visibility',
        'Chatbots page' => 'Chatbots Page Visibility',
        default => $slotType,
    };
}

function stripePromotionProductId(string $promotionId): string {
    return 'cs_promo_' . substr(str_replace('-', '', strtolower($promotionId)), 0, 24);
}

function getOrCreateStripePromotionProduct(array $promotion): string {
    $productId = stripePromotionProductId($promotion['id']);
    $label = stripePromotionLabel($promotion['slot_type']);

    try {
        $product = \Stripe\Product::retrieve($productId);
        if (!empty($product->id) && empty($product->deleted)) {
            return $product->id;
        }
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        // Product does not exist yet.
    }

    $createParams = [
        'id' => $productId,
        'name' => 'ChatbotScout - ' . $label,
        'metadata' => [
            'promotion_id' => $promotion['id'],
            'slot_type' => $promotion['slot_type'],
        ],
    ];

    try {
        $product = \Stripe\Product::create($createParams);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        unset($createParams['id']);
        $product = \Stripe\Product::create($createParams);
    }

    return $product->id;
}

function stripeAmountToCents(float $amount): int {
    return (int) round($amount * 100);
}

function findReusableStripeCheckout(
    PDO $db,
    string $userId,
    string $chatbotId,
    array $promotionIds,
    string $billingPeriod,
    float $expectedAmount
): ?array {
    if (empty($promotionIds)) {
        return null;
    }

    $promotionIds = array_values(array_map('strval', $promotionIds));
    sort($promotionIds, SORT_STRING);
    $expectedPromotionIds = implode(',', $promotionIds);
    $createdAfter = date('Y-m-d H:i:s', time() - (STRIPE_CHECKOUT_REUSE_MINUTES * 60));

    $stmt = $db->prepare("
        SELECT
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.stripe_subscription_id')) AS stripe_subscription_id,
            MAX(stripe_invoice_id) AS stripe_invoice_id,
            MAX(stripe_payment_intent_id) AS stripe_payment_intent_id,
            SUM(amount) AS total_amount,
            GROUP_CONCAT(
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.promotion_id'))
                ORDER BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.promotion_id'))
                SEPARATOR ','
            ) AS promotion_ids,
            COUNT(*) AS line_count,
            MAX(created_at) AS latest_created_at
        FROM payments
        WHERE user_id = :user_id
          AND chatbot_id = :chatbot_id
          AND payment_method = 'stripe'
          AND status IN ('pending', 'processing')
          AND created_at >= :created_after
          AND COALESCE(
              JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.billing_period')),
              JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.period_type'))
          ) = :billing_period
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.stripe_subscription_id')) IS NOT NULL
        GROUP BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.stripe_subscription_id'))
        HAVING COUNT(*) = :line_count
           AND promotion_ids = :promotion_ids
        ORDER BY latest_created_at DESC
        LIMIT 5
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':chatbot_id', $chatbotId, PDO::PARAM_STR);
    $stmt->bindValue(':created_after', $createdAfter, PDO::PARAM_STR);
    $stmt->bindValue(':billing_period', $billingPeriod, PDO::PARAM_STR);
    $stmt->bindValue(':line_count', count($promotionIds), PDO::PARAM_INT);
    $stmt->bindValue(':promotion_ids', $expectedPromotionIds, PDO::PARAM_STR);
    $stmt->execute();

    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidates as $candidate) {
        $stripeSubscriptionId = trim((string) ($candidate['stripe_subscription_id'] ?? ''));
        if ($stripeSubscriptionId === '') {
            continue;
        }

        if (abs((float) ($candidate['total_amount'] ?? 0) - $expectedAmount) > 0.01) {
            continue;
        }

        try {
            $stripeSubscription = \Stripe\Subscription::retrieve([
                'id' => $stripeSubscriptionId,
                'expand' => ['latest_invoice.payment_intent'],
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            error_log('Reusable checkout lookup skipped missing Stripe subscription: ' . $e->getMessage());
            continue;
        }

        $stripeStatus = (string) ($stripeSubscription->status ?? '');
        if ($stripeStatus !== 'incomplete') {
            continue;
        }

        $latestInvoice = $stripeSubscription->latest_invoice ?? null;
        if (!$latestInvoice) {
            continue;
        }

        if (is_string($latestInvoice)) {
            $latestInvoice = \Stripe\Invoice::retrieve([
                'id' => $latestInvoice,
                'expand' => ['payment_intent'],
            ]);
        }

        $invoiceStatus = (string) ($latestInvoice->status ?? '');
        if (in_array($invoiceStatus, ['paid', 'void', 'uncollectible'], true)) {
            continue;
        }

        $paymentIntent = $latestInvoice->payment_intent ?? null;
        if (is_string($paymentIntent)) {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntent);
        }

        $paymentIntentStatus = $paymentIntent ? (string) ($paymentIntent->status ?? '') : '';
        if (in_array($paymentIntentStatus, ['canceled', 'succeeded'], true)) {
            continue;
        }

        $clientSecret = $paymentIntent ? ($paymentIntent->client_secret ?? null) : null;
        if (!$clientSecret && isset($latestInvoice->confirmation_secret->client_secret)) {
            $clientSecret = $latestInvoice->confirmation_secret->client_secret;
        }

        if (!$clientSecret) {
            continue;
        }

        return [
            'client_secret' => $clientSecret,
            'amount' => (float) ($candidate['total_amount'] ?? 0),
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_invoice_id' => $latestInvoice->id ?? ($candidate['stripe_invoice_id'] ?? null),
        ];
    }

    return null;
}
