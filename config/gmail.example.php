<?php
/**
 * Gmail API Configuration
 *
 * Skopíruj tento súbor ako gmail.php a vyplň reálne hodnoty.
 * NIKDY necommituj gmail.php do gitu!
 *
 * Credentials nájdeš na: https://console.cloud.google.com/apis/credentials
 */

return [
    'client_id'     => 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
    'refresh_token' => 'YOUR_GMAIL_REFRESH_TOKEN',

    // Email z ktorého sa posiela
    'from_email' => 'your-email@gmail.com',
    'from_name'  => 'ChatbotScout Support',

    // Base URL pre linky v emailoch
    'base_url' => 'http://localhost/mainwebsite/public',
];
