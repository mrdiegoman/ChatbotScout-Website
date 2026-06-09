<?php
/**
 * Stripe Actions
 * Creates Stripe Billing subscriptions for promotion purchases.
 */

require '../../inc/bootstrap.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../inc/stripeCheckoutLogger.php';
require_once __DIR__ . '/../../inc/stripePaymentFinalizer.php';

$stripeConfig = require __DIR__ . '/../../config/stripe.php';
\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
if (!empty($stripeConfig['api_version'])) {
    \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
}

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

const STRIPE_CHECKOUT_REUSE_MINUTES = 60;

if ($action === 'preview_bundle_upgrade') {
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    validateCsrfToken();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $chatbotId = trim($input['chatbot_id'] ?? '');
    $promotionId = trim($input['promotion_id'] ?? '');
    $requestedBillingPeriod = trim($input['billing_period'] ?? '');
    $upgradeFromSubscriptionId = trim($input['upgrade_from_subscription_id'] ?? '');

    stripeCheckoutLog('preview_bundle_upgrade.request', [
        'user_id' => $user['id'] ?? null,
        'chatbot_id' => $chatbotId,
        'promotion_id' => $promotionId,
        'billing_period' => $requestedBillingPeriod,
        'upgrade_from_subscription_id' => $upgradeFromSubscriptionId,
    ]);

    if (!$chatbotId || !$promotionId || !$upgradeFromSubscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Missing upgrade details']);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();

        $chatbotStmt = $db->prepare("
            SELECT id
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

        if (!$chatbotStmt->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Invalid chatbot or inactive listing']);
            exit;
        }

        $userStmt = $db->prepare("
            SELECT id, stripe_customer_id
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
        $interval = $billingPeriod === 'yearly' ? 'year' : 'month';
        $currency = strtolower($promo['currency_code']);

        $result = previewStripeBundleUpgradePayment(
            $db,
            $userData,
            $chatbotId,
            $promo,
            $billingPeriod,
            $interval,
            $currency,
            trim((string) ($userData['stripe_customer_id'] ?? '')),
            $upgradeFromSubscriptionId
        );

        stripeCheckoutLog('preview_bundle_upgrade.response', [
            'success' => $result['success'] ?? false,
            'amount' => $result['amount'] ?? null,
            'currency' => $result['currency'] ?? null,
            'source' => $result['source'] ?? null,
            'stripe_subscription_id' => $result['stripe_subscription_id'] ?? null,
            'message' => $result['message'] ?? null,
        ]);

        echo json_encode($result);
        exit;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe upgrade preview error: ' . $e->getMessage());
        stripeCheckoutLog('preview_bundle_upgrade.stripe_error', stripeCheckoutExceptionContext($e));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        error_log('Stripe upgrade preview error: ' . $e->getMessage());
        stripeCheckoutLog('preview_bundle_upgrade.error', stripeCheckoutExceptionContext($e));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
}

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
    $upgradeFromSubscriptionId = trim($input['upgrade_from_subscription_id'] ?? '');

    stripeCheckoutLog('create_payment_intent.request', [
        'user_id' => $user['id'] ?? null,
        'chatbot_id' => $chatbotId,
        'promotion_id' => $promotionId,
        'promotion_id2' => $promotionId2,
        'billing_period' => $requestedBillingPeriod,
        'upgrade_from_subscription_id' => $upgradeFromSubscriptionId,
    ]);

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

        if ($upgradeFromSubscriptionId !== '') {
            if ($promo2) {
                echo json_encode(['success' => false, 'message' => 'Invalid bundle upgrade request']);
                exit;
            }

            $stripeCustomerId = trim((string) ($userData['stripe_customer_id'] ?? ''));

            stripeCheckoutLog('bundle_upgrade.create.start', [
                'user_id' => $userData['id'],
                'chatbot_id' => $chatbotId,
                'promotion_id' => $promo['id'],
                'billing_period' => $billingPeriod,
                'stripe_customer_id' => $stripeCustomerId,
                'upgrade_from_subscription_id' => $upgradeFromSubscriptionId,
            ]);

            $upgradeResult = createStripeBundleUpgradePayment(
                $db,
                $userData,
                $chatbot,
                $chatbotId,
                $promo,
                $billingPeriod,
                $durationDays,
                $interval,
                $currency,
                $stripeCustomerId,
                $upgradeFromSubscriptionId
            );

            stripeCheckoutLog('bundle_upgrade.create.response', [
                'success' => $upgradeResult['success'] ?? false,
                'paid' => $upgradeResult['paid'] ?? false,
                'amount' => $upgradeResult['amount'] ?? null,
                'currency' => $upgradeResult['currency'] ?? null,
                'stripe_subscription_id' => $upgradeResult['stripe_subscription_id'] ?? null,
                'stripe_invoice_id' => $upgradeResult['stripe_invoice_id'] ?? null,
                'reused' => $upgradeResult['reused'] ?? false,
                'message' => $upgradeResult['message'] ?? null,
            ]);

            echo json_encode($upgradeResult);
            exit;
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

        stripeCheckoutLog('subscription_create.response', [
            'success' => true,
            'amount' => $totalPrice,
            'currency' => $promo['currency_code'],
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,
            'line_count' => count($lines),
        ]);

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
        stripeCheckoutLog('create_payment_intent.stripe_error', stripeCheckoutExceptionContext($e));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log('stripeActions error: ' . $e->getMessage());
        stripeCheckoutLog('create_payment_intent.error', stripeCheckoutExceptionContext($e));
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

function ensureStripeCustomerForUser(PDO $db, array $userData): string {
    $stripeCustomerId = trim((string) ($userData['stripe_customer_id'] ?? ''));
    if ($stripeCustomerId !== '') {
        return $stripeCustomerId;
    }

    $customer = \Stripe\Customer::create([
        'email' => $userData['email'],
        'name' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
        'metadata' => ['user_id' => $userData['id']],
    ]);

    $db->prepare("
        UPDATE users
        SET stripe_customer_id = :stripe_customer_id
        WHERE id = :user_id
    ")->execute([
        'stripe_customer_id' => $customer->id,
        'user_id' => $userData['id'],
    ]);

    return $customer->id;
}

function loadStripeBundleUpgradeContext(
    PDO $db,
    string $userId,
    string $chatbotId,
    string $existingSubscriptionId,
    array $missingPromotion,
    string $billingPeriod
): ?array {
    $stmt = $db->prepare("
        SELECT
            s.id AS subscription_id,
            s.stripe_subscription_id,
            s.stripe_customer_id,
            s.status,
            s.start_date,
            s.end_date,
            p.id AS promotion_id,
            p.slot_type,
            p.price,
            p.discount_percentage,
            p.currency_id,
            c.code AS currency_code,
            bp.type AS plan_billing_period,
            (
                SELECT COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(pay.metadata, '$.billing_period')),
                    JSON_UNQUOTE(JSON_EXTRACT(pay.metadata, '$.period_type'))
                )
                FROM payments pay
                WHERE pay.subscription_id = s.id
                  AND pay.status = 'succeeded'
                ORDER BY COALESCE(pay.succeeded_at, pay.created_at) DESC
                LIMIT 1
            ) AS latest_payment_billing_period
        FROM subscriptions s
        JOIN promotions p ON p.id = s.promotions_id
        JOIN currencies c ON c.id = p.currency_id
        JOIN billing_periods bp ON bp.id = p.billing_period_id
        WHERE s.id = :subscription_id
          AND s.user_id = :user_id
          AND s.chatbot_id = :chatbot_id
          AND s.subscription_type = 'promotion'
          AND s.status IN ('active', 'trialing', 'past_due')
        LIMIT 1
    ");
    $stmt->execute([
        'subscription_id' => $existingSubscriptionId,
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
    ]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing || empty($existing['stripe_subscription_id'])) {
        return null;
    }

    $existingPlanBillingPeriod = promotionNormalizeBillingPeriod($existing['plan_billing_period'] ?? 'monthly');
    $existingPaymentBillingPeriod = promotionNormalizeBillingPeriod($existing['latest_payment_billing_period'] ?? '', '');
    $existingDateBillingPeriod = promotionBillingPeriodFromDates(
        $existing['start_date'] ?? null,
        $existing['end_date'] ?? null,
        $existingPlanBillingPeriod
    );
    $existingBillingPeriod = $existingPaymentBillingPeriod
        ?: (in_array($existingDateBillingPeriod, ['monthly', 'yearly'], true) ? $existingDateBillingPeriod : $existingPlanBillingPeriod);

    $missingSlot = $existing['slot_type'] === 'Homepage' ? 'Chatbots page' : 'Homepage';
    if ($existingBillingPeriod !== $billingPeriod || $missingPromotion['slot_type'] !== $missingSlot) {
        return null;
    }

    if ((string) $existing['currency_id'] !== (string) $missingPromotion['currency_id']) {
        return null;
    }

    if (chatbotHasActivePromotionSlot($db, $userId, $chatbotId, $missingPromotion['slot_type'])) {
        return null;
    }

    return [
        'existing' => $existing,
        'missing' => $missingPromotion,
        'existing_billing_period' => $existingBillingPeriod,
    ];
}

function previewStripeBundleUpgradePayment(
    PDO $db,
    array $userData,
    string $chatbotId,
    array $missingPromotion,
    string $billingPeriod,
    string $interval,
    string $currency,
    string $stripeCustomerId,
    string $existingSubscriptionId
): array {
    $context = loadStripeBundleUpgradeContext(
        $db,
        $userData['id'],
        $chatbotId,
        $existingSubscriptionId,
        $missingPromotion,
        $billingPeriod
    );

    if (!$context) {
        return ['success' => false, 'message' => 'This upgrade is no longer available. Please refresh and try again.'];
    }

    $existingPromotion = $context['existing'];
    $stripeSubscriptionId = $existingPromotion['stripe_subscription_id'];

    stripeCheckoutLog('bundle_upgrade.preview.context_loaded', [
        'user_id' => $userData['id'],
        'chatbot_id' => $chatbotId,
        'existing_subscription_id' => $existingSubscriptionId,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'existing_promotion_id' => $existingPromotion['promotion_id'],
        'missing_promotion_id' => $missingPromotion['id'],
        'billing_period' => $billingPeriod,
    ]);

    $stripeSubscription = \Stripe\Subscription::retrieve([
        'id' => $stripeSubscriptionId,
        'expand' => ['items.data.price', 'default_payment_method'],
    ]);

    $expectedStripeCustomerId = trim((string) ($existingPromotion['stripe_customer_id'] ?? '')) ?: $stripeCustomerId;
    if ((string) ($stripeSubscription->customer ?? '') !== $expectedStripeCustomerId) {
        return ['success' => false, 'message' => 'Stripe customer mismatch. Please contact support.'];
    }

    if (!empty($stripeSubscription->pending_update)) {
        return ['success' => false, 'message' => 'A previous subscription update is still pending payment. Please finish or retry that checkout first.'];
    }

    $existingItemId = findStripeSubscriptionItemId(
        $stripeSubscription,
        $existingPromotion['promotion_id'],
        $existingSubscriptionId
    );

    if (!$existingItemId) {
        throw new RuntimeException('Could not find the existing Stripe subscription item for upgrade preview.');
    }

    $missingBundleAmount = promotionBundleLineTotalPrice((float) $missingPromotion['price'], $billingPeriod);
    $amount = 0.0;
    $source = 'stripe';

    try {
        $preview = \Stripe\Invoice::createPreview([
            'customer' => $expectedStripeCustomerId,
            'subscription' => $stripeSubscriptionId,
            'subscription_details' => [
                'items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product' => getOrCreateStripePromotionProduct($missingPromotion),
                            'unit_amount' => stripeAmountToCents($missingBundleAmount),
                            'recurring' => ['interval' => $interval],
                            'tax_behavior' => 'exclusive',
                        ],
                        'quantity' => 1,
                    ],
                ],
                'proration_behavior' => 'always_invoice',
            ],
        ]);

        $amount = stripeInvoiceAmountDecimal($preview);
        stripeCheckoutLog('bundle_upgrade.preview.stripe_preview_created', [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'amount' => $amount,
            'currency' => strtoupper($missingPromotion['currency_code']),
            'preview_invoice_id' => $preview->id ?? null,
            'missing_bundle_amount' => $missingBundleAmount,
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe upgrade preview fallback used: ' . $e->getMessage());
        stripeCheckoutLog('bundle_upgrade.preview.stripe_preview_failed', stripeCheckoutExceptionContext($e));
        $source = 'local_estimate';
    }

    if ($amount <= 0) {
        $amount = stripeLocalBundleUpgradeEstimateAmount($existingPromotion, $missingPromotion, $billingPeriod);
        $source = 'local_estimate';
        stripeCheckoutLog('bundle_upgrade.preview.local_estimate_used', [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'amount' => $amount,
            'currency' => strtoupper($missingPromotion['currency_code']),
        ]);
    }

    if ($amount <= 0) {
        throw new RuntimeException('Stripe upgrade preview amount is missing.');
    }

    return [
        'success' => true,
        'preview' => true,
        'amount' => $amount,
        'currency' => strtoupper($missingPromotion['currency_code']),
        'source' => $source,
        'payment_method' => stripeDefaultPaymentMethodSummary($stripeSubscription, $expectedStripeCustomerId),
        'stripe_subscription_id' => $stripeSubscriptionId,
    ];
}

function createStripeBundleUpgradePayment(
    PDO $db,
    array $userData,
    array $chatbot,
    string $chatbotId,
    array $missingPromotion,
    string $billingPeriod,
    int $durationDays,
    string $interval,
    string $currency,
    string $stripeCustomerId,
    string $existingSubscriptionId
): array {
    $context = loadStripeBundleUpgradeContext(
        $db,
        $userData['id'],
        $chatbotId,
        $existingSubscriptionId,
        $missingPromotion,
        $billingPeriod
    );

    if (!$context) {
        return ['success' => false, 'message' => 'This upgrade is no longer available. Please refresh and try again.'];
    }

    $existingPromotion = $context['existing'];
    $stripeSubscriptionId = $existingPromotion['stripe_subscription_id'];

    stripeCheckoutLog('bundle_upgrade.payment.context_loaded', [
        'user_id' => $userData['id'],
        'chatbot_id' => $chatbotId,
        'existing_subscription_id' => $existingSubscriptionId,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'existing_promotion_id' => $existingPromotion['promotion_id'],
        'missing_promotion_id' => $missingPromotion['id'],
        'billing_period' => $billingPeriod,
    ]);

    $reusableCheckout = findReusableStripeUpgradeCheckout(
        $db,
        $userData['id'],
        $chatbotId,
        $existingSubscriptionId,
        $missingPromotion['id'],
        $billingPeriod
    );

    if ($reusableCheckout) {
        stripeCheckoutLog('bundle_upgrade.payment.reused_pending_checkout', [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $reusableCheckout['stripe_invoice_id'],
            'amount' => $reusableCheckout['amount'],
        ]);

        return [
            'success' => true,
            'client_secret' => $reusableCheckout['client_secret'],
            'amount' => $reusableCheckout['amount'],
            'currency' => strtoupper($missingPromotion['currency_code']),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $reusableCheckout['stripe_invoice_id'],
            'reused' => true,
        ];
    }

    $stripeSubscription = \Stripe\Subscription::retrieve([
        'id' => $stripeSubscriptionId,
        'expand' => ['items.data.price', 'latest_invoice.payment_intent'],
    ]);

    $expectedStripeCustomerId = trim((string) ($existingPromotion['stripe_customer_id'] ?? '')) ?: $stripeCustomerId;
    if ((string) ($stripeSubscription->customer ?? '') !== $expectedStripeCustomerId) {
        return ['success' => false, 'message' => 'Stripe customer mismatch. Please contact support.'];
    }

    if (!empty($stripeSubscription->pending_update)) {
        return ['success' => false, 'message' => 'A previous subscription update is still pending payment. Please finish or retry that checkout first.'];
    }

    $preserveCancelAtPeriodEnd = !empty($stripeSubscription->cancel_at_period_end);

    $existingItemId = findStripeSubscriptionItemId(
        $stripeSubscription,
        $existingPromotion['promotion_id'],
        $existingSubscriptionId
    );

    if (!$existingItemId) {
        throw new RuntimeException('Could not find the existing Stripe subscription item for upgrade.');
    }

    $existingBundleAmount = promotionBundleLineTotalPrice((float) $existingPromotion['price'], $billingPeriod);
    $missingBundleAmount = promotionBundleLineTotalPrice((float) $missingPromotion['price'], $billingPeriod);
    $bundleTotalAmount = $existingBundleAmount + $missingBundleAmount;

    $newSubscriptionId = generateUUID();
    $paymentId = generateUUID();

    stripeCheckoutLog('bundle_upgrade.payment.local_ids_created', [
        'payment_id' => $paymentId,
        'new_subscription_id' => $newSubscriptionId,
        'existing_subscription_id' => $existingSubscriptionId,
    ]);

    $missingPriceId = createStripePromotionPrice($missingPromotion, $missingBundleAmount, $currency, $interval, 'bundle');

    $upgradeMetadata = [
        'user_id' => $userData['id'],
        'chatbot_id' => $chatbotId,
        'chatbot_name' => $chatbot['name'],
        'promotion_ids' => $existingPromotion['promotion_id'] . ',' . $missingPromotion['id'],
        'payment_ids' => $paymentId,
        'subscription_ids' => $existingSubscriptionId . ',' . $newSubscriptionId,
        'line_amounts' => number_format((float) $existingBundleAmount, 2, '.', '') . ',' . number_format((float) $missingBundleAmount, 2, '.', ''),
        'total_amount' => number_format((float) $bundleTotalAmount, 2, '.', ''),
        'period_type' => $billingPeriod,
        'duration_days' => (string) $durationDays,
        'currency_id' => $missingPromotion['currency_id'],
        'is_bundle' => '1',
        'upgrade_type' => 'bundle_upgrade',
        'upgrade_pricing_version' => 'missing_bundle_only',
        'upgrade_existing_subscription_id' => $existingSubscriptionId,
        'upgrade_new_subscription_id' => $newSubscriptionId,
        'upgrade_existing_promotion_id' => $existingPromotion['promotion_id'],
        'upgrade_new_promotion_id' => $missingPromotion['id'],
        'upgrade_payment_id' => $paymentId,
    ];

    stripeCheckoutLog('bundle_upgrade.payment.stripe_subscription_update_start', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'payment_id' => $paymentId,
        'existing_item_id' => $existingItemId,
        'cancel_at_period_end' => $preserveCancelAtPeriodEnd,
        'existing_bundle_amount' => $existingBundleAmount,
        'missing_bundle_amount' => $missingBundleAmount,
    ]);

    $subscriptionUpdateParams = [
        'items' => [
            [
                'price' => $missingPriceId,
                'quantity' => 1,
                'metadata' => [
                    'promotion_id' => $missingPromotion['id'],
                    'subscription_id' => $newSubscriptionId,
                    'pricing_mode' => 'bundle',
                ],
            ],
        ],
        'metadata' => $upgradeMetadata,
        'payment_behavior' => 'pending_if_incomplete',
        'proration_behavior' => 'always_invoice',
        'expand' => ['latest_invoice.payment_intent', 'items.data.price'],
    ];

    $updatedSubscription = \Stripe\Subscription::update($stripeSubscriptionId, $subscriptionUpdateParams);
    if ($preserveCancelAtPeriodEnd && empty($updatedSubscription->cancel_at_period_end)) {
        try {
            $updatedSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
                'cancel_at_period_end' => true,
                'expand' => ['latest_invoice.payment_intent', 'items.data.price'],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            stripeCheckoutLog('bundle_upgrade.payment.cancel_restore_deferred', stripeCheckoutExceptionContext($e) + [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);
        }
    }

    $latestInvoice = $updatedSubscription->latest_invoice ?? null;
    if (is_string($latestInvoice)) {
        $latestInvoice = \Stripe\Invoice::retrieve([
            'id' => $latestInvoice,
            'expand' => ['payment_intent'],
        ]);
    }

    $paymentIntent = $latestInvoice ? ($latestInvoice->payment_intent ?? null) : null;
    if (is_string($paymentIntent)) {
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntent);
    }

    $stripeInvoiceId = $latestInvoice ? ($latestInvoice->id ?? null) : null;
    $stripePaymentIntentId = $paymentIntent ? ($paymentIntent->id ?? null) : null;
    $invoiceUrl = $latestInvoice
        ? (($latestInvoice->hosted_invoice_url ?? null) ?: ($latestInvoice->invoice_pdf ?? null))
        : null;
    $amountDue = stripeInvoiceAmountDecimal($latestInvoice);

    if ($amountDue <= 0) {
        throw new RuntimeException('Stripe upgrade invoice amount is missing.');
    }

    if ($stripeInvoiceId) {
        \Stripe\Invoice::update($stripeInvoiceId, ['metadata' => $upgradeMetadata]);
    }

    stripeCheckoutLog('bundle_upgrade.payment.stripe_subscription_update_response', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_invoice_id' => $stripeInvoiceId,
        'stripe_payment_intent_id' => $stripePaymentIntentId,
        'invoice_status' => $latestInvoice ? ($latestInvoice->status ?? null) : null,
        'invoice_paid' => $latestInvoice ? (bool) ($latestInvoice->paid ?? false) : null,
        'payment_intent_status' => $paymentIntent ? ($paymentIntent->status ?? null) : null,
        'amount' => $amountDue,
    ]);

    $paymentMetadata = json_encode([
        'upgrade_type' => 'bundle_upgrade',
        'upgrade_pricing_version' => 'missing_bundle_only',
        'billing_period' => $billingPeriod,
        'period_type' => $billingPeriod,
        'duration_days' => $durationDays,
        'promotion_id' => $missingPromotion['id'],
        'existing_promotion_id' => $existingPromotion['promotion_id'],
        'upgrade_from_subscription_id' => $existingSubscriptionId,
        'upgrade_new_subscription_id' => $newSubscriptionId,
        'line_amount' => $amountDue,
        'total_amount' => $amountDue,
        'is_bundle' => true,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_invoice_id' => $stripeInvoiceId,
    ]);

    $db->prepare("
        INSERT INTO payments (
            id, user_id, chatbot_id,
            amount, currency_id, payment_method, status,
            stripe_payment_intent_id, stripe_invoice_id, invoice_url,
            metadata, created_at, updated_at
        ) VALUES (
            :id, :user_id, :chatbot_id,
            :amount, :currency_id, 'stripe', 'pending',
            :stripe_payment_intent_id, :stripe_invoice_id, :invoice_url,
            :metadata, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            chatbot_id = VALUES(chatbot_id),
            amount = VALUES(amount),
            currency_id = VALUES(currency_id),
            payment_method = 'stripe',
            status = IF(status = 'succeeded', status, VALUES(status)),
            stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, VALUES(stripe_payment_intent_id)),
            stripe_invoice_id = VALUES(stripe_invoice_id),
            invoice_url = VALUES(invoice_url),
            metadata = VALUES(metadata),
            updated_at = NOW()
    ")->execute([
        'id' => $paymentId,
        'user_id' => $userData['id'],
        'chatbot_id' => $chatbotId,
        'amount' => $amountDue,
        'currency_id' => $missingPromotion['currency_id'],
        'stripe_payment_intent_id' => $stripePaymentIntentId,
        'stripe_invoice_id' => $stripeInvoiceId,
        'invoice_url' => $invoiceUrl,
        'metadata' => $paymentMetadata,
    ]);

    stripeCheckoutLog('bundle_upgrade.payment.pending_payment_saved', [
        'payment_id' => $paymentId,
        'amount' => $amountDue,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_invoice_id' => $stripeInvoiceId,
    ]);

    AuditLog::log('payment', $paymentId, 'created',
        null,
        [
            'status' => 'pending',
            'amount' => $amountDue,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'upgrade_type' => 'bundle_upgrade',
        ],
        $userData['id']
    );

    if (stripeInvoiceIsAlreadyPaid($latestInvoice, $paymentIntent)) {
        stripeCheckoutLog('bundle_upgrade.payment.already_paid_before_client_secret', [
            'payment_id' => $paymentId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,
            'amount' => $amountDue,
        ]);

        finalizeStripePaidInvoice($db, $latestInvoice, $userData['id']);

        return [
            'success' => true,
            'paid' => true,
            'amount' => $amountDue,
            'currency' => strtoupper($missingPromotion['currency_code']),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'redirect_url' => stripeCheckoutSuccessRedirectUrl($stripePaymentIntentId),
        ];
    }

    $clientSecret = checkoutPaymentClientSecret($latestInvoice, $paymentIntent);
    if (!$clientSecret) {
        throw new RuntimeException('Stripe did not return a payment client secret for the upgrade.');
    }

    stripeCheckoutLog('bundle_upgrade.payment.client_secret_returned', [
        'payment_id' => $paymentId,
        'stripe_invoice_id' => $stripeInvoiceId,
        'stripe_payment_intent_id' => $stripePaymentIntentId,
        'amount' => $amountDue,
    ]);

    return [
        'success' => true,
        'client_secret' => $clientSecret,
        'amount' => $amountDue,
        'currency' => strtoupper($missingPromotion['currency_code']),
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_invoice_id' => $stripeInvoiceId,
    ];
}

