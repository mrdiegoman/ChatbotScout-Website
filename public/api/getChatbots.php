<?php
/**
 * AJAX Endpoint - Get Chatbots
 *
 * Returns paginated, sorted, filtered chatbot listings in JSON format.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$db = Database::getInstance()->getConnection();

// ============================================
// INPUT VALIDATION — pagination + sort
// ============================================

$view = in_array($_GET['view'] ?? 'grid', ['grid', 'list']) ? $_GET['view'] : 'grid';
$sort = $_GET['sort'] ?? 'popularity';
$page = max(1, (int)($_GET['page'] ?? 1));

$limit  = ($view === 'list') ? 6 : 12;
$offset = ($page - 1) * $limit;

$allowedSorts = ['popularity', 'price', 'alphabetical', 'rating', 'number-of-ratings'];
if (!in_array($sort, $allowedSorts)) $sort = 'popularity';

$direction = ($_GET['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// ============================================
// INPUT VALIDATION — filters
// ============================================

$ratingStart = (isset($_GET['ratingStart']) && $_GET['ratingStart'] !== '') ? (float)$_GET['ratingStart'] : null;
$ratingEnd   = (isset($_GET['ratingEnd'])   && $_GET['ratingEnd']   !== '') ? (float)$_GET['ratingEnd']   : null;
$votesStart  = (isset($_GET['votesStart'])  && $_GET['votesStart']  !== '') ? (int)$_GET['votesStart']    : null;
$votesEnd    = (isset($_GET['votesEnd'])    && $_GET['votesEnd']    !== '') ? (int)$_GET['votesEnd']    : null;
$favoritesStart = (isset($_GET['favoritesStart']) && $_GET['favoritesStart'] !== '') ? (int)$_GET['favoritesStart'] : null;
$favoritesEnd   = (isset($_GET['favoritesEnd'])   && $_GET['favoritesEnd']   !== '') ? (int)$_GET['favoritesEnd']   : null;
$priceStart  = (isset($_GET['priceStart'])  && $_GET['priceStart']  !== '') ? (float)$_GET['priceStart']  : null;
$priceEnd    = (isset($_GET['priceEnd'])    && $_GET['priceEnd']    !== '') ? (float)$_GET['priceEnd']    : null;

// features[] — pole UUID
$featureIds  = array_values(array_filter(
    (array)($_GET['features'] ?? []),
    fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)
));
$featureCount = count($featureIds);

// integrations[] — OR logika, pole UUID
$integrationIds = array_values(array_filter(
    (array)($_GET['integrations'] ?? []),
    fn($id) => is_string($id) && preg_match('/^[0-9a-f\-]{36}$/i', $id)
));
$integrationCount = count($integrationIds);

// pricing[] — OR logika, pole stringov (ENUM hodnoty)
$pricingModels = array_values(array_filter(
    (array)($_GET['pricing'] ?? []),
    fn($m) => in_array($m, ['free','freemium','recurring','one-time','usage-based','custom'], true)
));
$pricingCount = count($pricingModels);

$allowedCurrencyCodes = ['USD', 'EUR', 'GBP'];
$currencyCodes = array_values(array_filter(
    (array)($_GET['currency'] ?? []),
    fn($c) => in_array($c, $allowedCurrencyCodes, true)
));
$currencyCount = count($currencyCodes);
$search = trim($_GET['search'] ?? '');

// ============================================
// SORT LOGIC
// ============================================

$dir = $direction === 'asc' ? 'ASC' : 'DESC';

$orderBy = match($sort) {
    'popularity'        => "(c.rating_average * LOG(c.click_count + 1)) $dir, c.click_count $dir, c.rating_count $dir, c.rating_average $dir",
    'alphabetical'      => "c.name " . ($direction === 'asc' ? 'DESC' : 'ASC'),
    'rating'            => "c.rating_average $dir, c.rating_count $dir",
    'number-of-ratings' => "c.rating_count $dir, c.rating_average $dir",
    'price'             => "ISNULL(c.starting_price) ASC, c.starting_price $dir",
    default             => "c.click_count $dir",
};
$orderBy = "c.is_sponsored DESC, " . $orderBy . ", c.created_at DESC";

// ============================================
// BUILD WHERE + PARAMS
// ============================================

$where  = ["c.status = 'active'", "c.deleted_at IS NULL"];
$params = [];

if ($ratingStart !== null) { $where[] = 'c.rating_average >= :ratingStart'; $params[':ratingStart'] = $ratingStart; }
if ($ratingEnd   !== null) { $where[] = 'c.rating_average <= :ratingEnd';   $params[':ratingEnd']   = $ratingEnd;   }
if ($votesStart  !== null) { $where[] = 'c.rating_count >= :votesStart';    $params[':votesStart']  = $votesStart;  }
if ($votesEnd    !== null) { $where[] = 'c.rating_count <= :votesEnd';      $params[':votesEnd']    = $votesEnd;    }
if ($favoritesStart !== null) { $where[] = 'c.favorite_count >= :favoritesStart'; $params[':favoritesStart'] = $favoritesStart; }
if ($favoritesEnd   !== null) { $where[] = 'c.favorite_count <= :favoritesEnd';   $params[':favoritesEnd']   = $favoritesEnd;   }
if ($priceStart  !== null) { $where[] = 'c.starting_price >= :priceStart';  $params[':priceStart']  = $priceStart;  }
if ($priceEnd    !== null) { $where[] = 'c.starting_price <= :priceEnd';    $params[':priceEnd']    = $priceEnd;    }

// Chatbot musí mať VŠETKY vybrané features (relational division)
if ($featureCount > 0) {
    $fp = implode(',', array_map(fn($i) => ":fid{$i}", range(0, $featureCount - 1)));
    $where[] = "(
        SELECT COUNT(DISTINCT cf2.feature_id)
        FROM chatbot_features cf2
        WHERE cf2.chatbot_id = c.id
          AND cf2.feature_id IN ({$fp})
          AND (cf2.boolean_value = 1 OR cf2.text_value IS NOT NULL)
    ) = {$featureCount}";
    foreach ($featureIds as $i => $id) { $params[":fid{$i}"] = $id; }
}

// Integrations — OR logika (chatbot musí mať aspoň jednu)
if ($integrationCount > 0) {
    $ip = implode(',', array_map(fn($i) => ":iid{$i}", range(0, $integrationCount - 1)));
    $where[] = "EXISTS (
        SELECT 1 FROM chatbot_integrations ci_f
        WHERE ci_f.chatbot_id = c.id
          AND ci_f.integration_id IN ({$ip})
          AND ci_f.is_supported = 1
    )";
    foreach ($integrationIds as $i => $id) { $params[":iid{$i}"] = $id; }
}

// Pricing models — OR logika (chatbot musí mať aspoň jeden z vybraných)
if ($pricingCount > 0) {
    $pp = implode(',', array_map(fn($i) => ":pm{$i}", range(0, $pricingCount - 1)));
    $where[] = "c.pricing_model IN ({$pp})";
    foreach ($pricingModels as $i => $m) { $params[":pm{$i}"] = $m; }
}

if ($currencyCount > 0) {
    $cp = implode(',', array_map(fn($i) => ":cur{$i}", range(0, $currencyCount - 1)));
    $where[] = "c.pricing_currency IN ({$cp})";
    foreach ($currencyCodes as $i => $c) { $params[":cur{$i}"] = $c; }
}

if ($search !== '') {
    $where[] = 'c.name LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$whereStr = implode(' AND ', $where);

// ============================================
// COUNT QUERY
// ============================================

$countStmt = $db->prepare("SELECT COUNT(*) FROM `chatbots` c WHERE {$whereStr}");
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total      = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $limit);

// ============================================
// MAIN QUERY
// ============================================

$currentUserId = ($isLoggedIn && !empty($user['id'])) ? $user['id'] : null;

$selectFav = $currentUserId
    ? "CASE WHEN fav.id IS NOT NULL THEN 1 ELSE 0 END AS is_favourited"
    : "0 AS is_favourited";

$joinFav = $currentUserId
    ? "LEFT JOIN `favorites` fav ON fav.chatbot_id = c.id AND fav.user_id = :user_id"
    : "";

$sql = "
    SELECT
        c.id, c.name, c.description, c.logo_url, c.website_url,
        c.has_affiliate_program, c.affiliate_link,
        c.has_free_trial, c.pricing_model,
        c.starting_price, c.pricing_currency,
        c.rating_average, c.rating_count,
        c.click_count, c.favorite_count,
        c.is_sponsored, c.created_at,
        {$selectFav}
    FROM `chatbots` c
    {$joinFav}
    WHERE {$whereStr}
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
if ($currentUserId) $stmt->bindValue(':user_id', $currentUserId, PDO::PARAM_STR);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$chatbots = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($chatbots)) {
    echo json_encode([
        'success' => true, 'chatbots' => [], 'total' => $total,
        'totalPages' => $totalPages, 'currentPage' => $page, 'limit' => $limit,
    ]);
    exit;
}

// ============================================
// FEATURES + TAGS
// ============================================

$chatbotIds   = array_column($chatbots, 'id');
$placeholders = implode(',', array_fill(0, count($chatbotIds), '?'));

$featStmt = $db->prepare("
    SELECT cf.chatbot_id, f.name AS feature_name
    FROM `chatbot_features` cf
    JOIN `features` f ON cf.feature_id = f.id
    WHERE cf.chatbot_id IN ({$placeholders})
      AND (cf.boolean_value = 1 OR cf.text_value IS NOT NULL)
");
$featStmt->execute($chatbotIds);
$featureMap = [];
foreach ($featStmt->fetchAll(PDO::FETCH_ASSOC) as $feat) {
    $featureMap[$feat['chatbot_id']][] = $feat['feature_name'];
}

$tagStmt = $db->prepare("
    SELECT ct.chatbot_id, t.name AS tag_name
    FROM `chatbot_tags` ct
    JOIN `tags` t ON ct.tag_id = t.id
    WHERE ct.chatbot_id IN ({$placeholders})
    ORDER BY t.name ASC
");
$tagStmt->execute($chatbotIds);
$tagMap = [];
foreach ($tagStmt->fetchAll(PDO::FETCH_ASSOC) as $tag) {
    $tagMap[$tag['chatbot_id']][] = ['name' => $tag['tag_name']];
}

// ============================================
// INTEGRATIONS
// ============================================

$intStmt = $db->prepare("
    SELECT ci.chatbot_id, i.name AS integration_name
    FROM `chatbot_integrations` ci
    JOIN `integrations` i ON ci.integration_id = i.id
    WHERE ci.chatbot_id IN ({$placeholders})
      AND ci.is_supported = 1
    ORDER BY i.name ASC
");
$intStmt->execute($chatbotIds);
$integrationMap = [];
foreach ($intStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $integrationMap[$row['chatbot_id']][] = $row['integration_name'];
}

// ============================================
// PRICING HELPER
// ============================================

function buildPricingDisplay(array $chatbot): string
{
    $parts = [];
    if ($chatbot['pricing_model']){
        $parts[] = strtoupper($chatbot['pricing_model']);
    }

    if ($chatbot['starting_price'] !== null && $chatbot['starting_price'] > 0) {
        $symbol  = ($chatbot['pricing_currency'] === 'EUR') ? '€' : '$';
        $parts[] = (int)$chatbot['starting_price']. $symbol . '+';
    }
    else{
        return $parts;
    }
    return implode(' | ', $parts) ?: 'null';
}

// ============================================
// RESPONSE
// ============================================

$result = [];
foreach ($chatbots as $chatbot) {
    $id   = $chatbot['id'];
    $link = ($chatbot['has_affiliate_program'] && !empty($chatbot['affiliate_link']))
        ? $chatbot['affiliate_link'] : $chatbot['website_url'];

    $isPopular    = false;
    $isSponsored  = false;
    foreach ($tagMap[$id] ?? [] as $tag) {
        if ($tag['name'] === 'Popular')   $isPopular   = true;
        if ($tag['name'] === 'Sponsored') $isSponsored = true;
    }

    $result[] = [
        'id'             => $id,
        'name'           => $chatbot['name'],
        'description'    => $chatbot['description'],
        'logo_url'       => $chatbot['logo_url'],
        'link'           => $link,
        'is_affiliate'   => (bool)$chatbot['has_affiliate_program'],
        'rating_average' => (float)$chatbot['rating_average'],
        'rating_count'   => (int)$chatbot['rating_count'],
        'favorite_count' => (int)$chatbot['favorite_count'],
        'is_favourited'  => (bool)$chatbot['is_favourited'],
        'pricing'        => buildPricingDisplay($chatbot),
        'features'       => $featureMap[$id] ?? [],
        'is_sponsored'   => $isSponsored || (bool)$chatbot['is_sponsored'],
        'is_popular'     => $isPopular,
        'tags'           => $tagMap[$id] ?? [],
        'integrations'   => $integrationMap[$id] ?? [],
    ];
}

echo json_encode([
    'success'     => true,
    'chatbots'    => $result,
    'total'       => $total,
    'totalPages'  => $totalPages,
    'currentPage' => $page,
    'limit'       => $limit,
]);