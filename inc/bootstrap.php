<?php
/**
 * Bootstrap
 *
 * Načíta sa pri každej requeste - inicializuje session, triedy, visitor tracking
 */

// Štart PHP session
session_start();

// Autoloader tried - automaticky načíta triedy z /classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Načítanie app configu
$appConfig = require __DIR__ . '/../config/app.php';

// ============================================
// SESSION MANAGEMENT
// ============================================

$isLoggedIn = false;
$user       = null;
$userRole   = null;

// Kontrola session tokenu z cookie
if (isset($_COOKIE[$appConfig['session']['cookie_name']])) {
    $sessionToken = $_COOKIE[$appConfig['session']['cookie_name']];

    $sessionManager = new Session();
    $userData = $sessionManager->validate($sessionToken);

    if ($userData) {
        $isLoggedIn = true;
        $user       = $userData;

        // ✅ Derive role from is_admin flag (set by Session::validate)
        $userRole = !empty($userData['is_admin']) ? 'admin' : 'user';

        // Ulož do PHP session pre spätnú kompatibilitu
        $_SESSION['user'] = $userData;

    } else {
        // Session expirovala alebo je neplatná - vymaž cookie
        setcookie($appConfig['session']['cookie_name'], '', time() - 3600, '/');
        unset($_COOKIE[$appConfig['session']['cookie_name']]);
    }
}

// ============================================
// VISITOR TRACKING
// ============================================

if (!$isLoggedIn) {
    $visitor = new Visitor();

    $utmParams = [
        'utm_source'   => $_GET['utm_source']   ?? null,
        'utm_medium'   => $_GET['utm_medium']   ?? null,
        'utm_campaign' => $_GET['utm_campaign'] ?? null,
        'utm_term'     => $_GET['utm_term']     ?? null,
        'utm_content'  => $_GET['utm_content']  ?? null,
    ];

    $visitorId = $visitor->getOrCreate($utmParams);
}

// ============================================
// SESSION CLEANUP (1% šanca)
// ============================================

if (rand(1, 100) === 1) {
    $sessionManager = new Session();
    $sessionManager->cleanup();
}

// ============================================
// PAGE DETECTION
// ============================================

$page = basename($_SERVER['PHP_SELF'], '.php');