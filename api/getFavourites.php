<?php
/**
 * AJAX Endpoint - Get Favourites
 *
 * Vracia filtrované + paginované obľúbené chatboty prihláseného usera.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode([
        'success' => true, 'chatbots' => [], 'total' => 0,
        'totalPages' => 0, 'currentPage' => 1, 'limit' => 20,
    ]);
    exit;
}

$db = Database::getInstance()->getConnection();

// ============================================
// INPUT VALIDATION — pagination + sort
// ============================================

$view = in_array($_GET['view'] ?? 'grid', ['grid', 'list']) ? $_GET['view'] : 'grid';
$sort = $_GET['sort'] ?? 'date-added';
$page = max(1, (int)($_GET['page'] ?? 1));

$limit  = ($view === 'list') ? 12 : 20;
$offset = ($page - 1) * $limit;

$allowedSorts = ['popularity', 'price', 'alphabetical', 'rating', 'number-of-ratings', 'date-added'];
if (!in_array($sort, $allowedSorts)) $sort = 'date-added';

$direction = ($_GET['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// ============================================
// INPUT VALIDATION — filters
// ============================================

$ratingStart = (isset($_GET['ratingStart']) && $_GET['ratingStart'] !== '') ? (float)$_GET['ratingStart'] : null;
$ratingEnd   = (isset($_GET['ratingEnd'])   && $_GET['ratingEnd']   !== '') ? (float)$_GET['ratingEnd']   : null;
$votesStart  = (isset($_GET['votesStart'])  && $_GET['votesStart']  !== '') ? (int)$_GET['votesStart']    : null;
$votesEnd    = (isset($_GET['votesEnd'])    && $_GET['votesEnd']    !== '') ? (int)$_GET['votesEnd']      : null;
$favoritesStart = (isset($_GET['favoritesStart']) && $_GET['favoritesStart'] !== '') ? (int)$_GET['favoritesStart'] : null;
$favoritesEnd   = (isset($_GET['favoritesEnd'])   && $_GET['favoritesEnd']   !== '') ? (int)$_GET['favoritesEnd']   : null;     
$priceStart  = (isset($_GET['priceStart'])  && $_GET['priceStart']  !== '') ? (float)$_GET['priceStart']  : null;
$priceEnd    = (isset($_GET['priceEnd'])    && $_GET['priceEnd']    !== '') ? (float)$_GET['priceEnd']    : null;

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

// ============================================
// SORT LOGIC
// ============================================

$dir = $direction === 'asc' ? 'ASC' : 'DESC';

$orderBy = match($sort) {
    'popularity'        => "c.click_count $dir, c.rating_average $dir",
    'alphabetical'      => "c.name $dir",
    'rating'            => "c.rating_average $dir, c.rating_count $dir",
    'number-of-ratings' => "c.rating_count $dir, c.rating_average $dir",
    'date-added'        => "fav.created_at $dir",
    default             => "fav.created_at $dir",
};
if ($sort === 'price') $orderBy = "ISNULL(c.starting_price) ASC, c.starting_price $dir";

// ============================================
// BUILD WHERE + PARAMS
// ============================================

$where  = ["fav.user_id = :user_id", "c.status = 'active'", "c.deleted_at IS NULL"];
$params = [':user_id' => $user['id']];

if ($ratingStart !== null) { $where[] = 'c.rating_average >= :ratingStart'; $params[':ratingStart'] = $ratingStart; }
if ($ratingEnd   !== null) { $where[] = 'c.rating_average <= :ratingEnd';   $params[':ratingEnd']   = $ratingEnd;   }
if ($votesStart  !== null) { $where[] = 'c.rating_count >= :votesStart';    $params[':votesStart']  = $votesStart;  }
if ($votesEnd    !== null) { $where[] = 'c.rating_count <= :votesEnd';      $params[':votesEnd']    = $votesEnd;    }
if ($favoritesStart !== null) { $where[] = 'c.favorite_count >= :favoritesStart'; $params[':favoritesStart'] = $favoritesStart; }
if ($favoritesEnd   !== null) { $where[] = 'c.favorite_count <= :favoritesEnd';   $params[':favoritesEnd']   = $favoritesEnd;   }
if ($priceStart  !== null) { $where[] = 'c.starting_price >= :priceStart';  $params[':priceStart']  = $priceStart;  }
if ($priceEnd    !== null) { $where[] = 'c.starting_price <= :priceEnd';    $params[':priceEnd']    = $priceEnd;    }

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

$whereStr = implode(' AND ', $where);

// ============================================
// COUNT QUERY
// ============================================

$countStmt = $db->prepare("
    SELECT COUNT(*) FROM `favorites` fav
    JOIN `chatbots` c ON c.id = fav.chatbot_id
    WHERE {$whereStr}
");
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total      = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $limit);

// ============================================
// MAIN QUERY
// ============================================

$stmt = $db->prepare("
    SELECT
        c.id, c.name, c.description, c.logo_url, c.website_url,
        c.has_affiliate_program, c.affiliate_link,
        c.has_free_trial, c.pricing_model,
        c.starting_price, c.pricing_currency,
        c.rating_average, c.rating_count,
        c.click_count, c.favorite_count,
        fav.created_at AS favourited_at
    FROM `favorites` fav
    JOIN `chatbots` c ON c.id = fav.chatbot_id
    WHERE {$whereStr}
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
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
    // if ($chatbot['has_free_plan']){
    //     $parts[] = 'FREE';
    // }
    // else if ($chatbot['has_free_trial']){ 
    //     $parts[] = 'TRIAL';
    // }
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
        'pricing'        => buildPricingDisplay($chatbot),
        'features'       => $featureMap[$id] ?? [],
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