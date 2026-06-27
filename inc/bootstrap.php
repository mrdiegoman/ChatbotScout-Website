<?php
/**
 * Bootstrap
 *
 * Načíta sa pri každej requeste - inicializuje session, triedy, visitor tracking
 */

define('BOOTSTRAP_LOADED', true);
require __DIR__ . '/errorHandler.php';
require_once __DIR__ . '/promotionPricing.php';
require_once __DIR__ . '/currencyRates.php';
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
 * Profile avatars may only be files uploaded through this application.
 * External provider URLs (including Google profile pictures) intentionally
 * resolve to null so they can never trigger a third-party image request.
 */
function localProfilePictureUrl(?string $url): ?string {
    if (!is_string($url)) {
        return null;
    }

    $url = trim(str_replace('\\', '/', $url));

    if (!preg_match('#^img/avatars/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)$#i', $url)) {
        return null;
    }

    return $url;
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
    "img-src 'self' data: https://*.stripe.com; " .
    "font-src 'self' data:; " .
    // Stripe uses these endpoints for Payment Element telemetry, wallet checks and
    // authentication/3DS return frames. Keep the allowlist limited to Stripe hosts.
    "connect-src 'self' https://api.stripe.com https://js.stripe.com https://m.stripe.network https://r.stripe.com https://www.google-analytics.com https://analytics.google.com https://region1.google-analytics.com; " .
    "frame-src https://js.stripe.com https://hooks.stripe.com; " .
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
// GDPR CONSENT HELPERS
// Supports both v2 (JSON category consent) and legacy accepted/declined cookie.
// ============================================

function getCookieConsent(): ?array
{
    $v2 = $_COOKIE['cookie_consent_v2'] ?? null;
    if (is_string($v2) && $v2 !== '') {
        $consent = json_decode($v2, true);
        if (is_array($consent) && array_key_exists('necessary', $consent)) {
            return [
                'necessary'   => true,
                'statistics'  => !empty($consent['statistics']),
                'preferences' => !empty($consent['preferences']),
                'marketing'   => !empty($consent['marketing']),
            ];
        }
    }

    $legacy = $_COOKIE['cookie_consent'] ?? null;
    if ($legacy === 'accepted') {
        return [
            'necessary'   => true,
            'statistics'  => true,
            'preferences' => true,
            'marketing'   => true,
        ];
    }

    if ($legacy === 'declined') {
        return [
            'necessary'   => true,
            'statistics'  => false,
            'preferences' => false,
            'marketing'   => false,
        ];
    }

    return null;
}

function hasCookieCategoryConsent(string $category): bool
{
    if ($category === 'necessary') {
        return true;
    }

    $consent = getCookieConsent();
    return is_array($consent) && !empty($consent[$category]);
}

function hasStatisticsConsent(): bool
{
    return hasCookieCategoryConsent('statistics');
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
            // Visitor už existuje — len aktualizuj last_seen. Ak cookie ukazuje na
            // zmazaný/neplatný záznam, zmaž ju a nechaj identify.php vytvoriť nový.
            $visitor   = new Visitor();
            $visitorId = $visitor->getExisting((string)$cookieVisitorId);

            if ($visitorId === null) {
                $visitor->clearCookie();
                unset($_COOKIE[$appConfig['visitor']['cookie_name']]);
            }
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
