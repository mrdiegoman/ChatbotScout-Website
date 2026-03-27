<?php

$authPages = ['login', 'signUp', 'forgotPassword', 'resetPassword', 'verifyEmail'];

$bodyClasses = [
    'page-' . $page,
    $isLoggedIn ? 'logged-in' : 'logged-out'
];

if ($userRole) {
    $bodyClasses[] = 'role-' . $userRole;
}

// Získaj profilovú fotku (Variant 2 - Upload + Default)
$profilePicture = isset($user['profile_picture_url'])
    ? $user['profile_picture_url']
    : '../img/default-avatar2.png';
$userName = $user['first_name'] ?? $user['username'] ?? $user['email'] ?? 'User';

$scheme = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ('/' . $page . '.php');

if ($page === 'index') {
    $canonicalPath = '/';
} else {
    $canonicalPath = $requestPath;
}

$canonicalUrl = $scheme . '://' . $host . $canonicalPath;
$defaultImage = $scheme . '://' . $host . '/img/websiteLogos/ChatbotScout_logo.png';

$seoDefaults = [
    'title'       => 'ChatbotScout',
    'description' => 'Compare AI chatbots by features, pricing, integrations, and user reviews.',
    'type'        => 'website',
];

$seoByPage = [
    'index' => [
        'title'       => 'Best AI Chatbots for Websites (2026)',
        'description' => 'Compare AI chatbots by features, pricing, integrations, and real user reviews. Find the best chatbot for your website in minutes.',
    ],
    'chatbotShowcase' => [
        'title'       => 'AI Chatbot Directory',
        'description' => 'Browse and compare AI chatbots with filters for pricing, features, integrations, ratings, and use cases.',
    ],
    'aboutUs' => [
        'title'       => 'About ChatbotScout',
        'description' => 'Learn about ChatbotScout and our mission to simplify chatbot comparison for website owners.',
    ],
    'contact' => [
        'title'       => 'Contact ChatbotScout',
        'description' => 'Get in touch with ChatbotScout for support, partnerships, and general questions.',
    ],
    'submitChatbot' => [
        'title'       => 'Submit a Chatbot',
        'description' => 'Submit your AI chatbot to ChatbotScout and reach users comparing chatbot tools.',
    ],
    'advertise' => [
        'title'       => 'Advertise on ChatbotScout',
        'description' => 'Promote your chatbot product to users actively comparing AI chatbot solutions.',
    ],
    'privacyPolicy' => [
        'title'       => 'Privacy Policy',
    ],
    'termsAndConditions' => [
        'title'       => 'Terms and Conditions',
    ],
    'affiliateDisclosure' => [
        'title'       => 'Affiliate Disclosure',
    ],
];

$seo = array_merge($seoDefaults, $seoByPage[$page] ?? []);
$fullTitle = str_contains($seo['title'], 'ChatbotScout') ? $seo['title'] : $seo['title'] . ' | ChatbotScout';

$noIndexPages = [
    'login',
    'signUp',
    'forgotPassword',
    'resetPassword',
    'verifyEmail',
    'verificationSent',
    'logout',
    'profile',
    'admin',
    'checkout',
    'payment-success',
    'getToken',
    'googleAuth',
    'googleCallback',
];

