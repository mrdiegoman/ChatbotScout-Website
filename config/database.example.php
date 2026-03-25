<?php
/**
 * Database Configuration
 *
 * Skopíruj tento súbor ako database.php a vyplň reálne hodnoty.
 * NIKDY necommituj database.php do gitu!
 */

return [
    'host'     => 'localhost',
    'dbname'   => 'websitedb',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
