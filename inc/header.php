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
    ? '../' . $user['profile_picture_url']
    : '../img/default-avatar2.png';
$userName = $user['first_name'] ?? $user['username'] ?? $user['email'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatbotHub</title>
    <link rel="stylesheet" href="../css/reset.css">
    <link rel="stylesheet" href="../css/main.css">
    <?php if ($page === 'chatbotShowcase'): ?>
        <link rel="stylesheet" href="../css/chatbotShowcase.css">
    <?php elseif ($page === 'contact'): ?>
        <link rel="stylesheet" href="../css/contact.css">
    <?php elseif ($page === 'aboutUs'): ?>
        <link rel="stylesheet" href="../css/aboutUs.css">
    <?php elseif ($page === 'affiliateDisclosure'): ?>
        <link rel="stylesheet" href="../css/affiliateDisclosure.css">
    <?php elseif ($page === 'favouritesShowcase'): ?>
        <link rel="stylesheet" href="../css/favouritesShowcase.css">
    <?php elseif ($page === 'profile'): ?>
        <link rel="stylesheet" href="../css/profile.css">
    <?php elseif (in_array($page, $authPages, true)): ?>
        <link rel="stylesheet" href="../css/userAuthAll.css">
    <?php endif; ?>
</head>

<body class="<?php echo implode(' ', $bodyClasses); ?>">
    <header class="header-main">
        <div class="header-main-logo" onclick="window.location.href='index.php'">
            <img src="../img/chatbothub_logo.png" alt="ChatbotHub Logo">
            <p class="header-main-logo-title">ChatbotHub</p>
        </div>
        <nav class="header-main-nav">
            <ul>
                <li><a href="chatbotShowcase.php">Chatbots</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="aboutUs.php">About Us</a></li>
            </ul>
        </nav>
        <div class="header-main-btns-promo">
            <a href="submit.php" class="header-main-btn-submit">Submit Chatbot</a>
            <a href="advertise.php" class="header-main-btn-advertise">Advertise</a>
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
                        <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile" class="profile-avatar-img user-avatar-img">
                    </a>

                    <div class="header-profile-dropdown" id="profileDropdown">
                        <div class="header-profile-dropdown-email">
                            user: <?php echo htmlspecialchars($user['email'] ?? ''); ?>
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

                <?php if ($userRole === 'admin'): ?>
                    <a href="admin.php" class="header-main-right-admin">⚙️ Admin</a>
                <?php endif; ?>
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

    <!-- Mobile Menu Overlay -->
    <div class="header-mobile-menu">
        <div class="header-mobile-menu-content">
            <button class="header-mobile-close" aria-label="Close menu">×</button>
            
            <nav class="header-mobile-nav">
                <a href="chatbotShowcase.php">Chatbots</a>
                <a href="contact.php">Contact</a>
                <a href="aboutUs.php">About Us</a>
            </nav>

            <div class="header-mobile-promo">
                <a href="submit.php" class="header-mobile-btn-submit">Submit Chatbot</a>
                <a href="advertise.php" class="header-mobile-btn-advertise">Advertise</a>
            </div>

            <div class="header-mobile-auth">
                <?php if (!$isLoggedIn): ?>
                    <a href="login.php" class="header-mobile-login">Log In</a>
                    <a href="signUp.php" class="header-mobile-signup">Sign Up</a>
                <?php else: ?>
                    <a href="profile.php" class="header-mobile-profile">
                        <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px; vertical-align: middle;">
                        <p>Profile</p>
                    </a>
                    <a href="favouritesShowcase.php" class="header-mobile-favourites">⭐ Favourites</a>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="admin.php" class="header-mobile-admin">⚙️ Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="header-mobile-logout">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/BurgerMenuHeader.js" defer></script>
    <script src="../js/ProfileDropdown.js" defer></script>