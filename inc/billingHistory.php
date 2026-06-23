<?php

function billingHistoryFilters(array $input): array {
    $dateFilter = $input['billing_date'] ?? 'all';
    $statusFilter = $input['billing_status'] ?? 'all';
    $methodFilter = $input['billing_method'] ?? 'all';

    if (!in_array($dateFilter, ['all', 'this-month', 'last-month', 'this-year'], true)) {
        $dateFilter = 'all';
    }
    if (!in_array($statusFilter, ['all', 'succeeded', 'pending', 'processing', 'failed', 'cancelled', 'refunded'], true)) {
        $statusFilter = 'all';
    }
    if (!in_array($methodFilter, ['all', 'stripe', 'paypal', 'bank_transfer'], true)) {
        $methodFilter = 'all';
    }

    return [
        'date' => $dateFilter,
        'status' => $statusFilter,
        'method' => $methodFilter,
    ];
}

function billingHistoryPage($value): int {
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
}

function billingHistoryStatusLabel(string $status): string {
    return match ($status) {
        'succeeded' => 'Paid',
        'pending' => 'Awaiting payment',
        'processing' => 'Payment processing',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially refunded',
        'cancelled' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function billingHistoryForUser(PDO $db, string $userId, array $filters, int $requestedPage, int $perPage = 10): array {
    $perPage = max(1, min($perPage, 50));
    $where = ['p.user_id = :billing_user_id'];
    $params = ['billing_user_id' => $userId];

    if ($filters['status'] !== 'all') {
        $where[] = 'p.status = :billing_status';
        $params['billing_status'] = $filters['status'];
    }
    if ($filters['method'] !== 'all') {
        $where[] = 'p.payment_method = :billing_method';
        $params['billing_method'] = $filters['method'];
    }
    if ($filters['date'] !== 'all') {
        $today = new DateTimeImmutable('today');
        if ($filters['date'] === 'this-month') {
            $dateStart = $today->modify('first day of this month');
            $dateEnd = $dateStart->modify('+1 month');
        } elseif ($filters['date'] === 'last-month') {
            $dateEnd = $today->modify('first day of this month');
            $dateStart = $dateEnd->modify('-1 month');
        } else {
            $dateStart = $today->setDate((int) $today->format('Y'), 1, 1);
            $dateEnd = $dateStart->modify('+1 year');
        }

        $where[] = 'p.created_at >= :billing_date_start AND p.created_at < :billing_date_end';
        $params['billing_date_start'] = $dateStart->format('Y-m-d H:i:s');
        $params['billing_date_end'] = $dateEnd->format('Y-m-d H:i:s');
    }

    $whereSql = implode("\n          AND ", $where);
    $countStmt = $db->prepare("SELECT COUNT(*) FROM payments p WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
    $page = $totalPages > 0 ? min(max(1, $requestedPage), $totalPages) : 1;
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare("
        SELECT
            p.*,
            c.symbol AS currency_symbol,
            c.code AS currency_code,
            COALESCE(pr.slot_type, metadata_pr.slot_type) AS package_slot_type,
            COALESCE(ch.name, payment_ch.name) AS package_chatbot_name,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.billing_period')),
                JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.period_type'))
            ) AS package_metadata_billing_period,
            COALESCE(bp.type, metadata_bp.type) AS package_plan_billing_period,
            s.start_date AS package_start_date,
            s.end_date AS package_end_date
        FROM payments p
        JOIN currencies c ON p.currency_id = c.id
        LEFT JOIN subscriptions s ON p.subscription_id = s.id
        LEFT JOIN promotions pr ON s.promotions_id = pr.id
        LEFT JOIN billing_periods bp ON pr.billing_period_id = bp.id
        LEFT JOIN promotions metadata_pr ON metadata_pr.id = JSON_UNQUOTE(JSON_EXTRACT(p.metadata, '$.promotion_id'))
        LEFT JOIN billing_periods metadata_bp ON metadata_pr.billing_period_id = metadata_bp.id
        LEFT JOIN chatbots ch ON s.chatbot_id = ch.id
        LEFT JOIN chatbots payment_ch ON p.chatbot_id = payment_ch.id
        WHERE {$whereSql}
        ORDER BY p.created_at DESC
        LIMIT :billing_limit OFFSET :billing_offset
    ");
    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':billing_limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':billing_offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payments as &$payment) {
        $payment['package_name'] = match ($payment['package_slot_type'] ?? '') {
            'Homepage' => 'Homepage Visibility',
            'Chatbots page' => 'Chatbots Page Visibility',
            default => $payment['package_slot_type'] ?: 'Promotion',
        };

        $billingPeriod = promotionNormalizeBillingPeriod($payment['package_metadata_billing_period'] ?? '', '');
        if (!$billingPeriod) {
            $billingPeriod = promotionBillingPeriodFromDates(
                $payment['package_start_date'] ?? null,
                $payment['package_end_date'] ?? null,
                $payment['package_plan_billing_period'] ?? ''
            );
        }

        $payment['package_meta'] = implode(' · ', array_filter([
            $payment['package_chatbot_name'] ?? '',
            $billingPeriod ? promotionBillingPeriodLabel($billingPeriod) : '',
        ]));
        $payment['status_label'] = billingHistoryStatusLabel((string) $payment['status']);
        $payment['date_label'] = date('M d, Y', strtotime((string) $payment['created_at']));
        $payment['method_label'] = ucfirst(str_replace('_', ' ', (string) $payment['payment_method']));
        $payment['amount_label'] = (string) $payment['currency_symbol'] . number_format((float) $payment['amount'], 2);
    }
    unset($payment);

    return [
        'payments' => $payments,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_filters' => $filters['date'] !== 'all'
            || $filters['status'] !== 'all'
            || $filters['method'] !== 'all',
    ];
}
