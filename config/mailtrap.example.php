<?php
/**
 * Mailtrap Email Configuration (Testing)
 *
 * Skopíruj tento súbor ako mailtrap.php a vyplň reálne hodnoty.
 * NIKDY necommituj mailtrap.php do gitu!
 *
 * Credentials nájdeš na: https://mailtrap.io/inboxes
 */

return [
    'host'       => 'sandbox.smtp.mailtrap.io',
    'port'       => 2525,
    'username'   => 'YOUR_MAILTRAP_USERNAME',
    'password'   => 'YOUR_MAILTRAP_PASSWORD',
    'from_email' => 'noreply@ChatbotScout.com',
    'from_name'  => 'ChatbotScout',

    // Base URL pre linky v emailoch
    'base_url' => 'http://localhost/mainwebsite/public',
];
