<?php
/**
 * Mailtrap Email Configuration (Testing)
 * 
 * Pre produkciu nahraď Mailtrap za skutočný SMTP provider (Resend, SendGrid, atď.)
 */

return [
    'host' => 'sandbox.smtp.mailtrap.io',
    'port' => 2525,
    'username' => '8f3566bf6e7147',
    'password' => '228072c9c49d8e',
    'from_email' => 'noreply@ChatbotScout.com',
    'from_name' => 'ChatbotScout',
    
    // Base URL pre linky v emailoch
    'base_url' => 'http://localhost/mainwebsite/public'
];