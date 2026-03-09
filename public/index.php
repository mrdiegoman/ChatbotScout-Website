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
            <div class="hp-hero-badge">
                <i class="fa-solid fa-bolt"></i> #1 AI Chatbot Directory
            </div>
            <h1 class="hp-hero-title">
                Find the Best <span>AI Chatbot</span><br>for Your Website
            </h1>
            <p class="hp-hero-subtitle">
                Compare hundreds of AI chatbots by features, pricing, and real user reviews.
                Save your favourites and pick the perfect fit for your business.
            </p>
            <div class="hp-hero-cta">
                <a href="chatbotShowcase.php" class="hp-btn hp-btn-green">
                    See All Chatbots <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>
        </div>

        <!-- Right: chatbot card carousel -->
        <div class="hp-carousel-wrap">
            <div class="hp-carousel-viewport" id="hp-hero-carousel">

                <!-- Card 0 — LegalAI -->
                <div class="hp-chatbot-card hp-theme-purple pos-far-left">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar"><i class="fa-solid fa-scale-balanced"></i></div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">LegalAI Assistant</div>
                            <div class="hp-card-status">Ready to help</div>
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
                        <div class="hp-card-send"><i class="fa-solid fa-paper-plane"></i></div>
                    </div>
                </div>

                <!-- Card 1 — RestoBot -->
                <div class="hp-chatbot-card hp-theme-green pos-left">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar"><i class="fa-solid fa-utensils"></i></div>
                        <div class="hp-card-info">
                            <div class="hp-card-name">RestoBot</div>
                            <div class="hp-card-status">Online</div>
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
                        <div class="hp-card-send"><i class="fa-solid fa-paper-plane"></i></div>
                    </div>
                </div>

                <!-- Card 2 — SupportBot Pro (default center) -->
                <div class="hp-chatbot-card hp-theme-blue pos-center">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar"><i class="fa-solid fa-headset"></i></div>
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
                        <div class="hp-msg hp-msg-bot">Fill out the short form here: <a href="chatbotShowcase.php" style="color:#0057FF">returns-form</a></div>
                    </div>
                    <div class="hp-card-input">
                        <span>Type your message...</span>
                        <div class="hp-card-send"><i class="fa-solid fa-paper-plane"></i></div>
                    </div>
                </div>

                <!-- Card 3 — ShopAssist -->
                <div class="hp-chatbot-card hp-theme-orange pos-right">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar"><i class="fa-solid fa-cart-shopping"></i></div>
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
                        <div class="hp-card-send"><i class="fa-solid fa-paper-plane"></i></div>
                    </div>
                </div>

                <!-- Card 4 — BookingBot -->
                <div class="hp-chatbot-card hp-theme-pink pos-far-right">
                    <div class="hp-card-header">
                        <div class="hp-card-avatar"><i class="fa-solid fa-calendar-check"></i></div>
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
                        <div class="hp-card-send"><i class="fa-solid fa-paper-plane"></i></div>
                    </div>
                </div>

            </div><!-- /hp-carousel-viewport -->

            <button class="hp-carousel-btn hp-prev" id="hp-carousel-prev" aria-label="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <button class="hp-carousel-btn hp-next" id="hp-carousel-next" aria-label="Next">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
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
            <a href="chatbotShowcase.php" class="hp-link-green">
                See More <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

        <div class="hp-pop-carousel hp-fade-up">
            <button class="hp-pop-arrow hp-pop-prev" id="hp-pop-prev" aria-label="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>

            <div class="hp-pop-track-wrap">
                <div class="hp-pop-track" id="hp-pop-track">

                    <?php
                    // Tag color cycle — keďže chatboty nemajú priamu kategóriu, použijeme cyklus farieb
                    $tagColors = ['hp-tag-blue', 'hp-tag-green', 'hp-tag-purple', 'hp-tag-orange'];

                    $popularChatbots = [];
                    $tagNames        = [];

                    try {
                        $db = Database::getInstance()->getConnection();

                        // Načítaj top 12 chatbotov podľa ratingu a počtu favoritov
                        $stmt = $db->query("
                            SELECT
                                c.id,
                                c.name,
                                c.logo_url,
                                c.rating_average,
                                c.rating_count,
                                c.favorite_count
                            FROM chatbots c
                            WHERE c.status     = 'active'
                              AND c.deleted_at IS NULL
                              AND c.rating_count > 0
                            ORDER BY c.rating_average DESC, c.favorite_count DESC
                            LIMIT 12
                        ");
                        $popularChatbots = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Načítaj tagy pre tieto chatboty
                        if (!empty($popularChatbots)) {
                            $ids          = array_column($popularChatbots, 'id');
                            $placeholders = implode(',', array_fill(0, count($ids), '?'));
                            $tagStmt      = $db->prepare("
                                SELECT ct.chatbot_id, t.name
                                FROM chatbot_tags ct
                                JOIN tags t ON ct.tag_id = t.id
                                WHERE ct.chatbot_id IN ({$placeholders})
                                LIMIT 1
                            ");
                            $tagStmt->execute($ids);
                            foreach ($tagStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                $tagNames[$row['chatbot_id']] = $row['name'];
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
                        $tagColor   = $tagColors[$i % count($tagColors)];
                        $tagLabel   = $tagNames[$bot['id']] ?? 'Popular';
                        $logoUrl    = !empty($bot['logo_url']) ? '../' . htmlspecialchars($bot['logo_url']) : null;
                    ?>
                    <a href="chatbotShowcase.php" class="hp-pop-card">
                        <div class="hp-pop-tags">
                            <span class="hp-tag <?= $tagColor ?>">
                                <?= htmlspecialchars($tagLabel) ?>
                            </span>
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
                                <img src="<?= $logoUrl ?>" alt="<?= htmlspecialchars($bot['name']) ?>" style="width:100%;height:100%;object-fit:contain;border-radius:10px;">
                            <?php else: ?>
                                <i class="fa-solid fa-robot"></i>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>

                </div><!-- /hp-pop-track -->
            </div><!-- /hp-pop-track-wrap -->

            <button class="hp-pop-arrow hp-pop-next" id="hp-pop-next" aria-label="Next">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div><!-- /hp-pop-carousel -->
    </div>
</section>


<!-- ═══════════════════════════════════════
     FEATURES — SPEECH BUBBLES
═══════════════════════════════════════ -->
<section class="hp-features-section">
    <div class="hp-features-inner">

        <!-- Row 1: photo left, bubble right -->
        <div class="hp-feature-row">
            <div class="hp-feature-photo hp-fade-up">
                <div class="hp-feature-photo-ring"></div>
                <img
                    src="../img/assets/cda7e0ea-29e8-4c16-85cf-6aa3f5f6ca89.png"
                    alt="Business professional"
                    class="hp-feature-photo-img"
                />
            </div>
            <div class="hp-feature-bubble-col">
                <div class="hp-bubble from-right">
                    <h3>🏆 Top-Rated Chatbots Chosen by Businesses</h3>
                    <p>
                        See which chatbots companies trust the most, based on real user feedback
                        and verified performance data. Our ranking factors in response quality,
                        uptime, and customer satisfaction.
                    </p>
                    <a href="chatbotShowcase.php" class="hp-link-green" style="margin-top:16px;display:inline-flex">
                        Explore top rated <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Row 2: bubble left, photo right -->
        <div class="hp-feature-row hp-reverse">
            <div class="hp-feature-bubble-col">
                <div class="hp-bubble hp-bubble-green">
                    <h3>📊 Compare AI Chatbots by Features, Pricing, and Reviews</h3>
                    <p>
                        Explore detailed listings that highlight key features, pricing models,
                        and real user feedback. Our side-by-side comparison tool helps you
                        make the best decision in minutes — not hours.
                    </p>
                    <a href="chatbotShowcase.php" class="hp-link-green" style="margin-top:16px;display:inline-flex">
                        Start comparing <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </div>
            </div>
            <div class="hp-feature-photo hp-fade-up">
                <div class="hp-feature-photo-ring"></div>
                <img
                    src="../img/assets/c29d67d0-6e6c-4e60-ae4b-1b8419a1371e.png"
                    alt="Business professional"
                    class="hp-feature-photo-img"
                />
            </div>
        </div>

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
                <div class="hp-filter-visual-title">Filter by Industry</div>

                <div class="hp-filter-chip-row">
                    <span class="hp-filter-chip hp-filter-chip-blue hp-chip-active">E-commerce</span>
                    <span class="hp-filter-chip hp-filter-chip-green">Healthcare</span>
                    <span class="hp-filter-chip hp-filter-chip-purple">Legal</span>
                    <span class="hp-filter-chip hp-filter-chip-orange">Retail</span>
                    <span class="hp-filter-chip hp-filter-chip-teal">Finance</span>
                    <span class="hp-filter-chip hp-filter-chip-blue">HR</span>
                    <span class="hp-filter-chip hp-filter-chip-green">Education</span>
                </div>

                <div class="hp-filter-divider"></div>

                <div class="hp-filter-visual-title">Filter by Platform</div>
                <div class="hp-filter-chip-row">
                    <span class="hp-filter-chip hp-filter-chip-blue hp-chip-active">WordPress</span>
                    <span class="hp-filter-chip hp-filter-chip-orange">Shopify</span>
                    <span class="hp-filter-chip hp-filter-chip-purple">Custom</span>
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
                <i class="fa-solid fa-sliders"></i> Smart Filtering
            </div>
            <h2>Filter Chatbots by<br>Use Case and Industry</h2>
            <div class="hp-filter-features">
                <div class="hp-filter-feature">
                    <div class="hp-filter-feature-icon">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>
                    <div class="hp-filter-feature-text">
                        <strong>Industry-specific results</strong>
                        <span>Choose solutions designed for your industry — without wasting time on irrelevant options.</span>
                    </div>
                </div>
                <div class="hp-filter-feature">
                    <div class="hp-filter-feature-icon">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                    <div class="hp-filter-feature-text">
                        <strong>Narrow down instantly</strong>
                        <span>Quickly find tools that fit your business goals and existing workflow.</span>
                    </div>
                </div>
                <div class="hp-filter-feature">
                    <div class="hp-filter-feature-icon">
                        <i class="fa-solid fa-puzzle-piece"></i>
                    </div>
                    <div class="hp-filter-feature-text">
                        <strong>Platform compatibility</strong>
                        <span>Filter by <a href="chatbotShowcase.php">WordPress</a>, <a href="chatbotShowcase.php">Shopify</a>, custom websites, and more.</span>
                    </div>
                </div>
            </div>
            <a href="chatbotShowcase.php" class="hp-filter-cta">
                Browse All Chatbots <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
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
            <img src="../img/assets/ai_chatbot_pic.png" alt="AI Chatbot" class="hp-discover-bot" />
        </div>

        <div class="hp-discover-text">
            <h2>
                Discover, Rate, and Save the Best
                <span>AI Chatbots</span> for Your Website or Business
            </h2>
            <p>
                Save your favourite AI chatbots to your wishlist and access them anytime —
                compare, revisit, and decide at your own pace.
            </p>
            <p>
                Share your experience by rating the AI chatbots you use and help thousands
                of others discover reliable, high-performing options.
            </p>
            <a href="chatbotShowcase.php" class="hp-btn hp-btn-blue">
                Start Discovering <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

    </div>
</section>


<script src="../js/homePage.js" defer></script>

<?php
require '../inc/footer.php';
?>