<?php
/**
 * Advertise Page
 * Promotion plans for chatbot owners
 */

require '../inc/bootstrap.php';
require '../app/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advertise – ChatbotHub</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="../css/advertise.css">
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════════ -->
<section class="adv-hero">
    <div class="adv-hero__bg">
        <div class="adv-hero__blob adv-hero__blob--1"></div>
        <div class="adv-hero__blob adv-hero__blob--2"></div>
        <div class="adv-hero__blob adv-hero__blob--3"></div>
    </div>
    <div class="adv-hero__content">
        <div class="adv-hero__badge">📢 Advertise on ChatbotHub</div>
        <h1 class="adv-hero__title">
            Reach More Users<br>
            <span class="adv-hero__title--accent">With ChatbotHub Ads</span>
        </h1>
        <p class="adv-hero__subtitle">
            Every day, companies browse ChatbotHub looking for chatbot solutions that fit their needs.
            ChatbotHub Ads are your solution to get a competitive advantage.
        </p>
        <a href="#plans" class="adv-hero__cta">See Promotion Plans</a>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     WHY ADVERTISE
════════════════════════════════════════════════════════════ -->
<section class="adv-why">
    <div class="adv-container">
        <h2 class="adv-section-title">Why Advertise With Us?</h2>
        <div class="adv-why__grid">
            <div class="adv-why__card">
                <div class="adv-why__icon">🎯</div>
                <h3>High-Intent Visitors</h3>
                <p>People who visit already want a chatbot. These businesses are actively searching for a tool that fits their needs, so every listing has a real chance to turn into a lead.</p>
            </div>
            <div class="adv-why__card">
                <div class="adv-why__icon">🌍</div>
                <h3>Our Audience Is Global</h3>
                <p>The site attracts businesses from many countries, which gives each listed chatbot a chance to be discovered by clients outside their usual market.</p>
            </div>
            <div class="adv-why__card">
                <div class="adv-why__icon">📈</div>
                <h3>Steady Traffic Over Time</h3>
                <p>A listing can bring new visitors continuously, even without paid promotion. It serves as an extra channel that works in the background.</p>
            </div>
            <div class="adv-why__card">
                <div class="adv-why__icon">⚖️</div>
                <h3>Equal Visibility</h3>
                <p>New chatbot products get a fair chance to be discovered, even if they are still building their customer base.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     PROMOTION PLANS
