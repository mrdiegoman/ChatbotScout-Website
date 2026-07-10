<?php
/**
 * Application Configuration
 *
 * Citlivé hodnoty sú načítané z .env súboru.
 * NIKDY necommituj .env do gitu!
 */

require_once __DIR__ . '/../classes/ClientIpResolver.php';

$trustedProxyEnv = $_ENV['TRUSTED_PROXY_CIDRS'] ?? getenv('TRUSTED_PROXY_CIDRS') ?: '127.0.0.1/32,::1/128';
$trustedProxyCidrs = array_values(array_filter(array_map(
    'trim',
    explode(',', (string)$trustedProxyEnv)
)));

$forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($forwardedProto === 'https' && ClientIpResolver::isTrustedProxyRequest());

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = $isHttps ? 'https' : 'http';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$basePath = '';

if (preg_match('#^(.*)/(?:google-auth|google-callback|login|sign-up)(?:/)?$#', $requestPath, $matches)) {
    $basePath = $matches[1] === '/' ? '' : $matches[1];
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if ($basePath === '') {
    $publicPathPosition = strpos($scriptName, '/public/');
    $basePath = $publicPathPosition === false ? '' : substr($scriptName, 0, $publicPathPosition);
}

$basePath = rtrim($basePath, '/');
$defaultGoogleRedirectUri = $scheme . '://' . $host . $basePath . '/google-callback';
$googleRedirectUri = trim((string)($_ENV['GOOGLE_REDIRECT_URI'] ?? ''));
if ($googleRedirectUri === '') {
    $googleRedirectUri = $defaultGoogleRedirectUri;
}

return [
    // Reverse proxies allowed to provide CF-Connecting-IP / X-Forwarded-For.
    'trusted_proxy_cidrs' => $trustedProxyCidrs,

    // Session settings
    'session' => [
        'lifetime'             => 24 * 60 * 60,
        'remember_me_lifetime' => 30 * 24 * 60 * 60,
        'cookie_name'          => 'session_token',
        'cookie_path'          => '/',
        'cookie_secure'        => $isHttps,
        'cookie_httponly'      => true,
        'cookie_samesite'      => 'Lax',
    ],

    // Visitor tracking
    'visitor' => [
        'cookie_name'     => 'visitor_id',
        'cookie_lifetime' => 365 * 24 * 60 * 60,
        'cookie_secure'   => $isHttps,
    ],

    // Email verification
    'email_verification' => [
        'token_lifetime'  => 24 * 60 * 60,
        'resend_cooldown' => 60,
    ],

    // Password reset
    'password_reset' => [
        'token_lifetime' => 60 * 60,
    ],

    // Rate limiting (all actions)
    'rate_limit' => [
        'login' => [
            'max_attempts' => 75,
            'window'       => 15 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 10,
            'email_window'       => 15 * 60,
            'email_lockout'      => 60 * 60,
        ],
        'admin_login' => [
            'max_attempts' => 10,
            'window'       => 15 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 5,
            'email_window'       => 15 * 60,
            'email_lockout'      => 60 * 60,
        ],
        'admin_login_ip' => [
            'max_attempts' => 10,
            'window'       => 15 * 60,
            'lockout'      => 30 * 60,
        ],
        'admin_login_user' => [
            'max_attempts' => 5,
            'window'       => 15 * 60,
            'lockout'      => 60 * 60,
        ],
        'registration' => [
            'max_attempts' => 75,
            'window'       => 60 * 60,
            'lockout'      => 30 * 60,
        ],
        'password_reset' => [
            'max_attempts' => 75,
            'window'       => 15 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 10,
            'email_window'       => 15 * 60,
            'email_lockout'      => 60 * 60,
        ],
        'resend_verification' => [
            'max_attempts' => 75,
            'window'       => 60 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 10,
            'email_window'       => 15 * 60,
            'email_lockout'      => 60 * 60,
        ],

        'contact_form' => [
            'max_attempts' => 10,
            'window'       => 60 * 60,
            'lockout'      => 60 * 60,
        ],
        'chatbot_click' => [
            'max_attempts' => 120,
            'window'       => 15 * 60,
            'lockout'      => 15 * 60,
        ],
    ],

    // Google OAuth
    'google' => [
        'client_id'     => $_ENV['GOOGLE_CLIENT_ID']     ?? '',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
        'redirect_uri'  => $googleRedirectUri,
        'default_redirect_uri' => $defaultGoogleRedirectUri,
    ],

    // reCAPTCHA v3
    'recaptcha' => [
        'site_key'   => $_ENV['RECAPTCHA_SITE_KEY']   ?? '',
        'secret_key' => $_ENV['RECAPTCHA_SECRET_KEY'] ?? '',
        'enabled'    => false,
    ],

    // Admin
    'admin' => [
        'email'       => $_ENV['ADMIN_EMAIL']       ?? '',
        'access_path' => $_ENV['ADMIN_ACCESS_PATH'] ?? '',
    ],
];
