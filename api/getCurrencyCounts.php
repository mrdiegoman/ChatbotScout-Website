<?php
/**
 * API: Get Currency Counts
 * Returns chatbot counts per pricing_currency, respecting all active filters
 */

require '../inc/bootstrap.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$ratingStart       = isset($input['ratingStart'])  && $input['ratingStart']  !== '' ? (float)$input['ratingStart']  : null;
$ratingEnd         = isset($input['ratingEnd'])    && $input['ratingEnd']    !== '' ? (float)$input['ratingEnd']    : null;
$votesStart        = isset($input['votesStart'])   && $input['votesStart']   !== '' ? (int)$input['votesStart']    : null;
$votesEnd          = isset($input['votesEnd'])     && $input['votesEnd']     !== '' ? (int)$input['votesEnd']      : null;
$favoritesStart    = isset($input['favoritesStart']) && $input['favoritesStart'] !== '' ? (int)$input['favoritesStart'] : null;
$favoritesEnd      = isset($input['favoritesEnd'])   && $input['favoritesEnd']   !== '' ? (int)$input['favoritesEnd']   : null;
$priceStart        = isset($input['priceStart'])   && $input['priceStart']   !== '' ? (float)$input['priceStart']  : null;
$priceEnd          = isset($input['priceEnd'])     && $input['priceEnd']     !== '' ? (float)$input['priceEnd']    : null;
$isFavourites      = !empty($input['isFavourites']);
$activeFeatureIds  = array_values(array_filter((array)($input['activeFeatureIds']  ?? []), fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)));
$activeIntIds      = array_values(array_filter((array)($input['activeIntegrationIds'] ?? []), fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)));
$activePricingModels = array_values(array_filter((array)($input['activePricingModels'] ?? []), fn($m) => in_array($m, ['free','freemium','recurring','one-time','usage-based','custom'], true)));

$allowedCurrencies = ['USD', 'EUR', 'GBP'];

try {
    $db = Database::getInstance()->getConnection();

    $innerFrom = '`chatbots` c';
    $where     = ["c.status = 'active'", "c.deleted_at IS NULL"];
    $params    = [];

    if ($ratingStart    !== null) { $where[] = 'c.rating_average  >= :ratingStart';    $params[':ratingStart']    = $ratingStart;    }
    if ($ratingEnd      !== null) { $where[] = 'c.rating_average  <= :ratingEnd';      $params[':ratingEnd']      = $ratingEnd;      }
    if ($votesStart     !== null) { $where[] = 'c.rating_count    >= :votesStart';     $params[':votesStart']     = $votesStart;     }
    if ($votesEnd       !== null) { $where[] = 'c.rating_count    <= :votesEnd';       $params[':votesEnd']       = $votesEnd;       }
    if ($favoritesStart !== null) { $where[] = 'c.favorite_count  >= :favoritesStart'; $params[':favoritesStart'] = $favoritesStart; }
    if ($favoritesEnd   !== null) { $where[] = 'c.favorite_count  <= :favoritesEnd';   $params[':favoritesEnd']   = $favoritesEnd;   }
    if ($priceStart     !== null) { $where[] = 'c.starting_price  >= :priceStart';     $params[':priceStart']     = $priceStart;     }
    if ($priceEnd       !== null) { $where[] = 'c.starting_price  <= :priceEnd';       $params[':priceEnd']       = $priceEnd;       }

    if (count($activeFeatureIds) > 0) {
        $fp = implode(',', array_map(fn($i) => ":fid{$i}", range(0, count($activeFeatureIds) - 1)));
        $where[] = "(SELECT COUNT(DISTINCT cf2.feature_id) FROM chatbot_features cf2
                     WHERE cf2.chatbot_id = c.id AND cf2.feature_id IN ({$fp})
                     AND (cf2.boolean_value = 1 OR cf2.text_value IS NOT NULL)) = " . count($activeFeatureIds);
        foreach ($activeFeatureIds as $i => $id) { $params[":fid{$i}"] = $id; }
    }

    if (count($activeIntIds) > 0) {
        $ip = implode(',', array_map(fn($i) => ":iid{$i}", range(0, count($activeIntIds) - 1)));
        $where[] = "EXISTS (SELECT 1 FROM chatbot_integrations ci_f WHERE ci_f.chatbot_id = c.id AND ci_f.integration_id IN ({$ip}) AND ci_f.is_supported = 1)";
        foreach ($activeIntIds as $i => $id) { $params[":iid{$i}"] = $id; }
    }

    if (count($activePricingModels) > 0) {
        $pp = implode(',', array_map(fn($i) => ":pm{$i}", range(0, count($activePricingModels) - 1)));
        $where[] = "c.pricing_model IN ({$pp})";
        foreach ($activePricingModels as $i => $m) { $params[":pm{$i}"] = $m; }
    }

    // Total (without currency filter)
    $totalWhereStr = implode(' AND ', $where);
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM {$innerFrom} WHERE {$totalWhereStr}");
    foreach ($params as $k => $v) $totalStmt->bindValue($k, $v);
    $totalStmt->execute();
    $total = (int)$totalStmt->fetchColumn();

    // Count per currency
    $counts = [];
    foreach ($allowedCurrencies as $code) {
        $cWhere  = array_merge($where, ["c.pricing_currency = :cur_code"]);
        $cParams = array_merge($params, [':cur_code' => $code]);
        $cStmt   = $db->prepare("SELECT COUNT(*) FROM {$innerFrom} WHERE " . implode(' AND ', $cWhere));
        foreach ($cParams as $k => $v) $cStmt->bindValue($k, $v);
        $cStmt->execute();
        $counts[$code] = (int)$cStmt->fetchColumn();
    }

    echo json_encode(['success' => true, 'counts' => $counts, 'total' => $total]);

} catch (Exception $e) {
    error_log('getCurrencyCounts error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}