$robotsContent = in_array($page, $noIndexPages, true) ? 'noindex,follow' : 'index,follow';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars($robotsContent, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:site_name" content="ChatbotScout">
    <meta property="og:type" content="<?php echo htmlspecialchars($seo['type'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($defaultImage, ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($defaultImage, ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="apple-touch-icon" sizes="180x180" href="../img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../img/favicons/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="../img/favicons/favicon.ico">
    <link rel="manifest" href="../img/favicons/site.webmanifest">

    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/main.css">
    <?php if ($page === 'index'): ?>
        <link rel="stylesheet" href="../css/homePage.css">
    <?php elseif ($page === 'chatbotShowcase'): ?>
        <link rel="stylesheet" href="../css/chatbotShowcase.css">
        <link rel="stylesheet" href="../css/featuresShowMore.css">
        <link rel="stylesheet" href="../css/platformsPopup.css">
    <?php elseif ($page === 'contact'): ?>
        <link rel="stylesheet" href="../css/contact.css">
    <?php elseif ($page === 'aboutUs'): ?>
        <link rel="stylesheet" href="../css/aboutUs.css">
    <?php elseif ($page === 'affiliateDisclosure'): ?>
        <link rel="stylesheet" href="../css/affiliateDisclosure.css">
    <?php elseif ($page === 'favouritesShowcase'): ?>
        <link rel="stylesheet" href="../css/favouritesShowcase.css">
        <link rel="stylesheet" href="../css/featuresShowMore.css">
        <link rel="stylesheet" href="../css/platformsPopup.css">
    <?php elseif ($page === 'advertise'): ?>
        <link rel="stylesheet" href="../css/advertise.css">
    <?php elseif ($page === 'profile'): ?>
        <link rel="stylesheet" href="../css/profile.css">
    <?php elseif (in_array($page, $authPages, true)): ?>
        <link rel="stylesheet" href="../css/userAuthAll.css">
    <?php elseif ($page === '404' || $page === '500'): ?>
        <link rel="stylesheet" href="../css/errorPages.css">
    <?php elseif ($page === 'privacyPolicy'): ?>
        <link rel="stylesheet" href="../css/privacyPolicy.css">
    <?php elseif ($page === 'termsAndConditions'): ?>
        <link rel="stylesheet" href="../css/termsAndConditions.css">
    <?php endif; ?>

</head>

<body class="<?php echo implode(' ', $bodyClasses); ?>">
    <header class="header-main">
        <div class="header-main-logo" onclick="window.location.href='index.php'">
            <img src="../img/ChatbotScout_logo.png" alt="ChatbotScout Logo">
            <p class="header-main-logo-title">ChatbotScout</p>
        </div>
        <nav class="header-main-nav">
            <ul>
                <li><a href="chatbotShowcase.php">Chatbots</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="aboutUs.php">About Us</a></li>
            </ul>
        </nav>
        <div class="header-main-btns-promo">
            <!-- <a href="submitChatbot.php" class="header-main-btn-submit">Submit Chatbot</a>
            <a href="advertise.php" class="header-main-btn-advertise">Advertise</a> -->
        </div>
        <div class="header-main-right">
            <?php if (!$isLoggedIn): ?>
                <a href="login.php" class="header-main-right-LogIn">Log In</a>
                <a href="signUp.php" class="header-main-right-SignUp">Sign Up</a>
            <?php else: ?>
                <p class="header-main-right-username" 
                    data-fullname="<?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>">
                    <span class="username-text">
                        <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                    </span>
                </p>

                <!-- Avatar + Dropdown -->
                <div class="header-profile-dropdown-wrapper">

                    <a class="header-main-right-profile-avatar" id="profileAvatarBtn" title="<?php echo htmlspecialchars($userName); ?>">
                        <img src="../img/default-avatar2.png" data-src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile" class="profile-avatar-img user-avatar-img">
                    </a>

                    <div class="header-profile-dropdown" id="profileDropdown">
                        <div class="header-profile-dropdown-email">
                            <span id="dropdown-email-text"></span>
                            <button type="button" id="dropdown-email-toggle" aria-label="Show email">
                                <svg id="dropdown-email-icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="dropdown-email-icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                            <input type="hidden" id="dropdown-email-full" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="header-profile-dropdown-divider"></div>

                        <a href="profile.php" class="header-profile-dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            Profile
                        </a>
                        <a href="favouritesShowcase.php" class="header-profile-dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            Favourites
                        </a>
                        <?php if ($userRole === 'admin'): ?>
                        <a href="admin.php" class="header-profile-dropdown-item header-profile-dropdown-item--admin">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Admin
                        </a>
                        <?php endif; ?>

                        <div class="header-profile-dropdown-divider"></div>

                        <a href="logout.php" class="header-profile-dropdown-item header-profile-dropdown-item--logout">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Log Out
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Burger Menu Button -->
        <button class="header-burger-btn" aria-label="Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" 
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" 
                stroke-linejoin="round">
                <path d="M4 5h16"/>
                <path d="M4 12h16"/>
                <path d="M4 19h16"/>
            </svg>
        </button>
    </header>

    <!-- DEV BANNER — remove before going live -->
    <div style="position:fixed;top:60px;left:0;width:100%;background:#f59e0b;color:#1c1917;text-align:center;padding:10px 16px;font-size:14px;font-family:inherit;z-index:999;">
        This site is currently under development. Chatbot listings are not yet available.
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="header-mobile-menu">
        <div class="header-mobile-menu-content">

            <!-- Header s logom a close btn -->
            <div class="header-mobile-menu-top">
                <a href="index.php" class="header-mobile-menu-top-logo">
                    <img src="../img/ChatbotScout_logo.png" alt="ChatbotScout logo">
                    <span>ChatbotScout</span>
                </a>
                <button class="header-mobile-close" aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <!-- Nav links -->
            <nav class="header-mobile-nav">
                <a href="chatbotShowcase.php" class="header-mobile-nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                    Chatbots
                </a>
                <a href="contact.php" class="header-mobile-nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.07 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 17z"/>
                    </svg>
                    Contact
                </a>
                <a href="aboutUs.php" class="header-mobile-nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    About Us
                </a>
            </nav>

            <!-- Divider -->
            <!-- <div class="header-mobile-divider"></div> -->

            <!-- Promo buttons -->
            <!-- <div class="header-mobile-promo">
                <a href="submitChatbot.php" class="header-mobile-btn-submit">Submit Chatbot</a>
                <a href="advertise.php" class="header-mobile-btn-advertise">Advertise</a>
            </div> -->

            <!-- Divider -->
            <div class="header-mobile-divider"></div>

            <!-- Auth -->
            <div class="header-mobile-auth">
                <?php if (!$isLoggedIn): ?>
                    <a href="login.php" class="header-mobile-login">Log In</a>
                    <a href="signUp.php" class="header-mobile-signup">Sign Up</a>
                <?php else: ?>
                    <a href="profile.php" class="header-mobile-profile">
                        <img src="../img/default-avatar2.png" data-src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile" class="user-avatar-img">
                        <span>Profile</span>
                    </a>
                    <a href="favouritesShowcase.php" class="header-mobile-favourites">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                        Favourites
                    </a>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="admin.php" class="header-mobile-admin">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Admin
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="header-mobile-logout">Log Out</a>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script src="../js/csrf.js"></script>
    <script nonce="<?= $cspNonce ?>">
        function loadAvatar(img) {
            var real = img.getAttribute('data-src');
            if (!real) return;
            var tmp = new Image();
            tmp.onload = function() { img.src = real; };
            tmp.src = real;
        }
        document.querySelectorAll('img.user-avatar-img').forEach(loadAvatar);
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('img.user-avatar-img').forEach(loadAvatar);
        });
    </script>
    <script src="../js/BurgerMenuHeader.js" defer></script>
    <script src="../js/ProfileDropdown.js" defer></script>
    