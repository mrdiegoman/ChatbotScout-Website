<?php
/**
 * API: Get Promotion Plans
 * Returns all active promotion plans with billing periods and currencies
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.slot_type,
            p.price,
            p.discount_percentage,
            p.display_order,
            bp.id   AS billing_period_id,
            bp.name AS billing_period_name,
            bp.type AS billing_period_type,
            bp.duration AS billing_period_duration,
            c.id     AS currency_id,
            c.code   AS currency_code,
            c.symbol AS currency_symbol
        FROM promotions p
        JOIN billing_periods bp ON p.billing_period_id = bp.id
        JOIN currencies      c  ON p.currency_id       = c.id
        WHERE p.is_active   = 1
          AND p.deleted_at  IS NULL
        ORDER BY p.slot_type, p.display_order ASC
    ");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by billing period type
    $grouped = [];
    foreach ($plans as $plan) {
        $grouped[$plan['slot_type']][$plan['billing_period_type']][] = $plan;
    }

    echo json_encode([
        'success' => true,
        'plans'   => $plans,
        'grouped' => $grouped
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load promotion plans'
    ]);
}