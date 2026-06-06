<?php

require_once __DIR__ . '/stripeCheckoutLogger.php';

function stripeObjectValue($object, string $key, $default = null) {
    if (is_array($object)) {
        return $object[$key] ?? $default;
    }

    if (is_object($object) && isset($object->{$key})) {
        return $object->{$key};
    }

    return $default;
}

function stripePaymentMetadataValue($metadata, string $key, $default = null) {
    return stripeObjectValue($metadata, $key, $default);
}

function stripePaymentIntentValue($paymentIntent, string $key, $default = null) {
    return stripeObjectValue($paymentIntent, $key, $default);
}

function stripeMetadataCsv($metadata, string $key): array {
    $value = (string) stripePaymentMetadataValue($metadata, $key, '');
    if ($value === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value)), static function ($item) {
        return $item !== '';
    }));
}

function stripeObjectId($value): ?string {
    if (is_string($value) && $value !== '') {
        return $value;
    }

    if (is_object($value) && isset($value->id)) {
        return (string) $value->id;
    }

    return null;
}

function stripeUnixToMysql($timestamp, ?string $fallback = null): string {
    $timestamp = is_numeric($timestamp) ? (int) $timestamp : 0;
    if ($timestamp <= 0) {
        return $fallback ?: date('Y-m-d H:i:s');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function stripeLocalSubscriptionStatus(?string $stripeStatus): string {
    return match ($stripeStatus) {
        'active' => 'active',
        'trialing' => 'trialing',
        'past_due', 'unpaid' => 'past_due',
        'canceled', 'incomplete_expired', 'paused' => 'cancelled',
        default => 'pending',
    };
}

function stripeInvoiceSubscriptionId($invoice): ?string {
    $subscription = stripeObjectValue($invoice, 'subscription');
    if ($subscription) {
        return stripeObjectId($subscription);
    }

    $parent = stripeObjectValue($invoice, 'parent');
    $subscriptionDetails = stripeObjectValue($parent, 'subscription_details');
    $parentSubscription = stripeObjectValue($subscriptionDetails, 'subscription');

    return stripeObjectId($parentSubscription);
}

function stripeInvoicePublicUrl($invoice): ?string {
    $hostedUrl = stripeObjectValue($invoice, 'hosted_invoice_url');
    if ($hostedUrl) {
        return (string) $hostedUrl;
    }

    $pdfUrl = stripeObjectValue($invoice, 'invoice_pdf');
    return $pdfUrl ? (string) $pdfUrl : null;
}

function stripeInvoicePaymentIntentId($invoice): ?string {
    $paymentIntent = stripeObjectValue($invoice, 'payment_intent');
    if ($paymentIntent) {
        return stripeObjectId($paymentIntent);
    }

    return null;
}

function stripePaymentIntentChargeId(?string $paymentIntentId): ?string {
    if (!$paymentIntentId) {
        return null;
    }

    try {
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        $chargeId = stripePaymentIntentValue($paymentIntent, 'latest_charge');
        return $chargeId ? (string) $chargeId : null;
    } catch (Exception $e) {
        error_log('Stripe: Could not retrieve payment intent charge: ' . $e->getMessage());
        return null;
    }
}

function stripeSubscriptionMetadata($stripeSubscription, $invoice) {
    $metadata = stripeObjectValue($stripeSubscription, 'metadata');
    if ($metadata) {
        return $metadata;
    }

    $parent = stripeObjectValue($invoice, 'parent');
    $subscriptionDetails = stripeObjectValue($parent, 'subscription_details');
    $metadata = stripeObjectValue($subscriptionDetails, 'metadata');
    if ($metadata) {
        return $metadata;
    }

    return stripeObjectValue($invoice, 'metadata', []);
}

function stripeSubscriptionPeriod($stripeSubscription, $invoice, string $periodType): array {
    $start = stripeObjectValue($stripeSubscription, 'current_period_start')
        ?: stripeObjectValue($invoice, 'period_start')
        ?: time();

    $end = stripeObjectValue($stripeSubscription, 'current_period_end')
        ?: stripeObjectValue($invoice, 'period_end');

    if (!$end) {
        $duration = match ($periodType) {
            'yearly' => '+1 year',
            'monthly' => '+1 month',
            'weekly' => '+1 week',
            'daily' => '+1 day',
            default => '+1 month',
        };
        $end = strtotime($duration, (int) $start);
    }

    return [
        stripeUnixToMysql($start),
        stripeUnixToMysql($end),
    ];
}

function stripePaymentMetadataJson(
    string $promotionId,
    string $billingPeriod,
    string $stripeSubscriptionId,
    string $stripeInvoiceId
): string {
    return json_encode([
        'promotion_id' => $promotionId,
        'billing_period' => $billingPeriod,
        'period_type' => $billingPeriod,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_invoice_id' => $stripeInvoiceId,
    ]);
}

function finalizeStripePaidInvoice(PDO $db, $invoice, ?string $expectedUserId = null): array {
    $invoiceStatus = (string) stripeObjectValue($invoice, 'status', '');
    $invoicePaid = (bool) stripeObjectValue($invoice, 'paid', false);

    stripeCheckoutLog('finalize_invoice.received', [
        'stripe_invoice_id' => (string) stripeObjectValue($invoice, 'id', ''),
        'status' => $invoiceStatus,
        'paid' => $invoicePaid,
        'expected_user_id' => $expectedUserId,
        'stripe_subscription_id' => stripeInvoiceSubscriptionId($invoice),
    ]);

    if ($invoiceStatus !== 'paid' && !$invoicePaid) {
        return ['finalized' => false, 'status' => $invoiceStatus ?: 'unpaid'];
    }

    $stripeSubscriptionId = stripeInvoiceSubscriptionId($invoice);
    if (!$stripeSubscriptionId) {
        throw new RuntimeException('Paid invoice is not linked to a Stripe subscription.');
    }

    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId, [
        'expand' => ['items.data'],
    ]);
    $metadata = stripeSubscriptionMetadata($stripeSubscription, $invoice);
    $invoiceMetadata = stripeObjectValue($invoice, 'metadata', []);
    $upgradeMetadata = stripePaymentMetadataValue($invoiceMetadata, 'upgrade_type', '')
        ? $invoiceMetadata
        : $metadata;

    if ((string) stripePaymentMetadataValue($upgradeMetadata, 'upgrade_type', '') === 'bundle_upgrade') {
        stripeCheckoutLog('finalize_invoice.bundle_upgrade_detected', [
            'stripe_invoice_id' => (string) stripeObjectValue($invoice, 'id', ''),
            'stripe_subscription_id' => $stripeSubscriptionId,
            'expected_user_id' => $expectedUserId,
        ]);

        return finalizeStripeBundleUpgradeInvoice($db, $invoice, $stripeSubscription, $upgradeMetadata, $expectedUserId);
    }

    $userId = (string) stripePaymentMetadataValue($metadata, 'user_id', '');
    $chatbotId = (string) stripePaymentMetadataValue($metadata, 'chatbot_id', '');
    $periodType = promotionNormalizeBillingPeriod(
        stripePaymentMetadataValue($metadata, 'period_type', 'monthly')
    );
    $currencyId = (string) stripePaymentMetadataValue($metadata, 'currency_id', '');
    $promotionIds = stripeMetadataCsv($metadata, 'promotion_ids');
    $localSubscriptionIds = stripeMetadataCsv($metadata, 'subscription_ids');
    $initialPaymentIds = stripeMetadataCsv($metadata, 'payment_ids');
    $lineAmounts = array_map('floatval', stripeMetadataCsv($metadata, 'line_amounts'));

    if (!$userId || !$chatbotId || empty($promotionIds) || empty($localSubscriptionIds)) {
        throw new RuntimeException('Stripe subscription metadata is incomplete.');
    }

    if ($expectedUserId !== null && $userId !== $expectedUserId) {
        throw new RuntimeException('Invoice does not belong to the current user.');
    }

    $stripeCustomerId = stripeObjectId(stripeObjectValue($stripeSubscription, 'customer'))
        ?: stripeObjectId(stripeObjectValue($invoice, 'customer'));
    $stripeInvoiceId = (string) stripeObjectValue($invoice, 'id', '');
    $invoiceUrl = stripeInvoicePublicUrl($invoice);
    $paymentIntentId = stripeInvoicePaymentIntentId($invoice);
    $chargeId = stripePaymentIntentChargeId($paymentIntentId);
    $stripeStatus = (string) stripeObjectValue($stripeSubscription, 'status', 'active');
    $localStatus = stripeLocalSubscriptionStatus($stripeStatus);
    $cancelAtPeriodEnd = (bool) stripeObjectValue($stripeSubscription, 'cancel_at_period_end', false);
    [$startDate, $endDate] = stripeSubscriptionPeriod($stripeSubscription, $invoice, $periodType);

    $db->beginTransaction();

    try {
        $finalizedPaymentIds = [];
        $finalizedSubscriptionIds = [];

        foreach ($localSubscriptionIds as $index => $localSubscriptionId) {
            $promotionId = $promotionIds[$index] ?? $promotionIds[0] ?? '';
            if (!$promotionId) {
                continue;
            }

            $amount = $lineAmounts[$index] ?? null;
            if ($amount === null || $amount <= 0) {
                $amountStmt = $db->prepare("
                    SELECT amount
                    FROM payments
                    WHERE id = :id
                    LIMIT 1
                ");
                $amountStmt->execute(['id' => $initialPaymentIds[$index] ?? '']);
                $amount = (float) ($amountStmt->fetchColumn() ?: 0);
            }

            if ($amount <= 0) {
                throw new RuntimeException('Invoice line amount is missing.');
            }

            if (!$currencyId) {
                $currencyStmt = $db->prepare("
                    SELECT currency_id
                    FROM promotions
                    WHERE id = :promotion_id
                    LIMIT 1
                ");
                $currencyStmt->execute(['promotion_id' => $promotionId]);
                $currencyId = (string) $currencyStmt->fetchColumn();
            }

            $existingSubStmt = $db->prepare("
                SELECT id
                FROM subscriptions
                WHERE id = :id
                  AND user_id = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $existingSubStmt->execute([
                'id' => $localSubscriptionId,
                'user_id' => $userId,
            ]);
            $existingSubscriptionId = $existingSubStmt->fetchColumn();
            $subscriptionCancellationSql = ($localStatus === 'cancelled' || $cancelAtPeriodEnd)
                ? "cancelled_at = COALESCE(cancelled_at, NOW()),"
                : "
                    cancelled_at = NULL,
                    cancellation_reason = NULL,
                ";

            if ($existingSubscriptionId) {
                $db->prepare("
                    UPDATE subscriptions
                    SET status = :status,
                        start_date = :start_date,
                        end_date = :end_date,
                        auto_renew = :auto_renew,
                        cancel_at_period_end = :cancel_at_period_end,
                        stripe_subscription_id = :stripe_subscription_id,
                        stripe_customer_id = :stripe_customer_id,
                        {$subscriptionCancellationSql}
                        updated_at = NOW()
                    WHERE id = :id
                      AND user_id = :user_id
                ")->execute([
                    'status' => $localStatus,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'auto_renew' => $cancelAtPeriodEnd ? 0 : 1,
                    'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'stripe_customer_id' => $stripeCustomerId,
                    'id' => $localSubscriptionId,
                    'user_id' => $userId,
                ]);
            } else {
                $db->prepare("
                    INSERT INTO subscriptions (
                        id, user_id, chatbot_id, subscription_type,
                        promotions_id, status, start_date, end_date, auto_renew,
                        cancel_at_period_end, stripe_subscription_id, stripe_customer_id,
                        created_at, updated_at
                    ) VALUES (
                        :id, :user_id, :chatbot_id, 'promotion',
                        :promotions_id, :status, :start_date, :end_date, :auto_renew,
                        :cancel_at_period_end, :stripe_subscription_id, :stripe_customer_id,
                        NOW(), NOW()
                    )
                ")->execute([
                    'id' => $localSubscriptionId,
                    'user_id' => $userId,
                    'chatbot_id' => $chatbotId,
                    'promotions_id' => $promotionId,
                    'status' => $localStatus,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'auto_renew' => $cancelAtPeriodEnd ? 0 : 1,
                    'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'stripe_customer_id' => $stripeCustomerId,
                ]);

                AuditLog::log('subscription', $localSubscriptionId, 'created',
                    null,
                    ['status' => $localStatus, 'chatbot_id' => $chatbotId, 'promotion_id' => $promotionId],
                    $userId
                );
            }

            $paymentStmt = $db->prepare("
                SELECT id, status, stripe_payment_intent_id, stripe_charge_id
                FROM payments
                WHERE stripe_invoice_id = :stripe_invoice_id
                  AND subscription_id = :subscription_id
                  AND user_id = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $paymentStmt->execute([
                'stripe_invoice_id' => $stripeInvoiceId,
                'subscription_id' => $localSubscriptionId,
                'user_id' => $userId,
            ]);
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $initialPaymentId = $initialPaymentIds[$index] ?? '';
                if ($initialPaymentId) {
                    $initialPaymentStmt = $db->prepare("
                        SELECT id, status, stripe_payment_intent_id, stripe_charge_id
                        FROM payments
                        WHERE id = :id
                          AND user_id = :user_id
                          AND status IN ('pending', 'processing')
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $initialPaymentStmt->execute([
                        'id' => $initialPaymentId,
                        'user_id' => $userId,
                    ]);
                    $payment = $initialPaymentStmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            $paymentMetadata = stripePaymentMetadataJson(
                $promotionId,
                $periodType,
                $stripeSubscriptionId,
                $stripeInvoiceId
            );

            if ($payment) {
                $effectivePaymentIntentId = $paymentIntentId ?: ($payment['stripe_payment_intent_id'] ?? null);
                $effectiveChargeId = $chargeId
                    ?: stripePaymentIntentChargeId($effectivePaymentIntentId)
                    ?: ($payment['stripe_charge_id'] ?? null);

                $db->prepare("
                    UPDATE payments
                    SET status = 'succeeded',
                        subscription_id = :subscription_id,
                        amount = :amount,
                        stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, :stripe_payment_intent_id),
                        stripe_charge_id = COALESCE(stripe_charge_id, :stripe_charge_id),
                        stripe_invoice_id = :stripe_invoice_id,
                        invoice_url = :invoice_url,
                        metadata = :metadata,
                        succeeded_at = COALESCE(succeeded_at, NOW()),
                        updated_at = NOW()
                    WHERE id = :id
                      AND user_id = :user_id
                ")->execute([
                    'subscription_id' => $localSubscriptionId,
                    'amount' => $amount,
                    'stripe_payment_intent_id' => $effectivePaymentIntentId,
                    'stripe_charge_id' => $effectiveChargeId,
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'invoice_url' => $invoiceUrl,
                    'metadata' => $paymentMetadata,
                    'id' => $payment['id'],
                    'user_id' => $userId,
                ]);

                $paymentId = $payment['id'];
            } else {
                $paymentId = generateUUID();
                $db->prepare("
                    INSERT INTO payments (
                        id, user_id, subscription_id, chatbot_id,
                        amount, currency_id, payment_method, status,
                        stripe_payment_intent_id, stripe_charge_id, stripe_invoice_id,
                        invoice_url, metadata, succeeded_at, created_at, updated_at
                    ) VALUES (
                        :id, :user_id, :subscription_id, :chatbot_id,
                        :amount, :currency_id, 'stripe', 'succeeded',
                        :stripe_payment_intent_id, :stripe_charge_id, :stripe_invoice_id,
                        :invoice_url, :metadata, NOW(), NOW(), NOW()
                    )
                ")->execute([
                    'id' => $paymentId,
                    'user_id' => $userId,
                    'subscription_id' => $localSubscriptionId,
                    'chatbot_id' => $chatbotId,
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'stripe_charge_id' => $chargeId,
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'invoice_url' => $invoiceUrl,
                    'metadata' => $paymentMetadata,
                ]);
            }

            AuditLog::log('payment', $paymentId, 'updated',
                null,
                ['status' => 'succeeded', 'stripe_invoice_id' => $stripeInvoiceId],
                $userId
            );

            $finalizedPaymentIds[] = $paymentId;
            $finalizedSubscriptionIds[] = $localSubscriptionId;
        }

        $db->commit();

        return [
            'finalized' => true,
            'status' => 'paid',
            'payment_ids' => $finalizedPaymentIds,
            'subscription_ids' => $finalizedSubscriptionIds,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'invoice_url' => $invoiceUrl,
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function finalizeStripeBundleUpgradeInvoice(PDO $db, $invoice, $stripeSubscription, $metadata, ?string $expectedUserId = null): array {
    $userId = (string) stripePaymentMetadataValue($metadata, 'user_id', '');
    $chatbotId = (string) stripePaymentMetadataValue($metadata, 'chatbot_id', '');
    $periodType = promotionNormalizeBillingPeriod(
        stripePaymentMetadataValue($metadata, 'period_type', 'monthly')
    );
    $currencyId = (string) stripePaymentMetadataValue($metadata, 'currency_id', '');
    $existingSubscriptionId = (string) stripePaymentMetadataValue($metadata, 'upgrade_existing_subscription_id', '');
    $newSubscriptionId = (string) stripePaymentMetadataValue($metadata, 'upgrade_new_subscription_id', '');
    $existingPromotionId = (string) stripePaymentMetadataValue($metadata, 'upgrade_existing_promotion_id', '');
    $newPromotionId = (string) stripePaymentMetadataValue($metadata, 'upgrade_new_promotion_id', '');
    $paymentId = (string) stripePaymentMetadataValue($metadata, 'upgrade_payment_id', '');

    if (!$userId || !$chatbotId || !$existingSubscriptionId || !$newSubscriptionId || !$existingPromotionId || !$newPromotionId) {
        throw new RuntimeException('Stripe bundle upgrade metadata is incomplete.');
    }

    stripeCheckoutLog('bundle_upgrade.finalize.start', [
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
        'existing_subscription_id' => $existingSubscriptionId,
        'new_subscription_id' => $newSubscriptionId,
        'existing_promotion_id' => $existingPromotionId,
        'new_promotion_id' => $newPromotionId,
        'payment_id' => $paymentId,
        'stripe_invoice_id' => (string) stripeObjectValue($invoice, 'id', ''),
    ]);

    if ($expectedUserId !== null && $userId !== $expectedUserId) {
        throw new RuntimeException('Invoice does not belong to the current user.');
    }

    $stripeSubscriptionId = stripeObjectId($stripeSubscription);
    if (!$stripeSubscriptionId) {
        $stripeSubscriptionId = stripeInvoiceSubscriptionId($invoice);
    }

    $stripeCustomerId = stripeObjectId(stripeObjectValue($stripeSubscription, 'customer'))
        ?: stripeObjectId(stripeObjectValue($invoice, 'customer'));
    $stripeInvoiceId = (string) stripeObjectValue($invoice, 'id', '');
    $invoiceUrl = stripeInvoicePublicUrl($invoice);
    $paymentIntentId = stripeInvoicePaymentIntentId($invoice);
    $chargeId = stripePaymentIntentChargeId($paymentIntentId);
    $stripeStatus = (string) stripeObjectValue($stripeSubscription, 'status', 'active');
    $localStatus = stripeLocalSubscriptionStatus($stripeStatus);
    $cancelAtPeriodEnd = false;
    $endDate = stripeUnixToMysql(stripeObjectValue($stripeSubscription, 'current_period_end') ?: time());
    $paidAt = stripeObjectValue(stripeObjectValue($invoice, 'status_transitions'), 'paid_at')
        ?: stripeObjectValue($invoice, 'created')
        ?: time();
    $upgradeStartDate = stripeUnixToMysql($paidAt);
    $amount = stripeInvoicePaidAmountDecimal($invoice);

    if ($amount <= 0) {
        throw new RuntimeException('Stripe bundle upgrade invoice amount is missing.');
    }

    if (!$currencyId) {
        $currencyStmt = $db->prepare("
            SELECT currency_id
            FROM promotions
            WHERE id = :promotion_id
            LIMIT 1
        ");
        $currencyStmt->execute(['promotion_id' => $newPromotionId]);
        $currencyId = (string) $currencyStmt->fetchColumn();
    }

    $db->beginTransaction();

    try {
        $existingStmt = $db->prepare("
            SELECT id
            FROM subscriptions
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1
            FOR UPDATE
        ");
        $existingStmt->execute([
            'id' => $existingSubscriptionId,
            'user_id' => $userId,
        ]);

        if (!$existingStmt->fetchColumn()) {
            throw new RuntimeException('Existing local subscription for bundle upgrade was not found.');
        }

        $cancellationSql = ($localStatus === 'cancelled' || $cancelAtPeriodEnd)
            ? "cancelled_at = COALESCE(cancelled_at, NOW()),"
            : "
                cancelled_at = NULL,
                cancellation_reason = NULL,
            ";

        $db->prepare("
            UPDATE subscriptions
            SET status = :status,
                end_date = :end_date,
                auto_renew = :auto_renew,
                cancel_at_period_end = :cancel_at_period_end,
                stripe_subscription_id = :stripe_subscription_id,
                stripe_customer_id = :stripe_customer_id,
                {$cancellationSql}
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ")->execute([
            'status' => $localStatus,
            'end_date' => $endDate,
            'auto_renew' => $cancelAtPeriodEnd ? 0 : 1,
            'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_customer_id' => $stripeCustomerId,
            'id' => $existingSubscriptionId,
            'user_id' => $userId,
        ]);

        $newStmt = $db->prepare("
            SELECT id
            FROM subscriptions
            WHERE user_id = :user_id
              AND chatbot_id = :chatbot_id
              AND subscription_type = 'promotion'
              AND promotions_id = :promotions_id
              AND id <> :existing_subscription_id
              AND (
                  id = :preferred_subscription_id
                  OR (
                      stripe_subscription_id = :stripe_subscription_id
                      AND status IN ('active', 'trialing', 'past_due', 'pending')
                  )
              )
            ORDER BY
              CASE WHEN id = :preferred_subscription_id_order THEN 0 ELSE 1 END,
              created_at DESC
            LIMIT 1
            FOR UPDATE
        ");
        $newStmt->execute([
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'promotions_id' => $newPromotionId,
            'existing_subscription_id' => $existingSubscriptionId,
            'preferred_subscription_id' => $newSubscriptionId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'preferred_subscription_id_order' => $newSubscriptionId,
        ]);
        $matchedNewSubscriptionId = $newStmt->fetchColumn();
        if ($matchedNewSubscriptionId) {
            $newSubscriptionId = (string) $matchedNewSubscriptionId;
        }
        $newExists = (bool) $matchedNewSubscriptionId;

        if (!$newExists) {
            $newStmt = $db->prepare("
                SELECT id
                FROM subscriptions
                WHERE id = :id
                  AND user_id = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $newStmt->execute([
                'id' => $newSubscriptionId,
                'user_id' => $userId,
            ]);
            $newExists = (bool) $newStmt->fetchColumn();
        }

        if ($newExists) {
            $db->prepare("
                UPDATE subscriptions
                SET chatbot_id = :chatbot_id,
                    promotions_id = :promotions_id,
                    status = :status,
                    start_date = :start_date,
                    end_date = :end_date,
                    auto_renew = :auto_renew,
                    cancel_at_period_end = :cancel_at_period_end,
                    stripe_subscription_id = :stripe_subscription_id,
                    stripe_customer_id = :stripe_customer_id,
                    {$cancellationSql}
                    updated_at = NOW()
                WHERE id = :id
                  AND user_id = :user_id
            ")->execute([
                'chatbot_id' => $chatbotId,
                'promotions_id' => $newPromotionId,
                'status' => $localStatus,
                'start_date' => $upgradeStartDate,
                'end_date' => $endDate,
                'auto_renew' => $cancelAtPeriodEnd ? 0 : 1,
                'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_customer_id' => $stripeCustomerId,
                'id' => $newSubscriptionId,
                'user_id' => $userId,
            ]);
        } else {
            $db->prepare("
                INSERT INTO subscriptions (
                    id, user_id, chatbot_id, subscription_type,
                    promotions_id, status, start_date, end_date, auto_renew,
                    cancel_at_period_end, stripe_subscription_id, stripe_customer_id,
                    created_at, updated_at
                ) VALUES (
                    :id, :user_id, :chatbot_id, 'promotion',
                    :promotions_id, :status, :start_date, :end_date, :auto_renew,
                    :cancel_at_period_end, :stripe_subscription_id, :stripe_customer_id,
                    NOW(), NOW()
                )
            ")->execute([
                'id' => $newSubscriptionId,
                'user_id' => $userId,
                'chatbot_id' => $chatbotId,
                'promotions_id' => $newPromotionId,
                'status' => $localStatus,
                'start_date' => $upgradeStartDate,
                'end_date' => $endDate,
                'auto_renew' => $cancelAtPeriodEnd ? 0 : 1,
                'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            AuditLog::log('subscription', $newSubscriptionId, 'created',
                null,
                ['status' => $localStatus, 'chatbot_id' => $chatbotId, 'promotion_id' => $newPromotionId, 'upgrade_type' => 'bundle_upgrade'],
                $userId
            );
        }

        stripeCancelDuplicateBundlePromotionRows(
            $db,
            $userId,
            $chatbotId,
            $stripeSubscriptionId,
            [$existingSubscriptionId, $newSubscriptionId],
            [$existingPromotionId, $newPromotionId]
        );

        $paymentMetadata = json_encode([
            'upgrade_type' => 'bundle_upgrade',
            'promotion_id' => $newPromotionId,
            'existing_promotion_id' => $existingPromotionId,
            'billing_period' => $periodType,
            'period_type' => $periodType,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'upgrade_from_subscription_id' => $existingSubscriptionId,
            'upgrade_new_subscription_id' => $newSubscriptionId,
            'is_bundle' => true,
        ]);

        if (!$paymentId) {
            $paymentId = generateUUID();
        }

        $paymentStmt = $db->prepare("
            SELECT id, stripe_payment_intent_id, stripe_charge_id
            FROM payments
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1
            FOR UPDATE
        ");
        $paymentStmt->execute([
            'id' => $paymentId,
            'user_id' => $userId,
        ]);
        $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            $paymentByIntentStmt = $db->prepare("
                SELECT id, stripe_payment_intent_id, stripe_charge_id
                FROM payments
                WHERE stripe_payment_intent_id = :stripe_payment_intent_id
                  AND user_id = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $paymentByIntentStmt->execute([
                'stripe_payment_intent_id' => $paymentIntentId,
                'user_id' => $userId,
            ]);
            $payment = $paymentByIntentStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($payment) {
            $effectivePaymentIntentId = $paymentIntentId ?: ($payment['stripe_payment_intent_id'] ?? null);
            $effectiveChargeId = $chargeId
                ?: stripePaymentIntentChargeId($effectivePaymentIntentId)
                ?: ($payment['stripe_charge_id'] ?? null);

            $db->prepare("
                UPDATE payments
                SET status = 'succeeded',
                    subscription_id = :subscription_id,
                    amount = :amount,
                    stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, :stripe_payment_intent_id),
                    stripe_charge_id = COALESCE(stripe_charge_id, :stripe_charge_id),
                    stripe_invoice_id = :stripe_invoice_id,
                    invoice_url = :invoice_url,
                    metadata = :metadata,
                    succeeded_at = COALESCE(succeeded_at, NOW()),
                    updated_at = NOW()
                WHERE id = :id
                  AND user_id = :user_id
            ")->execute([
                'subscription_id' => $newSubscriptionId,
                'amount' => $amount,
                'stripe_payment_intent_id' => $effectivePaymentIntentId,
                'stripe_charge_id' => $effectiveChargeId,
                'stripe_invoice_id' => $stripeInvoiceId,
                'invoice_url' => $invoiceUrl,
                'metadata' => $paymentMetadata,
                'id' => $payment['id'],
                'user_id' => $userId,
            ]);

            $paymentId = $payment['id'];
        } else {
            $db->prepare("
                INSERT INTO payments (
                    id, user_id, subscription_id, chatbot_id,
                    amount, currency_id, payment_method, status,
                    stripe_payment_intent_id, stripe_charge_id, stripe_invoice_id,
                    invoice_url, metadata, succeeded_at, created_at, updated_at
                ) VALUES (
                    :id, :user_id, :subscription_id, :chatbot_id,
                    :amount, :currency_id, 'stripe', 'succeeded',
                    :stripe_payment_intent_id, :stripe_charge_id, :stripe_invoice_id,
                    :invoice_url, :metadata, NOW(), NOW(), NOW()
                )
            ")->execute([
                'id' => $paymentId,
                'user_id' => $userId,
                'subscription_id' => $newSubscriptionId,
                'chatbot_id' => $chatbotId,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_charge_id' => $chargeId,
                'stripe_invoice_id' => $stripeInvoiceId,
                'invoice_url' => $invoiceUrl,
                'metadata' => $paymentMetadata,
            ]);
        }

        AuditLog::log('payment', $paymentId, 'updated',
            null,
            ['status' => 'succeeded', 'stripe_invoice_id' => $stripeInvoiceId, 'upgrade_type' => 'bundle_upgrade'],
            $userId
        );

        $db->commit();

        stripeFinalizeBundleUpgradeMetadata(
            $db,
            $stripeSubscriptionId,
            $userId,
            $chatbotId,
            $existingSubscriptionId,
            $newSubscriptionId,
            $existingPromotionId,
            $newPromotionId,
            $periodType,
            $currencyId,
            $metadata
        );

        stripeCheckoutLog('bundle_upgrade.finalize.success', [
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'payment_id' => $paymentId,
            'existing_subscription_id' => $existingSubscriptionId,
            'new_subscription_id' => $newSubscriptionId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'amount' => $amount,
        ]);

        return [
            'finalized' => true,
            'status' => 'paid',
            'payment_ids' => [$paymentId],
            'subscription_ids' => [$existingSubscriptionId, $newSubscriptionId],
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'invoice_url' => $invoiceUrl,
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        stripeCheckoutLog('bundle_upgrade.finalize.error', stripeCheckoutExceptionContext($e) + [
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'stripe_invoice_id' => $stripeInvoiceId ?? '',
        ]);
        throw $e;
    }
}

function stripeInvoicePaidAmountDecimal($invoice): float {
    $amount = stripeObjectValue($invoice, 'amount_paid');
    if (!$amount) {
        $amount = stripeObjectValue($invoice, 'total');
    }
    if (!$amount) {
        $amount = stripeObjectValue($invoice, 'amount_due');
    }

    return ((int) $amount) / 100;
}

function stripeCancelDuplicateBundlePromotionRows(
    PDO $db,
    string $userId,
    string $chatbotId,
    string $stripeSubscriptionId,
    array $keepSubscriptionIds,
    array $promotionIds
): void {
    $keepSubscriptionIds = array_values(array_filter(array_unique($keepSubscriptionIds)));
    $promotionIds = array_values(array_filter(array_unique($promotionIds)));

    if (!$stripeSubscriptionId || empty($keepSubscriptionIds) || empty($promotionIds)) {
        return;
    }

    $params = [
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
        'stripe_subscription_id' => $stripeSubscriptionId,
    ];

    $keepPlaceholders = [];
    foreach ($keepSubscriptionIds as $index => $subscriptionId) {
        $key = 'keep_subscription_' . $index;
        $keepPlaceholders[] = ':' . $key;
        $params[$key] = $subscriptionId;
    }

    $promotionPlaceholders = [];
    foreach ($promotionIds as $index => $promotionId) {
        $key = 'promotion_' . $index;
        $promotionPlaceholders[] = ':' . $key;
        $params[$key] = $promotionId;
    }

    $db->prepare("
        UPDATE subscriptions
        SET status = 'cancelled',
            auto_renew = 0,
            cancel_at_period_end = 0,
            cancelled_at = COALESCE(cancelled_at, NOW()),
            updated_at = NOW()
        WHERE user_id = :user_id
          AND chatbot_id = :chatbot_id
          AND subscription_type = 'promotion'
          AND stripe_subscription_id = :stripe_subscription_id
          AND promotions_id IN (" . implode(',', $promotionPlaceholders) . ")
          AND id NOT IN (" . implode(',', $keepPlaceholders) . ")
          AND status IN ('active', 'trialing', 'past_due', 'pending')
    ")->execute($params);
}

function stripeFinalizeBundleUpgradeMetadata(
    PDO $db,
    string $stripeSubscriptionId,
    string $userId,
    string $chatbotId,
    string $existingSubscriptionId,
    string $newSubscriptionId,
    string $existingPromotionId,
    string $newPromotionId,
    string $periodType,
    string $currencyId,
    $upgradeMetadata
): void {
    try {
        $promotions = stripePromotionRowsForMetadata($db, [$existingPromotionId, $newPromotionId]);
        $existingAmount = isset($promotions[$existingPromotionId])
            ? promotionBundleLineTotalPrice((float) $promotions[$existingPromotionId]['price'], $periodType)
            : 0.0;
        $newAmount = isset($promotions[$newPromotionId])
            ? promotionBundleLineTotalPrice((float) $promotions[$newPromotionId]['price'], $periodType)
            : 0.0;

        $metadata = [
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'chatbot_name' => (string) stripePaymentMetadataValue($upgradeMetadata, 'chatbot_name', ''),
            'promotion_ids' => $existingPromotionId . ',' . $newPromotionId,
            'payment_ids' => '',
            'subscription_ids' => $existingSubscriptionId . ',' . $newSubscriptionId,
            'line_amounts' => number_format((float) $existingAmount, 2, '.', '') . ',' . number_format((float) $newAmount, 2, '.', ''),
            'total_amount' => number_format((float) ($existingAmount + $newAmount), 2, '.', ''),
            'period_type' => $periodType,
            'duration_days' => (string) stripePaymentMetadataValue($upgradeMetadata, 'duration_days', ($periodType === 'yearly' ? '365' : '30')),
            'currency_id' => $currencyId,
            'is_bundle' => '1',
            'upgrade_type' => '',
            'upgrade_existing_subscription_id' => '',
            'upgrade_new_subscription_id' => '',
            'upgrade_existing_promotion_id' => '',
            'upgrade_new_promotion_id' => '',
            'upgrade_payment_id' => '',
        ];

        \Stripe\Subscription::update($stripeSubscriptionId, [
            'cancel_at_period_end' => false,
            'metadata' => $metadata,
        ]);
    } catch (Exception $e) {
        error_log('Stripe: Could not normalize bundle upgrade metadata: ' . $e->getMessage());
    }
}

function stripePromotionRowsForMetadata(PDO $db, array $promotionIds): array {
    $promotionIds = array_values(array_filter(array_unique($promotionIds)));
    if (empty($promotionIds)) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($promotionIds as $index => $promotionId) {
        $key = 'promotion_id_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $promotionId;
    }

    $stmt = $db->prepare("
        SELECT id, price
        FROM promotions
        WHERE id IN (" . implode(',', $placeholders) . ")
    ");
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[$row['id']] = $row;
    }

    return $rows;
}

function syncStripeSubscriptionStatus(PDO $db, $stripeSubscription): void {
    $stripeSubscriptionId = stripeObjectId($stripeSubscription);
    if (!$stripeSubscriptionId) {
        return;
    }

    $metadata = stripeObjectValue($stripeSubscription, 'metadata', []);
    $userId = (string) stripePaymentMetadataValue($metadata, 'user_id', '');
    $localSubscriptionIds = stripeMetadataCsv($metadata, 'subscription_ids');
    $partialCancelSubscriptionIds = stripeMetadataCsv($metadata, 'partial_cancel_subscription_ids');
    $status = stripeLocalSubscriptionStatus((string) stripeObjectValue($stripeSubscription, 'status', ''));
    $cancelAtPeriodEnd = (bool) stripeObjectValue($stripeSubscription, 'cancel_at_period_end', false);
    $autoRenew = ($status === 'active' || $status === 'trialing') && !$cancelAtPeriodEnd ? 1 : 0;
    $endDate = stripeUnixToMysql(stripeObjectValue($stripeSubscription, 'current_period_end') ?: time());

    if (!$userId && empty($localSubscriptionIds)) {
        return;
    }

    $params = [
        'status' => $status,
        'end_date' => $endDate,
        'auto_renew' => $autoRenew,
        'cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
    ];

    $where = "stripe_subscription_id = :stripe_subscription_id";
    $params['stripe_subscription_id'] = $stripeSubscriptionId;
    if ($userId) {
        $where .= " AND user_id = :user_id";
        $params['user_id'] = $userId;
    }

    if (!empty($localSubscriptionIds)) {
        unset($params['stripe_subscription_id']);
        $placeholders = [];
        foreach ($localSubscriptionIds as $index => $id) {
            $key = 'sub_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $where = "id IN (" . implode(',', $placeholders) . ")";
        if ($userId) {
            $where .= " AND user_id = :user_id";
        }
    }

    if ($status === 'cancelled' || $cancelAtPeriodEnd) {
        $cancellationSql = "cancelled_at = COALESCE(cancelled_at, NOW()),";
    } else {
        $cancellationSql = "
            cancelled_at = NULL,
            cancellation_reason = NULL,
        ";
    }

    $stmt = $db->prepare("
        UPDATE subscriptions
        SET status = :status,
            end_date = :end_date,
            auto_renew = :auto_renew,
            cancel_at_period_end = :cancel_at_period_end,
            {$cancellationSql}
            updated_at = NOW()
        WHERE {$where}
    ");
    $stmt->execute($params);

    if (!empty($partialCancelSubscriptionIds)) {
        $partialParams = [];
        $partialPlaceholders = [];
        foreach ($partialCancelSubscriptionIds as $index => $id) {
            $key = 'partial_id_' . $index;
            $partialPlaceholders[] = ':' . $key;
            $partialParams[$key] = $id;
        }

        $partialEndDate = $endDate;
        $maxEndStmt = $db->prepare("
            SELECT MAX(end_date)
            FROM subscriptions
            WHERE stripe_subscription_id = :stripe_subscription_id
              AND status IN ('active', 'trialing', 'past_due', 'pending')
              AND end_date >= NOW()
        ");
        $maxEndStmt->execute(['stripe_subscription_id' => $stripeSubscriptionId]);
        $maxLocalEndDate = $maxEndStmt->fetchColumn();
        if ($maxLocalEndDate && strtotime((string) $maxLocalEndDate) > strtotime($partialEndDate)) {
            $partialEndDate = (string) $maxLocalEndDate;
        }

        $partialParams['end_date'] = $partialEndDate;

        $db->prepare("
            UPDATE subscriptions
            SET end_date = :end_date,
                auto_renew = 0,
                cancel_at_period_end = 1,
                cancelled_at = COALESCE(cancelled_at, NOW()),
                updated_at = NOW()
            WHERE id IN (" . implode(',', $partialPlaceholders) . ")
              AND status IN ('active', 'trialing', 'past_due')
        ")->execute($partialParams);
    }
}

function markStripeInvoicePaymentFailed(PDO $db, $invoice): void {
    $stripeSubscriptionId = stripeInvoiceSubscriptionId($invoice);
    $stripeInvoiceId = (string) stripeObjectValue($invoice, 'id', '');
    $invoiceMetadata = stripeObjectValue($invoice, 'metadata', []);
    $isBundleUpgradeInvoice = (string) stripePaymentMetadataValue($invoiceMetadata, 'upgrade_type', '') === 'bundle_upgrade';
    if (!$stripeSubscriptionId && !$stripeInvoiceId) {
        return;
    }

    $db->prepare("
        UPDATE payments
        SET status = 'failed',
            failed_at = NOW(),
            failure_code = 'invoice_payment_failed',
            failure_message = 'Stripe invoice payment failed',
            updated_at = NOW()
        WHERE status IN ('pending', 'processing')
          AND (
              stripe_invoice_id = :stripe_invoice_id
              OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.stripe_subscription_id')) = :stripe_subscription_id
          )
    ")->execute([
        'stripe_invoice_id' => $stripeInvoiceId,
        'stripe_subscription_id' => $stripeSubscriptionId,
    ]);

    if ($stripeSubscriptionId && !$isBundleUpgradeInvoice) {
        $db->prepare("
            UPDATE subscriptions
            SET status = 'past_due',
                updated_at = NOW()
            WHERE stripe_subscription_id = :stripe_subscription_id
              AND status = 'active'
        ")->execute(['stripe_subscription_id' => $stripeSubscriptionId]);
    }
}

function syncRecentPaidStripeInvoicesForUser(PDO $db, string $userId, int $limit = 5): void {
    $limit = max(1, min(20, $limit));

    $stmt = $db->prepare("
        SELECT stripe_invoice_id
        FROM payments
        WHERE user_id = :user_id
          AND payment_method = 'stripe'
          AND status IN ('pending', 'processing')
          AND stripe_invoice_id IS NOT NULL
        GROUP BY stripe_invoice_id
        ORDER BY MAX(created_at) DESC
        LIMIT :limit_count
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $invoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($invoiceIds as $invoiceId) {
        try {
            $invoice = \Stripe\Invoice::retrieve($invoiceId);
            finalizeStripePaidInvoice($db, $invoice, $userId);
        } catch (Exception $e) {
            error_log('Stripe invoice sync skipped ' . $invoiceId . ': ' . $e->getMessage());
        }
    }
}
