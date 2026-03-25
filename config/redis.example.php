<?php
/**
 * Redis Configuration
 *
 * Skopíruj tento súbor ako redis.php a vyplň reálne hodnoty.
 * NIKDY necommituj redis.php do gitu!
 */

return [
    'host'         => '127.0.0.1',
    'port'         => 6379,
    'password'     => null, // NULL = žiadne heslo, inak 'YOUR_REDIS_PASSWORD'
    'database'     => 1,
    'timeout'      => 2.5,
    'read_timeout' => 2.5,

    'prefix'      => 'ChatbotScout:',
    'default_ttl' => 3600,

    'retry_interval' => 100,
    'max_retries'    => 3,

    'use_db_fallback' => true,
];