function createStripePromotionPrice(array $promotion, float $amount, string $currency, string $interval, string $pricingMode): string {
    if (empty($promotion['id']) && !empty($promotion['promotion_id'])) {
        $promotion['id'] = $promotion['promotion_id'];
    }

    $productId = getOrCreateStripePromotionProduct($promotion);

    $price = \Stripe\Price::create([
        'currency' => $currency,
        'product' => $productId,
        'unit_amount' => stripeAmountToCents($amount),
        'recurring' => ['interval' => $interval],
        'tax_behavior' => 'exclusive',
        'metadata' => [
            'promotion_id' => $promotion['id'] ?? $promotion['promotion_id'] ?? '',
            'slot_type' => $promotion['slot_type'] ?? '',
            'pricing_mode' => $pricingMode,
        ],
    ]);

    return $price->id;
}

function findStripeSubscriptionItemId($stripeSubscription, string $promotionId, string $subscriptionId): ?string {
    $items = $stripeSubscription->items->data ?? [];
    foreach ($items as $item) {
        $metadata = $item->metadata ?? null;
        $itemPromotionId = $metadata->promotion_id ?? null;
        $itemSubscriptionId = $metadata->subscription_id ?? null;
        if ((string) $itemPromotionId === $promotionId || (string) $itemSubscriptionId === $subscriptionId) {
            return $item->id;
        }
    }

    if (count($items) === 1 && !empty($items[0]->id)) {
        return $items[0]->id;
    }

    return null;
}

