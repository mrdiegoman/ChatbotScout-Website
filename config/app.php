<?php
/**
 * Application Configuration
 */

return [
    // Session settings
    'session' => [
        'lifetime'             => 24 * 60 * 60,
        'remember_me_lifetime' => 30 * 24 * 60 * 60,
        'cookie_name'          => 'session_token',
        'cookie_path'          => '/',
        'cookie_secure'        => false,
        'cookie_httponly'      => true,
    ],

    // Visitor tracking
    'visitor' => [
        'cookie_name'     => 'visitor_id',
        'cookie_lifetime' => 365 * 24 * 60 * 60,
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
            'window'       => 60 * 60, // 1 hour
            'lockout'      => 60 * 60, // 1 hour lockout
        ],
    ],

    // Google OAuth
    'google' => [
        'client_id'     => '726695024122-ge43n76e5fgkt0omrmlfbl9crr641fd5.apps.googleusercontent.com',
        'client_secret' => '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',
        'redirect_uri'  => 'http://localhost/mainwebsite/public/googleCallback.php',
    ],

    // reCAPTCHA v3
    'recaptcha' => [
        'site_key'   => '',
        'secret_key' => '',
        'enabled'    => false,
    ],

    // Admin
    'admin' => [
        'email' => 'rafaj.jakub@gmail.com', // ← zmeň na svoj reálny email
    ],
];