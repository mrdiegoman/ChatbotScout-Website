<?php
/**
 * Application Configuration
 */

return [
    // Session settings
    'session' => [
        'lifetime' => 24 * 60 * 60,
        'remember_me_lifetime' => 30 * 24 * 60 * 60,
        'cookie_name' => 'session_token',
        'cookie_path' => '/',
        'cookie_secure' => false,
        'cookie_httponly' => true,
    ],
    
    // Visitor tracking
    'visitor' => [
        'cookie_name' => 'visitor_id',
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
    
    // Rate limiting (všetky actions)
    'rate_limit' => [
        'login' => [
            // IP-based limit (pôvodné nastavenia - nezmenené)
            'max_attempts' => 50,
            'window'       => 15 * 60, // 15 minút
            'lockout'      => 30 * 60, // 30 minút lockout

            // 🆕 ZMENA: Email-based limit (prísnejší - chráni konkrétny účet)
            'email_max_attempts' => 10,
            'email_window'       => 15 * 60, // 15 minút
            'email_lockout'      => 60 * 60, // 1 hodina lockout
        ],
        'registration' => [
            'max_attempts' => 50,
            'window'       => 60 * 60,
            'lockout'      => 30 * 60,
            // Registrácia nepotrebuje email limit (email ešte neexistuje v systéme)
        ],
        'password_reset' => [
            'max_attempts' => 50,
            'window'       => 15 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 10,
            'email_window'       => 15 * 60, // 15 minút
            'email_lockout'      => 60 * 60, // 1 hodina lockout
        ],
        'resend_verification' => [
            'max_attempts' => 50,
            'window'       => 60 * 60,
            'lockout'      => 30 * 60,

            'email_max_attempts' => 10,
            'email_window'       => 15 * 60, // 15 minút
            'email_lockout'      => 60 * 60, // 1 hodina lockout
        ],
    ],
    
    // Google OAuth
    'google' => [
        'client_id'    => '',
        'client_secret' => '',
        'redirect_uri' => 'http://localhost/mainwebsite/public/googleCallback.php',
    ],
    
    // reCAPTCHA v3
    'recaptcha' => [
        'site_key'   => '',
        'secret_key' => '',
        'enabled'    => false,
    ],
];