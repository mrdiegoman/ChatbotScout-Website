<?php
require '../inc/bootstrap.php';

// Neprihlásený user → redirect na login
if (!$isLoggedIn) {
    header('Location: login?redirect=favourites');
    exit;
}

require '../inc/header.php';
?>

<div class="favourites-page-wrapper">
    <main class="favourites-page-main">
        <aside class="favourites-page-main-banner">
            <div class="favourites-page-main-banner-content">
                <div class="favourites-page-main-banner-textdiv">
                    <h1>Favourites</h1>
                    <p>
                        Here you'll find the chatbots you've marked as your favorites. This space makes
                        it easy to revisit the tools you're considering, compare their features, and
                        decide which one fits your needs best. You can remove items anytime or come back
                        later to continue expanding your list of favorites.
                    </p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                </svg>
            </div>
        </aside>

        <section class="favourites-page-main-content">
            <div class="favourites-page-main-content-topbar">
                <div class="favourites-page-main-content-topbar-group">

                    <!-- Desktop Search -->
                    <label class="favourites-page-main-content-topbar-search favourites-page-main-content-topbar-search-desktop">
                        <input type="text" placeholder="Search" class="favourites-page-main-content-topbar-search-input" id="searchInput">
                        <button type="button" class="favourites-page-main-content-topbar-search-input-clear-btn" id="searchClearBtn" aria-label="Clear search">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                class="favourites-page-main-content-topbar-search-input-clear-icon" fill="none"
                                stroke="#232127" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path d="m15 9-6 6" /><path d="m9 9 6 6" />
                            </svg>
                        </button>
                        <button type="button" class="favourites-page-main-content-topbar-search-submit-btn" aria-label="Toggle search">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path fill="#232127" d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z"/>
                            </svg>
                        </button>
                    </label>

                    <!-- Mobile search button -->
                    <button type="button" class="favourites-page-main-content-topbar-mobile-search-btn" id="mobileSearchBtn" aria-label="Toggle mobile search">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path fill="#232127"
                                d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z" />
                        </svg>
                    </button>

                    <div class="favourites-page-main-content-topbar-group-in">

                        <!-- Filters button -->
                        <button class="favourites-page-main-content-topbar-group-filters-button" id="filtersBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"
                                class="favourites-page-main-content-topbar-group-sort-filters-button-icon">
                                <path fill="#232127" d="M96 128C83.1 128 71.4 135.8 66.4 147.8C61.4 159.8 64.2 173.5 73.4 182.6L256 365.3L256 480C256 488.5 259.4 496.6 265.4 502.6L329.4 566.6C338.6 575.8 352.3 578.5 364.3 573.5C376.3 568.5 384 556.9 384 544L384 365.3L566.6 182.7C575.8 173.5 578.5 159.8 573.5 147.8C568.5 135.8 556.9 128 544 128L96 128z"/>
                            </svg>
                            <p>Filters</p>
                            <div class="favourites-page-main-content-topbar-group-filters-button-count" id="filtersCount" style="display:none;">
                                <p id="filtersCountNum">0</p>
                            </div>
                        </button>

                        <!-- Sort -->
                        <div class="favourites-page-main-content-topbar-group-sort">
                            <p class="favourites-page-main-content-topbar-group-sort-label">Sort by</p>
                            <div class="favourites-page-main-content-topbar-group-sort-select-div" id="sortSelectDiv">
                                <div class="favourites-page-main-content-topbar-group-sort-select">
                                    <div class="favourites-page-main-content-topbar-group-sort-select-selected" id="sortSelected">
                                        Date added
                                    </div>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#232127"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="favourites-page-main-content-topbar-group-sort-droparrow-icon">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                                <ul class="favourites-page-main-content-topbar-group-sort-select-options" id="sortOptions">
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option selected" data-value="date-added">Date added</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option" data-value="popularity">Popularity</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option" data-value="price">Price</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option" data-value="alphabetical">Alphabetical</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option" data-value="rating">Rating</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option" data-value="number-of-ratings">Number of Ratings</li>
                                </ul>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#232127"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                                class="favourites-page-main-content-topbar-group-sort-arrow-icon" id="sortArrow">
                                <path d="M8 18L12 22L16 18"/>
                                <path d="M12 2V22"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Layout toggle -->
                    <div class="favourites-page-main-content-topbar-group-layout-container">
                        <button class="favourites-page-main-content-topbar-group-layout-container-btn" id="listBtn" data-layout="list">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/>
                                <path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>
                            </svg>
                        </button>
                        <button class="favourites-page-main-content-topbar-group-layout-container-btn active" id="gridBtn" data-layout="grid">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v18"/><path d="M3 12h18"/>
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                            </svg>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Mobile search bar -->
            <div class="favourites-page-main-content-mobile-search-bar" id="mobileSearchBar">
                <label class="favourites-page-main-content-topbar-search active">
                    <input type="text" placeholder="Search" class="favourites-page-main-content-topbar-search-input" id="mobileSearchInput">
                    <button type="button" class="favourites-page-main-content-topbar-search-input-clear-btn" id="mobileSearchClearBtn" aria-label="Clear search">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            class="favourites-page-main-content-topbar-search-input-clear-icon" fill="none"
                            stroke="#232127" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <path d="m15 9-6 6" />
                            <path d="m9 9 6 6" />
                        </svg>
                    </button>
                    <button type="button" class="favourites-page-main-content-topbar-search-submit-btn" id="mobileSearchSubmitBtn" aria-label="Submit mobile search">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path fill="#232127"
                                d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z" />
                        </svg>
                    </button>
                </label>
            </div>

            <!-- CHATBOT ITEMS -->
            <div class="favourites-page-main-content-chatbots">

                <div class="favourites-showcase-loading" id="favouritesLoading">
                    <div class="favourites-showcase-loading-spinner"></div>
                </div>

                <div class="favourites-page-main-content-chatbots-grid-view active" id="gridView"></div>
                <div class="favourites-page-main-content-chatbots-list-view" id="listView"></div>

                <div class="favourites-showcase-empty" id="favouritesEmpty" style="display:none;">
                    <p>You have no favourites yet. <a href="chatbots">Browse chatbots</a></p>
                </div>

            </div>

            <!-- BOTTOM BAR -->
            <div class="favourites-showcase-bottom-bar" id="bottomBar" style="display:none;">
                <nav class="favourites-showcase-pagination" id="paginationContainer" aria-label="Favourites pages"></nav>
                <button class="favourites-showcase-move-up-btn" id="moveUpBtn" aria-label="Move to top">
                    Move Up
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 15l-6-6-6 6"/>
                    </svg>
                </button>
            </div>

        </section>
    </main>
