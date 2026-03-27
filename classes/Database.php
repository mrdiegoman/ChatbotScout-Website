<?php
/**
 * Database Class (Singleton Pattern)
 * 
 * Poskytuje jedno globálne PDO pripojenie k databáze.
 * Automaticky načíta config z /config/database.php
 */

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    
    /**
     * Private constructor - zabráni priamemu vytváraniu inštancie
     */
    private function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';
        
        $port = !empty($config['port']) ? ";port={$config['port']}" : '';
        $dsn = "mysql:host={$config['host']}{$port};dbname={$config['dbname']};charset={$config['charset']}";
        
        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            // V produkcii by si mal logovať chyby, nie ich zobrazovať
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Získaj singleton inštanciu
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Získaj PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
    
    /**
     * Zabráň klonovaniu
     */
    private function __clone() {}
    
    /**
     * Zabráň deserializácii
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}