function checkoutPaymentClientSecret($invoice, $paymentIntent): ?string {
    if ($paymentIntent && !empty($paymentIntent->client_secret)) {
        return (string) $paymentIntent->client_secret;
    }

    if ($invoice && isset($invoice->confirmation_secret->client_secret)) {
        return (string) $invoice->confirmation_secret->client_secret;
    }

    return null;
}

function stripeInvoiceAmountDecimal($invoice): float {
    if (!$invoice) {
        return 0.0;
    }

    $invoiceStatus = (string) ($invoice->status ?? '');
    $invoicePaid = !empty($invoice->paid);
    $amount = ($invoicePaid || $invoiceStatus === 'paid')
        ? ($invoice->amount_paid ?? $invoice->total ?? $invoice->amount_due ?? 0)
        : ($invoice->amount_due ?? $invoice->total ?? $invoice->amount_paid ?? 0);

    return ((int) $amount) / 100;
}

function stripeInvoiceIsAlreadyPaid($invoice, $paymentIntent): bool {
    if ($invoice) {
        $invoiceStatus = (string) ($invoice->status ?? '');
        if (!empty($invoice->paid) || $invoiceStatus === 'paid') {
            return true;
        }
    }

    return $paymentIntent && (string) ($paymentIntent->status ?? '') === 'succeeded';
}

