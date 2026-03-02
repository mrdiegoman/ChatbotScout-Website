<?php
require '../inc/bootstrap.php';
require '../inc/header.php';
?>

<div class="favourites-page-wrapper">
    <main class="favourites-page-main">
        <aside class="favourites-page-main-banner">
            <div class="favourites-page-main-banner-textdiv">
                <h1>
                    Favourites
                </h1>
                <p>
                    Here you’ll find the chatbots you’ve marked as your favorites. This space makes 
                    it easy to revisit the tools you’re considering, compare their features, and 
                    decide which one fits your needs best. You can remove items anytime or come back 
                    later to continue expanding your list of favorites. 
                </p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5" />
            </svg>
        </aside>
        <section class="favourites-page-main-content">
            <div class="favourites-page-main-content-topbar">
                <div class="favourites-page-main-content-topbar-group">
                    <!-- Desktop search bar -->
                    <label class="favourites-page-main-content-topbar-search favourites-page-main-content-topbar-search-desktop">
                        <input type="text" placeholder="Search" class="favourites-page-main-content-topbar-search-input">
                        <button class="favourites-page-main-content-topbar-search-input-clear-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                class="favourites-page-main-content-topbar-search-input-clear-icon" fill="none"
                                stroke="#232127" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path d="m15 9-6 6" />
                                <path d="m9 9 6 6" />
                            </svg>
                        </button>
                        <button class="favourites-page-main-content-topbar-search-submit-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path fill="#232127"
                                    d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z" />
                            </svg>
                        </button>
                    </label>

                    <!-- Mobile search button -->
                    <button class="favourites-page-main-content-topbar-mobile-search-btn" id="mobileSearchBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path fill="#232127"
                                d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z" />
                        </svg>
                    </button>
                    <div class="favourites-page-main-content-topbar-group-in">
                        <button class="favourites-page-main-content-topbar-group-filters-button" id="filtersBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"
                                class="favourites-page-main-content-topbar-group-sort-filters-button-icon">
                                <path fill="#232127"
                                    d="M96 128C83.1 128 71.4 135.8 66.4 147.8C61.4 159.8 64.2 173.5 73.4 182.6L256 365.3L256 480C256 488.5 259.4 496.6 265.4 502.6L329.4 566.6C338.6 575.8 352.3 578.5 364.3 573.5C376.3 568.5 384 556.9 384 544L384 365.3L566.6 182.7C575.8 173.5 578.5 159.8 573.5 147.8C568.5 135.8 556.9 128 544 128L96 128z" />
                            </svg>

                            <p>Filters</p>
                            <div class="favourites-page-main-content-topbar-group-filters-button-count">
                                <p>2</p>
                            </div>
                        </button>

                        <div class="favourites-page-main-content-topbar-group-sort">
                            <p class="favourites-page-main-content-topbar-group-sort-label">Sort by</p>
                            <div class="favourites-page-main-content-topbar-group-sort-select-div">
                                <div class="favourites-page-main-content-topbar-group-sort-select">
                                    <div class="favourites-page-main-content-topbar-group-sort-select-selected">
                                        Popularity
                                    </div>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#232127"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="favourites-page-main-content-topbar-group-sort-droparrow-icon">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                                <ul class="favourites-page-main-content-topbar-group-sort-select-options">
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option selected"
                                        data-value="popularity">Popularity</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option"
                                        data-value="price">Price</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option"
                                        data-value="alphabetical">Alphabetical</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option"
                                        data-value="rating">Rating</li>
                                    <li class="favourites-page-main-content-topbar-group-sort-select-option"
                                        data-value="number-of-ratings">Number of Ratings</li>
                                </ul>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0
                                24 24" fill="none" stroke="#232127" stroke-width="1.75" stroke-linecap="round"
                                stroke-linejoin="round" class="favourites-page-main-content-topbar-group-sort-arrow-icon">
                                <path d="M8 18L12 22L16 18" />
                                <path d="M12 2V22" />
                            </svg>
                        </div>
                    </div>
                     <div class="favourites-page-main-content-topbar-group-layout-container">
                        <button class="favourites-page-main-content-topbar-group-layout-container-btn" id="listBtn"
                            data-layout="list">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
                                class="favourites-page-main-content-topbar-group-layout-container-list-icon">
                                <path d="M3 5h.01" />
                                <path d="M3 12h.01" />
                                <path d="M3 19h.01" />
                                <path d="M8 5h13" />
                                <path d="M8 12h13" />
                                <path d="M8 19h13" />
                            </svg>
                        </button>

                        <button class="favourites-page-main-content-topbar-group-layout-container-btn active" id="gridBtn"
                            data-layout="grid">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-grid2x2-icon lucide-grid-2x2">
                                <path d="M12 3v18" />
                                <path d="M3 12h18" />
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile search bar (zobrazí sa pod topbarom) -->
            <div class="favourites-page-main-content-mobile-search-bar" id="mobileSearchBar">
                <label class="favourites-page-main-content-topbar-search">
                    <input type="text" placeholder="Search" class="favourites-page-main-content-topbar-search-input" id="mobileSearchInput">
                    <button class="favourites-page-main-content-topbar-search-input-clear-btn" id="mobileSearchClearBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            class="favourites-page-main-content-topbar-search-input-clear-icon" fill="none"
                            stroke="#232127" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <path d="m15 9-6 6" />
                            <path d="m9 9 6 6" />
                        </svg>
                    </button>
                    <button class="favourites-page-main-content-topbar-search-submit-btn" id="mobileSearchSubmitBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path fill="#232127"
                                d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z" />
                        </svg>
                    </button>
                </label>
            </div>

            <div class="favourites-page-main-content-chatbots">

                <div class="favourites-page-main-content-chatbots-grid-view active" id="gridView">
                    <div class="favourites-page-main-content-chatbots-grid-view-item">
                        <div class="favourites-page-main-content-chatbots-grid-view-item-tags">
                            <span>
                                <p>✦ Popular ✦</p>
                            </span>
                            <span>
                                <p>✦ Affiliate ✦</p>
                            </span>
                        </div>
                        <div class="favourites-page-main-content-chatbots-grid-view-item-top">
                            <div class="favourites-page-main-content-chatbots-grid-view-item-top-img-wrapper">
                                <img src="..\img\chatbothub_logo.png" alt="Website logo">
                            </div>
                            <div class="favourites-page-main-content-chatbots-grid-view-item-top-base">
                                <div class="favourites-page-main-content-chatbots-grid-view-item-top-base-1">
                                    <p>ChatbotHub</p>
                                    <div class="favourites-page-main-content-chatbots-grid-view-item-top-base-2">
                                        <div class="favourites-page-main-content-chatbots-item-top-base-2-rating"
                                            data-rating="4">
                                            <span>★</span>
                                            <span>★</span>
                                            <span>★</span>
                                            <span>★</span>
                                            <span>★</span>
                                        </div>
                                        <p>4.0</p>
                                        <p>(650)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="favourites-page-main-content-chatbots-grid-view-item-description-container">
                            <div class="favourites-page-main-content-chatbots-grid-view-item-description-container-header">
                                <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-subtitle">
                                    Description</p>
                                <button class="favourites-page-main-content-chatbots-grid-view-item-features-container-1-imp-btn">
                                    <p>Platforms</p>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                                        stroke-linejoin="round">
                                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="favourites-page-main-content-chatbots-grid-view-item-description-container-text">
                                At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis
                                praesentium voluptatum deleniti atque corrupti quos dolores et quas
                                molestias excepturi sint occaecati.</p>
                        </div>
                        
                        <div class="favourites-page-main-content-chatbots-grid-view-item-btns-container">
                            <div class="favourites-page-main-content-chatbots-grid-view-item-btns-container-sipka">
                                <span class="favourites-page-main-content-chatbots-grid-view-item-btns-container-sipka-text">FREE | 40$</span>
                            </div>
                            <a href="#" class="favourites-page-main-content-chatbots-grid-view-item-btns-container-go-to-website">
                                <p>Go to Website</p>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                                    stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/>
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="favourites-page-main-content-chatbots-grid-view-item">Položka 2</div>
                    <div class="favourites-page-main-content-chatbots-grid-view-item">Položka 3</div>
                    <div class="favourites-page-main-content-chatbots-grid-view-item">Položka 4</div>
                    <div class="favourites-page-main-content-chatbots-grid-view-item">Položka 5</div>
                    <div class="favourites-page-main-content-chatbots-grid-view-item">Položka 6</div>
                </div>
                <!-- LIST VIEW ----------------------------------------------------------------->
                <div class="favourites-page-main-content-chatbots-list-view" id="listView">
                    <div class="favourites-page-main-content-chatbots-list-view-item-containter">
                        <button class="favourites-page-main-content-chatbots-list-view-item-remove-btn" aria-label="Remove favourite">×</button>
                        <div class="favourites-page-main-content-chatbots-list-view-item-tags">
                            <span>
                                <p>✦ Popular ✦</p>
                            </span>
                            <span>
                                <p>✦ Affiliate ✦</p>
                            </span>
                        </div>
                        <div class="favourites-page-main-content-chatbots-list-view-item">
                            <div class="favourites-page-main-content-chatbots-list-view-item-features-container-left">
                                <div class="favourites-page-main-content-chatbots-list-view-item-top">
                                    <div class="favourites-page-main-content-chatbots-list-view-item-top-img-wrapper">
                                        <img src="..\img\chatbothub_logo.png" alt="Website logo">
                                    </div>
                                    <div class="favourites-page-main-content-chatbots-list-view-item-top-base">
                                        <div class="favourites-page-main-content-chatbots-list-view-item-top-base-1">
                                            <p>ChatbotHub</p>
                                            <div class="favourites-page-main-content-chatbots-list-view-item-top-base-2">
                                                <div class="favourites-page-main-content-chatbots-item-top-base-2-rating"
                                                    data-rating="4">
                                                    <span>★</span>
                                                    <span>★</span>
                                                    <span>★</span>
                                                    <span>★</span>
                                                    <span>★</span>
                                                </div>
                                                <p>4.0</p>
                                                <p>(650)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="favourites-page-main-content-chatbots-list-view-item-description-container">
                                    <div class="favourites-page-main-content-chatbots-list-view-item-description-container-header">
                                        <p class="favourites-page-main-content-chatbots-list-view-item-description-container-subtitle">
                                            Description</p>
                                        <button class="favourites-page-main-content-chatbots-list-view-item-features-container-1-imp-btn">
                                            <p>Platforms</p>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                                                stroke-linejoin="round">
                                                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="favourites-page-main-content-chatbots-list-view-item-description-container-text">
                                        At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis
                                        praesentium voluptatum deleniti atque corrupti quos dolores et quas
                                        molestias excepturi sint occaecati.</p>
                                </div>
                            </div>
                            <div class="favourites-page-main-content-chatbots-list-view-item-features-container-right">
                                <div class="favourites-page-main-content-chatbots-list-view-item-features-container">
                                    <div class="favourites-page-main-content-chatbots-list-view-item-features-container-1">
                                        <p class="favourites-page-main-content-chatbots-list-view-item-description-container-subtitle">
                                            Key Features
                                        </p>
                                    </div>
                                    <div class="favourites-page-main-content-chatbots-list-view-item-scroll-wrapper">
                                        <div class="favourites-page-main-content-chatbots-list-view-item-scroll-container">
                                            <div class="favourites-page-main-content-chatbots-list-view-item-cards-wrapper">
                                                <div class="favourites-page-main-content-chatbots-list-view-item-card">
                                                    <ul>
                                                        <li>Responzívne rozloženie</li>
                                                        <li>Moderné UI komponenty</li>
                                                        <li>Optimalizácia pre mobily</li>
                                                        <li>Prístupnosť a použiteľnosť</li>
                                                    </ul>
                                                </div>

                                                <div class="favourites-page-main-content-chatbots-list-view-item-card">
                                                    <ul>
                                                        <li>Frontend technológie</li>
                                                        <li>Backend integrácia</li>
                                                        <li>Databázové riešenia</li>
                                                        <li>API development</li>
                                                    </ul>
                                                </div>

                                                <div class="favourites-page-main-content-chatbots-list-view-item-card">
                                                    <ul>
                                                        <li>Používateľský prieskum</li>
                                                        <li>Wireframing a prototypy</li>
                                                        <li>Vizuálna hierarchia</li>
                                                        <li>Testovanie použiteľnosti</li>
                                                    </ul>
                                                </div>

                                                <div class="favourites-page-main-content-chatbots-list-view-item-card">
                                                    <ul>
                                                        <li>Rýchlosť načítania stránok</li>
                                                        <li>SEO optimalizácia</li>
                                                        <li>Kompresia obrázkov</li>
                                                        <li>Caching stratégie</li>
                                                    </ul>
                                                </div>

                                                <div class="favourites-page-main-content-chatbots-list-view-item-card">
                                                    <ul>
                                                        <li>HTTPS protokol</li>
                                                        <li>Autentifikácia užívateľov</li>
                                                        <li>Ochrana dát</li>
                                                        <li>Bezpečnostné audity</li>
                                                    </ul>
                                                </div>

                                                <div class="favourites-page-main-content-chatbots-list-view-item-card">
                                                    <ul>
                                                        <li>Unit testy</li>
                                                        <li>Integračné testovanie</li>
                                                        <li>E2E testovanie</li>
                                                        <li>Performance testing</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="favourites-page-main-content-chatbots-list-view-item-scrollbar">
                                            <div class="favourites-page-main-content-chatbots-list-view-item-scrollbar-thumb"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="favourites-page-main-content-chatbots-list-view-item-btns-container">
                                    <div class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka">
                                        <span class="favourites-page-main-content-chatbots-list-view-item-btns-container-sipka-text">FREE | 40$</span>
                                    </div>
                                    <a href="#" class="favourites-page-main-content-chatbots-list-view-item-btns-container-go-to-website">
                                        <p>Go to Website</p>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                                            stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/>
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <!-- koniec -->
                        </div>
                    </div>
                    <div class="favourites-page-main-content-chatbots-list-view-item">Položka 2</div>
                    <div class="favourites-page-main-content-chatbots-list-view-item">Položka 3</div>
                    <div class="favourites-page-main-content-chatbots-list-view-item">Položka 4</div>
                    <div class="favourites-page-main-content-chatbots-list-view-item">Položka 5</div>
                    <div class="favourites-page-main-content-chatbots-list-view-item">Položka 6</div>
                </div>
            </div>
        </section>
    </main>