════════════════════════════════════════════════════════════ -->
<section class="adv-plans" id="plans">
    <div class="adv-container">
        <h2 class="adv-section-title">Promotion Plans</h2>
        <p class="adv-section-subtitle">Choose the placement that works best for your chatbot</p>

        <!-- Billing period toggle — Monthly / Yearly only -->
        <div class="adv-billing-toggle" id="billingToggle">
            <button class="adv-billing-btn active" data-period="monthly">Monthly</button>
            <button class="adv-billing-btn" data-period="yearly">
                Yearly
                <span class="adv-billing-badge">Save more</span>
            </button>
        </div>

        <!-- Plans loading state -->
        <div class="adv-plans__loading" id="plansLoading">
            <div class="adv-spinner"></div>
            <p>Loading plans...</p>
        </div>

        <!-- Plans grid -->
        <div class="adv-plans__grid" id="plansGrid" style="display:none;">

            <!-- Homepage Hero Card -->
            <div class="adv-plan-card" id="card-homepage_hero">
                <div class="adv-plan-card__header">
                    <div class="adv-plan-card__icon">🏠</div>
                    <div class="adv-plan-card__badge">Most Popular</div>
                </div>
                <h3 class="adv-plan-card__name">Homepage Hero</h3>
                <p class="adv-plan-card__desc">Your chatbot appears at the very top of the homepage and inside the Most Popular section — the first thing every visitor sees.</p>
                <div class="adv-plan-card__price-wrap">
                    <div class="adv-plan-card__price">
                        <span class="adv-plan-card__currency">$</span>
                        <span class="adv-plan-card__amount" id="price-homepage_hero">—</span>
                        <span class="adv-plan-card__period" id="period-homepage_hero">/mo</span>
                    </div>
                </div>
                <ul class="adv-plan-card__features">
                    <li><span class="adv-check">✓</span> Displayed on Home page hero banner</li>
                    <li><span class="adv-check">✓</span> Featured in Most Popular carousel</li>
                    <li><span class="adv-check">✓</span> Logo, name & description highlighted</li>
                    <li><span class="adv-check">✓</span> Rotated every minute (max 3 chatbots)</li>
                </ul>
                <button class="adv-plan-card__btn" id="btn-homepage_hero" data-slot="homepage_hero">
                    Get Homepage Hero
                </button>
            </div>

            <!-- Category Featured Card -->
            <div class="adv-plan-card" id="card-category_featured">
                <div class="adv-plan-card__header">
                    <div class="adv-plan-card__icon">📂</div>
                </div>
                <h3 class="adv-plan-card__name">Category Featured</h3>
                <p class="adv-plan-card__desc">Your chatbot stands out at the beginning of the Chatbots page. Customers look at your chatbot as the first one they see in the list.</p>
                <div class="adv-plan-card__price-wrap">
                    <div class="adv-plan-card__price">
                        <span class="adv-plan-card__currency">$</span>
                        <span class="adv-plan-card__amount" id="price-category_featured">—</span>
                        <span class="adv-plan-card__period" id="period-category_featured">/mo</span>
                    </div>
                </div>
                <ul class="adv-plan-card__features">
                    <li><span class="adv-check">✓</span> Featured at top of Chatbots page</li>
                    <li><span class="adv-check">✓</span> Expanded chatbot card showcase</li>
                    <li><span class="adv-check">✓</span> Key Features & pricing highlighted</li>
                    <li><span class="adv-check">✓</span> 100% exposure if only promoter</li>
                </ul>
                <button class="adv-plan-card__btn" id="btn-category_featured" data-slot="category_featured">
                    Get Category Featured
                </button>
            </div>

            <!-- Bundle Card -->
            <div class="adv-plan-card adv-plan-card--bundle" id="card-bundle">
                <div class="adv-plan-card__header">
                    <div class="adv-plan-card__icon">⚡</div>
                    <div class="adv-plan-card__badge adv-plan-card__badge--gold">Best Value</div>
                </div>
                <h3 class="adv-plan-card__name">Full Bundle</h3>
                <p class="adv-plan-card__desc">Get both placements at once. Maximum visibility across the entire ChatbotHub platform — homepage and category page combined.</p>
                <div class="adv-plan-card__price-wrap">
                    <div class="adv-plan-card__price">
                        <span class="adv-plan-card__currency">$</span>
                        <span class="adv-plan-card__amount" id="price-bundle">—</span>
                        <span class="adv-plan-card__period" id="period-bundle">/mo</span>
                    </div>
                    <div class="adv-plan-card__savings" id="savings-bundle" style="display:none;">
                        Save <span id="savings-amount">0</span>% vs buying separately
                    </div>
                </div>
                <ul class="adv-plan-card__features">
                    <li><span class="adv-check">✓</span> Everything in Homepage Hero</li>
                    <li><span class="adv-check">✓</span> Everything in Category Featured</li>
                    <li><span class="adv-check">✓</span> Maximum platform-wide exposure</li>
                    <li><span class="adv-check">✓</span> Priority placement over single plans</li>
                </ul>
                <button class="adv-plan-card__btn adv-plan-card__btn--bundle" id="btn-bundle" data-slot="bundle">
                    Get Full Bundle
                </button>
            </div>

        </div>

        <!-- No listing warning -->
        <div class="adv-no-listing" id="noListingWarning" style="display:none;">
            <div class="adv-no-listing__icon">🔒</div>
            <h3>Active Listing Required</h3>
            <p>You need an active listing subscription before you can purchase a promotion. Submit your chatbot first and get it listed on ChatbotHub.</p>
            <a href="profile.php?tab=billing" class="adv-no-listing__btn">View My Listing</a>
        </div>

    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     HOW IT LOOKS — Preview section
