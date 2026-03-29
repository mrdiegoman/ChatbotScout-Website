<?php
/**
 * Database Configuration
 *
 * Localhost: používa XAMPP defaults.
 * Produkcia: host/port sú hardcoded, credentials cez SetEnv v .htaccess.
 */

// $host = $_SERVER['HTTP_HOST'] ?? '';
// $isLocal = $host === 'localhost' || str_starts_with($host, 'localhost:') || $host === '127.0.0.1';

// if ($isLocal) {
//     return [
//         'host'     => 'localhost',
//         'port'     => null,
//         'dbname'   => $_ENV['DB_NAME']     ?? '',
//         'username' => $_ENV['DB_USER']     ?? '',
//         'password' => $_ENV['DB_PASSWORD'] ?? '',
//         'charset'  => 'utf8mb4',
//         'options'  => [
//             PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
//             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//             PDO::ATTR_EMULATE_PREPARES   => false,
//         ],
//     ];
// }

return [
    'host'     => $_ENV['DB_HOST']     ?? '',
    'port'     => $_ENV['DB_PORT']     ?? '',
    'dbname'   => $_ENV['DB_NAME']     ?? '',
    'username' => $_ENV['DB_USER']     ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
