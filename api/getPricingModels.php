<?php
/**
 * GET /api/getPricingModels.php
 * Vráti všetky možné pricing modely (hardcoded ENUM z chatbots.pricing_model).
 * Vracia ich vždy všetky — aj keď žiadny chatbot daný model nemá.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600'); // 1h cache — ENUM sa nemení

$models = [
    ['id' => 'free',        'name' => 'Free'],
    ['id' => 'freemium',    'name' => 'Freemium'],
    ['id' => 'recurring',   'name' => 'Recurring'],
    ['id' => 'one-time',    'name' => 'One-time'],
    ['id' => 'usage-based', 'name' => 'Usage-based'],
    ['id' => 'custom',      'name' => 'Custom'],
];

echo json_encode([
    'success' => true,
    'models'  => $models,
]);
