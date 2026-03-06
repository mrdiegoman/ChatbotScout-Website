<?php
/**
 * AJAX Endpoint - Get Integration Counts
 *
 * Pre každú integráciu vráti počet chatbotov ktoré ju majú,
 * kombinovaný so všetkými ostatnými aktívnymi filtrami.
 *
 * POST JSON: {
 *   ratingStart, ratingEnd,
 *   votesStart, votesEnd,
 *   favoritesStart, favoritesEnd,
 *   priceStart, priceEnd,
 *   activeFeatureIds: [],
 *   activeIntegrationIds: [],   ← OR logika (ignorované pri počítaní — každá integrácia sa počíta samostatne)
 *   isFavourites: bool
 * }
 * Response: { success, counts: { "integration-uuid": 12, ... }, total: 5 }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

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

$activeIds = array_values(array_filter(
    (array)($data['activeFeatureIds'] ?? []),
    fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)
));
$activeCount = count($activeIds);

$activeIntIds = array_values(array_filter(
    (array)($data['activeIntegrationIds'] ?? []),
    fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)
));

$isFavourites = !empty($data['isFavourites']) && $isLoggedIn;

try {
    $db = Database::getInstance()->getConnection();

    // ============================================
    // BUILD BASE WHERE (bez integrácie)
    // ============================================

    $where  = ["c.status = 'active'", "c.deleted_at IS NULL"];
    $params = [];

    if ($ratingStart    !== null) { $where[] = 'c.rating_average >= :ratingStart';   $params[':ratingStart']    = $ratingStart;    }
    if ($ratingEnd      !== null) { $where[] = 'c.rating_average <= :ratingEnd';     $params[':ratingEnd']      = $ratingEnd;      }
    if ($votesStart     !== null) { $where[] = 'c.rating_count >= :votesStart';      $params[':votesStart']     = $votesStart;     }
    if ($votesEnd       !== null) { $where[] = 'c.rating_count <= :votesEnd';        $params[':votesEnd']       = $votesEnd;       }
    if ($favoritesStart !== null) { $where[] = 'c.favorite_count >= :favoritesStart';$params[':favoritesStart'] = $favoritesStart; }
    if ($favoritesEnd   !== null) { $where[] = 'c.favorite_count <= :favoritesEnd';  $params[':favoritesEnd']   = $favoritesEnd;   }
    if ($priceStart     !== null) { $where[] = 'c.starting_price >= :priceStart';    $params[':priceStart']     = $priceStart;     }
    if ($priceEnd       !== null) { $where[] = 'c.starting_price <= :priceEnd';      $params[':priceEnd']       = $priceEnd;       }

    // Features — AND logika
    if ($activeCount > 0) {
        $afPlaceholders = implode(',', array_map(fn($i) => ":af{$i}", range(0, $activeCount - 1)));
        $where[] = "(
            SELECT COUNT(DISTINCT cf2.feature_id)
            FROM chatbot_features cf2
            WHERE cf2.chatbot_id = c.id
              AND cf2.feature_id IN ({$afPlaceholders})
              AND (cf2.boolean_value = 1 OR cf2.text_value IS NOT NULL)
        ) = {$activeCount}";
        foreach ($activeIds as $i => $id) { $params[":af{$i}"] = $id; }
    }

    $whereStr = implode(' AND ', $where);

    if ($isFavourites) {
        $innerFrom = "chatbots c JOIN favorites fav_inner ON fav_inner.chatbot_id = c.id AND fav_inner.user_id = :fav_user_id";
        $params[':fav_user_id'] = $user['id'];
    } else {
        $innerFrom = "chatbots c";
    }

    // ============================================
    // TOTAL COUNT — chatboty ktoré matchujú všetky filtre + aktívne integrácie (OR)
    // ============================================

    $totalWhere  = $where;
    $totalParams = $params;

    if (count($activeIntIds) > 0) {
        $intPh = implode(',', array_map(fn($i) => ":ai{$i}", range(0, count($activeIntIds) - 1)));
        $totalWhere[] = "EXISTS (
            SELECT 1 FROM chatbot_integrations ci2
            WHERE ci2.chatbot_id = c.id
              AND ci2.integration_id IN ({$intPh})
              AND ci2.is_supported = 1
        )";
        foreach ($activeIntIds as $i => $id) { $totalParams[":ai{$i}"] = $id; }
    }

    $totalWhereStr = implode(' AND ', $totalWhere);
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM {$innerFrom} WHERE {$totalWhereStr}");
    foreach ($totalParams as $key => $val) { $totalStmt->bindValue($key, $val); }
    $totalStmt->execute();
    $total = (int)$totalStmt->fetchColumn();

    // ============================================
    // COUNTS PER INTEGRATION
    // Každá integrácia: koľko chatbotov má danú integráciu + spĺňa ostatné filtre
    // ============================================

    $sql = "
        SELECT
            i.id,
            COUNT(DISTINCT ci.chatbot_id) AS chatbot_count
        FROM integrations i
        JOIN chatbot_integrations ci
            ON ci.integration_id = i.id
           AND ci.is_supported = 1
        WHERE ci.chatbot_id IN (
            SELECT c.id FROM {$innerFrom} WHERE {$whereStr}
        )
        GROUP BY i.id
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->execute();

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['id']] = (int)$row['chatbot_count'];
    }

    echo json_encode(['success' => true, 'counts' => $counts, 'total' => $total]);

} catch (Exception $e) {
    error_log('getIntegrationCounts error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}