════════════════════════════════════════════════════════════ -->
<section class="adv-preview">
    <div class="adv-container">
        <h2 class="adv-section-title">What You Get</h2>
        <div class="adv-preview__grid">
            <div class="adv-preview__item">
                <div class="adv-preview__screen">
                    <div class="adv-preview__screen-bar">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="adv-preview__screen-hero">
                        <div class="adv-preview__screen-label">🏠 Homepage Hero</div>
                        <div class="adv-preview__screen-card adv-preview__screen-card--sponsored">
                            <span class="adv-preview__sponsored-tag">Sponsored</span>
                            <div class="adv-preview__card-logo"></div>
                            <div class="adv-preview__card-info">
                                <div class="adv-preview__card-name"></div>
                                <div class="adv-preview__card-stars">★★★★★</div>
                            </div>
                        </div>
                    </div>
                </div>
                <p>Your chatbot card is displayed on top of the Home page and inside the list of Most Popular chatbot websites.</p>
            </div>
            <div class="adv-preview__item">
                <div class="adv-preview__screen">
                    <div class="adv-preview__screen-bar">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="adv-preview__screen-hero adv-preview__screen-hero--category">
                        <div class="adv-preview__screen-label">📂 Category Featured</div>
                        <div class="adv-preview__screen-card adv-preview__screen-card--featured">
                            <span class="adv-preview__sponsored-tag adv-preview__sponsored-tag--green">Featured</span>
                            <div class="adv-preview__card-logo"></div>
                            <div class="adv-preview__card-info">
                                <div class="adv-preview__card-name"></div>
                                <div class="adv-preview__card-desc"></div>
                                <div class="adv-preview__card-desc" style="width:60%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p>You get to be at the beginning of the Chatbots page, where customers look at your chatbot as the first one.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Get Promotion
════════════════════════════════════════════════════════════ -->
<div class="adv-modal-overlay" id="promotionModalOverlay">
    <div class="adv-modal" id="promotionModal">
        <button class="adv-modal__close" id="modalCloseBtn">&times;</button>

        <div class="adv-modal__header">
            <h2 class="adv-modal__title" id="modalTitle">Get Promotion</h2>
            <p class="adv-modal__subtitle" id="modalSubtitle">Select your chatbot and billing period</p>
        </div>

        <div class="adv-modal__body">

            <!-- Step 1: Select Chatbot -->
            <div class="adv-modal__step" id="modalStep1">
                <label class="adv-modal__label">
                    Select Chatbot
                    <span class="adv-modal__label-hint">Only chatbots with active listings are shown</span>
                </label>
                <div class="adv-modal__select-wrap">
                    <select class="adv-modal__select" id="chatbotSelect">
                        <option value="">Loading chatbots...</option>
                    </select>
                </div>
            </div>

            <!-- Step 2: Billing Period -->
            <div class="adv-modal__step" id="modalStep2">
                <label class="adv-modal__label">Billing Period</label>
                <div class="adv-modal__period-grid" id="modalPeriodGrid">
                    <!-- Filled by JS -->
                </div>
            </div>

            <!-- Step 3: Summary -->
            <div class="adv-modal__summary" id="modalSummary">
                <div class="adv-modal__summary-row">
                    <span>Plan</span>
                    <strong id="summaryPlan">—</strong>
                </div>
                <div class="adv-modal__summary-row">
                    <span>Chatbot</span>
                    <strong id="summaryChatbot">—</strong>
                </div>
                <div class="adv-modal__summary-row">
                    <span>Billing</span>
                    <strong id="summaryBilling">—</strong>
                </div>
                <div class="adv-modal__summary-row adv-modal__summary-row--total">
                    <span>Total</span>
                    <strong id="summaryTotal">—</strong>
                </div>
            </div>

            <p class="adv-modal__error" id="modalError"></p>
        </div>

        <div class="adv-modal__footer">
            <button class="adv-modal__cancel" id="modalCancelBtn">Cancel</button>
            <button class="adv-modal__confirm" id="modalConfirmBtn">
                <span id="modalConfirmText">Continue to Payment</span>
                <span id="modalConfirmLoader" style="display:none;">Processing...</span>
            </button>
        </div>
    </div>
</div>

<!-- Hidden: pass PHP data to JS -->
<script>
    window.advData = {
        isLoggedIn: <?php echo $isLoggedIn ? 'true' : 'false'; ?>,
        loginUrl: 'login.php'
    };
</script>

<?php require '../inc/footer.php'; ?>
<script src="../js/advertise.js"></script>
</body>
</html>