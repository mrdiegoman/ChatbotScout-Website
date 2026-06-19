<?php
require '../inc/bootstrap.php';
require '../inc/header.php';
?>

<!-- ═══════════════════════════════════════
     HERO
═══════════════════════════════════════ -->
<section class="hp-hero">
    <div class="hp-hero-shape-1"></div>
    <div class="hp-hero-shape-2"></div>
    <div class="hp-hero-shape-3"></div>

    <div class="hp-hero-inner">

        <!-- Left: text -->
        <div class="hp-hero-text">
            <h1 class="hp-hero-title">
                Find the Best <span>AI Chatbot</span> for Your Website or App
            </h1>
            <p class="hp-hero-subtitle">
                Explore AI chatbots for your website, WhatsApp, Telegram, Instagram, and more.
                Compare by features, pricing, and real user reviews to find the perfect match.
            </p>
            <div class="hp-hero-cta">
                <a href="chatbots" class="hp-btn hp-btn-green">
                    See All Chatbots <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>
        </div>

        <!-- Right: chatbot card carousel -->
        <div class="hp-carousel-wrap">
            <div class="hp-carousel-viewport" id="hp-hero-carousel">

                <!-- Card 0 — LegalAI -->
                <div class="hp-chatbot-card hp-theme-purple pos-far-left">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path d="M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z"/>
                            </svg>
                        </div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">LegalAI Assistant</div>
                            <div class="hp-card-status">Here for you</div>
                        </div>
                    </div>
                    <div class="hp-card-body">
                        <div class="hp-msg hp-msg-bot">Hello! I can help with legal questions and document drafting.</div>
                        <div class="hp-msg hp-msg-user">Can you review my contract?</div>
                        <div class="hp-msg hp-msg-bot">Of course! Share the document and I'll analyse the key clauses.</div>
                        <div class="hp-msg hp-msg-user">Here it is, thank you!</div>
                    </div>
                    <div class="hp-card-input">
                        <span>Type your message...</span>
                        <div class="hp-card-send">
                            <svg xmlns="http://www.w3.org/2000/svg" class="fa-solid fa-paper-plane" viewBox="0 0 640 640" width="14" height="14">
                                <path fill="white" d="M568.4 37.7C578.2 34.2 589 36.7 596.4 44C603.8 51.3 606.2 62.2 602.7 72L424.7 568.9C419.7 582.8 406.6 592 391.9 592C377.7 592 364.9 583.4 359.6 570.3L295.4 412.3C290.9 401.3 292.9 388.7 300.6 379.7L395.1 267.3C400.2 261.2 399.8 252.3 394.2 246.7C388.6 241.1 379.6 240.7 373.6 245.8L261.2 340.1C252.1 347.7 239.6 349.7 228.6 345.3L70.1 280.8C57 275.5 48.4 262.7 48.4 248.5C48.4 233.8 57.6 220.7 71.5 215.7L568.4 37.7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Card 1 — RestoBot -->
                <div class="hp-chatbot-card hp-theme-green pos-left">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path d="M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z"/>
                            </svg>
                        </div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">RestoBot</div>
                            <div class="hp-card-status">Live</div>
                        </div>
                    </div>
                    <div class="hp-card-body">
                        <div class="hp-msg hp-msg-bot">Welcome! Would you like to make a reservation?</div>
                        <div class="hp-msg hp-msg-user">Yes, for 4 people Saturday at 7pm.</div>
                        <div class="hp-msg hp-msg-bot">Great! I have a table available. Name for the booking?</div>
                        <div class="hp-msg hp-msg-user">Under Johnson, please.</div>
                    </div>
                    <div class="hp-card-input">
                        <span>Type your message...</span>
                        <div class="hp-card-send">
                            <svg xmlns="http://www.w3.org/2000/svg" class="fa-solid fa-paper-plane" viewBox="0 0 640 640" width="14" height="14">
                                <path fill="white" d="M568.4 37.7C578.2 34.2 589 36.7 596.4 44C603.8 51.3 606.2 62.2 602.7 72L424.7 568.9C419.7 582.8 406.6 592 391.9 592C377.7 592 364.9 583.4 359.6 570.3L295.4 412.3C290.9 401.3 292.9 388.7 300.6 379.7L395.1 267.3C400.2 261.2 399.8 252.3 394.2 246.7C388.6 241.1 379.6 240.7 373.6 245.8L261.2 340.1C252.1 347.7 239.6 349.7 228.6 345.3L70.1 280.8C57 275.5 48.4 262.7 48.4 248.5C48.4 233.8 57.6 220.7 71.5 215.7L568.4 37.7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Card 2 — SupportBot Pro (default center) -->
                <div class="hp-chatbot-card hp-theme-blue pos-center">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path d="M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z"/>
                            </svg>
                        </div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">SupportBot Pro</div>
                            <div class="hp-card-status">Online</div>
                        </div>
                    </div>
                    <div class="hp-card-body">
                        <div class="hp-msg hp-msg-bot">Hello! How can I assist you today?</div>
                        <div class="hp-msg hp-msg-user">I want to return shoes that don't fit.</div>
                        <div class="hp-msg hp-msg-bot">Sure! I'll walk you through the return process step by step.</div>
                        <div class="hp-msg hp-msg-user">Yes please, that would help!</div>
                        <div class="hp-msg hp-msg-bot">Fill out the short form here: <a href="chatbots" style="color:#0057FF">returns-form</a></div>
                    </div>
                    <div class="hp-card-input">
                        <span>Type your message...</span>
                        <div class="hp-card-send">
                            <svg xmlns="http://www.w3.org/2000/svg" class="fa-solid fa-paper-plane" viewBox="0 0 640 640" width="14" height="14">
                                <path fill="white" d="M568.4 37.7C578.2 34.2 589 36.7 596.4 44C603.8 51.3 606.2 62.2 602.7 72L424.7 568.9C419.7 582.8 406.6 592 391.9 592C377.7 592 364.9 583.4 359.6 570.3L295.4 412.3C290.9 401.3 292.9 388.7 300.6 379.7L395.1 267.3C400.2 261.2 399.8 252.3 394.2 246.7C388.6 241.1 379.6 240.7 373.6 245.8L261.2 340.1C252.1 347.7 239.6 349.7 228.6 345.3L70.1 280.8C57 275.5 48.4 262.7 48.4 248.5C48.4 233.8 57.6 220.7 71.5 215.7L568.4 37.7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Card 3 — ShopAssist -->
                <div class="hp-chatbot-card hp-theme-orange pos-right">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path d="M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z"/>
                            </svg>
                        </div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">ShopAssist AI</div>
                            <div class="hp-card-status">Ready to help</div>
                        </div>
                    </div>
                    <div class="hp-card-body">
                        <div class="hp-msg hp-msg-bot">Hi! Looking for product recommendations today?</div>
                        <div class="hp-msg hp-msg-user">I need a laptop under $800.</div>
                        <div class="hp-msg hp-msg-bot">I found 3 great options in your budget. Let me show you!</div>
                        <div class="hp-msg hp-msg-user">That's perfect, show me!</div>
                    </div>
                    <div class="hp-card-input">
                        <span>Type your message...</span>
                        <div class="hp-card-send">
                            <svg xmlns="http://www.w3.org/2000/svg" class="fa-solid fa-paper-plane" viewBox="0 0 640 640" width="14" height="14">
                                <path fill="white" d="M568.4 37.7C578.2 34.2 589 36.7 596.4 44C603.8 51.3 606.2 62.2 602.7 72L424.7 568.9C419.7 582.8 406.6 592 391.9 592C377.7 592 364.9 583.4 359.6 570.3L295.4 412.3C290.9 401.3 292.9 388.7 300.6 379.7L395.1 267.3C400.2 261.2 399.8 252.3 394.2 246.7C388.6 241.1 379.6 240.7 373.6 245.8L261.2 340.1C252.1 347.7 239.6 349.7 228.6 345.3L70.1 280.8C57 275.5 48.4 262.7 48.4 248.5C48.4 233.8 57.6 220.7 71.5 215.7L568.4 37.7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Card 4 — BookingBot -->
                <div class="hp-chatbot-card hp-theme-pink pos-far-right">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path d="M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z"/>
                            </svg>
                        </div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">BookingBot</div>
                            <div class="hp-card-status">Available 24/7</div>
                        </div>
                    </div>
                    <div class="hp-card-body">
                        <div class="hp-msg hp-msg-bot">Hi! Ready to schedule your appointment?</div>
                        <div class="hp-msg hp-msg-user">I need a haircut on Friday morning.</div>
                        <div class="hp-msg hp-msg-bot">I have 9am and 10:30am available. Which works?</div>
                        <div class="hp-msg hp-msg-user">10:30am please!</div>
                    </div>
                    <div class="hp-card-input">
                        <span>Type your message...</span>
                        <div class="hp-card-send">
                            <svg xmlns="http://www.w3.org/2000/svg" class="fa-solid fa-paper-plane" viewBox="0 0 640 640" width="14" height="14">
                                <path fill="white" d="M568.4 37.7C578.2 34.2 589 36.7 596.4 44C603.8 51.3 606.2 62.2 602.7 72L424.7 568.9C419.7 582.8 406.6 592 391.9 592C377.7 592 364.9 583.4 359.6 570.3L295.4 412.3C290.9 401.3 292.9 388.7 300.6 379.7L395.1 267.3C400.2 261.2 399.8 252.3 394.2 246.7C388.6 241.1 379.6 240.7 373.6 245.8L261.2 340.1C252.1 347.7 239.6 349.7 228.6 345.3L70.1 280.8C57 275.5 48.4 262.7 48.4 248.5C48.4 233.8 57.6 220.7 71.5 215.7L568.4 37.7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

            </div><!-- /hp-carousel-viewport -->

            <button class="hp-carousel-btn hp-prev" id="hp-carousel-prev" aria-label="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <button class="hp-carousel-btn hp-next" id="hp-carousel-next" aria-label="Next">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <div class="hp-carousel-dots" id="hp-carousel-dots"></div>
        </div><!-- /hp-carousel-wrap -->

    </div><!-- /hp-hero-inner -->
