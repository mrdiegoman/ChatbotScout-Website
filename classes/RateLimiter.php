<?php
/**
 * RateLimiter Class (Redis + Database Fallback)
 * 
 * Ochrana proti brute-force útokom
 * Primárne používa Redis (rýchle), fallback na MySQL ak Redis zlyháva
 */

class RateLimiter
{
    private ?Redis $redis = null;
    private PDO $db;
    private array $config;
    private array $redisConfig;
    private bool $useRedis = true;
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->config = require __DIR__ . '/../config/app.php';
        $this->redisConfig = require __DIR__ . '/../config/redis.php';
        
        // Inicializuj Redis connection
        $this->initializeRedis();
        
        // Vytvor DB tabuľku (fallback)
        $this->createTableIfNotExists();
    }
    
    /**
     * Inicializácia Redis pripojenia
     */
    private function initializeRedis(): void
    {
        try {
            $this->redis = new Redis();
            
            $connected = $this->redis->connect(
                $this->redisConfig['host'],
                $this->redisConfig['port'],
                $this->redisConfig['timeout']
            );
            
            if (!$connected) {
                throw new Exception('Redis connection failed');
            }
            
            // Nastav read timeout
            $this->redis->setOption(Redis::OPT_READ_TIMEOUT, $this->redisConfig['read_timeout']);
            
            // Vyber databázu
            $this->redis->select($this->redisConfig['database']);
            
            // Test connection
            $this->redis->ping();
            
            $this->useRedis = true;
            
        } catch (Exception $e) {
            error_log("⚠️ Redis connection failed: " . $e->getMessage() . " - Using DB fallback");
            $this->useRedis = false;
            $this->redis = null;
        }
    }
    
    /**
     * Hlavná metóda - kontrola rate limitu
     * 
     * @param string $action Typ akcie (login, registration, password_reset, resend_verification)
     * @param string $ipAddress IP adresa
     * @return bool TRUE ak môže pokračovať, FALSE ak je zablokovaný
     */
    public function check(string $action, string $ipAddress): bool
    {
        // Kontrola či je zablokovaný
        if ($this->isBlocked($action, $ipAddress)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Kontrola či je IP adresa zablokovaná
     * 
     * @param string $action
     * @param string $ipAddress
     * @return bool TRUE ak je zablokovaná
     */
    public function isBlocked(string $action, string $ipAddress): bool
    {
        if ($this->useRedis && $this->redis) {
            return $this->isBlockedRedis($action, $ipAddress);
        }
        
        return $this->isBlockedDB($action, $ipAddress);
    }
    
    /**
     * Redis: Kontrola blokácie
     */
    private function isBlockedRedis(string $action, string $ipAddress): bool
    {
        try {
            $lockKey = $this->getRedisKey($action, $ipAddress, 'locked');
            $lockedUntil = $this->redis->get($lockKey);
            
            if ($lockedUntil && (int)$lockedUntil > time()) {
                return true;
            }
            
            // Ak lockout expiroval, vymaž key
            if ($lockedUntil) {
                $this->redis->del($lockKey);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Redis isBlocked error: " . $e->getMessage());
            return $this->isBlockedDB($action, $ipAddress);
        }
    }
    
    /**
     * Database: Kontrola blokácie
     */
    private function isBlockedDB(string $action, string $ipAddress): bool
    {
        $stmt = $this->db->prepare("
            SELECT locked_until 
            FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
        ");
        
        $stmt->execute([
            'ip' => $ipAddress,
            'action' => $action,
        ]);
        
        $result = $stmt->fetch();
        
        if (!$result || !$result['locked_until']) {
            return false;
        }
        
        $lockedUntil = strtotime($result['locked_until']);
        
        // Ak je lockout expirovaný, resetuj
        if ($lockedUntil < time()) {
            $this->reset($action, $ipAddress);
            return false;
        }
        
        return true;
    }
    
    /**
     * Zaznamenanie neúspešného pokusu
     * 
     * @param string $action
     * @param string $ipAddress
     */
    public function recordFailedAttempt(string $action, string $ipAddress): int
    {
        if ($this->useRedis && $this->redis) {
            return $this->recordFailedAttemptRedis($action, $ipAddress);
        } else {
            return $this->recordFailedAttemptDB($action, $ipAddress);
        }
        return 0;
    }
    
    /**
     * Redis: Zaznamenanie failed attempt
     */
    private function recordFailedAttemptRedis(string $action, string $ipAddress): int
    {
        try {
            $config = $this->config['rate_limit'][$action];
            $countKey = $this->getRedisKey($action, $ipAddress, 'count');
            $lockKey = $this->getRedisKey($action, $ipAddress, 'locked');
            
            // Inkrementuj counter
            $attempts = $this->redis->incr($countKey);
            
            // Nastav TTL na prvý pokus
            if ($attempts == 1) {
                $this->redis->expire($countKey, $config['window']);
            }
            
            // Ak dosiahol limit, zamkni
            if ($attempts >= $config['max_attempts']) {
                $lockedUntil = time() + $config['lockout'];
                $this->redis->set($lockKey, $lockedUntil, $config['lockout']);
                
                // Vymaž counter (už je zablokovaný)
                $this->redis->del($countKey);
                
                $remaining = (int)$lockedUntil - time();
                
                return max(0, $remaining);
            }
            return 0;
            
        } catch (Exception $e) {
            error_log("Redis recordFailedAttempt error: " . $e->getMessage());
            $this->recordFailedAttemptDB($action, $ipAddress);
        }
    }
    
    /**
     * Database: Zaznamenanie failed attempt
     */
    private function recordFailedAttemptDB(string $action, string $ipAddress): int
    {
        $config = $this->config['rate_limit'][$action];
        
        // Získaj aktuálny počet pokusov
        $stmt = $this->db->prepare("
            SELECT attempts_count, first_attempt_at 
            FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
        ");
        
        $stmt->execute([
            'ip' => $ipAddress,
            'action' => $action,
        ]);
        
        $result = $stmt->fetch();
        
        if (!$result) {
            // Prvý pokus - vytvor záznam
            $this->db->prepare("
                INSERT INTO rate_limits (ip_address, action, attempts_count, first_attempt_at, last_attempt_at)
                VALUES (:ip, :action, 1, NOW(), NOW())
            ")->execute([
                'ip' => $ipAddress,
                'action' => $action,
            ]);
            
            return 0;
        }
        
        // Kontrola časového okna
        $firstAttempt = strtotime($result['first_attempt_at']);
        $timeWindow = $config['window'];
        
        if (time() - $firstAttempt > $timeWindow) {
            // Časové okno vypršalo - resetuj counter
            $this->reset($action, $ipAddress);
            $this->recordFailedAttempt($action, $ipAddress);
            return 0;
        }
        
        // Inkrementuj počet pokusov
        $newCount = $result['attempts_count'] + 1;
        
        // Ak dosiahol limit, zamkni účet
        $lockedUntil = null;
        if ($newCount >= $config['max_attempts']) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $config['lockout']);
        }
        
        $updateStmt = $this->db->prepare("
            UPDATE rate_limits 
            SET attempts_count = :count,
                last_attempt_at = NOW(),
                locked_until = :locked_until
            WHERE ip_address = :ip 
            AND action = :action
        ");
        
        $updateStmt->execute([
            'count' => $newCount,
            'locked_until' => $lockedUntil,
            'ip' => $ipAddress,
            'action' => $action,
        ]);

        if ($lockedUntil) {
            $remaining = strtotime($lockedUntil) - time();
            return max(0, $remaining);
        }
        return 0;
    }
    
    /**
     * Reset rate limitera (po úspešnom logine/registrácii)
     * 
     * @param string $action
     * @param string $ipAddress
     */
    public function reset(string $action, string $ipAddress): void
    {
        if ($this->useRedis && $this->redis) {
            $this->resetRedis($action, $ipAddress);
        }
        
        // Vždy vymaž aj z DB (pre istotu)
        $this->resetDB($action, $ipAddress);
    }
    
    /**
     * Redis: Reset
     */
    private function resetRedis(string $action, string $ipAddress): void
    {
        try {
            $countKey = $this->getRedisKey($action, $ipAddress, 'count');
            $lockKey = $this->getRedisKey($action, $ipAddress, 'locked');
            
            $this->redis->del($countKey);
            $this->redis->del($lockKey);
            
        } catch (Exception $e) {
            error_log("Redis reset error: " . $e->getMessage());
        }
    }
    
    /**
     * Database: Reset
     */
    private function resetDB(string $action, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
        ");
        
        $stmt->execute([
            'ip' => $ipAddress,
            'action' => $action,
        ]);
    }
    
    /**
     * Získanie zostávajúceho času lockoutu
     * 
     * @param string $action
     * @param string $ipAddress
     * @return int Sekundy do odomknutia (0 ak nie je zablokovaný)
     */
    public function getRemainingLockoutTime(string $action, string $ipAddress): int
    {
        if ($this->useRedis && $this->redis) {
            return $this->getRemainingLockoutTimeRedis($action, $ipAddress);
        }
        
        return $this->getRemainingLockoutTimeDB($action, $ipAddress);
    }
    
    /**
     * Redis: Zostávajúci čas lockoutu
     */
    private function getRemainingLockoutTimeRedis(string $action, string $ipAddress): int
    {
        try {
            $lockKey = $this->getRedisKey($action, $ipAddress, 'locked');
            $lockedUntil = $this->redis->get($lockKey);
            
            if (!$lockedUntil) {
                return 0;
            }
            
            $remaining = (int)$lockedUntil - time();
            return max(0, $remaining);
            
        } catch (Exception $e) {
            error_log("Redis getRemainingLockoutTime error: " . $e->getMessage());
            return $this->getRemainingLockoutTimeDB($action, $ipAddress);
        }
    }
    
    /**
     * Database: Zostávajúci čas lockoutu
     */
    private function getRemainingLockoutTimeDB(string $action, string $ipAddress): int
    {
        $stmt = $this->db->prepare("
            SELECT locked_until 
            FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
        ");
        
        $stmt->execute([
            'ip' => $ipAddress,
            'action' => $action,
        ]);
        
        $result = $stmt->fetch();
        
        if (!$result || !$result['locked_until']) {
            return 0;
        }
        
        $remaining = strtotime($result['locked_until']) - time();
        return max(0, $remaining);
    }
    
    /**
     * Generovanie Redis key
     */
    private function getRedisKey(string $action, string $ipAddress, string $suffix): string
    {
        return $this->redisConfig['prefix'] . 'ratelimit:' . $action . ':' . $ipAddress . ':' . $suffix;
    }
    
    /**
     * Automatické vytvorenie tabuľky rate_limits (DB fallback)
     */
    private function createTableIfNotExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL DEFAULT 'login',
                attempts_count INT NOT NULL DEFAULT 0,
                first_attempt_at TIMESTAMP NULL,
                last_attempt_at TIMESTAMP NULL,
                locked_until TIMESTAMP NULL,
                UNIQUE KEY idx_ip_action (ip_address, action),
                KEY idx_locked (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
