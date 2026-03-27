<?php
/**
 * Database Configuration
 *
 * Credentials sú načítané z .env súboru.
 * NIKDY necommituj .env do gitu!
 */
error_log('DB config: ' . ($_ENV['DB_HOST'] ?? 'null') . ' | ' . ($_ENV['DB_NAME'] ?? 'null') . ' | ' . ($_ENV['DB_USER'] ?? 'null') . ' | ' . ($_ENV['DB_PASSWORD'] ?? 'null'));

return [
    'host'     => $_ENV['DB_HOST']     ?? 'localhost',
    'dbname'   => $_ENV['DB_NAME']     ?? 'websitedb',
    'username' => $_ENV['DB_USER']     ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
