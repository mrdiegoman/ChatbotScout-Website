<?php
/**
 * API: Get Currencies
 * Returns all active currencies from DB for filter UI
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

try {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT code, name, symbol
        FROM currencies
        WHERE is_active = 1
        ORDER BY code ASC
    ");
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'currencies' => $currencies]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load currencies']);
}