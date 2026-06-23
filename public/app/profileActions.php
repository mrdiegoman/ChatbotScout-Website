<?php
/**
 * Profile Actions Handler
 * 
 * AJAX endpoint pre profile operations
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../../inc/stripePaymentFinalizer.php';

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

// ✅ Exceptions - actions ktoré NEVYŽADUJÚ prihlásenie
$publicActions = ['resend_verification'];

// Must be logged in (okrem public actions)
if (!in_array($action, $publicActions) && !$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $publicActions)) {
    validateCsrfToken();
}

function deleteLocalProfilePicture(?string $profilePictureUrl): void
{
    if (!$profilePictureUrl || strpos($profilePictureUrl, 'default-avatar') !== false) {
        return;
    }

    $path = parse_url($profilePictureUrl, PHP_URL_PATH) ?: $profilePictureUrl;
    $relativePath = ltrim(str_replace('\\', '/', $path), '/');

    if (!str_starts_with($relativePath, 'img/avatars/')) {
        return;
    }

    $avatarDir = realpath(__DIR__ . '/../img/avatars');
    $targetPath = realpath(__DIR__ . '/../' . $relativePath);

    if (!$avatarDir || !$targetPath || !is_file($targetPath)) {
        return;
    }

    if (strpos($targetPath, $avatarDir . DIRECTORY_SEPARATOR) !== 0) {
        return;
    }

    if (!unlink($targetPath)) {
        throw new RuntimeException('Failed to delete profile picture file');
    }
}

function profileReturnUrl(string $tab = 'profile'): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $publicPath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

    return $scheme . '://' . $host . $publicPath . '/profile.php?tab=' . rawurlencode($tab);
}

function profileSafeReturnUrl(?string $requestedUrl, string $fallbackTab = 'profile'): string
{
    $fallback = profileReturnUrl($fallbackTab);
    $requestedUrl = is_string($requestedUrl) ? trim($requestedUrl) : '';
    if ($requestedUrl === '') {
        return $fallback;
    }

    $parts = parse_url($requestedUrl);
    if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
        return $fallback;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strcasecmp($parts['host'], $host) !== 0 || strtolower($parts['scheme']) !== $scheme) {
        return $fallback;
    }

    // The public site can be exposed either through /public/... or through rewrite URLs such as
    // /checkout. Requiring the endpoint's physical /public path rejects a valid checkout return
    // URL and makes Stripe Billing Portal fall back to the profile page. Same-origin validation
    // above still prevents an external open redirect.

    return $requestedUrl;
}

function configureStripeForProfileActions(): void
{
    $stripeConfig = require __DIR__ . '/../../config/stripe.php';
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    if (!empty($stripeConfig['api_version'])) {
        \Stripe\Stripe::setApiVersion($stripeConfig['api_version']);
    }
}

function profileStripeAmountToCents(float $amount): int
{
    return (int) round($amount * 100);
}

function profileStripePromotionProductId(string $promotionId): string
{
    return 'cs_promo_' . substr(str_replace('-', '', strtolower($promotionId)), 0, 24);
}

function profileStripePromotionLabel(string $slotType): string
{
    return match ($slotType) {
        'Homepage' => 'Homepage Visibility',
        'Chatbots page' => 'Chatbots Page Visibility',
        default => $slotType ?: 'Promotion',
    };
}

function profileGetOrCreateStripePromotionProduct(array $promotion): string
{
    $promotionId = (string) ($promotion['promotion_id'] ?? $promotion['promotions_id'] ?? $promotion['id'] ?? '');
    if ($promotionId === '') {
        throw new RuntimeException('Promotion ID is missing for Stripe product.');
    }

    $productId = profileStripePromotionProductId($promotionId);
    $label = profileStripePromotionLabel((string) ($promotion['slot_type'] ?? $promotion['plan_name'] ?? ''));

    try {
        \Stripe\Product::retrieve($productId);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        \Stripe\Product::create([
            'id' => $productId,
            'name' => 'ChatbotScout - ' . $label,
            'metadata' => [
                'promotion_id' => $promotionId,
                'slot_type' => (string) ($promotion['slot_type'] ?? $promotion['plan_name'] ?? ''),
            ],
        ]);
    }

    return $productId;
}

function profileCreateStripePromotionPrice(array $promotion, float $amount, string $currency, string $interval, string $pricingMode): string
{
    $promotionId = (string) ($promotion['promotion_id'] ?? $promotion['promotions_id'] ?? $promotion['id'] ?? '');
    $productId = profileGetOrCreateStripePromotionProduct($promotion);

    $price = \Stripe\Price::create([
        'currency' => strtolower($currency),
        'product' => $productId,
        'unit_amount' => profileStripeAmountToCents($amount),
        'recurring' => ['interval' => $interval],
        'tax_behavior' => 'exclusive',
        'metadata' => [
            'promotion_id' => $promotionId,
            'slot_type' => (string) ($promotion['slot_type'] ?? $promotion['plan_name'] ?? ''),
            'pricing_mode' => $pricingMode,
        ],
    ]);

    return $price->id;
}

function profileFindStripeSubscriptionItemId($stripeSubscription, string $promotionId, string $subscriptionId): ?string
{
    $items = $stripeSubscription->items->data ?? [];
    foreach ($items as $item) {
        $metadata = $item->metadata ?? null;
        $priceMetadata = $item->price->metadata ?? null;
        $productId = stripeObjectId($item->price->product ?? null);

        $itemPromotionId = $metadata->promotion_id ?? ($priceMetadata->promotion_id ?? null);
        $itemSubscriptionId = $metadata->subscription_id ?? null;

        if ((string) $itemPromotionId === $promotionId || (string) $itemSubscriptionId === $subscriptionId) {
            return (string) $item->id;
        }

        if ($productId && $productId === profileStripePromotionProductId($promotionId)) {
            return (string) $item->id;
        }
    }

    return null;
}

function profilePromotionDisplayName(string $slotType): string
{
    return match ($slotType) {
        'Homepage' => 'Homepage Visibility',
        'Chatbots page' => 'Chatbots Page Visibility',
        default => $slotType ?: 'Promotion',
    };
}

function profileLoadPromotionSubscriptionGroup(PDO $db, string $userId, string $subscriptionId): array
{
    $stmt = $db->prepare("
        SELECT
            s.id,
            s.user_id,
            s.chatbot_id,
            s.promotions_id,
            s.status,
            s.start_date,
            s.end_date,
            s.cancel_at_period_end,
            s.auto_renew,
            s.stripe_subscription_id,
            s.stripe_customer_id,
            p.slot_type,
            p.price,
            p.discount_percentage,
            c.code AS currency_code,
            c.id AS currency_id,
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
          AND s.status IN ('active', 'trialing', 'past_due')
          AND s.subscription_type = 'promotion'
        LIMIT 1
    ");
    $stmt->execute([
        'subscription_id' => $subscriptionId,
        'user_id' => $userId,
    ]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selected || empty($selected['stripe_subscription_id'])) {
        return [];
    }

    $groupStmt = $db->prepare("
        SELECT
            s.id,
            s.user_id,
            s.chatbot_id,
            s.promotions_id,
            s.status,
            s.start_date,
            s.end_date,
            s.cancel_at_period_end,
            s.auto_renew,
            s.stripe_subscription_id,
            s.stripe_customer_id,
            p.slot_type,
            p.price,
            p.discount_percentage,
            c.code AS currency_code,
            c.id AS currency_id,
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
        WHERE s.user_id = :user_id
          AND s.chatbot_id = :chatbot_id
          AND s.stripe_subscription_id = :stripe_subscription_id
          AND s.status IN ('active', 'trialing', 'past_due')
          AND s.subscription_type = 'promotion'
        ORDER BY p.slot_type
    ");
    $groupStmt->execute([
        'user_id' => $userId,
        'chatbot_id' => $selected['chatbot_id'],
        'stripe_subscription_id' => $selected['stripe_subscription_id'],
    ]);

    $rows = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $planPeriod = promotionNormalizeBillingPeriod($row['plan_billing_period'] ?? 'monthly');
        $paymentPeriod = promotionNormalizeBillingPeriod($row['latest_payment_billing_period'] ?? '', '');
        $datePeriod = promotionBillingPeriodFromDates($row['start_date'] ?? null, $row['end_date'] ?? null, $planPeriod);
        $row['billing_period'] = $paymentPeriod
            ?: (in_array($datePeriod, ['monthly', 'yearly'], true) ? $datePeriod : $planPeriod);
        $row['display_plan_name'] = profilePromotionDisplayName((string) ($row['slot_type'] ?? ''));
        $row['standalone_amount'] = promotionSingleTotalPrice(
            (float) $row['price'],
            (float) ($row['discount_percentage'] ?? 0),
            $row['billing_period']
        );
        $row['bundle_amount'] = promotionBundleLineTotalPrice((float) $row['price'], $row['billing_period']);
    }
    unset($row);

    return $rows;
}

function profileSubscriptionGroupMetadata(array $rows, string $billingPeriod, bool $isBundle, array $extra = []): array
{
    $promotionIds = array_map(static fn ($row) => (string) $row['promotions_id'], $rows);
    $subscriptionIds = array_map(static fn ($row) => (string) $row['id'], $rows);
    $lineAmounts = array_map(static function ($row) use ($isBundle) {
        $amount = $isBundle ? (float) $row['bundle_amount'] : (float) $row['standalone_amount'];
        return number_format($amount, 2, '.', '');
    }, $rows);
    $totalAmount = array_sum(array_map('floatval', $lineAmounts));

    return array_merge([
        'user_id' => (string) ($rows[0]['user_id'] ?? ''),
        'chatbot_id' => (string) ($rows[0]['chatbot_id'] ?? ''),
        'promotion_ids' => implode(',', $promotionIds),
        'subscription_ids' => implode(',', $subscriptionIds),
        'line_amounts' => implode(',', $lineAmounts),
        'total_amount' => number_format($totalAmount, 2, '.', ''),
        'period_type' => $billingPeriod,
        'currency_id' => (string) ($rows[0]['currency_id'] ?? ''),
        'is_bundle' => $isBundle ? '1' : '0',
    ], $extra);
}

function profileStripeSubscriptionPhaseItems($stripeSubscription): array
{
    $items = [];
    foreach (($stripeSubscription->items->data ?? []) as $item) {
        $priceId = stripeObjectId($item->price ?? null);
        if (!$priceId) {
            continue;
        }

        $items[] = [
            'price' => $priceId,
            'quantity' => (int) ($item->quantity ?? 1),
        ];
    }

    return $items;
}

function profileStripeIntervalForBillingPeriod(string $billingPeriod): string
{
    return $billingPeriod === 'yearly' ? 'year' : 'month';
}

function profileCurrentPeriodEndTimestampForGroup(array $rows, $stripeSubscription): int
{
    $timestamps = [];
    $stripeEnd = stripeObjectValue($stripeSubscription, 'current_period_end');
    if (is_numeric($stripeEnd) && (int) $stripeEnd > 0) {
        $timestamps[] = (int) $stripeEnd;
    }

    foreach ($rows as $row) {
        $timestamp = strtotime((string) ($row['end_date'] ?? ''));
        if ($timestamp !== false && $timestamp > 0) {
            $timestamps[] = $timestamp;
        }
    }

    return max($timestamps ?: [time()]);
}

function profileCurrentPeriodEndDateForGroup(array $rows, $stripeSubscription): string
{
    return stripeUnixToMysql(profileCurrentPeriodEndTimestampForGroup($rows, $stripeSubscription));
}

function profileRowsById(array $rows): array
{
    $byId = [];
    foreach ($rows as $row) {
        $byId[(string) $row['id']] = $row;
    }
    return $byId;
}

function profileRowWillRenewAtPeriodEnd(array $row): bool
{
    return empty($row['cancel_at_period_end'])
        && (!array_key_exists('auto_renew', $row) || !empty($row['auto_renew']));
}

function profileApplyPartialCancelDbState(PDO $db, array $selectedRows, array $remainingRows, string $cancelType, ?string $reason, $stripeSubscription): void
{
    $selectedIds = array_map(static fn ($row) => (string) $row['id'], $selectedRows);
    $remainingRenewRows = array_values(array_filter($remainingRows, static fn ($row) => profileRowWillRenewAtPeriodEnd($row)));
    $remainingCancelRows = array_values(array_filter($remainingRows, static fn ($row) => !profileRowWillRenewAtPeriodEnd($row)));
    $periodEndDate = profileCurrentPeriodEndDateForGroup(array_merge($selectedRows, $remainingRows), $stripeSubscription);

    if (!empty($selectedIds)) {
        $placeholders = [];
        $params = [
            'reason' => $reason,
        ];
        foreach ($selectedIds as $index => $id) {
            $key = 'selected_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        if ($cancelType === 'immediately') {
            $sql = "
                UPDATE subscriptions
                SET status = 'cancelled',
                    auto_renew = FALSE,
                    cancel_at_period_end = FALSE,
                    cancelled_at = NOW(),
                    end_date = NOW(),
                    cancellation_reason = :reason,
                    updated_at = NOW()
                WHERE id IN (" . implode(',', $placeholders) . ")
            ";
        } else {
            $params['end_date'] = $periodEndDate;
            $sql = "
                UPDATE subscriptions
                SET auto_renew = FALSE,
                    cancel_at_period_end = TRUE,
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    end_date = :end_date,
                    cancellation_reason = :reason,
                    updated_at = NOW()
                WHERE id IN (" . implode(',', $placeholders) . ")
            ";
        }

        $db->prepare($sql)->execute($params);
    }

    if (!empty($remainingRenewRows)) {
        $placeholders = [];
        $params = [
            'end_date' => $periodEndDate,
        ];
        foreach ($remainingRenewRows as $index => $row) {
            $key = 'remaining_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (string) $row['id'];
        }

        $db->prepare("
            UPDATE subscriptions
            SET status = CASE WHEN status = 'cancelled' THEN 'active' ELSE status END,
                end_date = :end_date,
                auto_renew = TRUE,
                cancel_at_period_end = FALSE,
                cancelled_at = NULL,
                cancellation_reason = NULL,
                updated_at = NOW()
            WHERE id IN (" . implode(',', $placeholders) . ")
        ")->execute($params);
    }

    if (!empty($remainingCancelRows)) {
        $placeholders = [];
        $params = [
            'end_date' => $periodEndDate,
        ];
        foreach ($remainingCancelRows as $index => $row) {
            $key = 'remaining_cancel_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (string) $row['id'];
        }

        $db->prepare("
            UPDATE subscriptions
            SET status = CASE WHEN status = 'cancelled' THEN 'active' ELSE status END,
                end_date = :end_date,
                auto_renew = FALSE,
                cancel_at_period_end = TRUE,
                cancelled_at = COALESCE(cancelled_at, NOW()),
                updated_at = NOW()
            WHERE id IN (" . implode(',', $placeholders) . ")
        ")->execute($params);
    }
}

function profileUpdateStripeSubscriptionMetadata(string $stripeSubscriptionId, array $metadata): void
{
    \Stripe\Subscription::update($stripeSubscriptionId, [
        'metadata' => $metadata,
    ]);
}

function profileReleaseSubscriptionScheduleIfNeeded($stripeSubscription, string $logEvent)
{
    $stripeSubscriptionId = stripeObjectId($stripeSubscription);
    if (!$stripeSubscriptionId) {
        throw new RuntimeException('Stripe subscription ID is missing.');
    }

    $scheduleId = stripeObjectId(stripeObjectValue($stripeSubscription, 'schedule'));
    if (!$scheduleId) {
        return $stripeSubscription;
    }

    try {
        $schedule = \Stripe\SubscriptionSchedule::retrieve($scheduleId);
        $schedule->release();

        stripeCheckoutLog('partial_cancel.schedule_released', [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'schedule_id' => $scheduleId,
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        stripeCheckoutLog($logEvent, stripeCheckoutExceptionContext($e) + [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'schedule_id' => $scheduleId,
        ]);
        throw $e;
    }

    return \Stripe\Subscription::retrieve([
        'id' => $stripeSubscriptionId,
        'expand' => ['items.data.price.product'],
    ]);
}

function profileScheduleWholeGroupCancelAtPeriodEnd($stripeSubscription, array $groupRows, string $billingPeriod)
{
    $stripeSubscriptionId = stripeObjectId($stripeSubscription);
    if (!$stripeSubscriptionId) {
        throw new RuntimeException('Stripe subscription ID is missing.');
    }

    if (empty($groupRows)) {
        throw new RuntimeException('Subscription group is missing.');
    }

    $currentEnd = profileCurrentPeriodEndTimestampForGroup($groupRows, $stripeSubscription);
    if ($currentEnd <= time()) {
        throw new RuntimeException('Current billing period has already ended.');
    }

    $stripeSubscription = profileReleaseSubscriptionScheduleIfNeeded(
        $stripeSubscription,
        'partial_cancel.whole_group_schedule_release_failed'
    );

    $metadata = profileSubscriptionGroupMetadata(
        $groupRows,
        $billingPeriod,
        count($groupRows) > 1,
        [
            'partial_cancel_subscription_ids' => '',
            'partial_cancel_effective_at' => '',
        ]
    );

    $updatedSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
        'cancel_at_period_end' => true,
        'metadata' => $metadata,
        'expand' => ['items.data.price.product'],
    ]);

    stripeCheckoutLog('partial_cancel.whole_group_period_end_updated', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $groupRows)),
        'effective_at' => (string) $currentEnd,
    ]);

    return $updatedSubscription;
}

function profileSchedulePartialCancelAtPeriodEnd($stripeSubscription, array $selectedRows, array $remainingRows, string $billingPeriod): void
{
    $stripeSubscriptionId = stripeObjectId($stripeSubscription);
    if (!$stripeSubscriptionId) {
        throw new RuntimeException('Stripe subscription ID is missing.');
    }

    if (empty($selectedRows) || empty($remainingRows)) {
        throw new RuntimeException('Partial cancellation rows are incomplete.');
    }

    $stripeSubscription = profileReleaseSubscriptionScheduleIfNeeded(
        $stripeSubscription,
        'partial_cancel.schedule_release_failed'
    );

    $currentStart = (int) (stripeObjectValue($stripeSubscription, 'current_period_start') ?: time());
    $currentEnd = profileCurrentPeriodEndTimestampForGroup(array_merge($selectedRows, $remainingRows), $stripeSubscription);
    if ($currentEnd <= time()) {
        throw new RuntimeException('Current billing period has already ended.');
    }

    $currency = strtolower((string) ($remainingRows[0]['currency_code'] ?? 'eur'));
    $interval = profileStripeIntervalForBillingPeriod($billingPeriod);
    $remainingIsBundle = count($remainingRows) > 1;

    $futureItems = [];
    foreach ($remainingRows as $row) {
        $amount = $remainingIsBundle ? (float) $row['bundle_amount'] : (float) $row['standalone_amount'];
        $futureItems[] = [
            'price' => profileCreateStripePromotionPrice($row, $amount, $currency, $interval, $remainingIsBundle ? 'bundle' : 'single'),
            'quantity' => 1,
        ];
    }

    $pendingMetadata = profileSubscriptionGroupMetadata(
        array_merge($selectedRows, $remainingRows),
        $billingPeriod,
        true,
        [
            'partial_cancel_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $selectedRows)),
            'partial_cancel_effective_at' => (string) $currentEnd,
        ]
    );

    $futureMetadata = profileSubscriptionGroupMetadata(
        $remainingRows,
        $billingPeriod,
        $remainingIsBundle,
        [
            'partial_cancel_subscription_ids' => '',
            'partial_cancel_effective_at' => '',
        ]
    );

    $schedule = \Stripe\SubscriptionSchedule::create([
        'from_subscription' => $stripeSubscriptionId,
    ]);
    $scheduleId = (string) $schedule->id;

    $currentItems = profileStripeSubscriptionPhaseItems($stripeSubscription);
    if (empty($currentItems)) {
        throw new RuntimeException('Stripe subscription items are missing.');
    }

    \Stripe\SubscriptionSchedule::update($scheduleId, [
        'end_behavior' => 'release',
        'proration_behavior' => 'none',
        'phases' => [
            [
                'start_date' => $currentStart,
                'end_date' => $currentEnd,
                'items' => $currentItems,
                'metadata' => $pendingMetadata,
            ],
            [
                'start_date' => $currentEnd,
                'iterations' => 1,
                'items' => $futureItems,
                'metadata' => $futureMetadata,
                'proration_behavior' => 'none',
            ],
        ],
    ]);
    profileUpdateStripeSubscriptionMetadata($stripeSubscriptionId, $pendingMetadata);

    stripeCheckoutLog('partial_cancel.schedule_updated', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'schedule_id' => $scheduleId,
        'selected_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $selectedRows)),
        'remaining_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingRows)),
    ]);
}

function profileApplyPartialCancelImmediately($stripeSubscription, array $selectedRows, array $remainingRows, string $billingPeriod)
{
    $stripeSubscriptionId = stripeObjectId($stripeSubscription);
    if (!$stripeSubscriptionId) {
        throw new RuntimeException('Stripe subscription ID is missing.');
    }

    $stripeSubscription = profileReleaseSubscriptionScheduleIfNeeded(
        $stripeSubscription,
        'partial_cancel.schedule_release_failed'
    );

    $currency = strtolower((string) ($remainingRows[0]['currency_code'] ?? 'eur'));
    $interval = profileStripeIntervalForBillingPeriod($billingPeriod);
    $remainingIsBundle = count($remainingRows) > 1;
    $remainingRenewRows = array_values(array_filter($remainingRows, static fn ($row) => profileRowWillRenewAtPeriodEnd($row)));
    $remainingCancelRows = array_values(array_filter($remainingRows, static fn ($row) => !profileRowWillRenewAtPeriodEnd($row)));
    $items = [];

    foreach ($remainingRows as $row) {
        $itemId = profileFindStripeSubscriptionItemId($stripeSubscription, (string) $row['promotions_id'], (string) $row['id']);
        if (!$itemId) {
            throw new RuntimeException('Could not find remaining Stripe subscription item.');
        }

        $amount = $remainingIsBundle ? (float) $row['bundle_amount'] : (float) $row['standalone_amount'];
        $items[] = [
            'id' => $itemId,
            'price' => profileCreateStripePromotionPrice($row, $amount, $currency, $interval, $remainingIsBundle ? 'bundle' : 'single'),
            'quantity' => 1,
        ];
    }

    foreach ($selectedRows as $row) {
        $itemId = profileFindStripeSubscriptionItemId($stripeSubscription, (string) $row['promotions_id'], (string) $row['id']);
        if (!$itemId) {
            throw new RuntimeException('Could not find cancelled Stripe subscription item.');
        }

        $items[] = [
            'id' => $itemId,
            'deleted' => true,
        ];
    }

    $metadata = profileSubscriptionGroupMetadata(
        $remainingRows,
        $billingPeriod,
        $remainingIsBundle,
        [
            'partial_cancel_subscription_ids' => '',
            'partial_cancel_effective_at' => '',
        ]
    );

    $updatedSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
        'items' => $items,
        'metadata' => $metadata,
        'proration_behavior' => 'none',
        'payment_behavior' => 'allow_incomplete',
        'expand' => ['items.data.price.product'],
    ]);

    if (!empty($remainingCancelRows) && empty($remainingRenewRows)) {
        $updatedSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
            'cancel_at_period_end' => true,
            'expand' => ['items.data.price.product'],
        ]);
    } elseif (!empty($remainingCancelRows)) {
        profileSchedulePartialCancelAtPeriodEnd($updatedSubscription, $remainingCancelRows, $remainingRenewRows, $billingPeriod);
        $updatedSubscription = \Stripe\Subscription::retrieve([
            'id' => $stripeSubscriptionId,
            'expand' => ['items.data.price.product'],
        ]);
    } elseif (!empty($updatedSubscription->cancel_at_period_end)) {
        $updatedSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
            'cancel_at_period_end' => false,
            'expand' => ['items.data.price.product'],
        ]);
    }

    stripeCheckoutLog('partial_cancel.immediate_updated', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'selected_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $selectedRows)),
        'remaining_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingRows)),
        'remaining_cancel_at_period_end_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingCancelRows)),
    ]);

    return $updatedSubscription;
}

// ============================================
// RESEND VERIFICATION EMAIL (na začiatok - public action)
// ============================================

if ($action === 'resend_verification') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = $data['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }
    
    // ✅ 60-second timer check (session-based)
    $lastSentKey = 'last_verification_sent_' . md5($email);
    $lastSentTime = $_SESSION[$lastSentKey] ?? 0;
    $timeSinceLastSend = time() - $lastSentTime;
    
    if ($timeSinceLastSend < 60) {
        $remainingSeconds = 60 - $timeSinceLastSend;
        echo json_encode([
            'success' => false, 
            'message' => "Please wait {$remainingSeconds} more seconds before resending.",
            'remainingSeconds' => $remainingSeconds
        ]);
        exit;
    }
    
    // ✅ Rate limiting check
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$rateLimiter->check('resend_verification', $clientIp)) {
        $remaining = $rateLimiter->getRemainingLockoutTime('resend_verification', $clientIp);
        if ($remaining >= 60) {
            $minutes = ceil($remaining / 60);
            echo json_encode([
                'success' => false, 
                'message' => "Too many requests. Please try again in {$minutes} minutes."
            ]);
        }
        elseif ($remaining > 0) {
                echo json_encode([
                'success' => false, 
                'message' => "Too many requests. Please try again in {$remaining} seconds."
            ]);
        }
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Skontroluj user
    $stmt = $db->prepare("
        SELECT id, is_email_verified, first_name, last_name 
        FROM users 
        WHERE email = :email
    ");
    $stmt->execute(['email' => $email]);
    $userCheck = $stmt->fetch();
    
    if (!$userCheck) {
        // Security: Vráť success aj keď user neexistuje
        $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
        echo json_encode(['success' => true, 'message' => 'If email exists, verification link was sent']);
        exit;
    }
    
    if ($userCheck['is_email_verified']) {
        // ✅ Record failed attempt
        $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
        echo json_encode(['success' => false, 'message' => 'Email is already verified']);
        exit;
    }
    
    // Generuj nový token
    $appConfig = require __DIR__ . '/../../config/app.php';
    $newToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + $appConfig['email_verification']['token_lifetime']);
    
    // Ulož do DB
    $stmt = $db->prepare("
        UPDATE users 
        SET email_verification_token = :token,
            email_verification_expires_at = :expires
        WHERE id = :id
    ");
    $stmt->execute([
        'token' => $newToken,
        'expires' => $expiresAt,
        'id' => $userCheck['id']
    ]);
    
    // Odošli cez Email class
    $emailService = new Email();
    $fullName = $userCheck['first_name'] . ' ' . $userCheck['last_name'];
    $emailService->sendVerification($email, $fullName, $newToken);
    $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
    
    // ✅ Ulož timestamp do session (pre 60-sec timer)
    $_SESSION[$lastSentKey] = time();
    
    // ✅ Reset rate limiter (úspešné odoslanie)
    // $rateLimiter->reset('resend_verification', $clientIp);
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification email has been resent successfully'
    ]);
    
    exit;
}

// ============================================
// UPLOAD AVATAR
// ============================================

if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
        exit;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
    $uploadDir = __DIR__ . '/../img/avatars/';
    $uploadPath = $uploadDir . $filename;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    try {
        $image = null;
        switch ($file['type']) {
            case 'image/jpeg': $image = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png': $image = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $image = imagecreatefromwebp($file['tmp_name']); break;
        }
        
        if ($image === false) throw new Exception('Failed to process image');
        
        $width = imagesx($image);
        $height = imagesy($image);
        $size = 400;
        $thumb = imagecreatetruecolor($size, $size);
        
        if ($file['type'] === 'image/png' || $file['type'] === 'image/webp') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        
        $minDimension = min($width, $height);
        $srcX = ($width - $minDimension) / 2;
        $srcY = ($height - $minDimension) / 2;
        
        imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $size, $size, $minDimension, $minDimension);
        
        switch ($file['type']) {
            case 'image/jpeg': imagejpeg($thumb, $uploadPath, 90); break;
            case 'image/png': imagepng($thumb, $uploadPath, 9); break;
            case 'image/webp': imagewebp($thumb, $uploadPath, 90); break;
        }
        
        imagedestroy($image);
        imagedestroy($thumb);
        
        deleteLocalProfilePicture($user['profile_picture_url'] ?? null);
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET profile_picture_url = :url WHERE id = :id");
        $newUrl = 'img/avatars/' . $filename;
        $stmt->execute(['url' => $newUrl, 'id' => $user['id']]);
        
        $_SESSION['user']['profile_picture_url'] = $newUrl;

        AuditLog::log('user', $user['id'], 'updated',
            ['profile_picture_url' => $user['profile_picture_url']],
            ['profile_picture_url' => $newUrl]
        );

        echo json_encode(['success' => true, 'url' => $newUrl]);
        
    } catch (Exception $e) {
        error_log('upload_avatar error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Profile picture could not be updated. Please try again.']);
    }
    
    exit;
}

// ============================================
// UPDATE NAME
// ============================================

if ($action === 'update_name') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    
    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['success' => false, 'message' => 'Both names are required']);
        exit;
    }
    
    if (mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50) {
        echo json_encode(['success' => false, 'message' => 'Names too long (max 50 characters)']);
        exit;
    }
    
    // if (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2) {
    //     echo json_encode(['success' => false, 'message' => 'Names too short (min 2 characters)']);
    //     exit;
    // }
    
    $firstName = mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8');
    $lastName = mb_convert_case($lastName, MB_CASE_TITLE, 'UTF-8');
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE users SET first_name = :first, last_name = :last WHERE id = :id");
    $stmt->execute(['first' => $firstName, 'last' => $lastName, 'id' => $user['id']]);
    
    $_SESSION['user']['first_name'] = $firstName;
    $_SESSION['user']['last_name'] = $lastName;

    AuditLog::log('user', $user['id'], 'updated',
        ['first_name' => $user['first_name'], 'last_name' => $user['last_name']],
        ['first_name' => $firstName, 'last_name' => $lastName]
    );

    echo json_encode(['success' => true, 'firstName' => $firstName, 'lastName' => $lastName]);
    exit;
}

// ============================================
// CHANGE PASSWORD
// ============================================

if ($action === 'change_password') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $data['currentPassword'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $passwordErrors = PasswordPolicy::validate($newPassword);
    if ($passwordErrors) {
        echo json_encode(['success' => false, 'message' => implode(' ', $passwordErrors)]);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute(['id' => $user['id']]);
    $userData = $stmt->fetch();
    
    if (!password_verify($currentPassword, $userData['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $stmt->execute(['hash' => $newHash, 'id' => $user['id']]);

    AuditLog::log('user', $user['id'], 'updated',
        ['password' => '***'],
        ['password' => '***']
    );

    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// UPDATE MARKETING EMAILS
// ============================================

if ($action === 'update_marketing_emails') {
    $data = json_decode(file_get_contents('php://input'), true);
    $enabled = $data['enabled'] ?? false;
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("UPDATE users SET marketing_emails = :enabled WHERE id = :id");
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $user['id']]);
        $_SESSION['user']['marketing_emails'] = $enabled;
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
    
    exit;
}

// ============================================
// STRIPE CUSTOMER PORTAL
// ============================================

if ($action === 'stripe_customer_portal') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');
    $returnUrl = profileSafeReturnUrl($data['return_url'] ?? null, 'profile');

    stripeCheckoutLog('customer_portal.request', [
        'user_id' => $user['id'] ?? null,
        'subscription_id' => $subscriptionId,
        'return_url' => $returnUrl,
    ]);

    $db = Database::getInstance()->getConnection();
    if ($subscriptionId) {
        $stmt = $db->prepare("
            SELECT stripe_customer_id, stripe_subscription_id
            FROM subscriptions
            WHERE id = :subscription_id
              AND user_id = :user_id
              AND status IN ('active', 'trialing', 'past_due')
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'user_id' => $user['id'],
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT stripe_customer_id, stripe_subscription_id
            FROM subscriptions
            WHERE user_id = :user_id
              AND status IN ('active', 'trialing', 'past_due')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $user['id']]);
    }
    $sub = $stmt->fetch();

    if (!$sub || empty($sub['stripe_customer_id'])) {
        $userCustomerStmt = $db->prepare("
            SELECT stripe_customer_id
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $userCustomerStmt->execute(['user_id' => $user['id']]);
        $stripeCustomerId = (string) ($userCustomerStmt->fetchColumn() ?: '');
        if ($stripeCustomerId !== '') {
            $sub = [
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_subscription_id' => null,
            ];
        }
    }
    
    if (!$sub || !$sub['stripe_customer_id']) {
        echo json_encode(['success' => false, 'message' => 'Payment account was not found.']);
    } else {
        try {
            configureStripeForProfileActions();

            $portalSession = \Stripe\BillingPortal\Session::create([
                'customer' => $sub['stripe_customer_id'],
                'return_url' => $returnUrl,
            ]);

            stripeCheckoutLog('customer_portal.response', [
                'user_id' => $user['id'] ?? null,
                'subscription_id' => $subscriptionId,
                'stripe_customer_id' => $sub['stripe_customer_id'],
                'stripe_subscription_id' => $sub['stripe_subscription_id'],
            ]);

            echo json_encode(['success' => true, 'url' => $portalSession->url]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe customer portal error: ' . $e->getMessage());
            stripeCheckoutLog('customer_portal.stripe_error', stripeCheckoutExceptionContext($e));
            echo json_encode([
                'success' => false,
                'message' => 'Payment settings could not be opened right now. Please try again later.',
            ]);
        }
    }
    
    exit;
}

// ============================================
// RENEW SUBSCRIPTION
// ============================================

if ($action === 'renew_subscription') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');

    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Missing subscription']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $groupRows = profileLoadPromotionSubscriptionGroup($db, $user['id'], $subscriptionId);
    if (empty($groupRows)) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
        exit;
    }

    $sub = null;
    foreach ($groupRows as $row) {
        if ((string) $row['id'] === $subscriptionId) {
            $sub = $row;
            break;
        }
    }

    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
        exit;
    }

    if (empty($sub['stripe_subscription_id'])) {
        echo json_encode(['success' => false, 'message' => 'Subscription record was not found.']);
        exit;
    }

    try {
        configureStripeForProfileActions();

        $stripeSubscriptionId = (string) $sub['stripe_subscription_id'];
        $stripeSubscription = \Stripe\Subscription::retrieve([
            'id' => $stripeSubscriptionId,
            'expand' => ['items.data.price.product'],
        ]);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');

        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            syncStripeSubscriptionStatus($db, $stripeSubscription);
            echo json_encode(['success' => false, 'message' => 'This subscription has already ended and cannot be renewed.']);
            exit;
        }

        $stripeCancelsAtPeriodEnd = !empty($stripeSubscription->cancel_at_period_end);
        if (profileRowWillRenewAtPeriodEnd($sub) && !$stripeCancelsAtPeriodEnd) {
            syncStripeSubscriptionStatus($db, $stripeSubscription);
            echo json_encode(['success' => true, 'message' => 'Subscription is already renewed.']);
            exit;
        }

        $billingPeriod = (string) ($sub['billing_period'] ?? $groupRows[0]['billing_period'] ?? 'monthly');
        $renewRows = [];
        $cancelRows = [];

        foreach ($groupRows as $row) {
            if ((string) $row['id'] === $subscriptionId || profileRowWillRenewAtPeriodEnd($row)) {
                $row['cancel_at_period_end'] = false;
                $row['auto_renew'] = true;
                $renewRows[] = $row;
            } else {
                $cancelRows[] = $row;
            }
        }

        if (empty($renewRows)) {
            echo json_encode(['success' => false, 'message' => 'No promotion could be renewed.']);
            exit;
        }

        if (empty($cancelRows)) {
            $stripeSubscription = profileReleaseSubscriptionScheduleIfNeeded(
                $stripeSubscription,
                'partial_cancel.renew_schedule_release_failed'
            );
            $stripeSubscription = \Stripe\Subscription::update($sub['stripe_subscription_id'], [
                'cancel_at_period_end' => false,
                'metadata' => profileSubscriptionGroupMetadata($renewRows, $billingPeriod, count($renewRows) > 1, [
                    'partial_cancel_subscription_ids' => '',
                    'partial_cancel_effective_at' => '',
                ]),
                'expand' => ['items.data.price.product'],
            ]);
        } else {
            if ($stripeCancelsAtPeriodEnd) {
                $stripeSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
                    'cancel_at_period_end' => false,
                    'expand' => ['items.data.price.product'],
                ]);
            }

            profileSchedulePartialCancelAtPeriodEnd($stripeSubscription, $cancelRows, $renewRows, $billingPeriod);
            $stripeSubscription = \Stripe\Subscription::retrieve([
                'id' => $stripeSubscriptionId,
                'expand' => ['items.data.price.product'],
            ]);
        }

        syncStripeSubscriptionStatus($db, $stripeSubscription);

        stripeCheckoutLog('partial_cancel.renewed', [
            'user_id' => $user['id'] ?? null,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'renewed_subscription_id' => $subscriptionId,
            'renew_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $renewRows)),
            'remaining_cancel_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $cancelRows)),
        ]);

        AuditLog::log('subscription', $sub['id'], 'updated',
            ['cancel_at_period_end' => true],
            ['cancel_at_period_end' => false, 'auto_renew' => true]
        );

        echo json_encode(['success' => true, 'message' => 'Promotion renewed.']);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe renew subscription error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'This promotion could not be renewed right now. Please try again later.',
        ]);
    } catch (Exception $e) {
        error_log('Renew subscription error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'This promotion could not be renewed right now. Please try again later.',
        ]);
    }

    exit;
}

// ============================================
// CANCEL SUBSCRIPTION
// ============================================

if ($action === 'cancel_subscription') {
    $data = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');
    $cancelType = $data['cancelType'] ?? 'at-period-end';
    $reason = $data['reason'] ?? null;
    $cancelItemIds = $data['cancel_item_ids'] ?? $data['cancelItemIds'] ?? [];

    if (!in_array($cancelType, ['at-period-end', 'immediately'], true)) {
        $cancelType = 'at-period-end';
    }

    if (!is_array($cancelItemIds)) {
        $cancelItemIds = [];
    }
    $cancelItemIds = array_values(array_unique(array_filter(array_map(static function ($id) {
        return is_string($id) ? trim($id) : '';
    }, $cancelItemIds))));

    $reason = is_string($reason) ? trim($reason) : null;
    if ($reason === '') {
        $reason = null;
    }

    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Missing subscription']);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    $groupRows = profileLoadPromotionSubscriptionGroup($db, $user['id'], $subscriptionId);
    $rowsById = profileRowsById($groupRows);
    $sub = $rowsById[$subscriptionId] ?? ($groupRows[0] ?? null);
    
    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'No active subscription found']);
        exit;
    }

    if (empty($sub['stripe_subscription_id'])) {
        echo json_encode(['success' => false, 'message' => 'Subscription record was not found.']);
        exit;
    }

    if (empty($cancelItemIds)) {
        $cancelItemIds = [$subscriptionId];
    }

    $cancelItemIds = array_values(array_filter($cancelItemIds, static fn ($id) => isset($rowsById[$id])));
    if (empty($cancelItemIds)) {
        echo json_encode(['success' => false, 'message' => 'Choose at least one promotion to cancel.']);
        exit;
    }

    $selectedRows = array_values(array_map(static fn ($id) => $rowsById[$id], $cancelItemIds));
    $remainingRows = array_values(array_filter($groupRows, static fn ($row) => !in_array((string) $row['id'], $cancelItemIds, true)));
    $isPartialCancel = count($groupRows) > 1 && !empty($remainingRows);

    try {
        configureStripeForProfileActions();

        $stripeSubscription = \Stripe\Subscription::retrieve([
            'id' => $sub['stripe_subscription_id'],
            'expand' => ['items.data.price.product'],
        ]);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');

        if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
            syncStripeSubscriptionStatus($db, $stripeSubscription);

            $cancelledPlaceholders = [];
            $cancelledParams = [
                'reason' => $reason,
                'user_id' => $user['id'],
            ];
            foreach ($selectedRows as $index => $row) {
                $key = 'cancelled_id_' . $index;
                $cancelledPlaceholders[] = ':' . $key;
                $cancelledParams[$key] = (string) $row['id'];
            }

            $db->prepare("
                UPDATE subscriptions
                SET status = 'cancelled',
                    auto_renew = FALSE,
                    cancel_at_period_end = FALSE,
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancellation_reason = COALESCE(:reason, cancellation_reason),
                    updated_at = NOW()
                WHERE id IN (" . implode(',', $cancelledPlaceholders) . ")
                  AND user_id = :user_id
            ")->execute($cancelledParams);

            syncChatbotSponsoredTag($db, (string) $sub['chatbot_id']);

            echo json_encode(['success' => true, 'message' => 'Subscription was already cancelled.']);
            exit;
        }

        if ($isPartialCancel) {
            $billingPeriod = (string) ($sub['billing_period'] ?? 'monthly');
            $remainingRenewRows = array_values(array_filter($remainingRows, static fn ($row) => profileRowWillRenewAtPeriodEnd($row)));
            $remainingCancelRows = array_values(array_filter($remainingRows, static fn ($row) => !profileRowWillRenewAtPeriodEnd($row)));
            stripeCheckoutLog('partial_cancel.start', [
                'user_id' => $user['id'] ?? null,
                'stripe_subscription_id' => $sub['stripe_subscription_id'],
                'cancel_type' => $cancelType,
                'selected_subscription_ids' => implode(',', $cancelItemIds),
                'remaining_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingRows)),
                'remaining_renew_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingRenewRows)),
                'remaining_cancel_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingCancelRows)),
            ]);

            if ($cancelType === 'immediately') {
                $stripeSubscription = profileApplyPartialCancelImmediately($stripeSubscription, $selectedRows, $remainingRows, $billingPeriod);
                syncStripeSubscriptionStatus($db, $stripeSubscription);
            } else {
                $alreadyScheduled = !empty($selectedRows) && count(array_filter(
                    $selectedRows,
                    static fn ($row) => !empty($row['cancel_at_period_end'])
                )) === count($selectedRows);

                if ($alreadyScheduled) {
                    stripeCheckoutLog('partial_cancel.already_scheduled_repair', [
                        'user_id' => $user['id'] ?? null,
                        'stripe_subscription_id' => $sub['stripe_subscription_id'],
                        'selected_subscription_ids' => implode(',', $cancelItemIds),
                    ]);
                }

                if (empty($remainingRenewRows)) {
                    $stripeSubscription = profileScheduleWholeGroupCancelAtPeriodEnd($stripeSubscription, $groupRows, $billingPeriod);
                    syncStripeSubscriptionStatus($db, $stripeSubscription);
                } else {
                    $periodEndCancelRows = array_merge($selectedRows, $remainingCancelRows);
                    profileSchedulePartialCancelAtPeriodEnd($stripeSubscription, $periodEndCancelRows, $remainingRenewRows, $billingPeriod);
                }
            }

            profileApplyPartialCancelDbState($db, $selectedRows, $remainingRows, $cancelType, $reason, $stripeSubscription);
            syncChatbotSponsoredTag($db, (string) $sub['chatbot_id']);

            AuditLog::log('subscription', $sub['id'], 'updated',
                ['status' => 'active', 'cancel_at_period_end' => false],
                [
                    'partial_cancel' => true,
                    'cancel_type' => $cancelType,
                    'cancel_item_ids' => $cancelItemIds,
                    'remaining_item_ids' => array_map(static fn ($row) => (string) $row['id'], $remainingRows),
                ]
            );

            $remainingNoRenewCount = count(array_filter(
                $remainingRows,
                static fn ($row) => !profileRowWillRenewAtPeriodEnd($row)
            ));
            $allRemainingCancelAtPeriodEnd = !empty($remainingRows)
                && $remainingNoRenewCount === count($remainingRows);
            if ($cancelType === 'immediately') {
                $partialCancelMessage = $allRemainingCancelAtPeriodEnd
                    ? 'Selected promotion cancelled. Remaining promotion will still end after this billing period.'
                    : 'Selected promotion cancelled. Remaining promotion will continue at its standalone price.';
            } else {
                $partialCancelMessage = $allRemainingCancelAtPeriodEnd
                    ? 'Selected promotion will end after this billing period. All promotions for this chatbot will end then.'
                    : 'Selected promotion will end after this billing period. Remaining promotion will renew at its standalone price.';
            }

            echo json_encode([
                'success' => true,
                'message' => $partialCancelMessage,
            ]);
            exit;
        }

        if (!empty($sub['cancel_at_period_end']) && $cancelType !== 'immediately' && !empty($stripeSubscription->cancel_at_period_end)) {
            echo json_encode(['success' => true, 'message' => 'Subscription is already scheduled for cancellation.']);
            exit;
        }

        $nonPartialBillingPeriod = (string) ($selectedRows[0]['billing_period'] ?? $sub['billing_period'] ?? 'monthly');
        profileUpdateStripeSubscriptionMetadata(
            $sub['stripe_subscription_id'],
            profileSubscriptionGroupMetadata($selectedRows, $nonPartialBillingPeriod, count($selectedRows) > 1, [
                'partial_cancel_subscription_ids' => '',
                'partial_cancel_effective_at' => '',
            ])
        );

        if ($cancelType === 'immediately') {
            $stripeSubscription = $stripeSubscription->cancel();
        } else {
            if (!empty($stripeSubscription->cancel_at_period_end)) {
                syncStripeSubscriptionStatus($db, $stripeSubscription);
                echo json_encode(['success' => true, 'message' => 'Subscription is already scheduled for cancellation.']);
                exit;
            }

            $stripeSubscription = \Stripe\Subscription::update($sub['stripe_subscription_id'], [
                'cancel_at_period_end' => true,
            ]);
        }

        syncStripeSubscriptionStatus($db, $stripeSubscription);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe cancel subscription error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'This subscription could not be cancelled right now. Please try again later.',
        ]);
        exit;
    } catch (Exception $e) {
        error_log('Cancel subscription error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'This subscription could not be cancelled right now. Please try again later.',
        ]);
        exit;
    }
    
    $targetCancelPlaceholders = [];
    $targetCancelParams = [
        'reason' => $reason,
        'user_id' => $user['id'],
    ];
    foreach ($selectedRows as $index => $row) {
        $key = 'target_id_' . $index;
        $targetCancelPlaceholders[] = ':' . $key;
        $targetCancelParams[$key] = (string) $row['id'];
    }

    if ($cancelType === 'immediately') {
        $stmt = $db->prepare("
            UPDATE subscriptions
            SET status = 'cancelled',
                auto_renew = FALSE,
                cancelled_at = NOW(),
                end_date = NOW(),
                cancellation_reason = :reason
            WHERE id IN (" . implode(',', $targetCancelPlaceholders) . ")
              AND user_id = :user_id
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE subscriptions
            SET cancel_at_period_end = TRUE,
                auto_renew = FALSE,
                cancelled_at = NOW(),
                cancellation_reason = :reason
            WHERE id IN (" . implode(',', $targetCancelPlaceholders) . ")
              AND user_id = :user_id
        ");
    }
    
    $stmt->execute($targetCancelParams);
    syncChatbotSponsoredTag($db, (string) $sub['chatbot_id']);

    AuditLog::log('subscription', $sub['id'], 'updated',
        ['status' => 'active', 'cancel_at_period_end' => false],
        ['status' => $cancelType === 'immediately' ? 'cancelled' : 'active',
         'cancel_at_period_end' => $cancelType !== 'immediately',
         'cancellation_reason' => $reason]
    );

    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// SAVE CANCELLATION FEEDBACK
// ============================================

if ($action === 'save_cancellation_feedback') {
    $data = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = trim($data['subscription_id'] ?? $data['subscriptionId'] ?? '');
    $reason = $data['reason'] ?? null;
    $cancelItemIds = $data['cancel_item_ids'] ?? $data['cancelItemIds'] ?? [];
    $reason = is_string($reason) ? trim($reason) : '';

    if (!is_array($cancelItemIds)) {
        $cancelItemIds = [];
    }
    $cancelItemIds = array_values(array_unique(array_filter(array_map(static function ($id) {
        return is_string($id) ? trim($id) : '';
    }, $cancelItemIds))));

    if (!$subscriptionId || $reason === '') {
        echo json_encode(['success' => false, 'message' => 'Missing cancellation feedback']);
        exit;
    }

    if (mb_strlen($reason) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Cancellation feedback is too long']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, stripe_subscription_id, cancellation_reason
        FROM subscriptions
        WHERE id = :subscription_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        'subscription_id' => $subscriptionId,
        'user_id' => $user['id'],
    ]);
    $sub = $stmt->fetch();

    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'Subscription was not found']);
        exit;
    }

    if (!empty($cancelItemIds)) {
        $placeholders = [];
        $params = [
            'reason' => $reason,
            'user_id' => $user['id'],
        ];
        foreach ($cancelItemIds as $index => $id) {
            $key = 'cancel_item_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stmt = $db->prepare("
            UPDATE subscriptions
            SET cancellation_reason = :reason,
                updated_at = NOW()
            WHERE id IN (" . implode(',', $placeholders) . ")
              AND user_id = :user_id
        ");
        $stmt->execute($params);
    } elseif (!empty($sub['stripe_subscription_id'])) {
        $stmt = $db->prepare("
            UPDATE subscriptions
            SET cancellation_reason = :reason,
                updated_at = NOW()
            WHERE stripe_subscription_id = :stripe_subscription_id
              AND user_id = :user_id
        ");
        $stmt->execute([
            'reason' => $reason,
            'stripe_subscription_id' => $sub['stripe_subscription_id'],
            'user_id' => $user['id'],
        ]);
    } else {
        $stmt = $db->prepare("
            UPDATE subscriptions
            SET cancellation_reason = :reason,
                updated_at = NOW()
            WHERE id = :subscription_id
              AND user_id = :user_id
        ");
        $stmt->execute([
            'reason' => $reason,
            'subscription_id' => $subscriptionId,
            'user_id' => $user['id'],
        ]);
    }

    AuditLog::log('subscription', $sub['id'], 'updated',
        ['cancellation_reason' => $sub['cancellation_reason'] ?? null],
        ['cancellation_reason' => $reason]
    );

    echo json_encode(['success' => true, 'message' => 'Cancellation feedback saved']);
    exit;
}

// ============================================
// DELETE ACCOUNT — GDPR compliant
// Anonymizuje osobné údaje namiesto soft delete.
// Zachová len účtovné záznamy (payments) pre právne účely.
// ============================================

if ($action === 'delete_account') {
    $db = Database::getInstance()->getConnection();
 
    try {
        $userId    = $user['id'];
        $anonymousEmail = 'deleted_' . $userId . '@deleted.invalid';

        $activeSubscriptionStmt = $db->prepare("
            SELECT COUNT(*)
            FROM subscriptions
            WHERE user_id = :user_id
              AND subscription_type = 'promotion'
              AND (
                  status = 'pending'
                  OR (
                      status IN ('active', 'trialing', 'past_due')
                      AND end_date >= NOW()
                  )
              )
        ");
        $activeSubscriptionStmt->execute(['user_id' => $userId]);
        if ((int) $activeSubscriptionStmt->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cancel active promotions before deleting your account.',
            ]);
            exit;
        }

        $db->beginTransaction();

        $pictureStmt = $db->prepare("
            SELECT profile_picture_url
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $pictureStmt->execute(['id' => $userId]);
        $profilePictureUrl = $pictureStmt->fetchColumn();

        deleteLocalProfilePicture($profilePictureUrl ?: null);
 
        // 1. Anonymizuj osobné údaje v users tabuľke
        $db->prepare("
            UPDATE users SET
                email                          = :email,
                password_hash                  = '',
                google_id                      = NULL,
                first_name                     = 'Deleted',
                last_name                      = 'User',
                profile_picture_url            = NULL,
                bio                            = NULL,
                website_url                    = NULL,
                marketing_emails               = 0,
                email_verification_token       = NULL,
                email_verification_expires_at  = NULL,
                password_reset_token           = NULL,
                password_reset_expires_at      = NULL,
                status                         = 'disabled',
                disabled_at                    = NOW()
            WHERE id = :id
        ")->execute(['email' => $anonymousEmail, 'id' => $userId]);
 
        // 2. Mark any inactive/ended local subscriptions as cancelled for account cleanup.
        $db->prepare("
            UPDATE subscriptions
            SET status              = 'cancelled',
                cancelled_at        = NOW(),
                end_date            = NOW(),
                cancellation_reason = 'Account deleted by user'
            WHERE user_id = :user_id
              AND status IN ('active', 'trialing', 'past_due', 'pending')
        ")->execute(['user_id' => $userId]);
 
        // 3. Vymaž visitor_id cookie a visitor_user_links (tracking dáta)
        $db->prepare("
            DELETE FROM visitor_user_links WHERE user_id = :user_id
        ")->execute(['user_id' => $userId]);
 
        // 4. Anonymizuj IP adresu v contact_messages (osobný údaj)
        $db->prepare("
            UPDATE contact_messages
            SET ip_address = NULL,
                first_name = 'Deleted',
                last_name  = 'User'
            WHERE user_id = :user_id
        ")->execute(['user_id' => $userId]);
 
        // 5. Anonymizuj IP v audit_log pre tohto usera
        $db->prepare("
            UPDATE audit_log
            SET ip_address = NULL,
                user_agent = NULL
            WHERE user_id = :user_id
        ")->execute(['user_id' => $userId]);
 
        $db->commit();
 
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('delete_account error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Deletion failed. Please try again.']);
        exit;
    }
 
    // Vymaž visitor_id cookie z browsera
    $appConfig = require __DIR__ . '/../../config/app.php';
    setcookie($appConfig['visitor']['cookie_name'], '', time() - 3600, '/');
    setcookie('cookie_consent', '', time() - 3600, '/');
 
    logout();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
