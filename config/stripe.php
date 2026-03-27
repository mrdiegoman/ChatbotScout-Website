<?php
/**
 * Stripe Configuration
 *
 * Credentials sú načítané z .env súboru.
 * NIKDY necommituj .env do gitu!
 *
 * Kľúče nájdeš na: https://dashboard.stripe.com/apikeys
 */

return [
    'publishable_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
    'secret_key'      => $_ENV['STRIPE_SECRET_KEY']      ?? '',
    'webhook_secret'  => $_ENV['STRIPE_WEBHOOK_SECRET']  ?? '',

    'success_url' => $_ENV['STRIPE_SUCCESS_URL'] ?? '',
    'cancel_url'  => $_ENV['STRIPE_CANCEL_URL']  ?? '',

    'api_version' => '2023-10-16',
];
