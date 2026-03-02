<?php
/**
 * Application Configuration
 */

return [
    // Session settings
    'session' => [
        'lifetime' => 24 * 60 * 60, // 24 hodín (v sekundách)
        'remember_me_lifetime' => 30 * 24 * 60 * 60, // 30 dní
        'cookie_name' => 'session_token',
        'cookie_path' => '/',
        'cookie_secure' => false, // Pre produkciu nastav na TRUE (HTTPS)
        'cookie_httponly' => true,
    ],
    
    // Visitor tracking
    'visitor' => [
        'cookie_name' => 'visitor_id',
        'cookie_lifetime' => 365 * 24 * 60 * 60, // 1 rok
    ],
    
    // Email verification
    'email_verification' => [
        'token_lifetime' => 24 * 60 * 60, // 24 hodín
    ],
    
    // Password reset
    'password_reset' => [
        'token_lifetime' => 60 * 60, // 1 hodina
    ],
    
    // Rate limiting (všetky actions)
    'rate_limit' => [
        'login' => [
            'max_attempts' => 5,
            'window' => 15 * 60, // 15 minút
            'lockout' => 30 * 60, // 30 minút lockout
        ],
        'registration' => [
            'max_attempts' => 3,
            'window' => 60 * 60, // 1 hodina
            'lockout' => 30 * 60, // 30 minút lockout
        ],
        'password_reset' => [
            'max_attempts' => 3,
            'window' => 60 * 60, // 1 hodina
            'lockout' => 30 * 60, // 30 minút lockout
        ],
        'resend_verification' => [
            'max_attempts' => 3,
            'window' => 60 * 60, // 1 hodina
            'lockout' => 30 * 60, // 30 minút lockout
        ],
    ],
    
    // Google OAuth (placeholder - vyplň neskôr)
    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'http://localhost/mainwebsite/public/googleCallback.php',
    ],
    
    // reCAPTCHA v3 (placeholder - vyplň neskôr)
    'recaptcha' => [
        'site_key' => '',
        'secret_key' => '',
        'enabled' => false, // Zapni keď budeš mať credentials
    ],
];
