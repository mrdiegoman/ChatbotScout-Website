<?php
/**
 * Bootstrap
 *
 * Načíta sa pri každej requeste - inicializuje session, triedy, visitor tracking
 */

define('BOOTSTRAP_LOADED', true);
require __DIR__ . '/errorHandler.php';
require_once __DIR__ . '/promotionPricing.php';
require_once __DIR__ . '/chatbotTags.php';

// ============================================
// ENVIRONMENT VARIABLES (.env)
// ============================================
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// ============================================
// HELPERS
// ============================================

function generateUUID(): string {
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Validácia CSRF tokenu.
 * Kontroluje X-CSRF-Token header (fetch) alebo csrf_token POST pole (HTML formy).
 *
 * @param string $redirectOnFail URL na redirect ak validácia zlyhá (pre HTML formy).
 *                               Ak je prázdny, vráti JSON 403 (pre API).
 */
function validateCsrfToken(string $redirectOnFail = ''): void {
    $token    = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!$expected || !hash_equals($expected, $token)) {
        if ($redirectOnFail) {
            header('Location: ' . $redirectOnFail);
            exit;
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
        exit;
    }
}

// ============================================
// SECURITY HEADERS
// ============================================

// CSP nonce — unikátny pre každý request
$cspNonce = bin2hex(random_bytes(16));

// Admin page has many inline onclick handlers — use unsafe-inline instead of nonce
$isAdminPage  = str_contains($_SERVER['PHP_SELF'] ?? '', 'admin.php');
$cspScriptSrc = $isAdminPage
    ? "script-src 'self' 'unsafe-inline' https://js.stripe.com https://www.googletagmanager.com; "
    : "script-src 'self' 'nonce-{$cspNonce}' https://js.stripe.com https://www.googletagmanager.com; ";

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    $cspScriptSrc .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: https://lh3.googleusercontent.com; " .
    "font-src 'self' data:; " .
    "connect-src 'self' https://api.stripe.com https://js.stripe.com https://www.google-analytics.com https://analytics.google.com https://region1.google-analytics.com; " .
    "frame-src https://js.stripe.com; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "object-src 'none'"
);

// Štart PHP session
session_start();

// CSRF token — generuj raz za session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

        $userRole = 'user';

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
    (new RateLimiter())->cleanup();
}

// ============================================
// PAGE DETECTION
// ============================================

$page = basename($_SERVER['PHP_SELF'], '.php');
