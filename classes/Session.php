<?php
/**
 * Session Class
 * 
 * Správa sessions: vytvorenie, validácia, zmazanie
 */

class Session
{
    private PDO $db;
    private array $appConfig;
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->appConfig = require __DIR__ . '/../config/app.php';
    }
    
    /**
     * Vytvorenie novej session
     * 
     * @param string $userId
     * @param bool $rememberMe Ak TRUE, session trvá 30 dní, inak 24 hodín
     * @return array Session data
     */
    public function create(string $userId, bool $rememberMe = false): array
    {
        $sessionId = $this->generateUuid();
        $sessionToken = bin2hex(random_bytes(32));
        
        // IP adresa a User-Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Device type detection
        $deviceType = $this->detectDeviceType($userAgent);
        
        // Expirácia
        $lifetime = $rememberMe 
            ? $this->appConfig['session']['remember_me_lifetime']
            : $this->appConfig['session']['lifetime'];
        

        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        // INSERT do databázy
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (
                id, user_id, session_token, 
                ip_address, user_agent, device_type,
                expires_at, created_at, last_activity_at
            ) VALUES (
                :id, :user_id, :token,
                :ip, :user_agent, :device_type,
                :expires, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            'id' => $sessionId,
            'user_id' => $userId,
            'token' => $sessionToken,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'expires' => $expiresAt,
        ]);
        
        return [
            'id' => $sessionId,
            'token' => $sessionToken,
            'expires' => strtotime($expiresAt),
            'remember_me' => $rememberMe,
        ];
    }

    /**
     * Validácia session tokenu
     * 
     * @param string $sessionToken
     * @return array|null User data ak je session platná, NULL ak nie
     */
    public function validate(string $sessionToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id as session_id,
                s.user_id,
                s.expires_at,
                u.email,
                u.first_name,
                u.last_name,
                u.profile_picture_url,
                u.is_email_verified,
                u.is_admin,
                u.status
            FROM user_sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.session_token = :token
            AND s.expires_at > NOW()
        ");
        
        $stmt->execute(['token' => $sessionToken]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }
        
        // Kontrola statusu účtu
        if ($data['status'] !== 'active' && $data['status'] !== 'pending_verification') {
            return null;
        }
        
        // $testStmt = $this->db->prepare("
        //     select expires_at
        //     from user_sessions
        //     where id = :id
        // ");

        // $testStmt->execute(['id' => $data['session_id']]);
        // $expiresAt = $testStmt->fetchColumn();
        // error_log("Session expires at before1: $expiresAt");
        // error_log("Session ID: " . $data['session_id']);

        // Update last_activity_at
        $updateStmt = $this->db->prepare("
            UPDATE user_sessions 
            SET last_activity_at = NOW() 
            WHERE id = :id
        ");
        $updateStmt->execute(['id' => $data['session_id']]);
        
        // $testStmt = $this->db->prepare("
        //     select expires_at
        //     from user_sessions
        //     where id = :id
        // ");

        // $testStmt->execute(['id' => $data['session_id']]);
        // $expiresAt = $testStmt->fetchColumn();
        // error_log("Session expires at after1: $expiresAt");
        // error_log("Session ID: " . $data['session_id']);

        return [
            'id'                  => $data['user_id'],
            'email'               => $data['email'],
            'first_name'          => $data['first_name'],
            'last_name'           => $data['last_name'],
            'profile_picture_url' => $data['profile_picture_url'],
            'is_email_verified'   => (bool)$data['is_email_verified'],
            'is_admin'            => (bool)$data['is_admin'],
            'status'              => $data['status'],
        ];
    }
    
    /**
     * Zmazanie session (logout)
     * 
     * @param string $sessionToken
     * @return bool
     */
    public function destroy(string $sessionToken): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_token = :token");
        return $stmt->execute(['token' => $sessionToken]);
    }
    
    /**
     * Vyčistenie expirovaných sessions (volá sa automaticky v bootstrap)
     * 
     * @return int Počet zmazaných sessions
     */
    public function cleanup(): int
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Detekcia typu zariadenia z User-Agent
     */
    private function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'other';
        }
        
        $userAgent = strtolower($userAgent);
        
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
            return 'tablet';
        }
        
        if (preg_match('/(mobile|iphone|ipod|blackberry|android|windows phone)/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'desktop';
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