<?php
/**
 * Stripe Configuration
 *
 * Skopíruj tento súbor ako stripe.php a vyplň reálne hodnoty.
 * NIKDY necommituj stripe.php do gitu!
 *
 * Kľúče nájdeš na: https://dashboard.stripe.com/apikeys
 */

return [
    // Test kľúče (používaj počas vývoja)
    'publishable_key' => 'pk_test_YOUR_PUBLISHABLE_KEY',
    'secret_key'      => 'sk_test_YOUR_SECRET_KEY',

    // Webhook secret — https://dashboard.stripe.com/webhooks
    'webhook_secret'  => 'whsec_YOUR_WEBHOOK_SECRET',

    // URLs
    'success_url' => 'http://localhost/mainwebsite/public/payment-success.php',
    'cancel_url'  => 'http://localhost/mainwebsite/public/advertise.php',

    // Pre produkciu odkomentuj:
    // 'publishable_key' => 'pk_live_...',
    // 'secret_key'      => 'sk_live_...',
    // 'webhook_secret'  => 'whsec_...',
    // 'success_url' => 'https://tvojadomena.com/public/payment-success.php',
    // 'cancel_url'  => 'https://tvojadomena.com/public/advertise.php',

    // Stripe API version
    'api_version' => '2023-10-16',
];
