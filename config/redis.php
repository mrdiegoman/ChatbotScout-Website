<?php
/**
 * Redis Configuration
 *
 * Heslo je načítané z .env súboru.
 * NIKDY necommituj .env do gitu!
 */

$redisPassword = $_ENV['REDIS_PASSWORD'] ?? null;
$redisSocket   = $_ENV['REDIS_SOCKET']   ?? null;
$redisSocket   = ($redisSocket !== '' && $redisSocket !== null) ? $redisSocket : null;

return [
    'socket'       => $redisSocket,
    'host'         => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port'         => (int)($_ENV['REDIS_PORT'] ?? 6379),
    'password'     => ($redisPassword !== '' && $redisPassword !== null) ? $redisPassword : null,
    'database'     => (int)($_ENV['REDIS_DB'] ?? 1),
    'timeout'      => 2.5,
    'read_timeout' => 2.5,

    'prefix'      => 'ChatbotScout:',
    'default_ttl' => 3600,

    'retry_interval' => 100,
    'max_retries'    => 3,

    'use_db_fallback' => true,
];