</div>

<?php require '../inc/footer.php'; ?>

<!-- FILTERS MODAL -->
<div class="favourites-filters-modal-overlay" id="filtersModalOverlay">
    <div class="favourites-filters-modal-content">
        <div class="favourites-filters-modal-header">
            <h2>Filter favorite chatbots by</h2>
            <button type="button" class="modal-close favourites-filters-modal-close-btn" id="filtersCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="favourites-filters-modal-body">
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label"><p>Rating</p></span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0" max="5" step="0.1" id="ratingInputStart" placeholder="0.0">
                    <p>to</p>
                    <input type="number" min="0" max="5" step="0.1" id="ratingInputEnd" placeholder="5.0">
                </div>
            </div>
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label"><p>Number of ratings</p></span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0" max="1000000" step="1" id="votesInputStart" placeholder="0">
                    <p>to</p>
                    <input type="number" min="0" max="1000000" step="1" id="votesInputEnd" placeholder="max">
                </div>
            </div>
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label"><p>Number of favorites</p></span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0" max="1000000" step="1" id="favoritesInputStart" placeholder="0">
                    <p>to</p>
                    <input type="number" min="0" max="1000000" step="1" id="favoritesInputEnd" placeholder="max">
                </div>
            </div>
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Price, approx. in</p>
                    <p class="favourites-filters-modal-body-row-group-label-currency">€</p>
                </span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0.00" max="10000.00" step="0.01" id="priceInputStart" placeholder="0.00">
                    <p>to</p>
                    <input type="number" min="0.00" max="10000.00" step="0.01" id="priceInputEnd" placeholder="max">
                </div>
            </div>
            <!-- CURRENCY FILTER -->
            <div class="favourites-filters-modal-body-features-wrapper">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Currency</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224C352 241.7 337.7 256 320 256C302.3 256 288 241.7 288 224zM280 288L328 288C341.3 288 352 298.7 352 312L352 400L360 400C373.3 400 384 410.7 384 424C384 437.3 373.3 448 360 448L280 448C266.7 448 256 437.3 256 424C256 410.7 266.7 400 280 400L304 400L304 336L280 336C266.7 336 256 325.3 256 312C256 298.7 266.7 288 280 288z"/>
                    </svg>
                    <div class="favourites-filters-modal-body-row-group-label-tooltip">
                        <p>Select one or more currencies. Results will include chatbots
                        that use <strong>at least one</strong> of the selected currencies.</p>
                    </div>
                </span>
                <div class="favourites-filters-modal-body-features-container" id="currencyContainer">
                    <!-- Dynamicky plnené cez filterCurrencyAdding.js -->
                </div>
            </div>
            <div class="favourites-filters-modal-body-features-wrapper">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Features</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224C352 241.7 337.7 256 320 256C302.3 256 288 241.7 288 224zM280 288L328 288C341.3 288 352 298.7 352 312L352 400L360 400C373.3 400 384 410.7 384 424C384 437.3 373.3 448 360 448L280 448C266.7 448 256 437.3 256 424C256 410.7 266.7 400 280 400L304 400L304 336L280 336C266.7 336 256 325.3 256 312C256 298.7 266.7 288 280 288z"/>
                    </svg>
                    <div class="favourites-filters-modal-body-row-group-label-tooltip">
                        <p>The numbers in the bubbles show how many chatbots offer that feature 
                            along with your current selections. These counts will update 
                            automatically as you select more features.</p>
                        </div>
                </span>
                <div class="favourites-filters-modal-body-features-container" id="basicFeaturesContainer">
                    <!-- Dynamicky plnené cez filterFeaturesAdding.js -->
                </div>
                <span class="favourites-advanced-filters-modal-body-row-group-label" id="advancedFeaturesToggleLabel">
                    <p>Show advanced features</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M300.3 440.8C312.9 451 331.4 450.3 343.1 438.6L471.1 310.6C480.3 301.4 483 287.7 478 275.7C473 263.7 461.4 256 448.5 256L192.5 256C179.6 256 167.9 263.8 162.9 275.8C157.9 287.8 160.7 301.5 169.9 310.6L297.9 438.6L300.3 440.8z"/>
                    </svg>
                </span>
                <div class="favourites-advanced-filters-modal-body-features-container" id="advancedFeaturesContainer">
                    <!-- Dynamicky plnené cez filterFeaturesAdding.js -->
                </div>
            </div>
            <!-- INTEGRATIONS FILTER -->
            <div class="favourites-filters-modal-body-features-wrapper">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Integrations</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224C352 241.7 337.7 256 320 256C302.3 256 288 241.7 288 224zM280 288L328 288C341.3 288 352 298.7 352 312L352 400L360 400C373.3 400 384 410.7 384 424C384 437.3 373.3 448 360 448L280 448C266.7 448 256 437.3 256 424C256 410.7 266.7 400 280 400L304 400L304 336L280 336C266.7 336 256 325.3 256 312C256 298.7 266.7 288 280 288z"/>
                    </svg>
                    <div class="favourites-filters-modal-body-row-group-label-tooltip">
                        <p>Select one or more integrations. Results will include chatbots 
                           that support <strong>at least one</strong> of your selected integrations →
                           the more you pick, the broader the results.</p>
                    </div>
                </span>
                <div class="favourites-filters-modal-body-features-container" id="integrationsContainer">
                    <!-- Dynamicky plnené cez filterIntegrationsAdding.js -->
                </div>
            </div>
            <!-- PRICING MODEL FILTER -->
            <div class="favourites-filters-modal-body-features-wrapper">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Pricing Model</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224C352 241.7 337.7 256 320 256C302.3 256 288 241.7 288 224zM280 288L328 288C341.3 288 352 298.7 352 312L352 400L360 400C373.3 400 384 410.7 384 424C384 437.3 373.3 448 360 448L280 448C266.7 448 256 437.3 256 424C256 410.7 266.7 400 280 400L304 400L304 336L280 336C266.7 336 256 325.3 256 312C256 298.7 266.7 288 280 288z"/>
                    </svg>
                    <div class="favourites-filters-modal-body-row-group-label-tooltip">
                        <p>Select one or more pricing models. Results will include chatbots
                           that match <strong>at least one</strong> of the selected models.</p>
                    </div>
                </span>
                <div class="favourites-filters-modal-body-features-container" id="pricingContainer">
                    <!-- Dynamicky plnené cez filterPricingAdding.js -->
                </div>
            </div>
            <div class="favourites-filters-modal-body-features-wrapper">
                <span class="favourites-filters-modal-body-row-group-label"><p>Free trial</p></span>
                <div class="favourites-filters-modal-body-features-container">
                    <button type="button" class="favourites-free-trial-filter" id="freeTrialFilter" aria-pressed="false">Free trial</button>
                </div>
            </div>
        </div>
        <div class="favourites-filters-modal-footer">
            <button class="favourites-filters-modal-clear-btn" id="filtersClearBtn">Clear all</button>
            <span class="favourites-filters-modal-results-count" id="filterResultsCount"></span>
            <button class="favourites-filters-modal-apply-btn">Apply Filters</button>
        </div>
    </div>
