<?php
/**
 * Bootstrap
 *
 * Načíta sa pri každej requeste - inicializuje session, triedy, visitor tracking
 */

define('BOOTSTRAP_LOADED', true);
require __DIR__ . '/errorHandler.php';

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

        $userRole = !empty($userData['is_admin']) ? 'admin' : 'user';

        $_SESSION['user'] = $userData;

    } else {
        // Session expirovala alebo je neplatná - vymaž cookie
        setcookie($appConfig['session']['cookie_name'], '', time() - 3600, '/');
        unset($_COOKIE[$appConfig['session']['cookie_name']]);
    }
}

// ============================================
// VISITOR TRACKING
// Spustí sa IBA ak user dal súhlas s cookies.
// Ak cookie_consent neexistuje alebo je 'declined' → tracking sa preskočí.
// ============================================

$visitorId  = null;
$cookieConsent = $_COOKIE['cookie_consent'] ?? null;

if ($cookieConsent === 'accepted') {
    $userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isBotOrApi = empty($userAgent) || preg_match('/(Stripe|curl|wget|bot|crawler)/i', $userAgent);

    if (!$isBotOrApi) {
        $visitor   = new Visitor();
        $utmParams = [
            'utm_source'   => $_GET['utm_source']   ?? null,
            'utm_medium'   => $_GET['utm_medium']   ?? null,
            'utm_campaign' => $_GET['utm_campaign'] ?? null,
            'utm_term'     => $_GET['utm_term']     ?? null,
            'utm_content'  => $_GET['utm_content']  ?? null,
        ];

        $visitorId = $visitor->getOrCreate($utmParams);
    }
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