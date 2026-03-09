<?php
/**
 * AJAX Endpoint - Get Features
 *
 * Vracia všetky features z DB rozdelené na basic (advanced=0) a advanced (advanced=1).
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // 5 min cache - features sa nemenia často

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT id, name, advanced
        FROM `features`
        WHERE EXISTS (
            SELECT 1 FROM chatbot_features cf
            WHERE cf.feature_id = features.id
            AND (cf.boolean_value = 1 OR cf.text_value IS NOT NULL)
        )
        ORDER BY advanced ASC, name ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $basic    = [];
    $advanced = [];

    foreach ($rows as $row) {
        $item = ['id' => $row['id'], 'name' => $row['name']];
        if ((bool)$row['advanced']) {
            $advanced[] = $item;
        } else {
            $basic[] = $item;
        }
    }

    echo json_encode([
        'success'  => true,
        'basic'    => $basic,
        'advanced' => $advanced,
    ]);

} catch (Exception $e) {
    error_log('getFeatures error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}