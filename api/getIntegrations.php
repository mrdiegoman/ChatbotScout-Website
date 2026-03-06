<?php
/**
 * GET /api/getIntegrations.php
 * Vráti zoznam všetkých integrácií pre filter popup.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // 5 min cache — integrácie sa menia zriedka

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query("
        SELECT i.id, i.name
        FROM integrations i
        WHERE EXISTS (
            SELECT 1 FROM chatbot_integrations ci
            WHERE ci.integration_id = i.id AND ci.is_supported = 1
        )
        ORDER BY i.name ASC
    ");

    $integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'      => true,
        'integrations' => $integrations,
    ]);

} catch (Exception $e) {
    error_log('getIntegrations error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}