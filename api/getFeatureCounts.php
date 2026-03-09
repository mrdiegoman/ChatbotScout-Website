<?php
/**
 * AJAX Endpoint - Get Feature Counts
 *
 * Pre každú feature vráti počet chatbotov ktoré by zostali
 * ak by user vybral danú feature spolu s aktuálne aktívnymi filtrami.
 *
 * POST JSON: {
 *   ratingStart, ratingEnd,
 *   votesStart, votesEnd,
 *   priceStart, priceEnd,
 *   activeFeatureIds: [],
 *   isFavourites: bool   ← ak true, počíta iba z favoritov usera
 * }
 * Response: { success, counts: { "feature-uuid": 12, ... }, total: 5 }
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

$ratingStart = (isset($data['ratingStart']) && $data['ratingStart'] !== '') ? (float)$data['ratingStart'] : null;
$ratingEnd   = (isset($data['ratingEnd'])   && $data['ratingEnd']   !== '') ? (float)$data['ratingEnd']   : null;
$votesStart  = (isset($data['votesStart'])  && $data['votesStart']  !== '') ? (int)$data['votesStart']    : null;
$votesEnd    = (isset($data['votesEnd'])    && $data['votesEnd']    !== '') ? (int)$data['votesEnd']      : null;
$favoritesStart = (isset($data['favoritesStart']) && $data['favoritesStart'] !== '') ? (int)$data['favoritesStart'] : null;
$favoritesEnd   = (isset($data['favoritesEnd'])   && $data['favoritesEnd']   !== '') ? (int)$data['favoritesEnd']   : null;
$priceStart  = (isset($data['priceStart'])  && $data['priceStart']  !== '') ? (float)$data['priceStart']  : null;
$priceEnd    = (isset($data['priceEnd'])    && $data['priceEnd']    !== '') ? (float)$data['priceEnd']    : null;

// Sanitize activeFeatureIds — must be non-empty strings (UUIDs)
$activeIds   = array_values(array_filter(
    (array)($data['activeFeatureIds'] ?? []),
    fn($id) => is_string($id) && $id !== ''
));
$activeCount = count($activeIds);

// Sanitize activeIntegrationIds
$activeIntIds = array_values(array_filter(
    (array)($data['activeIntegrationIds'] ?? []),
    fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)
));
// activePricingModels
$activePricingModels = array_values(array_filter(
    (array)($data['activePricingModels'] ?? []),
    fn($m) => in_array($m, ['free','freemium','recurring','one-time','usage-based','custom'], true)
));
$activeIntCount = count($activeIntIds);

// isFavourites — restrict counts to current user's favourites only
$isFavourites = !empty($data['isFavourites']) && $isLoggedIn && !empty($user['id']);

try {
    $db = Database::getInstance()->getConnection();

    // ============================================
    // BUILD BASE WHERE for inner subquery
    // ============================================

    $where  = ["c.status = 'active'", "c.deleted_at IS NULL"];
    $params = [];

    if ($isFavourites) {
        $params[':fav_user_id'] = $user['id'];
    }

    if ($ratingStart !== null) { $where[] = 'c.rating_average >= :ratingStart'; $params[':ratingStart'] = $ratingStart; }
    if ($ratingEnd   !== null) { $where[] = 'c.rating_average <= :ratingEnd';   $params[':ratingEnd']   = $ratingEnd;   }
    if ($votesStart  !== null) { $where[] = 'c.rating_count >= :votesStart';    $params[':votesStart']  = $votesStart;  }
    if ($votesEnd    !== null) { $where[] = 'c.rating_count <= :votesEnd';      $params[':votesEnd']    = $votesEnd;    }
    if ($favoritesStart !== null) { $where[] = 'c.favorite_count >= :favoritesStart'; $params[':favoritesStart'] = $favoritesStart; }
    if ($favoritesEnd   !== null) { $where[] = 'c.favorite_count <= :favoritesEnd';   $params[':favoritesEnd']   = $favoritesEnd;   }
    if ($priceStart  !== null) { $where[] = 'c.starting_price >= :priceStart';  $params[':priceStart']  = $priceStart;  }
    if ($priceEnd    !== null) { $where[] = 'c.starting_price <= :priceEnd';    $params[':priceEnd']    = $priceEnd;    }

    if ($activeCount > 0) {
        $afPlaceholders = implode(',', array_map(fn($i) => ":af{$i}", range(0, $activeCount - 1)));
        $where[] = "(
            SELECT COUNT(DISTINCT cf2.feature_id)
            FROM chatbot_features cf2
            WHERE cf2.chatbot_id = c.id
              AND cf2.feature_id IN ({$afPlaceholders})
              AND (cf2.boolean_value = 1 OR cf2.text_value IS NOT NULL)
        ) = {$activeCount}";
        foreach ($activeIds as $i => $id) {
            $params[":af{$i}"] = $id;
        }
    }

    // Integrations — OR logika
    if ($activeIntCount > 0) {
        $intPh = implode(',', array_map(fn($i) => ":ai{$i}", range(0, $activeIntCount - 1)));
        $where[] = "EXISTS (
            SELECT 1 FROM chatbot_integrations ci_fc
            WHERE ci_fc.chatbot_id = c.id
              AND ci_fc.integration_id IN ({$intPh})
              AND ci_fc.is_supported = 1
        )";
        foreach ($activeIntIds as $i => $id) { $params[":ai{$i}"] = $id; }
    }

    // Pricing — OR logika
    if (count($activePricingModels) > 0) {
        $pmPh = implode(',', array_map(fn($i) => ":apm{$i}", range(0, count($activePricingModels) - 1)));
        $where[] = "c.pricing_model IN ({$pmPh})";
        foreach ($activePricingModels as $i => $m) { $params[":apm{$i}"] = $m; }
    }

    $whereStr = implode(' AND ', $where);

    // ============================================
    // INNER FROM — favourites alebo všetky chatboty
    // ============================================

    if ($isFavourites) {
        $innerFrom = "chatbots c
            JOIN favorites fav_inner ON fav_inner.chatbot_id = c.id
                AND fav_inner.user_id = :fav_user_id";
    } else {
        $innerFrom = "chatbots c";
    }

    // ============================================
    // TOTAL COUNT — pre live zobrazenie počtu výsledkov
    // ============================================

    $totalStmt = $db->prepare("SELECT COUNT(*) FROM {$innerFrom} WHERE {$whereStr}");
    foreach ($params as $key => $val) {
        $totalStmt->bindValue($key, $val);
    }
    $totalStmt->execute();
    $total = (int)$totalStmt->fetchColumn();

    // ============================================
    // MAIN QUERY — počty per feature
    // ============================================

    $sql = "
        SELECT
            f.id,
            COUNT(DISTINCT cf.chatbot_id) AS chatbot_count
        FROM features f
        JOIN chatbot_features cf
            ON cf.feature_id = f.id
           AND (cf.boolean_value = 1 OR cf.text_value IS NOT NULL)
        WHERE cf.chatbot_id IN (
            SELECT c.id FROM {$innerFrom} WHERE {$whereStr}
        )
        GROUP BY f.id
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['id']] = (int)$row['chatbot_count'];
    }

    echo json_encode(['success' => true, 'counts' => $counts, 'total' => $total]);

} catch (Exception $e) {
    error_log('getFeatureCounts error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}