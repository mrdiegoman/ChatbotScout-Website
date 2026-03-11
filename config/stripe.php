<?php
/**
 * Stripe Configuration
 *
 * DÔLEŽITÉ: Tento súbor obsahuje citlivé API kľúče!
 * Daj ho do .gitignore a nikdy ho nenahraj na GitHub!
 *
 * Kľúče nájdeš na: https://dashboard.stripe.com/apikeys
 */

return [
    // Test kľúče (používaj počas vývoja)
    'publishable_key' => 'pk_test_51T5Ud6RCiEPK67SxOic3h6jFSYiC3DErpfPXtiNUBT1IJoXDCXRiXQBEsCadxQ75NJC1AMuaDTI1Dl2tvK54UcSb00ZMN2rsyj',
    'secret_key'      => 'sk_test_51T5Ud6RCiEPK67SxcHhqXqan9oNMR6lWv9uGwgjROWng1WIQMRyM3EoZpHIKqWK6Vbb0CkGI2o1ZCTExYaTPhOwY00KxWQIpdL',

    // Webhook secret (nájdeš ho po vytvorení webhooku v Stripe dashboarde)
    // https://dashboard.stripe.com/webhooks
    'webhook_secret'  => 'whsec_facd9d550afdbf4cb5bb271239e83a0462ff2e1dcef399bfc23f84a28cd8a0aa',

    // URLs
    'success_url' => 'http://localhost/mainwebsite/public/payment-success.php',
    'cancel_url'  => 'http://localhost/mainwebsite/public/advertise.php',


    // TEST → LIVE treab zmenit pre live stripe
    // 'publishable_key' => 'pk_live_...',   // z Stripe Dashboard
    // 'secret_key'      => 'sk_live_...',   // z Stripe Dashboard
    // 'webhook_secret'  => 'whsec_...',     // NOVÝ — po registrácii webhook URL

    // A URL zmeniť z localhost na reálnu doménu
    // 'success_url' => 'https://tvojadomena.com/public/payment-success.php',
    // 'cancel_url'  => 'https://tvojadomena.com/public/advertise.php',

    // Stripe API version
    'api_version' => '2023-10-16',
];
