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
    'host'     => 'sql23.hostcreators.sk',
    'port'     => 3328,
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