function stripeCheckoutSuccessRedirectUrl(?string $paymentIntentId): string {
    if ($paymentIntentId) {
        return 'payment-success.php?payment_intent=' . rawurlencode($paymentIntentId);
    }

    return 'profile.php?tab=billing';
}

function stripeDefaultPaymentMethodSummary($stripeSubscription, string $stripeCustomerId): ?array {
    $paymentMethod = $stripeSubscription->default_payment_method ?? null;

    if (is_string($paymentMethod) && $paymentMethod !== '') {
        $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethod);
    }

    if (!$paymentMethod && $stripeCustomerId !== '') {
        try {
            $customer = \Stripe\Customer::retrieve([
                'id' => $stripeCustomerId,
                'expand' => ['invoice_settings.default_payment_method'],
            ]);
            $paymentMethod = $customer->invoice_settings->default_payment_method ?? null;
            if (is_string($paymentMethod) && $paymentMethod !== '') {
                $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethod);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            stripeCheckoutLog('payment_method.summary_lookup_failed', stripeCheckoutExceptionContext($e));
        }
    }

    if (!$paymentMethod || empty($paymentMethod->id)) {
        return null;
    }

    $type = (string) ($paymentMethod->type ?? '');
    if ($type !== 'card' || empty($paymentMethod->card)) {
        return [
            'id' => (string) $paymentMethod->id,
            'type' => $type ?: 'payment_method',
            'label' => ucfirst(str_replace('_', ' ', $type ?: 'Payment method')),
        ];
    }

    $card = $paymentMethod->card;
    $brand = ucfirst(strtolower((string) ($card->brand ?? 'card')));
    $last4 = (string) ($card->last4 ?? '');
    $expMonth = (int) ($card->exp_month ?? 0);
    $expYear = (int) ($card->exp_year ?? 0);

    return [
        'id' => (string) $paymentMethod->id,
        'type' => 'card',
        'brand' => $brand,
        'last4' => $last4,
        'exp_month' => $expMonth,
        'exp_year' => $expYear,
        'funding' => (string) ($card->funding ?? ''),
        'label' => trim($brand . ($last4 ? ' ending in ' . $last4 : '')),
    ];
}

