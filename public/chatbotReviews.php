<?php
require '../inc/bootstrap.php';

$page = 'chatbotReviews';
$chatbotId = trim($_GET['chatbot'] ?? '');
$initialRating = filter_input(INPUT_GET, 'rating', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 5],
]) ?: 0;
$chatbot = null;
$ratingCounts = array_fill(1, 5, 0);
$reviewCount = 0;
$ratingAverage = 0.0;

if (preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $chatbotId)) {
    try {
        $db = Database::getInstance()->getConnection();

        $chatbotStmt = $db->prepare("\n            SELECT id, name, logo_url\n            FROM chatbots\n            WHERE id = :id\n              AND status = 'active'\n              AND deleted_at IS NULL\n        ");
        $chatbotStmt->execute(['id' => $chatbotId]);
        $chatbot = $chatbotStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($chatbot) {
            $distributionStmt = $db->prepare("\n                SELECT rating, COUNT(*) AS review_count\n                FROM reviews\n                WHERE chatbot_id = :chatbot_id\n                  AND status = 'approved'\n                GROUP BY rating\n            ");
            $distributionStmt->execute(['chatbot_id' => $chatbotId]);

            foreach ($distributionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rating = (int)$row['rating'];
                if ($rating >= 1 && $rating <= 5) {
                    $ratingCounts[$rating] = (int)$row['review_count'];
                }
            }

            $reviewCount = array_sum($ratingCounts);
            if ($reviewCount > 0) {
                $ratingTotal = 0;
                foreach ($ratingCounts as $rating => $count) {
                    $ratingTotal += $rating * $count;
                }
                $ratingAverage = $ratingTotal / $reviewCount;
            }
        }
    } catch (PDOException $e) {
        error_log('chatbotReviews page error: ' . $e->getMessage());
        $chatbot = null;
    }
}

if (!$chatbot) {
    http_response_code(404);
}

$logoUrl = trim((string)($chatbot['logo_url'] ?? '')) ?: 'img/ChatbotScout_logo.png';
$chatbotName = $chatbot['name'] ?? 'Chatbot';

require '../inc/header.php';
?>

<link rel="stylesheet" href="css/chatbotReviews.css?v=<?= filemtime(__DIR__ . '/css/chatbotReviews.css') ?>">

<main class="reviews-page">
    <a class="reviews-back-link" href="chatbots">← Back to chatbots</a>

    <?php if (!$chatbot): ?>
        <section class="reviews-not-found" aria-labelledby="reviews-not-found-title">
            <h1 id="reviews-not-found-title">Chatbot not found</h1>
            <p>This chatbot is unavailable or no longer listed.</p>
            <a class="reviews-primary-link" href="chatbots">Browse chatbots</a>
        </section>
    <?php else: ?>
        <?php
            $averagePercent = max(0, min(100, ($ratingAverage / 5) * 100));
            $reviewLabel = $reviewCount === 1 ? 'review' : 'reviews';
        ?>
        <section class="reviews-chatbot-summary" aria-labelledby="chatbot-reviews-title">
            <a class="reviews-chatbot-identity" href="chatbots" aria-label="Back to the chatbot directory">
                <div class="reviews-chatbot-logo">
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($chatbotName, ENT_QUOTES, 'UTF-8') ?> logo">
                </div>
                <div>
                    <p class="reviews-eyebrow">Ratings &amp; reviews</p>
                    <h1 id="chatbot-reviews-title"><?= htmlspecialchars($chatbotName, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            </a>

            <div class="reviews-rating-overview">
                <div class="reviews-average">
                    <strong><?= $reviewCount > 0 ? number_format($ratingAverage, 1) : '—' ?></strong>
                    <span>out of 5</span>
                </div>
                <div>
                    <div class="reviews-star-meter" role="img" aria-label="<?= $reviewCount > 0 ? number_format($ratingAverage, 1) . ' out of 5 stars' : 'No ratings yet' ?>">
                        <span class="reviews-star-meter__empty" aria-hidden="true">★★★★★</span>
                        <span class="reviews-star-meter__filled" aria-hidden="true" style="width: <?= number_format($averagePercent, 2, '.', '') ?>%">★★★★★</span>
                    </div>
                    <p class="reviews-count-summary"><?= number_format($reviewCount) ?> <?= $reviewLabel ?></p>
                </div>
                <button type="button" class="reviews-breakdown-toggle" id="ratingBreakdownToggle" aria-expanded="false" aria-controls="ratingBreakdown">
                    See rating breakdown
                    <span class="reviews-breakdown-toggle__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </span>
                </button>
            </div>

            <div class="reviews-breakdown" id="ratingBreakdown" hidden>
                <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                    <?php
                        $count = $ratingCounts[$rating];
                        $percentage = $reviewCount > 0 ? ($count / $reviewCount) * 100 : 0;
                    ?>
                    <button type="button"
                            class="reviews-breakdown-row"
                            data-rating-filter="<?= $rating ?>"
                            aria-label="Show <?= $rating ?> star reviews (<?= $count ?>)">
                        <span class="reviews-breakdown-rating"><?= $rating ?> <span aria-hidden="true">★</span></span>
                        <span class="reviews-breakdown-track" aria-hidden="true">
                            <span class="reviews-breakdown-fill" style="width: <?= number_format($percentage, 2, '.', '') ?>%"></span>
                        </span>
                        <span class="reviews-breakdown-count"><?= number_format($count) ?></span>
                    </button>
                <?php endfor; ?>
            </div>
        </section>

        <section class="reviews-list-section"
                 id="reviews-list"
                 tabindex="-1"
                 data-chatbot-reviews
                 data-chatbot-id="<?= htmlspecialchars($chatbotId, ENT_QUOTES, 'UTF-8') ?>"
                 data-chatbot-name="<?= htmlspecialchars($chatbotName, ENT_QUOTES, 'UTF-8') ?>"
                 data-initial-rating="<?= $initialRating ?>"
                 data-api-url="api/getChatbotReviews.php">
            <div class="reviews-list-header">
                <div>
                    <h2>Reviews</h2>
                    <p id="reviewsFilterSummary" aria-live="polite"></p>
                </div>
                <div class="reviews-filter" role="group" aria-label="Filter reviews by rating">
                    <button type="button" class="reviews-filter-btn" data-rating-filter="0">All</button>
                    <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                        <button type="button" class="reviews-filter-btn" data-rating-filter="<?= $rating ?>">
                            <?= $rating ?> <span aria-hidden="true">★</span>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="reviews-loading" id="reviewsLoading" aria-live="polite">
                <span class="reviews-loading-spinner" aria-hidden="true"></span>
                Loading reviews…
            </div>
            <div class="reviews-cards" id="reviewsContainer"></div>
            <div class="reviews-empty" id="reviewsEmpty" hidden></div>
            <div class="reviews-load-more-wrap">
                <button type="button" class="reviews-load-more" id="reviewsLoadMore" hidden>Load more reviews</button>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php if ($chatbot): ?>
    <script src="js/chatbotReviews.js?v=<?= filemtime(__DIR__ . '/js/chatbotReviews.js') ?>" defer></script>
<?php endif; ?>

<?php require '../inc/footer.php'; ?>
