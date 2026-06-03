<?php

function stripePaymentMetadataValue($metadata, string $key, $default = null) {
    if (is_array($metadata)) {
        return $metadata[$key] ?? $default;
    }

    if (is_object($metadata) && isset($metadata->{$key})) {
        return $metadata->{$key};
    }

    return $default;
}

function stripePaymentIntentValue($paymentIntent, string $key, $default = null) {
    if (is_array($paymentIntent)) {
        return $paymentIntent[$key] ?? $default;
    }

    if (is_object($paymentIntent) && isset($paymentIntent->{$key})) {
        return $paymentIntent->{$key};
    }

    return $default;
}

function finalizeStripeSucceededPayment(PDO $db, $paymentIntent, ?string $expectedUserId = null): array {
    $status = (string) stripePaymentIntentValue($paymentIntent, 'status', '');
    if ($status !== 'succeeded') {
        return ['finalized' => false, 'status' => $status];
    }

    $metadata = stripePaymentIntentValue($paymentIntent, 'metadata');
    $userId = (string) stripePaymentMetadataValue($metadata, 'user_id', '');
    $chatbotId = (string) stripePaymentMetadataValue($metadata, 'chatbot_id', '');
    $promotionId = (string) stripePaymentMetadataValue($metadata, 'promotion_id', '');
    $promotionId2 = (string) stripePaymentMetadataValue($metadata, 'promotion_id2', '');
    $paymentId = (string) stripePaymentMetadataValue($metadata, 'payment_id', '');
    $paymentId2 = (string) stripePaymentMetadataValue($metadata, 'payment_id2', '');
    $periodType = (string) stripePaymentMetadataValue($metadata, 'period_type', 'monthly');
    $durationDays = (int) stripePaymentMetadataValue($metadata, 'duration_days', 30);

    if (!$userId || !$chatbotId || !$promotionId || !$paymentId) {
        throw new RuntimeException('Payment metadata is incomplete.');
    }

    if ($expectedUserId !== null && $userId !== $expectedUserId) {
        throw new RuntimeException('Payment does not belong to the current user.');
    }

    $customerId = stripePaymentIntentValue($paymentIntent, 'customer');
    $customerId = $customerId ? (string) $customerId : null;

    $receiptUrl = null;
    $chargeId = null;
    $latestCharge = stripePaymentIntentValue($paymentIntent, 'latest_charge');

    if (!empty($latestCharge)) {
        try {
            $charge = \Stripe\Charge::retrieve((string) $latestCharge);
            $receiptUrl = $charge->receipt_url ?? null;
            $chargeId = $charge->id ?? null;
        } catch (Exception $e) {
            error_log('Stripe: Could not retrieve charge: ' . $e->getMessage());
        }
    }

    $startDate = date('Y-m-d H:i:s');
    $endDate = match($periodType) {
        'daily' => date('Y-m-d H:i:s', strtotime('+' . max(1, $durationDays) . ' days')),
        'weekly' => date('Y-m-d H:i:s', strtotime('+1 week')),
        'monthly' => date('Y-m-d H:i:s', strtotime('+1 month')),
        'yearly' => date('Y-m-d H:i:s', strtotime('+1 year')),
        default => date('Y-m-d H:i:s', strtotime('+1 month')),
    };

    $db->beginTransaction();

    try {
        $subscriptionId = finalizeStripePaymentLine(
            $db,
            $paymentId,
            $userId,
            $chatbotId,
            $promotionId,
            $startDate,
            $endDate,
            $customerId,
            $chargeId,
            $receiptUrl
        );

        $subscriptionId2 = null;
        if ($promotionId2 && $paymentId2) {
            $subscriptionId2 = finalizeStripePaymentLine(
                $db,
                $paymentId2,
                $userId,
                $chatbotId,
                $promotionId2,
                $startDate,
                $endDate,
                $customerId,
                $chargeId,
                $receiptUrl
            );
        }

        $db->commit();

        return [
            'finalized' => true,
            'status' => $status,
            'payment_id' => $paymentId,
            'payment_id2' => $paymentId2 ?: null,
            'subscription_id' => $subscriptionId,
            'subscription_id2' => $subscriptionId2,
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function finalizeStripePaymentLine(
    PDO $db,
    string $paymentId,
    string $userId,
    string $chatbotId,
    string $promotionId,
    string $startDate,
    string $endDate,
    ?string $customerId,
    ?string $chargeId,
    ?string $receiptUrl
): ?string {
    $paymentStmt = $db->prepare("
        SELECT id, status, subscription_id
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
        throw new RuntimeException('Payment record was not found.');
    }

    if ($payment['status'] === 'succeeded' && !empty($payment['subscription_id'])) {
        return $payment['subscription_id'];
    }

    $subscriptionId = $payment['subscription_id'] ?: generateUUID();

    if (empty($payment['subscription_id'])) {
        $db->prepare("
            INSERT INTO subscriptions (
                id, user_id, chatbot_id, subscription_type,
                promotions_id, status, start_date, end_date, auto_renew,
                stripe_customer_id, created_at, updated_at
            ) VALUES (
                :id, :user_id, :chatbot_id, 'promotion',
                :promotions_id, 'active', :start_date, :end_date, 1,
                :stripe_customer_id, NOW(), NOW()
            )
        ")->execute([
            'id' => $subscriptionId,
            'user_id' => $userId,
            'chatbot_id' => $chatbotId,
            'promotions_id' => $promotionId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'stripe_customer_id' => $customerId,
        ]);

        AuditLog::log('subscription', $subscriptionId, 'created',
            null,
            ['status' => 'active', 'chatbot_id' => $chatbotId, 'promotion_id' => $promotionId],
            $userId
        );
    }

    $db->prepare("
        UPDATE payments
        SET status = 'succeeded',
            subscription_id = :subscription_id,
            stripe_charge_id = :charge_id,
            receipt_url = :receipt_url,
            succeeded_at = COALESCE(succeeded_at, NOW()),
            updated_at = NOW()
        WHERE id = :id
    ")->execute([
        'subscription_id' => $subscriptionId,
        'charge_id' => $chargeId,
        'receipt_url' => $receiptUrl,
        'id' => $paymentId,
    ]);

    AuditLog::log('payment', $paymentId, 'updated',
        ['status' => $payment['status']],
        ['status' => 'succeeded', 'stripe_charge_id' => $chargeId],
        $userId
    );

    return $subscriptionId;
}

function syncRecentSucceededStripePaymentsForUser(PDO $db, string $userId, int $limit = 5): void {
    $limit = max(1, min(20, $limit));

    $stmt = $db->prepare("
        SELECT stripe_payment_intent_id
        FROM payments
        WHERE user_id = :user_id
          AND payment_method = 'stripe'
          AND status IN ('pending', 'processing')
          AND stripe_payment_intent_id IS NOT NULL
        GROUP BY stripe_payment_intent_id
        ORDER BY MAX(created_at) DESC
        LIMIT :limit_count
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':limit_count', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $paymentIntentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($paymentIntentIds as $paymentIntentId) {
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            finalizeStripeSucceededPayment($db, $paymentIntent, $userId);
        } catch (Exception $e) {
            error_log('Stripe payment sync skipped ' . $paymentIntentId . ': ' . $e->getMessage());
        }
    }
}
