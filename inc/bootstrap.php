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
// GDPR CONSENT HELPER
// Supports both v2 (JSON) and legacy (string) cookie
// ============================================

function hasStatisticsConsent(): bool
{
    // Check new v2 cookie first
    $v2 = $_COOKIE['cookie_consent_v2'] ?? null;
    if ($v2) {
        $consent = json_decode($v2, true);
        if (is_array($consent)) {
            return !empty($consent['statistics']);
        }
    }

    // Fallback: legacy cookie
    return ($_COOKIE['cookie_consent'] ?? null) === 'accepted';
}

// ============================================
// VISITOR TRACKING
// Spustí sa IBA ak user dal súhlas so Statistics cookies.
// ============================================

$visitorId  = null;

if (hasStatisticsConsent()) {
    $userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isBotOrApi = empty($userAgent) || preg_match('/(Stripe|curl|wget|bot|crawler)/i', $userAgent);

    if (!$isBotOrApi) {
        $appConfig = require __DIR__ . '/../config/app.php';
        $cookieVisitorId = $_COOKIE[$appConfig['visitor']['cookie_name']] ?? null;

        if ($cookieVisitorId) {
            // Visitor už existuje — len aktualizuj last_seen
            $visitor   = new Visitor();
            $visitorId = $visitor->getExisting($cookieVisitorId);
        }
        // Nový visitor sa vytvorí až cez ensureVisitor.php po súhlase
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