</div>



<?php
require '../inc/footer.php';
?>

<div class="favourites-filters-modal-overlay" id="filtersModalOverlay">
    <div class="favourites-filters-modal-content">
        <div class="favourites-filters-modal-header">
            <h2>Filter chatbots by</h2>
            <button class="favourites-filters-modal-close-btn" id="filtersCloseBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="#232127" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m15 9-6 6" />
                    <path d="m9 9 6 6" />
                </svg>
            </button>
        </div>
        <div class="favourites-filters-modal-body">
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Rating</p>
                </span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0" max="5" step="0.1" id="ratingInputStart" placeholder="0.0">
                    <p>to</p>
                    <input type="number" min="0" max="5" step="0.1" id="ratingInputEnd" placeholder="5.0">
                </div>
            </div>
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Number of votes</p>
                </span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0" max="1000000" step="1" id="votesInputStart" placeholder="0">
                    <p>to</p>
                    <input type="number" min="0" max="1000000" step="1" id="votesInputEnd" placeholder="max">
                </div>
            </div>
            <div class="favourites-filters-modal-body-row-group">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Price in</p>
                    <p class="favourites-filters-modal-body-row-group-label-currency">$</p> <!-- neskor pridat viacej ako $  -->
                </span>
                <div class="favourites-filters-modal-body-row-group-values">
                    <input type="number" min="0.00" max="10000.00" step="0.01" id="priceInputStart" placeholder="0.00">
                    <p>to</p>
                    <input type="number" min="0.00" max="10000.00" step="0.01" id="priceInputEnd" placeholder="max">
                </div>
            </div>
            <div class="favourites-filters-modal-body-features-wrapper">
                <span class="favourites-filters-modal-body-row-group-label">
                    <p>Features</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><
                        <path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224C352 241.7 337.7 256 320 256C302.3 256 288 241.7 288 224zM280 288L328 288C341.3 288 352 298.7 352 312L352 400L360 400C373.3 400 384 410.7 384 424C384 437.3 373.3 448 360 448L280 448C266.7 448 256 437.3 256 424C256 410.7 266.7 400 280 400L304 400L304 336L280 336C266.7 336 256 325.3 256 312C256 298.7 266.7 288 280 288z"/>
                    </svg>
                    <div class="favourites-filters-modal-body-row-group-label-tooltip">
                        <p>The number shows how many chatbots remain if that feature is selected, 
                            together with the previously selected filters.</p>
                    </div>
                </span>
                <div class="favourites-filters-modal-body-features-container">
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-ending"> +8</span>
                </div>
                <span class="favourites-advanced-filters-modal-body-row-group-label" id="advancedFeaturesToggleLabel">
                    <p>Show advanced features</p>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M300.3 440.8C312.9 451 331.4 450.3 343.1 438.6L471.1 310.6C480.3 301.4 483 287.7 478 275.7C473 263.7 461.4 256 448.5 256L192.5 256C179.6 256 167.9 263.8 162.9 275.8C157.9 287.8 160.7 301.5 169.9 310.6L297.9 438.6L300.3 440.8z"/>
                    </svg>
                </span>
                <div class="favourites-advanced-filters-modal-body-features-container" id="advancedFeaturesContainer">
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-single"> Mobile application (40)</span>
                    <span class="favourites-filters-modal-body-features-ending"> +8</span>
                </div>
            </div>
        </div>
        <div class="favourites-filters-modal-footer">
            <button class="favourites-filters-modal-apply-btn">Apply Filters</button>
        </div>
        
    </div>
</div>


<script src="../js/featuresSwiping.js" defer></script>
<script src="../js/layoutSwapping.js" defer></script>
<script src="../js/clearButton.js" defer></script>
<script src="../js/sortSelect.js" defer></script>
<script src="../js/favouritesButtonAdding.js" defer></script>
<script src="../js/filterFeaturesAdding.js" defer></script>
<script src="../js/chatbotFilters.js" defer></script>
<script src="../js/showAdvancedFeatures.js" defer></script>
<script src="../js/mobileSearch.js" defer></script>