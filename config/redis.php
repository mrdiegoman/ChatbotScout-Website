<?php
/**
 * Redis Configuration
 * 
 * Nastavenia pre Redis server (rate limiting, caching, sessions)
 */

return [
    // Redis server connection
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null, // NULL = žiadne heslo
    'database' => 1, // DB 1 pre rate limiting (oddelené od default DB 0)
    'timeout' => 2.5, // Connection timeout v sekundách
    'read_timeout' => 2.5, // Read timeout v sekundách
    
    // Key prefix (predíde konfliktom)
    'prefix' => 'chatbothub:',
    
    // TTL (Time To Live) defaults
    'default_ttl' => 3600, // 1 hodina
    
    // Retry strategy
    'retry_interval' => 100, // milliseconds
    'max_retries' => 3,
    
    // Fallback na databázu ak Redis zlyháva
    'use_db_fallback' => true,
];
