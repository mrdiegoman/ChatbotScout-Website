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

function stripeInvoiceSubscriptionDetailsMetadata($invoice) {
    $parent = stripeObjectValue($invoice, 'parent');
    $subscriptionDetails = stripeObjectValue($parent, 'subscription_details');

    return stripeObjectValue($subscriptionDetails, 'metadata', []);
}

function stripeFirstMetadataWithValue(array $metadataObjects, string $key) {
    foreach ($metadataObjects as $metadata) {
        if ((string) stripePaymentMetadataValue($metadata, $key, '') !== '') {
            return $metadata;
        }
    }

    return [];
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
    $invoiceSubscriptionMetadata = stripeInvoiceSubscriptionDetailsMetadata($invoice);
    $upgradeMetadata = stripeFirstMetadataWithValue(
        [$invoiceMetadata, $invoiceSubscriptionMetadata, $metadata],
        'upgrade_type'
    ) ?: $metadata;

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
    $promotionSlotMap = stripeFinalizerPromotionSlotMap($db, $promotionIds);

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

    $promotionLockName = stripeFinalizerPromotionLockName($userId, $chatbotId);
    $promotionLockAcquired = false;

    try {
        $promotionLockAcquired = stripeFinalizerAcquirePromotionLock($db, $promotionLockName);
        $db->beginTransaction();

        $finalizedPaymentIds = [];
        $finalizedSubscriptionIds = [];
        $duplicateStripeItems = [];

        foreach ($localSubscriptionIds as $index => $localSubscriptionId) {
            $promotionId = $promotionIds[$index] ?? $promotionIds[0] ?? '';
            if (!$promotionId) {
                continue;
            }

            $promotionSlot = $promotionSlotMap[$promotionId] ?? '';
            if ($promotionSlot !== '') {
                $duplicateConflict = stripeFinalizerActivePromotionSlotConflict(
                    $db,
                    $userId,
                    $chatbotId,
                    $promotionSlot,
                    $localSubscriptionId
                );

                if ($duplicateConflict) {
                    stripeCheckoutLog('finalize_invoice.duplicate_slot_skipped', [
                        'user_id' => $userId,
                        'chatbot_id' => $chatbotId,
                        'promotion_id' => $promotionId,
                        'slot_type' => $promotionSlot,
                        'local_subscription_id' => $localSubscriptionId,
                        'existing_subscription_id' => $duplicateConflict['id'] ?? '',
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'stripe_invoice_id' => $stripeInvoiceId,
                    ]);

                    stripeFinalizerCancelPendingDuplicatePayment(
                        $db,
                        $userId,
                        $initialPaymentIds[$index] ?? '',
                        $stripeInvoiceId,
                        $invoiceUrl,
                        'Duplicate promotion slot was already active for this chatbot.'
                    );

                    $duplicateStripeItems[] = [
                        'promotion_id' => $promotionId,
                        'subscription_id' => $localSubscriptionId,
                    ];
                    continue;
                }
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

        if (!empty($duplicateStripeItems)) {
            stripeFinalizerRemoveDuplicateStripeItems(
                $stripeSubscriptionId,
                $stripeSubscription,
                $duplicateStripeItems
            );
        }

        syncChatbotSponsoredTag($db, $chatbotId);

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
    } finally {
        if ($promotionLockAcquired) {
            stripeFinalizerReleasePromotionLock($db, $promotionLockName);
        }
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
    $stripeCancelAtPeriodEnd = (bool) stripeObjectValue($stripeSubscription, 'cancel_at_period_end', false);
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

    $alreadyFinalized = stripeBundleUpgradeAlreadyFinalized(
        $db,
        $userId,
        $existingSubscriptionId,
        $newSubscriptionId,
        $paymentId,
        $stripeInvoiceId,
        $paymentIntentId
    );
    if ($alreadyFinalized) {
        stripeCheckoutLog('bundle_upgrade.finalize.idempotent_success', [
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'payment_id' => $alreadyFinalized['payment_id'],
            'existing_subscription_id' => $existingSubscriptionId,
            'new_subscription_id' => $newSubscriptionId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
        ]);

        syncChatbotSponsoredTag($db, $chatbotId);

        return [
            'finalized' => true,
            'status' => 'paid',
            'payment_ids' => [$alreadyFinalized['payment_id']],
            'subscription_ids' => [$existingSubscriptionId, $newSubscriptionId],
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'invoice_url' => $invoiceUrl,
        ];
    }

    $db->beginTransaction();

    try {
        $existingStmt = $db->prepare("
            SELECT id, auto_renew, cancel_at_period_end
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
        $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRow) {
            throw new RuntimeException('Existing local subscription for bundle upgrade was not found.');
        }

        $existingCancelAtPeriodEnd = !empty($existingRow['cancel_at_period_end']) || $stripeCancelAtPeriodEnd;
        $existingAutoRenew = $existingCancelAtPeriodEnd ? 0 : (!empty($existingRow['auto_renew']) ? 1 : 0);
        $newCancelAtPeriodEnd = false;
        $newAutoRenew = $localStatus === 'cancelled' ? 0 : 1;

        $existingCancellationSql = ($localStatus === 'cancelled' || $existingCancelAtPeriodEnd)
            ? "cancelled_at = COALESCE(cancelled_at, NOW()),"
            : "
                cancelled_at = NULL,
                cancellation_reason = NULL,
            ";

        $newCancellationSql = ($localStatus === 'cancelled' || $newCancelAtPeriodEnd)
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
                {$existingCancellationSql}
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :user_id
        ")->execute([
            'status' => $localStatus,
            'end_date' => $endDate,
            'auto_renew' => $existingAutoRenew,
            'cancel_at_period_end' => $existingCancelAtPeriodEnd ? 1 : 0,
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
                    {$newCancellationSql}
                    updated_at = NOW()
                WHERE id = :id
                  AND user_id = :user_id
            ")->execute([
                'chatbot_id' => $chatbotId,
                'promotions_id' => $newPromotionId,
                'status' => $localStatus,
                'start_date' => $upgradeStartDate,
                'end_date' => $endDate,
                'auto_renew' => $newAutoRenew,
                'cancel_at_period_end' => $newCancelAtPeriodEnd ? 1 : 0,
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
                'auto_renew' => $newAutoRenew,
                'cancel_at_period_end' => $newCancelAtPeriodEnd ? 1 : 0,
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
            'upgrade_pricing_version' => 'missing_bundle_only',
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

        $scheduleRows = stripeFinalizerLoadBundleUpgradeScheduleRows(
            $db,
            $userId,
            $chatbotId,
            $existingSubscriptionId,
            $newSubscriptionId,
            $periodType
        );

        stripeFinalizeBundleUpgradeCurrentBundlePrices(
            $stripeSubscriptionId,
            [$scheduleRows[$existingSubscriptionId], $scheduleRows[$newSubscriptionId]],
            $periodType
        );

        $shouldSchedulePartialRenewal = $existingCancelAtPeriodEnd && $localStatus !== 'cancelled';
        if ($shouldSchedulePartialRenewal) {
            stripeScheduleBundleUpgradePartialRenewal(
                $stripeSubscriptionId,
                [$scheduleRows[$existingSubscriptionId]],
                [$scheduleRows[$newSubscriptionId]],
                $periodType,
                $metadata
            );
        }

        syncChatbotSponsoredTag($db, $chatbotId);

        $db->commit();

        if (!$shouldSchedulePartialRenewal) {
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
        }

        stripeCheckoutLog('bundle_upgrade.finalize.success', [
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'payment_id' => $paymentId,
            'existing_subscription_id' => $existingSubscriptionId,
            'new_subscription_id' => $newSubscriptionId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'amount' => $amount,
            'partial_renewal_scheduled' => $shouldSchedulePartialRenewal,
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

function stripeBundleUpgradeAlreadyFinalized(
    PDO $db,
    string $userId,
    string $existingSubscriptionId,
    string $newSubscriptionId,
    string $paymentId,
    string $stripeInvoiceId,
    ?string $paymentIntentId
): ?array {
    $stmt = $db->prepare("
        SELECT p.id AS payment_id
        FROM payments p
        WHERE p.user_id = :user_id
          AND p.subscription_id = :new_subscription_id
          AND p.status = 'succeeded'
          AND (
              (:payment_id_filter <> '' AND p.id = :payment_id_match)
              OR (:stripe_invoice_id_filter <> '' AND p.stripe_invoice_id = :stripe_invoice_id_match)
              OR (:payment_intent_id_filter <> '' AND p.stripe_payment_intent_id = :payment_intent_id_match)
          )
          AND EXISTS (
              SELECT 1
              FROM subscriptions s_existing
              WHERE s_existing.id = :existing_subscription_id
                AND s_existing.user_id = p.user_id
          )
          AND EXISTS (
              SELECT 1
              FROM subscriptions s_new
              WHERE s_new.id = :new_subscription_id_exists
                AND s_new.user_id = p.user_id
                AND s_new.status IN ('active', 'trialing', 'past_due')
          )
        LIMIT 1
    ");
    $stmt->execute([
        'user_id' => $userId,
        'new_subscription_id' => $newSubscriptionId,
        'payment_id_filter' => $paymentId,
        'payment_id_match' => $paymentId,
        'stripe_invoice_id_filter' => $stripeInvoiceId,
        'stripe_invoice_id_match' => $stripeInvoiceId,
        'payment_intent_id_filter' => (string) ($paymentIntentId ?? ''),
        'payment_intent_id_match' => (string) ($paymentIntentId ?? ''),
        'existing_subscription_id' => $existingSubscriptionId,
        'new_subscription_id_exists' => $newSubscriptionId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
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

function stripeFinalizerPromotionSlotMap(PDO $db, array $promotionIds): array {
    $promotionIds = array_values(array_filter(array_unique(array_map('strval', $promotionIds))));
    if (empty($promotionIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($promotionIds), '?'));
    $stmt = $db->prepare("
        SELECT id, slot_type
        FROM promotions
        WHERE id IN ({$placeholders})
    ");
    $stmt->execute($promotionIds);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string) $row['id']] = (string) $row['slot_type'];
    }

    return $map;
}

function stripeFinalizerActivePromotionSlotConflict(
    PDO $db,
    string $userId,
    string $chatbotId,
    string $slotType,
    string $excludeSubscriptionId = ''
): ?array {
    $params = [
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
        'slot_type' => $slotType,
    ];
    $excludeSql = '';
    if ($excludeSubscriptionId !== '') {
        $excludeSql = 'AND s.id <> :exclude_subscription_id';
        $params['exclude_subscription_id'] = $excludeSubscriptionId;
    }

    $stmt = $db->prepare("
        SELECT s.id, s.stripe_subscription_id
        FROM subscriptions s
        JOIN promotions p ON p.id = s.promotions_id
        WHERE s.user_id = :user_id
          AND s.chatbot_id = :chatbot_id
          AND s.subscription_type = 'promotion'
          AND s.status IN ('active', 'trialing', 'past_due')
          AND s.end_date > NOW()
          AND p.slot_type = :slot_type
          {$excludeSql}
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function stripeFinalizerCancelPendingDuplicatePayment(
    PDO $db,
    string $userId,
    string $paymentId,
    string $stripeInvoiceId,
    ?string $invoiceUrl,
    string $message
): void {
    if ($paymentId === '') {
        return;
    }

    $db->prepare("
        UPDATE payments
        SET status = 'cancelled',
            stripe_invoice_id = COALESCE(stripe_invoice_id, :stripe_invoice_id),
            invoice_url = COALESCE(invoice_url, :invoice_url),
            metadata = JSON_SET(COALESCE(metadata, JSON_OBJECT()), '$.duplicate_slot_cancelled', true),
            failure_code = NULL,
            failure_message = :failure_message,
            updated_at = NOW()
        WHERE id = :id
          AND user_id = :user_id
          AND status IN ('pending', 'processing')
    ")->execute([
        'stripe_invoice_id' => $stripeInvoiceId,
        'invoice_url' => $invoiceUrl,
        'failure_message' => $message,
        'id' => $paymentId,
        'user_id' => $userId,
    ]);
}

function stripeFinalizerRemoveDuplicateStripeItems(
    string $stripeSubscriptionId,
    $stripeSubscription,
    array $duplicateItems
): void {
    if ($stripeSubscriptionId === '' || empty($duplicateItems)) {
        return;
    }

    $items = [];
    foreach ($duplicateItems as $item) {
        $itemId = stripeFinalizerFindStripeSubscriptionItemId(
            $stripeSubscription,
            (string) ($item['promotion_id'] ?? ''),
            (string) ($item['subscription_id'] ?? '')
        );

        if ($itemId) {
            $items[] = [
                'id' => $itemId,
                'deleted' => true,
            ];
        }
    }

    if (empty($items)) {
        stripeCheckoutLog('finalize_invoice.duplicate_slot_stripe_cleanup_skipped', [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'reason' => 'No matching Stripe subscription items found.',
        ]);
        return;
    }

    try {
        \Stripe\Subscription::update($stripeSubscriptionId, [
            'items' => $items,
            'proration_behavior' => 'none',
        ]);

        stripeCheckoutLog('finalize_invoice.duplicate_slot_stripe_items_removed', [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'item_count' => count($items),
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        stripeCheckoutLog('finalize_invoice.duplicate_slot_stripe_cleanup_failed', stripeCheckoutExceptionContext($e) + [
            'stripe_subscription_id' => $stripeSubscriptionId,
            'item_count' => count($items),
        ]);
    }
}

function stripeFinalizerPromotionLockName(string $userId, string $chatbotId): string {
    return 'promotion-slot:' . substr(hash('sha256', $userId . ':' . $chatbotId), 0, 48);
}

function stripeFinalizerAcquirePromotionLock(PDO $db, string $lockName): bool {
    $stmt = $db->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $stmt->execute(['lock_name' => $lockName]);

    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Could not lock promotion checkout finalization. Please retry.');
    }

    return true;
}

function stripeFinalizerReleasePromotionLock(PDO $db, string $lockName): void {
    try {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => $lockName]);
    } catch (Throwable $e) {
        stripeCheckoutLog('finalize_invoice.promotion_lock_release_failed', [
            'message' => $e->getMessage(),
        ]);
    }
}

function stripeCancelDuplicatePendingPromotionPaymentsForUser(PDO $db, string $userId): int {
    $stmt = $db->prepare("
        UPDATE payments pending_pay
        JOIN promotions pending_p
          ON pending_p.id = JSON_UNQUOTE(JSON_EXTRACT(pending_pay.metadata, '$.promotion_id'))
        JOIN subscriptions active_sub
          ON active_sub.user_id = pending_pay.user_id
         AND active_sub.chatbot_id = pending_pay.chatbot_id
         AND active_sub.subscription_type = 'promotion'
         AND active_sub.status IN ('active', 'trialing', 'past_due')
         AND active_sub.end_date > NOW()
        JOIN promotions active_p
          ON active_p.id = active_sub.promotions_id
         AND active_p.slot_type = pending_p.slot_type
        SET pending_pay.status = 'cancelled',
            pending_pay.failure_code = NULL,
            pending_pay.failure_message = 'Promotion slot is already active for this chatbot.',
            pending_pay.invoice_url = NULL,
            pending_pay.metadata = JSON_SET(
                COALESCE(pending_pay.metadata, JSON_OBJECT()),
                '$.cancelled_reason', 'duplicate_active_promotion_slot'
            ),
            pending_pay.updated_at = NOW()
        WHERE pending_pay.user_id = :user_id
          AND pending_pay.payment_method = 'stripe'
          AND pending_pay.status IN ('pending', 'processing')
          AND pending_pay.chatbot_id IS NOT NULL
          AND (
              pending_pay.subscription_id IS NULL
              OR pending_pay.subscription_id <> active_sub.id
          )
    ");
    $stmt->execute(['user_id' => $userId]);

    return $stmt->rowCount();
}

function stripeCancelPendingPaymentsForStripeSubscription(PDO $db, string $stripeSubscriptionId, string $message): int {
    if ($stripeSubscriptionId === '') {
        return 0;
    }

    $stmt = $db->prepare("
        UPDATE payments
        SET status = 'cancelled',
            failure_code = NULL,
            failure_message = :failure_message,
            metadata = JSON_SET(
                COALESCE(metadata, JSON_OBJECT()),
                '$.cancelled_reason', 'stripe_subscription_cancelled'
            ),
            updated_at = NOW()
        WHERE payment_method = 'stripe'
          AND status IN ('pending', 'processing')
          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.stripe_subscription_id')) = :stripe_subscription_id
    ");
    $stmt->execute([
        'failure_message' => $message,
        'stripe_subscription_id' => $stripeSubscriptionId,
    ]);

    return $stmt->rowCount();
}

function stripeFinalizerPromotionLabel(string $slotType): string {
    return match ($slotType) {
        'Homepage' => 'Homepage Visibility',
        'Chatbots page' => 'Chatbots Page Visibility',
        default => $slotType ?: 'Promotion',
    };
}

function stripeFinalizerPromotionProductId(string $promotionId): string {
    return 'cs_promo_' . substr(str_replace('-', '', strtolower($promotionId)), 0, 24);
}

function stripeFinalizerGetOrCreateStripePromotionProduct(array $promotion): string {
    $promotionId = (string) ($promotion['promotion_id'] ?? $promotion['promotions_id'] ?? $promotion['id'] ?? '');
    if ($promotionId === '') {
        throw new RuntimeException('Promotion ID is missing for Stripe product.');
    }

    $productId = stripeFinalizerPromotionProductId($promotionId);
    $slotType = (string) ($promotion['slot_type'] ?? $promotion['plan_name'] ?? '');
    $label = stripeFinalizerPromotionLabel($slotType);

    try {
        $product = \Stripe\Product::retrieve($productId);
        if (!empty($product->id) && empty($product->deleted)) {
            return (string) $product->id;
        }
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        // Product does not exist yet.
    }

    $createParams = [
        'id' => $productId,
        'name' => 'ChatbotScout - ' . $label,
        'metadata' => [
            'promotion_id' => $promotionId,
            'slot_type' => $slotType,
        ],
    ];

    try {
        $product = \Stripe\Product::create($createParams);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        unset($createParams['id']);
        $product = \Stripe\Product::create($createParams);
    }

    return (string) $product->id;
}

function stripeFinalizerStripeAmountToCents(float $amount): int {
    return (int) round($amount * 100);
}

function stripeFinalizerCreatePromotionPrice(array $promotion, float $amount, string $currency, string $interval, string $pricingMode): string {
    $promotionId = (string) ($promotion['promotion_id'] ?? $promotion['promotions_id'] ?? $promotion['id'] ?? '');
    $productId = stripeFinalizerGetOrCreateStripePromotionProduct($promotion);

    $price = \Stripe\Price::create([
        'currency' => strtolower($currency),
        'product' => $productId,
        'unit_amount' => stripeFinalizerStripeAmountToCents($amount),
        'recurring' => ['interval' => $interval],
        'tax_behavior' => 'exclusive',
        'metadata' => [
            'promotion_id' => $promotionId,
            'slot_type' => (string) ($promotion['slot_type'] ?? $promotion['plan_name'] ?? ''),
            'pricing_mode' => $pricingMode,
        ],
    ]);

    return (string) $price->id;
}

function stripeFinalizerIntervalForBillingPeriod(string $billingPeriod): string {
    return $billingPeriod === 'yearly' ? 'year' : 'month';
}

function stripeFinalizerFindStripeSubscriptionItemId($stripeSubscription, string $promotionId, string $subscriptionId): ?string {
    foreach (($stripeSubscription->items->data ?? []) as $item) {
        $metadata = $item->metadata ?? null;
        $priceMetadata = $item->price->metadata ?? null;
        $productId = stripeObjectId($item->price->product ?? null);

        $itemPromotionId = $metadata->promotion_id ?? ($priceMetadata->promotion_id ?? null);
        $itemSubscriptionId = $metadata->subscription_id ?? null;

        if ((string) $itemPromotionId === $promotionId || (string) $itemSubscriptionId === $subscriptionId) {
            return (string) $item->id;
        }

        if ($productId && $productId === stripeFinalizerPromotionProductId($promotionId)) {
            return (string) $item->id;
        }
    }

    return null;
}

function stripeFinalizeBundleUpgradeCurrentBundlePrices(
    string $stripeSubscriptionId,
    array $rows,
    string $billingPeriod
): void {
    $stripeSubscription = \Stripe\Subscription::retrieve([
        'id' => $stripeSubscriptionId,
        'expand' => ['items.data.price.product'],
    ]);

    $currency = strtolower((string) ($rows[0]['currency_code'] ?? 'eur'));
    $interval = stripeFinalizerIntervalForBillingPeriod($billingPeriod);
    $items = [];

    foreach ($rows as $row) {
        $promotionId = (string) ($row['promotions_id'] ?? $row['promotion_id'] ?? '');
        $subscriptionId = (string) ($row['id'] ?? '');
        $itemId = stripeFinalizerFindStripeSubscriptionItemId($stripeSubscription, $promotionId, $subscriptionId);
        if (!$itemId) {
            throw new RuntimeException('Could not find Stripe subscription item for bundle repricing.');
        }

        $items[] = [
            'id' => $itemId,
            'price' => stripeFinalizerCreatePromotionPrice(
                $row,
                (float) $row['bundle_amount'],
                $currency,
                $interval,
                'bundle'
            ),
            'quantity' => 1,
            'metadata' => [
                'promotion_id' => $promotionId,
                'subscription_id' => $subscriptionId,
                'pricing_mode' => 'bundle',
            ],
        ];
    }

    if (empty($items)) {
        return;
    }

    \Stripe\Subscription::update($stripeSubscriptionId, [
        'items' => $items,
        'proration_behavior' => 'none',
        'payment_behavior' => 'allow_incomplete',
        'expand' => ['items.data.price.product'],
    ]);

    stripeCheckoutLog('bundle_upgrade.current_items_repriced_without_proration', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'subscription_ids' => implode(',', array_map(static fn ($row) => (string) ($row['id'] ?? ''), $rows)),
        'billing_period' => $billingPeriod,
    ]);
}

function stripeFinalizerSubscriptionPhaseItems($stripeSubscription): array {
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

function stripeFinalizerCurrentPeriodEndTimestampForRows(array $rows, $stripeSubscription): int {
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

function stripeFinalizerSubscriptionGroupMetadata(array $rows, string $billingPeriod, bool $isBundle, array $extra = []): array {
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
        'total_amount' => number_format((float) $totalAmount, 2, '.', ''),
        'period_type' => $billingPeriod,
        'currency_id' => (string) ($rows[0]['currency_id'] ?? ''),
        'is_bundle' => $isBundle ? '1' : '0',
    ], $extra);
}

function stripeFinalizerLoadBundleUpgradeScheduleRows(
    PDO $db,
    string $userId,
    string $chatbotId,
    string $existingSubscriptionId,
    string $newSubscriptionId,
    string $periodType
): array {
    $stmt = $db->prepare("
        SELECT
            s.id,
            s.user_id,
            s.chatbot_id,
            s.promotions_id,
            s.end_date,
            p.slot_type,
            p.price,
            p.discount_percentage,
            p.currency_id,
            c.code AS currency_code
        FROM subscriptions s
        JOIN promotions p ON p.id = s.promotions_id
        JOIN currencies c ON c.id = p.currency_id
        WHERE s.id IN (:existing_subscription_id, :new_subscription_id)
          AND s.user_id = :user_id
          AND s.chatbot_id = :chatbot_id
          AND s.subscription_type = 'promotion'
        FOR UPDATE
    ");
    $stmt->execute([
        'existing_subscription_id' => $existingSubscriptionId,
        'new_subscription_id' => $newSubscriptionId,
        'user_id' => $userId,
        'chatbot_id' => $chatbotId,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['promotion_id'] = (string) $row['promotions_id'];
        $row['billing_period'] = $periodType;
        $row['standalone_amount'] = promotionSingleTotalPrice(
            (float) $row['price'],
            (float) ($row['discount_percentage'] ?? 0),
            $periodType
        );
        $row['bundle_amount'] = promotionBundleLineTotalPrice((float) $row['price'], $periodType);
        $rows[(string) $row['id']] = $row;
    }

    if (empty($rows[$existingSubscriptionId]) || empty($rows[$newSubscriptionId])) {
        throw new RuntimeException('Bundle upgrade subscription rows are missing for Stripe renewal schedule.');
    }

    return $rows;
}

function stripeScheduleBundleUpgradePartialRenewal(
    string $stripeSubscriptionId,
    array $selectedRows,
    array $remainingRows,
    string $billingPeriod,
    $upgradeMetadata
): void {
    if (!$stripeSubscriptionId) {
        throw new RuntimeException('Stripe subscription ID is missing for bundle upgrade schedule.');
    }

    $stripeSubscription = \Stripe\Subscription::retrieve([
        'id' => $stripeSubscriptionId,
        'expand' => ['items.data.price'],
    ]);

    if (!empty($stripeSubscription->cancel_at_period_end)) {
        $stripeSubscription = \Stripe\Subscription::update($stripeSubscriptionId, [
            'cancel_at_period_end' => false,
            'expand' => ['items.data.price'],
        ]);
    }

    $allRows = array_merge($selectedRows, $remainingRows);
    $currentStart = (int) (stripeObjectValue($stripeSubscription, 'current_period_start') ?: time());
    $currentEnd = stripeFinalizerCurrentPeriodEndTimestampForRows($allRows, $stripeSubscription);
    if ($currentEnd <= time()) {
        throw new RuntimeException('Current billing period has already ended.');
    }

    $currentItems = stripeFinalizerSubscriptionPhaseItems($stripeSubscription);
    if (empty($currentItems)) {
        throw new RuntimeException('Stripe subscription items are missing for bundle upgrade schedule.');
    }

    $currency = strtolower((string) ($remainingRows[0]['currency_code'] ?? 'eur'));
    $interval = stripeFinalizerIntervalForBillingPeriod($billingPeriod);
    $remainingIsBundle = count($remainingRows) > 1;
    $futureItems = [];

    foreach ($remainingRows as $row) {
        $amount = $remainingIsBundle ? (float) $row['bundle_amount'] : (float) $row['standalone_amount'];
        $futureItems[] = [
            'price' => stripeFinalizerCreatePromotionPrice(
                $row,
                $amount,
                $currency,
                $interval,
                $remainingIsBundle ? 'bundle' : 'single'
            ),
            'quantity' => 1,
        ];
    }

    $clearUpgradeMetadata = [
        'chatbot_name' => (string) stripePaymentMetadataValue($upgradeMetadata, 'chatbot_name', ''),
        'payment_ids' => '',
        'duration_days' => (string) stripePaymentMetadataValue($upgradeMetadata, 'duration_days', ($billingPeriod === 'yearly' ? '365' : '30')),
        'upgrade_type' => '',
        'upgrade_existing_subscription_id' => '',
        'upgrade_new_subscription_id' => '',
        'upgrade_existing_promotion_id' => '',
        'upgrade_new_promotion_id' => '',
        'upgrade_payment_id' => '',
    ];

    $pendingMetadata = stripeFinalizerSubscriptionGroupMetadata(
        $allRows,
        $billingPeriod,
        true,
        array_merge($clearUpgradeMetadata, [
            'partial_cancel_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $selectedRows)),
            'partial_cancel_effective_at' => (string) $currentEnd,
        ])
    );

    $futureMetadata = stripeFinalizerSubscriptionGroupMetadata(
        $remainingRows,
        $billingPeriod,
        $remainingIsBundle,
        array_merge($clearUpgradeMetadata, [
            'partial_cancel_subscription_ids' => '',
            'partial_cancel_effective_at' => '',
        ])
    );

    $scheduleId = stripeObjectId(stripeObjectValue($stripeSubscription, 'schedule'));
    if ($scheduleId) {
        \Stripe\SubscriptionSchedule::retrieve($scheduleId);
    } else {
        $schedule = \Stripe\SubscriptionSchedule::create([
            'from_subscription' => $stripeSubscriptionId,
        ]);
        $scheduleId = (string) $schedule->id;
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

    \Stripe\Subscription::update($stripeSubscriptionId, [
        'metadata' => $pendingMetadata,
    ]);

    stripeCheckoutLog('bundle_upgrade.partial_renewal_schedule_updated', [
        'stripe_subscription_id' => $stripeSubscriptionId,
        'schedule_id' => $scheduleId,
        'selected_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $selectedRows)),
        'remaining_subscription_ids' => implode(',', array_map(static fn ($row) => (string) $row['id'], $remainingRows)),
        'future_pricing_mode' => $remainingIsBundle ? 'bundle' : 'single',
    ]);
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

    if ((string) stripePaymentMetadataValue($metadata, 'upgrade_type', '') === 'bundle_upgrade'
        && $status !== 'cancelled'
    ) {
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

    if ($status === 'cancelled') {
        stripeCancelPendingPaymentsForStripeSubscription(
            $db,
            $stripeSubscriptionId,
            'Stripe subscription was cancelled before payment completed.'
        );
    }

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

    syncChatbotsSponsoredTagsForStripeSubscription($db, $stripeSubscriptionId);
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

function markStripeInvoiceCancelled(PDO $db, $invoice, ?string $expectedUserId = null): void {
    $stripeInvoiceId = (string) stripeObjectValue($invoice, 'id', '');
    if ($stripeInvoiceId === '') {
        return;
    }

    $params = [
        'stripe_invoice_id' => $stripeInvoiceId,
        'failure_message' => 'Stripe invoice expired before payment was completed.',
    ];
    $userScope = '';
    if ($expectedUserId !== null) {
        $userScope = ' AND user_id = :user_id';
        $params['user_id'] = $expectedUserId;
    }

    $db->prepare("
        UPDATE payments
        SET status = 'cancelled',
            failure_code = NULL,
            failure_message = :failure_message,
            metadata = JSON_SET(
                COALESCE(metadata, JSON_OBJECT()),
                '$.cancelled_reason', 'stripe_invoice_void',
                '$.stripe_invoice_status', 'void'
            ),
            updated_at = NOW()
        WHERE status IN ('pending', 'processing')
          AND stripe_invoice_id = :stripe_invoice_id{$userScope}
    ")->execute($params);
}

function markStripePaymentIntentProcessing(PDO $db, $paymentIntent): void {
    $paymentIntentId = (string) stripeObjectValue($paymentIntent, 'id', '');
    if ($paymentIntentId === '') {
        return;
    }

    $db->prepare("
        UPDATE payments
        SET status = 'processing',
            updated_at = NOW()
        WHERE status = 'pending'
          AND stripe_payment_intent_id = :stripe_payment_intent_id
    ")->execute(['stripe_payment_intent_id' => $paymentIntentId]);
}

function syncStripeInvoicePaymentStatus(PDO $db, $invoice, ?string $expectedUserId = null): array {
    $invoiceStatus = (string) stripeObjectValue($invoice, 'status', '');
    $invoicePaid = (bool) stripeObjectValue($invoice, 'paid', false);
    $stripeInvoiceId = (string) stripeObjectValue($invoice, 'id', '');

    if ($invoiceStatus === 'paid' || $invoicePaid) {
        return finalizeStripePaidInvoice($db, $invoice, $expectedUserId);
    }

    if ($invoiceStatus === 'void') {
        markStripeInvoiceCancelled($db, $invoice, $expectedUserId);
        return ['finalized' => false, 'status' => 'void'];
    }

    if ($invoiceStatus === 'uncollectible') {
        markStripeInvoicePaymentFailed($db, $invoice);
        return ['finalized' => false, 'status' => 'uncollectible'];
    }

    if ($stripeInvoiceId === '') {
        return ['finalized' => false, 'status' => $invoiceStatus ?: 'unknown'];
    }

    $paymentIntent = stripeObjectValue($invoice, 'payment_intent');
    if (is_string($paymentIntent) && $paymentIntent !== '') {
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntent);
    }
    $paymentIntentStatus = (string) stripePaymentIntentValue($paymentIntent, 'status', '');
    $localStatus = $paymentIntentStatus === 'processing' ? 'processing' : 'pending';

    $params = [
        'status' => $localStatus,
        'stripe_invoice_id' => $stripeInvoiceId,
    ];
    $userScope = '';
    if ($expectedUserId !== null) {
        $userScope = ' AND user_id = :user_id';
        $params['user_id'] = $expectedUserId;
    }

    $db->prepare("
        UPDATE payments
        SET status = :status,
            updated_at = NOW()
        WHERE status IN ('pending', 'processing')
          AND stripe_invoice_id = :stripe_invoice_id{$userScope}
    ")->execute($params);

    return [
        'finalized' => false,
        'status' => $invoiceStatus ?: 'open',
        'payment_intent_status' => $paymentIntentStatus,
    ];
}

function syncRecentStripeInvoicesForUser(PDO $db, string $userId, int $limit = 5): void {
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
            $invoice = \Stripe\Invoice::retrieve([
                'id' => $invoiceId,
                'expand' => ['payment_intent'],
            ]);
            syncStripeInvoicePaymentStatus($db, $invoice, $userId);
        } catch (Exception $e) {
            error_log('Stripe invoice sync skipped ' . $invoiceId . ': ' . $e->getMessage());
        }
    }
}