function stripeLocalBundleUpgradeEstimateAmount(array $existingPromotion, array $missingPromotion, string $billingPeriod): float {
    $missingBundleAmount = promotionBundleLineTotalPrice((float) $missingPromotion['price'], $billingPeriod);

    $startTs = !empty($existingPromotion['start_date']) ? strtotime((string) $existingPromotion['start_date']) : false;
    $endTs = !empty($existingPromotion['end_date']) ? strtotime((string) $existingPromotion['end_date']) : false;
    $remainingRatio = 1.0;

    if ($startTs && $endTs && $endTs > $startTs) {
        $remainingRatio = max(0.0, min(1.0, ($endTs - time()) / ($endTs - $startTs)));
    }

    return round($missingBundleAmount * $remainingRatio, 2);
}

function findReusableStripeUpgradeCheckout(
    PDO $db,
    string $userId,
    string $chatbotId,
    string $existingSubscriptionId,
    string $missingPromotionId,
    string $billingPeriod
): ?array {
    $createdAfter = date('Y-m-d H:i:s', time() - (STRIPE_CHECKOUT_REUSE_MINUTES * 60));

    $stmt = $db->prepare("
        SELECT
            id,
            amount,
            stripe_invoice_id,
            stripe_payment_intent_id,
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.stripe_subscription_id')) AS stripe_subscription_id
        FROM payments
        WHERE user_id = :user_id
          AND chatbot_id = :chatbot_id
          AND payment_method = 'stripe'
          AND status IN ('pending', 'processing')
          AND created_at >= :created_after
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.upgrade_type')) = 'bundle_upgrade'
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.upgrade_pricing_version')) = 'missing_bundle_only'
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.upgrade_from_subscription_id')) = :existing_subscription_id
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.promotion_id')) = :missing_promotion_id
          AND COALESCE(
              JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.billing_period')),
              JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.period_type'))
          ) = :billing_period
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
        'created_after' => $createdAfter,
        'existing_subscription_id' => $existingSubscriptionId,
        'missing_promotion_id' => $missingPromotionId,
        'billing_period' => $billingPeriod,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
        $paymentIntentId = trim((string) ($candidate['stripe_payment_intent_id'] ?? ''));
        $invoiceId = trim((string) ($candidate['stripe_invoice_id'] ?? ''));
        if ($paymentIntentId === '' || $invoiceId === '') {
            continue;
        }

        try {
            $invoice = \Stripe\Invoice::retrieve([
                'id' => $invoiceId,
                'expand' => ['payment_intent'],
            ]);
            $paymentIntent = $invoice->payment_intent ?? null;
            if (is_string($paymentIntent)) {
                $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntent);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Reusable upgrade checkout lookup skipped Stripe invoice: ' . $e->getMessage());
            continue;
        }

        $invoiceStatus = (string) ($invoice->status ?? '');
        if (in_array($invoiceStatus, ['paid', 'void', 'uncollectible'], true)) {
            continue;
        }

        $paymentIntentStatus = $paymentIntent ? (string) ($paymentIntent->status ?? '') : '';
        if (in_array($paymentIntentStatus, ['canceled', 'succeeded'], true)) {
            continue;
        }

        $clientSecret = checkoutPaymentClientSecret($invoice, $paymentIntent);
        if (!$clientSecret) {
            continue;
        }

        return [
            'client_secret' => $clientSecret,
            'amount' => (float) $candidate['amount'],
            'stripe_invoice_id' => $invoice->id ?? $invoiceId,
        ];
    }

    return null;
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
    if (empty($promotion['id']) && !empty($promotion['promotion_id'])) {
        $promotion['id'] = $promotion['promotion_id'];
    }

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