</section>


<!-- ═══════════════════════════════════════
     MOST POPULAR
═══════════════════════════════════════ -->
<section class="hp-popular-section">
    <div class="hp-section-inner">
        <div class="hp-section-top hp-fade-up">
            <h2 class="hp-section-title">Most Popular</h2>
            <a href="chatbots" class="hp-link-green">
                See More <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

        <div class="hp-pop-carousel hp-fade-up">
            <button class="hp-pop-arrow hp-pop-prev" id="hp-pop-prev" aria-label="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>

            <div class="hp-pop-track-wrap">
                <div class="hp-pop-track" id="hp-pop-track">

                    <?php
                    $popularChatbots = [];
                    $tagNames        = [];
                    $homepageLogoSrc = static function (?string $path): string {
                        $value = trim((string) $path);
                        if ($value === '') {
                            return '';
                        }

                        if (preg_match('/^(https?:)?\/\//i', $value) || str_starts_with($value, '/') || str_starts_with($value, 'data:') || str_starts_with($value, '../')) {
                            return $value;
                        }

                        if (str_starts_with($value, 'websiteLogos/')) {
                            return 'img/' . $value;
                        }

                        if (str_starts_with($value, 'public/')) {
                            return '../' . $value;
                        }

                        return $value;
                    };

                    try {
                        $db = Database::getInstance()->getConnection();
                        $homepagePromotionExistsSql = "
                            EXISTS (
                                SELECT 1
                                FROM subscriptions s_homepage_promotion
                                JOIN promotions p_homepage_promotion ON p_homepage_promotion.id = s_homepage_promotion.promotions_id
                                WHERE s_homepage_promotion.chatbot_id = c.id
                                  AND s_homepage_promotion.subscription_type = 'promotion'
                                  AND s_homepage_promotion.status IN ('active', 'trialing', 'past_due')
                                  AND s_homepage_promotion.end_date > NOW()
                                  AND p_homepage_promotion.slot_type = 'Homepage'
                            )
                        ";

                        $stmt = $db->query("
                            SELECT
                                c.id,
                                c.name,
                                c.logo_url,
                                c.rating_average,
                                c.rating_count,
                                c.favorite_count,
                                c.has_affiliate_program,
                                c.is_sponsored,
                                c.click_count,
                                {$homepagePromotionExistsSql} AS has_homepage_promotion
                            FROM chatbots c
                            WHERE c.status     = 'active'
                            AND c.deleted_at IS NULL
                            ORDER BY has_homepage_promotion DESC,
                                     (c.rating_average * LOG(c.click_count + 1)) DESC,
                                     c.click_count DESC,
                                     c.rating_count DESC,
                                     c.created_at DESC
                            LIMIT 8
                        ");
                        $popularChatbots = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($popularChatbots)) {
                            $ids          = array_column($popularChatbots, 'id');
                            $placeholders = implode(',', array_fill(0, count($ids), '?'));
                            $tagStmt      = $db->prepare("
                                SELECT ct.chatbot_id, t.name
                                FROM chatbot_tags ct
                                JOIN tags t ON ct.tag_id = t.id
                                WHERE ct.chatbot_id IN ({$placeholders})
                                  AND t.name NOT IN ('Popular', 'Sponsored', 'Affiliate')
                                ORDER BY ct.chatbot_id,
                                         t.name ASC
                            ");
                            $tagStmt->execute($ids);
                            foreach ($tagStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                if (!isset($tagNames[$row['chatbot_id']])) {
                                    $tagNames[$row['chatbot_id']] = [];
                                }
                                if (count($tagNames[$row['chatbot_id']]) < 2) {
                                    $tagNames[$row['chatbot_id']][] = $row['name'];
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Homepage popular chatbots error: ' . $e->getMessage());
                    }
                    ?>

                    <?php foreach ($popularChatbots as $i => $bot):
                        $rating     = (float) $bot['rating_average'];
                        $fullStars  = (int) floor($rating);
                        $emptyStars = 5 - $fullStars;
                        $botTags    = [];
                        if (!empty($bot['has_homepage_promotion'])) {
                            $botTags[] = 'Sponsored';
                        }
                        if (!empty($bot['has_affiliate_program']) && count($botTags) < 2) {
                            $botTags[] = 'Affiliate';
                        }
                        foreach ($tagNames[$bot['id']] ?? [] as $tag) {
                            if (count($botTags) >= 2) {
                                break;
                            }
                            if (!in_array($tag, $botTags, true)) {
                                $botTags[] = $tag;
                            }
                        }
                        $logoUrl    = $homepageLogoSrc($bot['logo_url'] ?? '');
                    ?>
                    <a href="chatbots?search=<?= urlencode($bot['name']) ?>" class="hp-pop-card">
                        <div class="hp-pop-tags">
                            <?php if (!empty($botTags)): ?>
                                <?php foreach ($botTags as $tag): ?>
                                    <span class="hp-tag">
                                        <p>✦ <?= htmlspecialchars($tag) ?> ✦</p>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="hp-tag" style="visibility:hidden;"><p>placeholder</p></span>
                            <?php endif; ?>
                        </div>
                        <div class="hp-pop-name">
                            <?= htmlspecialchars($bot['name']) ?>
                            <button class="hp-pop-heart" title="Add to favourites">
                                <i class="fa-regular fa-heart"></i>
                            </button>
                        </div>
                        <div class="hp-pop-rating">
                            <div class="hp-stars">
                                <?= str_repeat('★', $fullStars) . str_repeat('☆', $emptyStars) ?>
                            </div>
                            <span><?= number_format($rating, 1) ?> (<?= (int)$bot['rating_count'] ?>)</span>
                        </div>
                        <div class="hp-pop-logo">
                            <?php if ($logoUrl): ?>
                                <img
                                    src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($bot['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    onerror="this.onerror=null;this.src='img/ChatbotScout_logo.png';"
                                    style="width:100%;height:100%;object-fit:contain;border-radius:10px;"
                                >
                            <?php else: ?>
                                <i class="fa-solid fa-robot"></i>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>

                </div><!-- /hp-pop-track -->
            </div><!-- /hp-pop-track-wrap -->

            <button class="hp-pop-arrow hp-pop-next" id="hp-pop-next" aria-label="Next">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div><!-- /hp-pop-carousel -->
    </div>
</section>


<!-- ═══════════════════════════════════════
     FEATURES — CHAT UI
═══════════════════════════════════════ -->
<section class="hp-features-section">
    <div class="hp-features-inner">

        <!-- Section label -->
        <!-- <div class="hp-chat-label hp-fade-up">
            <span>✦ Why ChatbotScout?</span>
        </div> -->

        <!-- Messages -->
        <div class="hp-chat-messages">

            <!-- User message 1 -->
            <div class="hp-chat-row hp-chat-row-user hp-chat-animate">
                <!-- <div class="hp-chat-avatar-user">
                    <i class="fa-solid fa-user"></i>
                </div> -->
                <div class="hp-chat-msg hp-chat-msg-user">
                    How do I find the right AI chatbot widget for my website?
                </div>
            </div>

            <!-- Bot response 1 -->
            <div class="hp-chat-row hp-chat-row-bot hp-chat-animate">
                <div class="hp-chat-avatar-bot">
                    <img src="img/ChatbotScout_logo.png" alt="ChatbotScout">
                </div>
                <div class="hp-chat-msg hp-chat-msg-bot">
                    <h3>Search, Filter & Sort - Find Your Perfect Chatbot for Your App</h3>
                    <p>
                        Use our search, filters and sorting to narrow down chatbots by pricing, 
                        features, and ratings. Whether you need a free plan or live agent 
                        support - find exactly what fits your business in seconds.

                    </p>
                    <a href="chatbots" class="hp-link-green">
                        Start searching
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </div>
            </div>

            <!-- User message 2 -->
            <div class="hp-chat-row hp-chat-row-user hp-chat-animate">
                 <!-- <div class="hp-chat-avatar-user">
                    <i class="fa-solid fa-user"></i>
                </div> -->
                <div class="hp-chat-msg hp-chat-msg-user">
                    Can I find chatbots for WhatsApp, Telegram, or Instagram too?
                </div>
            </div>

            <!-- Bot response 2 -->
            <div class="hp-chat-row hp-chat-row-bot hp-chat-animate">
                <div class="hp-chat-avatar-bot">
                    <img src="img/ChatbotScout_logo.png" alt="ChatbotScout">
                </div>
                <div class="hp-chat-msg hp-chat-msg-bot">
                    <h3>Not Just Websites — Bots for Every Platform</h3>
                    <p>
                        Every listing shows which platforms the chatbot supports — including WhatsApp,
                        Telegram, Instagram, Facebook Messenger, Slack, and more. Filter by integration
                        to find the right bot for your messaging app in seconds.
                    </p>
                    <a href="chatbots" class="hp-link-green">
                        Explore listings
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </div>
            </div>

        </div><!-- /hp-chat-messages -->

    </div>
</section>


<!-- ═══════════════════════════════════════
     FILTER SECTION
═══════════════════════════════════════ -->
<section class="hp-filter-section">
    <div class="hp-filter-inner">

        <!-- Visual card mock-up -->
        <div class="hp-filter-visual hp-fade-up">
            <div class="hp-filter-visual-card">
                <div class="hp-filter-visual-title">Filter by Pricing</div>
                <div class="hp-filter-chip-row">
                    <span class="hp-filter-chip hp-filter-chip-green hp-chip-active">Free</span>
                    <span class="hp-filter-chip hp-filter-chip-blue">Freemium</span>
                    <span class="hp-filter-chip hp-filter-chip-purple">Recurring</span>
                    <span class="hp-filter-chip hp-filter-chip-orange">Usage-based</span>
                </div>

                <div class="hp-filter-divider"></div>

                <div class="hp-filter-visual-title">Filter by Features</div>
                <div class="hp-filter-chip-row">
                    <span class="hp-filter-chip hp-filter-chip-blue hp-chip-active">Live agent support</span>
                    <span class="hp-filter-chip hp-filter-chip-teal">Multilingual</span>
                    <span class="hp-filter-chip hp-filter-chip-purple">AI agent</span>
                    <span class="hp-filter-chip hp-filter-chip-green">Dashboard</span>
                </div>

                <div class="hp-filter-divider"></div>

                <div class="hp-filter-result-preview">
                    <div class="hp-filter-result-item">
                        <div class="hp-filter-result-dot hp-dot-blue"><i class="fa-solid fa-robot"></i></div>
                        <div class="hp-filter-result-lines">
                            <div class="hp-filter-result-line"></div>
                            <div class="hp-filter-result-line short"></div>
                        </div>
                    </div>
                    <div class="hp-filter-result-item">
                        <div class="hp-filter-result-dot hp-dot-green"><i class="fa-solid fa-robot"></i></div>
                        <div class="hp-filter-result-lines">
                            <div class="hp-filter-result-line"></div>
                            <div class="hp-filter-result-line short"></div>
                        </div>
                    </div>
                    <div class="hp-filter-result-item">
                        <div class="hp-filter-result-dot hp-dot-purple"><i class="fa-solid fa-robot"></i></div>
                        <div class="hp-filter-result-lines">
                            <div class="hp-filter-result-line"></div>
                            <div class="hp-filter-result-line short"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="hp-filter-content hp-fade-up">
            <div class="hp-filter-badge">
                Smart Filtering
            </div>
            <h2>Filter Chatbots by actual up-to-date <br> Features, Pricing, Integrations and more</h2>
            <div class="hp-filter-features">
                <div class="hp-filter-feature">
                    <div class="hp-filter-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M320 32C373 32 416 75 416 128C416 181 373 224 320 224C267 224 224 181 224 128C224 75 267 32 320 32zM80 368C80 297.9 127 236.6 197.1 203.1C222.4 244.4 268 272 320 272C375.7 272 424.1 240.3 448 194C463.8 182.7 483.1 176 504 176L523.5 176C533.9 176 541.5 185.8 539 195.9L521.9 264.2C531.8 276.6 540.1 289.9 546.3 304L568 304C581.3 304 592 314.7 592 328L592 440C592 453.3 581.3 464 568 464L528 464C511.5 486 489.5 503.6 464 514.7L464 544C464 561.7 449.7 576 432 576L399 576C384.7 576 372.2 566.5 368.2 552.8L361.1 528L278.8 528L271.7 552.8C267.8 566.5 255.3 576 241 576L208 576C190.3 576 176 561.7 176 544L176 514.7C119.5 490 80 433.6 80 368zM456 384C469.3 384 480 373.3 480 360C480 346.7 469.3 336 456 336C442.7 336 432 346.7 432 360C432 373.3 442.7 384 456 384z"/>
                        </svg>
                    </div>
                    <div class="hp-filter-feature-text">
                        <strong>Pricing that fits your budget</strong>
                        <span>Filter by free, freemium, recurring, or usage-based plans - find what works for your business in any industry.</span>
                    </div>
                </div>
                <div class="hp-filter-feature">
                    <div class="hp-filter-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M320 128C241 128 175.3 185.3 162.3 260.7C171.6 257.7 181.6 256 192 256L208 256C234.5 256 256 277.5 256 304L256 400C256 426.5 234.5 448 208 448L192 448C139 448 96 405 96 352L96 288C96 164.3 196.3 64 320 64C443.7 64 544 164.3 544 288L544 456.1C544 522.4 490.2 576.1 423.9 576.1L336 576L304 576C277.5 576 256 554.5 256 528C256 501.5 277.5 480 304 480L336 480C362.5 480 384 501.5 384 528L384 528L424 528C463.8 528 496 495.8 496 456L496 435.1C481.9 443.3 465.5 447.9 448 447.9L432 447.9C405.5 447.9 384 426.4 384 399.9L384 303.9C384 277.4 405.5 255.9 432 255.9L448 255.9C458.4 255.9 468.3 257.5 477.7 260.6C464.7 185.3 399.1 127.9 320 127.9z"/>
                        </svg>
                    </div>
                    <div class="hp-filter-feature-text">
                        <strong>Filter by specific features</strong>
                        <span>Live agent support, multilingual, AI agent, dashboard, file uploads and more - pick exactly what you need.</span>
                    </div>
                </div>
                <div class="hp-filter-feature">
                    <div class="hp-filter-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M64 160C64 124.7 92.7 96 128 96L512 96C547.3 96 576 124.7 576 160L576 400L512 400L512 160L128 160L128 400L64 400L64 160zM0 467.2C0 456.6 8.6 448 19.2 448L620.8 448C631.4 448 640 456.6 640 467.2C640 509.6 605.6 544 563.2 544L76.8 544C34.4 544 0 509.6 0 467.2zM281 273L250 304L281 335C290.4 344.4 290.4 359.6 281 368.9C271.6 378.2 256.4 378.3 247.1 368.9L199.1 320.9C189.7 311.5 189.7 296.3 199.1 287L247.1 239C256.5 229.6 271.7 229.6 281 239C290.3 248.4 290.4 263.6 281 272.9zM393 239L441 287C450.4 296.4 450.4 311.6 441 320.9L393 368.9C383.6 378.3 368.4 378.3 359.1 368.9C349.8 359.5 349.7 344.3 359.1 335L390.1 304L359.1 273C349.7 263.6 349.7 248.4 359.1 239.1C368.5 229.8 383.7 229.7 393 239.1z"/>
                        </svg>
                    </div>
                    <div class="hp-filter-feature-text">
                        <strong>Integration compatibility</strong>
                        <span>Filter by platforms like <a href="chatbots">WordPress</a>, <a href="chatbots">Shopify</a>, Slack, and many more.</span>
                    </div>
                </div>
            </div>
            <a href="chatbots" class="hp-filter-cta">
                Browse All Chatbots 
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

    </div>
</section>


<!-- ═══════════════════════════════════════
     DISCOVER / RATE / SAVE
═══════════════════════════════════════ -->
<section class="hp-discover-section">
    <div class="hp-discover-card hp-fade-up">

        <div class="hp-discover-visual">
            <img src="img/assets/ai_chatbot_pic.png" alt="AI Chatbot" class="hp-discover-bot" />
        </div>

        <div class="hp-discover-text">
            <h2>
                Discover, Rate, and Save the Best
                <span>AI Chatbots</span> for Your Website or Business
            </h2>
            <p>
                Save your favourite AI chatbots to your wishlist and access them anytime -
                compare, revisit, and decide at your own pace.
            </p>
            <p>
                Share your experience by rating the AI chatbots you use and help thousands
                of others discover reliable, high-performing options.
            </p>
            <a href="chatbots" class="hp-btn hp-btn-blue">
                Start Discovering <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

    </div>
</section>

<!-- ═══════════════════════════════════════
     SEO CONTENT + FAQ
═══════════════════════════════════════ -->
<section class="hp-seo-section">
    <div class="hp-section-inner">
        <!-- <h2 class="hp-section-title">Compare AI Chatbots by Use Case, Features, and Platform</h2>
        <p>
            ChatbotScout helps website owners compare AI chatbot software for ecommerce, lead generation, customer
            support, and appointment booking. Use one place to evaluate pricing models, integrations, supported
            platforms, and real user ratings.
        </p>
        <p>
            Start with popular categories:
            <a href="chatbots?platform=WordPress">AI chatbots for WordPress</a>,
            <a href="chatbots?platform=Shopify">AI chatbots for Shopify</a>,
            <a href="chatbots?pricing=Free">free AI chatbots</a>, and
            <a href="chatbots?feature=Live+agent+support">chatbots with live agent support</a>.
        </p> -->

        <div class="hp-faq">
            <h3>Frequently Asked Questions</h3>

            <article class="hp-faq-item">
                <h4>How do I choose the best AI chatbot for my website?</h4>
                <p><span>Compare chatbot goals, pricing model, integrations, and ratings. Then shortlist tools based on
                    your platform and must-have features.</span></p>
            </article>

            <article class="hp-faq-item">
                <h4>Can I filter chatbots by platform like WordPress or Shopify?</h4>
                <p><span>Yes. You can narrow results by integration compatibility and focus on tools that work with your
                    current website stack.</span></p>
            </article>

            <article class="hp-faq-item">
                <h4>Are free or freemium AI chatbots available?</h4>
                <p><span>Yes. You can filter chatbot listings by pricing model to find free, freemium, recurring, or
                    usage-based plans.</span></p>
            </article>

            <article class="hp-faq-item">
                <h4>Can I compare real user ratings before choosing?</h4>
                <p><span>Yes. Listings include rating data and review context so you can evaluate reliability before you
                    decide.</span></p>
            </article>

            <article class="hp-faq-item">
                <h4>Can I find AI chatbots for WhatsApp, Telegram, or Instagram?</h4>
                <p><span>Yes. ChatbotScout lists chatbots for messaging apps including WhatsApp, Telegram, Instagram,
                    Facebook Messenger, and more — not just website widgets. Filter by platform to find the right bot
                    for your app.</span></p>
            </article>
        </div>
    </div>
</section>

<?php
$baseScheme = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $baseScheme = 'https';
}
$baseUrl = $baseScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$itemListElements = [];
foreach ($popularChatbots as $index => $bot) {
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'name' => $bot['name'],
        'url' => $baseUrl . '/chatbotShowcase.php?search=' . urlencode($bot['name']),
    ];
}

$webSiteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'ChatbotScout',
    'url' => $baseUrl . '/',
    'description' => 'Compare AI chatbots by features, pricing, integrations, and user reviews.',
];

$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'ChatbotScout',
    'url' => $baseUrl . '/',
    'logo' => $baseUrl . '/img/websiteLogos/ChatbotScout_logo.png',
];

$faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'How do I choose the best AI chatbot for my website?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Compare chatbot goals, pricing model, integrations, and ratings. Then shortlist tools based on your platform and must-have features.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Can I filter chatbots by platform like WordPress or Shopify?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes. You can narrow results by integration compatibility and focus on tools that work with your current website stack.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Are free or freemium AI chatbots available?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes. You can filter chatbot listings by pricing model to find free, freemium, recurring, or usage-based plans.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Can I compare real user ratings before choosing?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes. Listings include rating data and review context so you can evaluate reliability before you decide.',
            ],
        ],
    ],
];

$itemListSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Most Popular AI Chatbots',
    'itemListElement' => $itemListElements,
];
?>

<script type="application/ld+json"><?php echo json_encode($webSiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script type="application/ld+json"><?php echo json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script type="application/ld+json"><?php echo json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<?php if (!empty($itemListElements)): ?>
<script type="application/ld+json"><?php echo json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<?php endif; ?>



<script src="js/homePage.js?v=<?= filemtime(__DIR__ . '/js/homePage.js') ?>" defer></script>

<?php
require '../inc/footer.php';
?>
