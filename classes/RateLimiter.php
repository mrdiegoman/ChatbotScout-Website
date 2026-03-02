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
     * @param string|null $email Email adresa (voliteľné) // 🆕 ZMENA: pridaný parameter
     * @return bool TRUE ak môže pokračovať, FALSE ak je zablokovaný
     */
    public function check(string $action, string $ipAddress, ?string $email = null): bool // 🆕 ZMENA: pridaný parameter $email
    {
        // Kontrola IP blokácie
        if ($this->isBlocked($action, $ipAddress)) {
            return false;
        }

        // 🆕 ZMENA: Ak je zadaný email, skontroluj aj email blokáciu
        if ($email !== null && $this->isBlockedByEmail($action, $email)) {
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

    // 🆕 ZMENA: Nová metóda - kontrola blokacie podla emailu
    /**
     * Kontrola či je email zablokovaný
     * 
     * @param string $action
     * @param string $email
     * @return bool TRUE ak je zablokovaný
     */
    public function isBlockedByEmail(string $action, string $email): bool
    {
        if ($this->useRedis && $this->redis) {
            return $this->isBlockedByEmailRedis($action, $email);
        }

        return $this->isBlockedByEmailDB($action, $email);
    }
    
    /**
     * Redis: Kontrola blokácie podľa IP
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

    // 🆕 ZMENA: Nová metóda - Redis kontrola blokacie podla emailu
    /**
     * Redis: Kontrola blokácie podľa emailu
     */
    private function isBlockedByEmailRedis(string $action, string $email): bool
    {
        try {
            $lockKey = $this->getRedisEmailKey($action, $email, 'locked');
            $lockedUntil = $this->redis->get($lockKey);

            if ($lockedUntil && (int)$lockedUntil > time()) {
                return true;
            }

            if ($lockedUntil) {
                $this->redis->del($lockKey);
            }

            return false;

        } catch (Exception $e) {
            error_log("Redis isBlockedByEmail error: " . $e->getMessage());
            return $this->isBlockedByEmailDB($action, $email);
        }
    }
    
    /**
     * Database: Kontrola blokácie podľa IP
     */
    private function isBlockedDB(string $action, string $ipAddress): bool
    {
        $stmt = $this->db->prepare("
            SELECT locked_until 
            FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
            AND email IS NULL
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

    // 🆕 ZMENA: Nová metóda - DB kontrola blokacie podla emailu
    /**
     * Database: Kontrola blokácie podľa emailu
     */
    private function isBlockedByEmailDB(string $action, string $email): bool
    {
        $stmt = $this->db->prepare("
            SELECT locked_until 
            FROM rate_limits 
            WHERE email = :email 
            AND action = :action
            AND ip_address IS NULL
        ");

        $stmt->execute([
            'email' => $email,
            'action' => $action,
        ]);

        $result = $stmt->fetch();

        if (!$result || !$result['locked_until']) {
            return false;
        }

        $lockedUntil = strtotime($result['locked_until']);

        if ($lockedUntil < time()) {
            $this->resetByEmail($action, $email);
            return false;
        }

        return true;
    }
    
    /**
     * Zaznamenanie neúspešného pokusu
     * 
     * @param string $action
     * @param string $ipAddress
     * @param string|null $email Email adresa (voliteľné) // 🆕 ZMENA: pridaný parameter
     * @return int Zostávajúci čas lockoutu v sekundách
     */
    public function recordFailedAttempt(string $action, string $ipAddress, ?string $email = null): int // 🆕 ZMENA: pridaný parameter $email
    {
        // Zaznamenaj pokus pre IP
        $remainingIp = 0;
        if ($this->useRedis && $this->redis) {
            $remainingIp = $this->recordFailedAttemptRedis($action, $ipAddress);
        } else {
            $remainingIp = $this->recordFailedAttemptDB($action, $ipAddress);
        }

        // 🆕 ZMENA: Zaznamenaj pokus aj pre email (ak je zadaný)
        $remainingEmail = 0;
        if ($email !== null) {
            if ($this->useRedis && $this->redis) {
                $remainingEmail = $this->recordFailedAttemptByEmailRedis($action, $email);
            } else {
                $remainingEmail = $this->recordFailedAttemptByEmailDB($action, $email);
            }
        }

        // 🆕 ZMENA: Vrát väčší z dvoch zostávajúcich časov
        return max($remainingIp, $remainingEmail);
    }
    
    /**
     * Redis: Zaznamenanie failed attempt podľa IP
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
            return 0;
        }
    }

    // 🆕 ZMENA: Nová metóda - Redis zaznamenanie failed attempt podla emailu
    /**
     * Redis: Zaznamenanie failed attempt podľa emailu
     */
    private function recordFailedAttemptByEmailRedis(string $action, string $email): int
    {
        try {
            $config = $this->config['rate_limit'][$action];
            error_log("email: $email");
            $countKey = $this->getRedisEmailKey($action, $email, 'count');
            $lockKey  = $this->getRedisEmailKey($action, $email, 'locked');

            $attempts = $this->redis->incr($countKey);

            if ($attempts == 1) {
                $this->redis->expire($countKey, $config['email_window'] ?? $config['window']);
            }

            if ($attempts >= ($config['email_max_attempts'] ?? $config['max_attempts'])) {
                $lockedUntil = time() + ($config['email_lockout'] ?? $config['lockout']);
                $this->redis->set($lockKey, $lockedUntil, ($config['email_lockout'] ?? $config['lockout']));
                $this->redis->del($countKey);

                return max(0, (int)$lockedUntil - time());
            }

            return 0;

        } catch (Exception $e) {
            error_log("Redis recordFailedAttemptByEmail error: " . $e->getMessage());
            $this->recordFailedAttemptByEmailDB($action, $email);
            return 0;
        }
    }
    
    /**
     * Database: Zaznamenanie failed attempt podľa IP
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
            AND email IS NULL
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
            AND email IS NULL
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

    // 🆕 ZMENA: Nová metóda - DB zaznamenanie failed attempt podla emailu
    /**
     * Database: Zaznamenanie failed attempt podľa emailu
     */
    private function recordFailedAttemptByEmailDB(string $action, string $email): int
    {
        $config = $this->config['rate_limit'][$action];

        $emailMaxAttempts = $config['email_max_attempts'] ?? $config['max_attempts'];
        $emailWindow      = $config['email_window']       ?? $config['window'];
        $emailLockout     = $config['email_lockout']      ?? $config['lockout'];

        $stmt = $this->db->prepare("
            SELECT attempts_count, first_attempt_at 
            FROM rate_limits 
            WHERE email = :email 
            AND action = :action
            AND ip_address IS NULL
        ");

        $stmt->execute([
            'email'  => $email,
            'action' => $action,
        ]);

        $result = $stmt->fetch();

        if (!$result) {
            $this->db->prepare("
                INSERT INTO rate_limits (email, action, attempts_count, first_attempt_at, last_attempt_at)
                VALUES (:email, :action, 1, NOW(), NOW())
            ")->execute([
                'email'  => $email,
                'action' => $action,
            ]);

            return 0;
        }

        $firstAttempt = strtotime($result['first_attempt_at']);

        if (time() - $firstAttempt > $emailWindow) {
            $this->resetByEmail($action, $email);
            $this->recordFailedAttemptByEmailDB($action, $email);
            return 0;
        }

        $newCount    = $result['attempts_count'] + 1;
        $lockedUntil = null;

        if ($newCount >= $emailMaxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $emailLockout);
        }

        $this->db->prepare("
            UPDATE rate_limits 
            SET attempts_count = :count,
                last_attempt_at = NOW(),
                locked_until = :locked_until
            WHERE email = :email 
            AND action = :action
            AND ip_address IS NULL
        ")->execute([
            'count'        => $newCount,
            'locked_until' => $lockedUntil,
            'email'        => $email,
            'action'       => $action,
        ]);

        if ($lockedUntil) {
            return max(0, strtotime($lockedUntil) - time());
        }
        return 0;
    }
    
    /**
     * Reset rate limitera podľa IP (po úspešnom logine/registrácii)
     * 
     * @param string $action
     * @param string $ipAddress
     * @param string|null $email // 🆕 ZMENA: pridaný parameter
     */
    public function reset(string $action, string $ipAddress, ?string $email = null): void // 🆕 ZMENA: pridaný parameter $email
    {
        if ($this->useRedis && $this->redis) {
            $this->resetRedis($action, $ipAddress);
        }
        
        // Vždy vymaž aj z DB (pre istotu)
        $this->resetDB($action, $ipAddress);

        // 🆕 ZMENA: Reset aj emailového limitu
        if ($email !== null) {
            $this->resetByEmail($action, $email);
        }
    }

    // 🆕 ZMENA: Nová metóda - reset rate limitera podla emailu
    /**
     * Reset rate limitera podľa emailu
     */
    public function resetByEmail(string $action, string $email): void
    {
        if ($this->useRedis && $this->redis) {
            $this->resetByEmailRedis($action, $email);
        }

        $this->resetByEmailDB($action, $email);
    }
    
    /**
     * Redis: Reset podľa IP
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

    // 🆕 ZMENA: Nová metóda - Redis reset podla emailu
    /**
     * Redis: Reset podľa emailu
     */
    private function resetByEmailRedis(string $action, string $email): void
    {
        try {
            $countKey = $this->getRedisEmailKey($action, $email, 'count');
            $lockKey  = $this->getRedisEmailKey($action, $email, 'locked');
            error_log("Resetting Redis keys for email: $email, countKey: $countKey, lockKey: $lockKey");
            $this->redis->del($countKey);
            $this->redis->del($lockKey);

        } catch (Exception $e) {
            error_log("Redis resetByEmail error: " . $e->getMessage());
        }
    }
    
    /**
     * Database: Reset podľa IP
     */
    private function resetDB(string $action, string $ipAddress): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
            AND email IS NULL
        ");
        
        $stmt->execute([
            'ip' => $ipAddress,
            'action' => $action,
        ]);
    }

    // 🆕 ZMENA: Nová metóda - DB reset podla emailu
    /**
     * Database: Reset podľa emailu
     */
    private function resetByEmailDB(string $action, string $email): void
    {
        $this->db->prepare("
            DELETE FROM rate_limits 
            WHERE email = :email 
            AND action = :action
            AND ip_address IS NULL
        ")->execute([
            'email'  => $email,
            'action' => $action,
        ]);
    }
    
    /**
     * Získanie zostávajúceho času lockoutu
     * 
     * @param string $action
     * @param string $ipAddress
     * @param string|null $email // 🆕 ZMENA: pridaný parameter
     * @return int Sekundy do odomknutia (0 ak nie je zablokovaný)
     */
    public function getRemainingLockoutTime(string $action, string $ipAddress, ?string $email = null): int // 🆕 ZMENA: pridaný parameter $email
    {
        $remainingIp = 0;
        if ($this->useRedis && $this->redis) {
            $remainingIp = $this->getRemainingLockoutTimeRedis($action, $ipAddress);
        } else {
            $remainingIp = $this->getRemainingLockoutTimeDB($action, $ipAddress);
        }

        // 🆕 ZMENA: Získaj aj zostávajúci čas pre email
        $remainingEmail = 0;
        if ($email !== null) {
            $remainingEmail = $this->getRemainingLockoutTimeByEmail($action, $email);
        }

        // 🆕 ZMENA: Vrát väčší z dvoch časov
        return max($remainingIp, $remainingEmail);
    }

    // 🆕 ZMENA: Nová metóda - zostávajúci čas lockoutu pre email
    /**
     * Získanie zostávajúceho času lockoutu pre email
     */
    public function getRemainingLockoutTimeByEmail(string $action, string $email): int
    {
        if ($this->useRedis && $this->redis) {
            return $this->getRemainingLockoutTimeByEmailRedis($action, $email);
        }

        return $this->getRemainingLockoutTimeByEmailDB($action, $email);
    }
    
    /**
     * Redis: Zostávajúci čas lockoutu podľa IP
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

    // 🆕 ZMENA: Nová metóda - Redis zostávajúci čas pre email
    /**
     * Redis: Zostávajúci čas lockoutu podľa emailu
     */
    private function getRemainingLockoutTimeByEmailRedis(string $action, string $email): int
    {
        try {
            $lockKey     = $this->getRedisEmailKey($action, $email, 'locked');
            $lockedUntil = $this->redis->get($lockKey);

            if (!$lockedUntil) {
                return 0;
            }

            return max(0, (int)$lockedUntil - time());

        } catch (Exception $e) {
            error_log("Redis getRemainingLockoutTimeByEmail error: " . $e->getMessage());
            return $this->getRemainingLockoutTimeByEmailDB($action, $email);
        }
    }
    
    /**
     * Database: Zostávajúci čas lockoutu podľa IP
     */
    private function getRemainingLockoutTimeDB(string $action, string $ipAddress): int
    {
        $stmt = $this->db->prepare("
            SELECT locked_until 
            FROM rate_limits 
            WHERE ip_address = :ip 
            AND action = :action
            AND email IS NULL
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

    // 🆕 ZMENA: Nová metóda - DB zostávajúci čas pre email
    /**
     * Database: Zostávajúci čas lockoutu podľa emailu
     */
    private function getRemainingLockoutTimeByEmailDB(string $action, string $email): int
    {
        $stmt = $this->db->prepare("
            SELECT locked_until 
            FROM rate_limits 
            WHERE email = :email 
            AND action = :action
            AND ip_address IS NULL
        ");

        $stmt->execute([
            'email'  => $email,
            'action' => $action,
        ]);

        $result = $stmt->fetch();

        if (!$result || !$result['locked_until']) {
            return 0;
        }

        return max(0, strtotime($result['locked_until']) - time());
    }
    
    /**
     * Generovanie Redis key pre IP
     */
    private function getRedisKey(string $action, string $ipAddress, string $suffix): string
    {
        return $this->redisConfig['prefix'] . 'ratelimit:' . $action . ':' . $ipAddress . ':' . $suffix;
    }

    // 🆕 ZMENA: Nová metóda - generovanie Redis key pre email
    /**
     * Generovanie Redis key pre email
     */
    private function getRedisEmailKey(string $action, string $email, string $suffix): string
    {
        return $this->redisConfig['prefix'] . 'ratelimit:' . $action . ':email:' . $email . ':' . $suffix;
    }
    
    /**
     * Automatické vytvorenie tabuľky rate_limits (DB fallback)
     */
    private function createTableIfNotExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NULL,
                email VARCHAR(255) NULL,
                action VARCHAR(50) NOT NULL DEFAULT 'login',
                attempts_count INT NOT NULL DEFAULT 0,
                first_attempt_at TIMESTAMP NULL,
                last_attempt_at TIMESTAMP NULL,
                locked_until TIMESTAMP NULL,
                KEY idx_locked (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // 🆕 ZMENA: Zmenená štruktúra tabulky:
        //   - ip_address je teraz NULL (nie NOT NULL) - pre email-only záznamy
        //   - pridaný stĺpec email VARCHAR(255) NULL - pre email-based rate limiting
        //   - odstránený UNIQUE KEY idx_ip_action - nahradený samostatnými NULL queries
    }
}