</div>

<!-- RATING MODAL -->
<div class="chatbot-rating-modal-overlay" id="ratingModalOverlay">
    <div class="chatbot-rating-modal-content">
        <div class="chatbot-rating-modal-header">
            <h2 id="ratingModalTitle">Rate this chatbot</h2>
            <button type="button" class="modal-close chatbot-rating-modal-close-btn" id="ratingModalCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="chatbot-rating-modal-body">
            <div class="chatbot-rating-modal-stars-wrapper">
                <div class="chatbot-rating-modal-stars" id="ratingStarsPicker">
                    <span class="chatbot-rating-modal-star" data-value="1">★</span>
                    <span class="chatbot-rating-modal-star" data-value="2">★</span>
                    <span class="chatbot-rating-modal-star" data-value="3">★</span>
                    <span class="chatbot-rating-modal-star" data-value="4">★</span>
                    <span class="chatbot-rating-modal-star" data-value="5">★</span>
                </div>
                <p class="chatbot-rating-modal-stars-label" id="ratingStarsLabel">Select a rating</p>
            </div>
            <div class="chatbot-rating-modal-field">
                <label for="ratingTextInput">Your review</label>
                <textarea id="ratingTextInput" maxlength="5000" rows="4" wrap="soft" placeholder="Share your experience with this chatbot..."></textarea>
            </div>
            <p class="chatbot-rating-modal-error" id="ratingModalError"></p>
            <p class="chatbot-rating-modal-success" id="ratingModalSuccess"></p>
        </div>
        <div class="chatbot-rating-modal-footer">
            <button class="chatbot-rating-modal-cancel-btn" id="ratingModalCancelBtn">Cancel</button>
            <button class="chatbot-rating-modal-submit-btn" id="ratingModalSubmitBtn">Submit Review</button>
        </div>
    </div>
