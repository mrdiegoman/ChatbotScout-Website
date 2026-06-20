<?php
/**
 * POST /api/getPricingCounts.php
 *
 * Pre každý pricing_model vráti počet chatbotov ktoré by zostali
 * ak by user vybral daný model spolu s aktuálne aktívnymi filtrami.
 * OR logika — chatbot musí mať aspoň jeden z vybraných modelov.
 *
 * POST JSON: {
 *   ratingStart, ratingEnd,
 *   votesStart, votesEnd,
 *   favoritesStart, favoritesEnd,
 *   priceStart, priceEnd,
 *   activeFeatureIds: [],
 *   activeIntegrationIds: [],
 *   activePricingModels: [],
 *   isFavourites: bool
 * }
 * Response: { success, counts: { "free": 5, "freemium": 12, ... }, total: N }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// ============================================
// PARSE INPUTS
// ============================================

$ratingStart    = (isset($data['ratingStart'])    && $data['ratingStart']    !== '') ? (float)$data['ratingStart']    : null;
$ratingEnd      = (isset($data['ratingEnd'])      && $data['ratingEnd']      !== '') ? (float)$data['ratingEnd']      : null;
$votesStart     = (isset($data['votesStart'])     && $data['votesStart']     !== '') ? (int)$data['votesStart']       : null;
$votesEnd       = (isset($data['votesEnd'])       && $data['votesEnd']       !== '') ? (int)$data['votesEnd']         : null;
$favoritesStart = (isset($data['favoritesStart']) && $data['favoritesStart'] !== '') ? (int)$data['favoritesStart']   : null;
$favoritesEnd   = (isset($data['favoritesEnd'])   && $data['favoritesEnd']   !== '') ? (int)$data['favoritesEnd']     : null;
$priceStart     = (isset($data['priceStart'])     && $data['priceStart']     !== '') ? (float)$data['priceStart']     : null;
$priceEnd       = (isset($data['priceEnd'])       && $data['priceEnd']       !== '') ? (float)$data['priceEnd']       : null;

$allowedModels = ['free', 'freemium', 'recurring', 'one-time', 'usage-based', 'custom'];

// activeFeatureIds
$activeIds   = array_values(array_filter(
    (array)($data['activeFeatureIds'] ?? []),
    fn($id) => is_string($id) && $id !== ''
));
$activeCount = count($activeIds);

// activeIntegrationIds
$activeIntIds  = array_values(array_filter(
    (array)($data['activeIntegrationIds'] ?? []),
    fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)
));
$activeIntCount = count($activeIntIds);

// activePricingModels — OR, použijeme pri total count
$activePricingModels = array_values(array_filter(
    (array)($data['activePricingModels'] ?? []),
    fn($m) => in_array($m, $allowedModels, true)
));
$hasFreeTrial = filter_var($data['hasFreeTrial'] ?? false, FILTER_VALIDATE_BOOLEAN);

$isFavourites = !empty($data['isFavourites']) && $isLoggedIn && !empty($user['id']);

$allowedCurrencyCodes = ['USD', 'EUR', 'GBP'];
$activeCurrencies = array_values(array_filter(
    (array)($data['activeCurrencies'] ?? []),
    fn($c) => in_array($c, $allowedCurrencyCodes, true)
));

try {
    $db = Database::getInstance()->getConnection();

    // ============================================
    // BUILD BASE WHERE (bez pricing filtru)
    // ============================================

    $where  = ["c.status = 'active'", "c.deleted_at IS NULL"];
    $params = [];

    if ($isFavourites) {
        $params[':fav_user_id'] = $user['id'];
    }

    if ($ratingStart    !== null) { $where[] = 'c.rating_average >= :ratingStart';   $params[':ratingStart']    = $ratingStart;    }
    if ($ratingEnd      !== null) { $where[] = 'c.rating_average <= :ratingEnd';     $params[':ratingEnd']      = $ratingEnd;      }
    if ($votesStart     !== null) { $where[] = 'c.rating_count >= :votesStart';      $params[':votesStart']     = $votesStart;     }
    if ($votesEnd       !== null) { $where[] = 'c.rating_count <= :votesEnd';        $params[':votesEnd']       = $votesEnd;       }
    if ($favoritesStart !== null) { $where[] = 'c.favorite_count >= :favoritesStart'; $params[':favoritesStart'] = $favoritesStart; }
    if ($favoritesEnd   !== null) { $where[] = 'c.favorite_count <= :favoritesEnd';   $params[':favoritesEnd']   = $favoritesEnd;   }
    if ($priceStart     !== null) { $where[] = 'c.starting_price >= :priceStart';    $params[':priceStart']     = $priceStart;     }
    if ($priceEnd       !== null) { $where[] = 'c.starting_price <= :priceEnd';      $params[':priceEnd']       = $priceEnd;       }
    if ($hasFreeTrial) { $where[] = 'c.has_free_trial = 1'; }

    // Features — AND logika
    if ($activeCount > 0) {
        $afPh = implode(',', array_map(fn($i) => ":af{$i}", range(0, $activeCount - 1)));
        $where[] = "(
            SELECT COUNT(DISTINCT cf2.feature_id)
            FROM chatbot_features cf2
            WHERE cf2.chatbot_id = c.id
              AND cf2.feature_id IN ({$afPh})
              AND (cf2.boolean_value = 1 OR cf2.text_value IS NOT NULL)
        ) = {$activeCount}";
        foreach ($activeIds as $i => $id) { $params[":af{$i}"] = $id; }
    }

    // Integrations — OR logika
    if ($activeIntCount > 0) {
        $intPh = implode(',', array_map(fn($i) => ":ai{$i}", range(0, $activeIntCount - 1)));
        $where[] = "EXISTS (
            SELECT 1 FROM chatbot_integrations ci_pc
            WHERE ci_pc.chatbot_id = c.id
              AND ci_pc.integration_id IN ({$intPh})
              AND ci_pc.is_supported = 1
        )";
        foreach ($activeIntIds as $i => $id) { $params[":ai{$i}"] = $id; }
    }

    if (count($activeCurrencies) > 0) {
        $cp = implode(',', array_map(fn($i) => ":cur{$i}", range(0, count($activeCurrencies) - 1)));
        $where[] = "c.pricing_currency IN ({$cp})";
        foreach ($activeCurrencies as $i => $c) { $params[":cur{$i}"] = $c; }
    }

    $whereStr = implode(' AND ', $where);

    if ($isFavourites) {
        $innerFrom = "chatbots c JOIN favorites fav_inner ON fav_inner.chatbot_id = c.id AND fav_inner.user_id = :fav_user_id";
    } else {
        $innerFrom = "chatbots c";
    }

    // ============================================
    // TOTAL COUNT — so všetkými aktívnymi pricing modelmi (OR)
    // ============================================

    $totalWhere  = $where;
    $totalParams = $params;

    if (count($activePricingModels) > 0) {
        $pmPh = implode(',', array_map(fn($i) => ":apm{$i}", range(0, count($activePricingModels) - 1)));
        $totalWhere[] = "c.pricing_model IN ({$pmPh})";
        foreach ($activePricingModels as $i => $m) { $totalParams[":apm{$i}"] = $m; }
    }

    $totalWhereStr = implode(' AND ', $totalWhere);
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM {$innerFrom} WHERE {$totalWhereStr}");
    foreach ($totalParams as $key => $val) { $totalStmt->bindValue($key, $val); }
    $totalStmt->execute();
    $total = (int)$totalStmt->fetchColumn();

    // ============================================
    // COUNTS PER PRICING MODEL
    // Každý model: koľko chatbotov matchuje ostatné filtre + má daný model
    // ============================================

    $counts = [];

    foreach ($allowedModels as $model) {
        $modelWhere  = array_merge($where, ["c.pricing_model = :pm_model"]);
        $modelParams = array_merge($params, [':pm_model' => $model]);
        $modelWhereStr = implode(' AND ', $modelWhere);

        $stmt = $db->prepare("SELECT COUNT(*) FROM {$innerFrom} WHERE {$modelWhereStr}");
        foreach ($modelParams as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->execute();
        $counts[$model] = (int)$stmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'counts' => $counts, 'total' => $total]);

} catch (Exception $e) {
    error_log('getPricingCounts error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
