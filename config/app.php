<?php
/**
 * Application Configuration
 *
 * Citlivé hodnoty sú načítané z .env súboru.
 * NIKDY necommituj .env do gitu!
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

return [
    // Session settings
    'session' => [
        'lifetime'             => 24 * 60 * 60,
        'remember_me_lifetime' => 30 * 24 * 60 * 60,
        'cookie_name'          => 'session_token',
        'cookie_path'          => '/',
        'cookie_secure'        => $isHttps,
        'cookie_httponly'      => true,
    ],

    // Visitor tracking
    'visitor' => [
        'cookie_name'     => 'visitor_id',
        'cookie_lifetime' => 365 * 24 * 60 * 60,
        'cookie_secure'   => $isHttps,
    ],

    // Email verification
    'email_verification' => [
        'token_lifetime' => 24 * 60 * 60,
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
    ],

    // Google OAuth
    'google' => [
        'client_id'     => $_ENV['GOOGLE_CLIENT_ID']     ?? '',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
        'redirect_uri'  => $_ENV['GOOGLE_REDIRECT_URI']  ?? '',
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