</div>

<script src="js/featuresSwiping.js?v=<?= filemtime(__DIR__ . '/js/featuresSwiping.js') ?>" defer></script>
<script src="js/layoutSwapping.js?v=<?= filemtime(__DIR__ . '/js/layoutSwapping.js') ?>" defer></script>
<script src="js/filterCurrencyAdding.js?v=<?= filemtime(__DIR__ . '/js/filterCurrencyAdding.js') ?>"></script>
<script src="js/filterFeaturesAdding.js?v=<?= filemtime(__DIR__ . '/js/filterFeaturesAdding.js') ?>" defer></script>
<script src="js/filterIntegrationsAdding.js?v=<?= filemtime(__DIR__ . '/js/filterIntegrationsAdding.js') ?>" defer></script>
<script src="js/filterPricingAdding.js?v=<?= filemtime(__DIR__ . '/js/filterPricingAdding.js') ?>"></script>
<script src="js/showAdvancedFeatures.js?v=<?= filemtime(__DIR__ . '/js/showAdvancedFeatures.js') ?>" defer></script>
<script src="js/favouritesShowcase.js?v=<?= filemtime(__DIR__ . '/js/favouritesShowcase.js') ?>" defer></script>
<script src="js/platformsPopup.js?v=<?= filemtime(__DIR__ . '/js/platformsPopup.js') ?>" defer></script>

