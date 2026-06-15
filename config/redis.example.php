<?php
/**
 * Redis Configuration
 *
 * Skopíruj tento súbor ako redis.php a vyplň reálne hodnoty.
 * NIKDY necommituj redis.php do gitu!
 */

return [
    'socket'       => $_ENV['REDIS_SOCKET'] ?? null,
    'host'         => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port'         => (int)($_ENV['REDIS_PORT'] ?? 6379),
    'password'     => !empty($_ENV['REDIS_PASSWORD']) ? $_ENV['REDIS_PASSWORD'] : null,
    'database'     => (int)($_ENV['REDIS_DB'] ?? 1),
    'timeout'      => 2.5,
    'read_timeout' => 2.5,

    'prefix'      => 'ChatbotScout:',
    'default_ttl' => 3600,

    'retry_interval' => 100,
    'max_retries'    => 3,

    'use_db_fallback' => true,
];
