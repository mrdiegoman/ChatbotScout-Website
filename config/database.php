<?php
/**
 * Database Configuration
 * 
 * DÔLEŽITÉ: Tento súbor obsahuje citlivé údaje!
 * V produkcii ho daj do .gitignore a nikdy ho nenahraj na GitHub!
 */

return [
    'host' => 'localhost',
    'dbname' => 'websitedb',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];