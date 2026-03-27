<?php
/**
 * Gmail API Configuration
 *
 * Credentials sú načítané z .env súboru.
 * NIKDY necommituj .env do gitu!
 *
 * Credentials nájdeš na: https://console.cloud.google.com/apis/credentials
 */

return [
    'client_id'     => $_ENV['GMAIL_CLIENT_ID']     ?? '',
    'client_secret' => $_ENV['GMAIL_CLIENT_SECRET'] ?? '',
    'refresh_token' => $_ENV['GMAIL_REFRESH_TOKEN'] ?? '',

    'from_email' => $_ENV['GMAIL_FROM_EMAIL'] ?? '',
    'from_name'  => 'ChatbotScout Support',

    'base_url' => $_ENV['APP_BASE_URL'] ?? '',
];
