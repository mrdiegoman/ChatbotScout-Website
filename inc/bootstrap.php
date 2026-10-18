<?php
/**
 * Bootstrap
 *
 * Načíta sa pri každej requeste - inicializuje session, triedy, visitor tracking
 */

define('BOOTSTRAP_LOADED', true);
require_once __DIR__ . '/../classes/ClientIpResolver.php';
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

function appIsHttpsRequest(): bool {
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($forwardedProto === 'https' && ClientIpResolver::isTrustedProxyRequest());
}

function appCookieOptions(
    int $expires = 0,
    string $path = '/',
    bool $httpOnly = true,
    ?bool $secure = null,
    string $sameSite = 'Lax',
    string $domain = ''
): array {
    $options = [
        'expires'  => $expires,
        'path'     => $path,
        'secure'   => $secure ?? appIsHttpsRequest(),
        'httponly' => $httpOnly,
        'samesite' => $sameSite,
    ];

    if ($domain !== '') {
        $options['domain'] = $domain;
    }

    return $options;
}

function setAppCookie(
    string $name,
    string $value,
    int $expires = 0,
    string $path = '/',
    bool $httpOnly = true,
    ?bool $secure = null,
    string $sameSite = 'Lax',
    string $domain = ''
): void {
    setcookie($name, $value, appCookieOptions($expires, $path, $httpOnly, $secure, $sameSite, $domain));
    $_COOKIE[$name] = $value;
}

function expireAppCookie(
    string $name,
    string $path = '/',
    bool $httpOnly = true,
    ?bool $secure = null,
    string $sameSite = 'Lax',
    string $domain = ''
): void {
    setcookie($name, '', appCookieOptions(time() - 3600, $path, $httpOnly, $secure, $sameSite, $domain));
    unset($_COOKIE[$name]);
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

$cspScriptSrc = "script-src 'self' 'nonce-{$cspNonce}' https://js.stripe.com https://www.googletagmanager.com https://challenges.cloudflare.com; ";

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
    "connect-src 'self' https://api.stripe.com https://js.stripe.com https://m.stripe.network https://r.stripe.com https://www.google-analytics.com https://analytics.google.com https://region1.google-analytics.com https://challenges.cloudflare.com; " .
    "frame-src https://js.stripe.com https://hooks.stripe.com https://challenges.cloudflare.com; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "object-src 'none'"
);

// Štart PHP session
$sessionCookieOptions = appCookieOptions();
unset($sessionCookieOptions['expires']);
$sessionCookieOptions['lifetime'] = 0;
session_set_cookie_params($sessionCookieOptions);
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
        expireAppCookie(
            $appConfig['session']['cookie_name'],
            $appConfig['session']['cookie_path'],
            (bool)$appConfig['session']['cookie_httponly'],
            (bool)$appConfig['session']['cookie_secure'],
            $appConfig['session']['cookie_samesite'] ?? 'Lax'
        );
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
