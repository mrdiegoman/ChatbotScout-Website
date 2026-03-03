<?php
/**
 * AJAX Endpoint - Get Chatbots
 * 
 * Returns paginated, sorted chatbot listings in JSON format.
 * Used by chatbotShowcase.php for dynamic loading.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$db = Database::getInstance()->getConnection();

// ============================================
// INPUT VALIDATION
// ============================================

$view   = in_array($_GET['view'] ?? 'grid', ['grid', 'list']) ? $_GET['view'] : 'grid';
$sort   = $_GET['sort'] ?? 'popularity';
$page   = max(1, (int)($_GET['page'] ?? 1));

// Limits per view type
$limit  = ($view === 'list') ? 12 : 20;
$offset = ($page - 1) * $limit;

// Allowed sort options
$allowedSorts = ['popularity', 'price', 'alphabetical', 'rating', 'number-of-ratings'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'popularity';
}

// ============================================
// SORT LOGIC
// ============================================

$orderBy = match($sort) {
    'popularity'       => 'c.click_count DESC, c.rating_average DESC',
    'price'            => 'c.starting_price ASC NULLS LAST, c.has_free_plan DESC',
    'alphabetical'     => 'c.name ASC',
    'rating'           => 'c.rating_average DESC, c.rating_count DESC',
    'number-of-ratings'=> 'c.rating_count DESC, c.rating_average DESC',
    default            => 'c.click_count DESC',
};

// MySQL doesn't support NULLS LAST directly, use ISNULL workaround for price
if ($sort === 'price') {
    $orderBy = 'ISNULL(c.starting_price) ASC, c.starting_price ASC';
}

// ============================================
// COUNT QUERY
// ============================================

$countStmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM `chatbots` c
    WHERE c.status = 'active'
      AND c.deleted_at IS NULL
");
$countStmt->execute();
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
        c.favorite_count
    FROM `chatbots` c
    WHERE c.status = 'active'
      AND c.deleted_at IS NULL
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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
// FETCH FEATURES FOR EACH CHATBOT
// ============================================

$chatbotIds    = array_column($chatbots, 'id');
$placeholders  = implode(',', array_fill(0, count($chatbotIds), '?'));

$featStmt = $db->prepare("
    SELECT
        cf.chatbot_id,
        f.name AS feature_name,
        cf.text_value,
        cf.boolean_value
    FROM `chatbot_features` cf
    JOIN `features` f ON cf.feature_id = f.id
    WHERE cf.chatbot_id IN ({$placeholders})
      AND (cf.boolean_value = 1 OR cf.text_value IS NOT NULL) 
");
$featStmt->execute($chatbotIds);
$allFeatures = $featStmt->fetchAll(PDO::FETCH_ASSOC);

// Group features by chatbot_id
$featureMap = [];
foreach ($allFeatures as $feat) {
    $featureMap[$feat['chatbot_id']][] = $feat['feature_name'];
}

// ============================================
// FETCH TAGS FOR EACH CHATBOT
// ============================================

$tagStmt = $db->prepare("
    SELECT
        ct.chatbot_id,
        t.name AS tag_name
    FROM `chatbot_tags` ct
    JOIN `tags` t ON ct.tag_id = t.id
    WHERE ct.chatbot_id IN ({$placeholders})
    ORDER BY t.name ASC
");
$tagStmt->execute($chatbotIds);
$allTags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

// Group tags by chatbot_id
$tagMap = [];
foreach ($allTags as $tag) {
    $tagMap[$tag['chatbot_id']][] = [
        'name' => $tag['tag_name'],
    ];
}

// ============================================
// BUILD PRICING DISPLAY STRING
// ============================================

function buildPricingDisplay(array $chatbot): string
{
    $parts = [];

    if ($chatbot['has_free_plan'] || $chatbot['has_free_trial']) {
        $parts[] = 'FREE';
    }

    if ($chatbot['starting_price'] !== null && $chatbot['starting_price'] > 0) {
        $price    = (int)$chatbot['starting_price'];
        $currency = $chatbot['pricing_currency'] ?? 'USD';
        $symbol   = ($currency === 'EUR') ? '€' : '$';
        $parts[]  = "{$symbol}{$price}+";
    }

    return implode(' | ', $parts) ?: 'Contact';
}

// ============================================
// ASSEMBLE RESPONSE
// ============================================

$result = [];
foreach ($chatbots as $chatbot) {
    $id = $chatbot['id'];

    // Choose link: affiliate if available, else website
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