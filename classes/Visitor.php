<?php
/**
 * Visitor Class
 * 
 * Tracking neprihlásených návštevníkov (pre analytics)
 */

class Visitor
{
    private PDO $db;
    private array $appConfig;
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->appConfig = require __DIR__ . '/../config/app.php';
    }
    
    /**
     * Získaj visitor ID (alebo vytvor nový záznam)
     * 
     * @param array $utmParams UTM parametre z URL
     * @return string Visitor UUID
     */
    public function getOrCreate(array $utmParams = []): string
    {
        $cookieName = $this->appConfig['visitor']['cookie_name'];
        
        // Ak už má visitor cookie, over či existuje v DB
        if (isset($_COOKIE[$cookieName])) {
            $visitorId = $_COOKIE[$cookieName];
            
            if ($this->exists($visitorId)) {
                // Update last_seen
                $this->updateLastSeen($visitorId);
                return $visitorId;
            }
        }
        
        // Vytvor nového visitora
        return $this->create($utmParams);
    }
    
    /**
     * Vytvorenie nového visitor záznamu
     */
    private function create(array $utmParams): string
    {
        $visitorId = $this->generateUuid();
        $fingerprint = $this->generateFingerprint();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $landingPage = $_SERVER['REQUEST_URI'] ?? null;
        
        // NOVÉ: Vypočítaj expiráciu (1 rok)
        $cookieLifetime = $this->appConfig['visitor']['cookie_lifetime'];
        $expiresAt = date('Y-m-d H:i:s', time() + $cookieLifetime);
        
        $stmt = $this->db->prepare("
            INSERT INTO visitors (
                id, fingerprint, ip_address, user_agent,
                referrer_url, landing_page,
                utm_source, utm_medium, utm_campaign, utm_term, utm_content,
                first_seen, last_seen, expires_at
            ) VALUES (
                :id, :fingerprint, :ip, :user_agent,
                :referrer, :landing_page,
                :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content,
                NOW(), NOW(), :expires
            )
        ");
        
        $stmt->execute([
            'id' => $visitorId,
            'fingerprint' => $fingerprint,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'referrer' => $referrer,
            'landing_page' => $landingPage,
            'utm_source' => $utmParams['utm_source'] ?? null,
            'utm_medium' => $utmParams['utm_medium'] ?? null,
            'utm_campaign' => $utmParams['utm_campaign'] ?? null,
            'utm_term' => $utmParams['utm_term'] ?? null,
            'utm_content' => $utmParams['utm_content'] ?? null,
            'expires' => $expiresAt, // NOVÉ
        ]);
        
        setcookie(
            $this->appConfig['visitor']['cookie_name'],
            $visitorId,
            time() + $cookieLifetime,
            '/',
            '',
            false,
            true
        );
        
        return $visitorId;
    }
    
    /**
     * Konverzia visitora na registrovaného usera
     * 
     * @param string $visitorId
     * @param string $userId
     */
    public function convertToUser(string $visitorId, string $userId): void
    {
        // Skontroluj či spojenie už neexistuje
        $check = $this->db->prepare("
            SELECT COUNT(*) FROM visitor_user_links 
            WHERE visitor_id = :visitor_id AND user_id = :user_id
        ");
        $check->execute(['visitor_id' => $visitorId, 'user_id' => $userId]);
        
        if ($check->fetchColumn() > 0) {
            return; // Spojenie už existuje
        }

        $stmt = $this->db->prepare("
            INSERT INTO visitor_user_links (id, visitor_id, user_id, converted_at)
            VALUES (:id, :visitor_id, :user_id, NOW())
        ");

        $stmt->execute([
            'id'         => $this->generateUuid(),
            'visitor_id' => $visitorId,
            'user_id'    => $userId,
        ]);
    }
    
    /**
     * Update last_seen
     */
    private function updateLastSeen(string $visitorId): void
    {
        $stmt = $this->db->prepare("
            UPDATE visitors 
            SET last_seen = NOW() 
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $visitorId]);
    }
    
    /**
     * Kontrola či visitor existuje v DB
     */
    private function exists(string $visitorId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM visitors WHERE id = :id");
        $stmt->execute(['id' => $visitorId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Generovanie browser fingerprintu (server-side)
     * 
     * Pre presnejší fingerprint môžeš neskôr pridať JS knižnicu ako FingerprintJS
     */
    private function generateFingerprint(): string
    {
        $components = [
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Generovanie UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}