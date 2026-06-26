<?php
/**
 * Application Configuration
 *
 * Skopíruj tento súbor ako app.php a vyplň reálne hodnoty.
 * NIKDY necommituj app.php do gitu!
 */

return [
    // Session settings
    'session' => [
        'lifetime'             => 24 * 60 * 60,
        'remember_me_lifetime' => 30 * 24 * 60 * 60,
        'cookie_name'          => 'session_token',
        'cookie_path'          => '/',
        'cookie_secure'        => false, // true v produkcii (HTTPS)
        'cookie_httponly'      => true,
    ],

    // Visitor tracking
    'visitor' => [
        'cookie_name'     => 'visitor_id',
        'cookie_lifetime' => 365 * 24 * 60 * 60,
        'cookie_secure'   => false, // true v produkcii (HTTPS)
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
            'max_attempts' => 50,
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
            'max_attempts' => 50,
            'window'       => 60 * 60,
            'lockout'      => 30 * 60,
        ],
        'password_reset' => [
            'max_attempts' => 50,
            'window'       => 15 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 10,
            'email_window'       => 15 * 60,
            'email_lockout'      => 60 * 60,
        ],
        'resend_verification' => [
            'max_attempts' => 50,
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

    // Google OAuth — https://console.cloud.google.com/apis/credentials
    'google' => [
        'client_id'     => 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        // Environment-specific. Leave GOOGLE_REDIRECT_URI blank in app.php to
        // build it from the current host, or set the exact production URI:
        // https://chatbotscout.com/google-callback
        // It must exactly match an Authorized redirect URI in Google Cloud Console.
        'redirect_uri'  => $_ENV['GOOGLE_REDIRECT_URI'] ?? 'http://localhost/MainWebsite/google-callback',
    ],

    // reCAPTCHA v3 — https://www.google.com/recaptcha/admin
    'recaptcha' => [
        'site_key'   => '',
        'secret_key' => '',
        'enabled'    => false,
    ],

    // Admin
    'admin' => [
        'email'       => $_ENV['ADMIN_EMAIL']       ?? 'your-admin-email@example.com',
        'access_path' => $_ENV['ADMIN_ACCESS_PATH'] ?? '',
    ],
];
