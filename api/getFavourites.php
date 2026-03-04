<?php
/**
 * AJAX Endpoint - Get Favourites
 *
 * Vracia chatboty ktoré má prihlásený user v obľúbených.
 * Neprihlásený user dostane prázdne pole.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Neprihlásený user → prázdny výsledok (redirect rieši PHP stránka)
if (!$isLoggedIn || empty($user['id'])) {
    echo json_encode([
        'success'    => true,
        'chatbots'   => [],
        'total'      => 0,
        'totalPages' => 0,
        'currentPage'=> 1,
        'limit'      => 20,
    ]);
    exit;
}

$db = Database::getInstance()->getConnection();

// ============================================
// INPUT VALIDATION
// ============================================

$view = in_array($_GET['view'] ?? 'grid', ['grid', 'list']) ? $_GET['view'] : 'grid';
$sort = $_GET['sort'] ?? 'popularity';
$page = max(1, (int)($_GET['page'] ?? 1));

$limit  = ($view === 'list') ? 12 : 20;
$offset = ($page - 1) * $limit;

$allowedSorts = ['popularity', 'price', 'alphabetical', 'rating', 'number-of-ratings', 'date-added'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'date-added';
}

// ============================================
// SORT LOGIC
// ============================================

$orderBy = match($sort) {
    'popularity'        => 'c.click_count DESC, c.rating_average DESC',
    'alphabetical'      => 'c.name ASC',
    'rating'            => 'c.rating_average DESC, c.rating_count DESC',
    'number-of-ratings' => 'c.rating_count DESC, c.rating_average DESC',
    'date-added'        => 'fav.created_at DESC',
    default             => 'fav.created_at DESC',
};

if ($sort === 'price') {
    $orderBy = 'ISNULL(c.starting_price) ASC, c.starting_price ASC';
}

// ============================================
// COUNT QUERY
// ============================================

$countStmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM `favorites` fav
    JOIN `chatbots` c ON c.id = fav.chatbot_id
    WHERE fav.user_id = :user_id
      AND c.status = 'active'
      AND c.deleted_at IS NULL
");
$countStmt->execute([':user_id' => $user['id']]);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $limit);

// ============================================
// MAIN QUERY
// ============================================

$stmt = $db->prepare("
    SELECT
        c.id,
        c.name,
        c.description,
        c.logo_url,
        c.website_url,
        c.has_affiliate_program,
        c.affiliate_link,
        c.has_free_plan,
        c.has_free_trial,
        c.starting_price,
        c.pricing_currency,
        c.rating_average,
        c.rating_count,
        c.click_count,
        c.favorite_count,
        fav.created_at AS favourited_at
    FROM `favorites` fav
    JOIN `chatbots` c ON c.id = fav.chatbot_id
    WHERE fav.user_id = :user_id
      AND c.status = 'active'
      AND c.deleted_at IS NULL
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':user_id', $user['id'], PDO::PARAM_STR);
$stmt->bindValue(':limit',   $limit,      PDO::PARAM_INT);
$stmt->bindValue(':offset',  $offset,     PDO::PARAM_INT);
$stmt->execute();
$chatbots = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($chatbots)) {
    echo json_encode([
        'success'     => true,
        'chatbots'    => [],
        'total'       => $total,
        'totalPages'  => $totalPages,
        'currentPage' => $page,
        'limit'       => $limit,
    ]);
    exit;
}

// ============================================
// FEATURES
// ============================================

$chatbotIds   = array_column($chatbots, 'id');
$placeholders = implode(',', array_fill(0, count($chatbotIds), '?'));

$featStmt = $db->prepare("
    SELECT cf.chatbot_id, f.name AS feature_name, cf.text_value, cf.boolean_value
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

// ============================================
// TAGS
// ============================================

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
// PRICING
// ============================================

function buildPricingDisplay(array $chatbot): string
{
    $parts = [];
    if ($chatbot['has_free_plan'] || $chatbot['has_free_trial']) {
        $parts[] = 'FREE';
    }
    if ($chatbot['starting_price'] !== null && $chatbot['starting_price'] > 0) {
        $price   = (int)$chatbot['starting_price'];
        $symbol  = ($chatbot['pricing_currency'] === 'EUR') ? '€' : '$';
        $parts[] = "{$symbol}{$price}+";
    }
    return implode(' | ', $parts) ?: 'Contact';
}

// ============================================
// RESPONSE
// ============================================

$result = [];
foreach ($chatbots as $chatbot) {
    $id   = $chatbot['id'];
    $link = ($chatbot['has_affiliate_program'] && !empty($chatbot['affiliate_link']))
          ? $chatbot['affiliate_link']
          : $chatbot['website_url'];

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