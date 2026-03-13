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
        $this->db        = Database::getInstance()->getConnection();
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
        $visitorId   = $this->generateUuid();
        $fingerprint = $this->generateFingerprint();
        $ipAddress   = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer    = $_SERVER['HTTP_REFERER'] ?? null;
        $landingPage = $_SERVER['REQUEST_URI'] ?? null;

        $cookieLifetime = $this->appConfig['visitor']['cookie_lifetime'];
        $expiresAt      = date('Y-m-d H:i:s', time() + $cookieLifetime);

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
            'id'           => $visitorId,
            'fingerprint'  => $fingerprint,
            'ip'           => $ipAddress,
            'user_agent'   => $userAgent,
            'referrer'     => $referrer,
            'landing_page' => $landingPage,
            'utm_source'   => $utmParams['utm_source']   ?? null,
            'utm_medium'   => $utmParams['utm_medium']   ?? null,
            'utm_campaign' => $utmParams['utm_campaign'] ?? null,
            'utm_term'     => $utmParams['utm_term']     ?? null,
            'utm_content'  => $utmParams['utm_content']  ?? null,
            'expires'      => $expiresAt,
        ]);

        $this->setCookie($visitorId);

        return $visitorId;
    }

    /**
     * Vymaž orphaned visitorov s prázdnym fingerprintom okrem zadaného ID.
     * Maže IBA tých čo sú staršie ako 5 minút (aby sme nedali čas visitor.js dobehnúť)
     * a nemajú žiadne aktivity.
     *
     * @param string $excludeVisitorId Tento visitor sa nevymaže (aktuálny)
     */
    public function deleteOrphanedEmpty(string $excludeVisitorId): void
    {
        $this->db->prepare("
            DELETE FROM visitors
            WHERE (fingerprint = '' OR fingerprint IS NULL)
              AND id != :exclude_id
              AND first_seen < NOW() - INTERVAL 5 MINUTE
              AND id NOT IN (SELECT DISTINCT visitor_id FROM chatbot_clicks)
              AND id NOT IN (SELECT DISTINCT visitor_id FROM visitor_user_links)
        ")->execute(['exclude_id' => $excludeVisitorId]);
    }

    /**
     * Vymaž visitora ak nemá žiadne aktivity (chatbot_clicks, visitor_user_links)
     * Používa sa na vymazanie orphaned visitorov ktorých vytvoril bootstrap
     * keď fingerprint neskôr nájde pôvodného visitora.
     *
     * @param string $visitorId
     */
    public function deleteIfEmpty(string $visitorId): void
    {
        // Skontroluj či má nejaké aktivity
        $stmt = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM chatbot_clicks WHERE visitor_id = :id) +
                (SELECT COUNT(*) FROM visitor_user_links WHERE visitor_id = :id2)
            AS activity_count
        ");
        $stmt->execute(['id' => $visitorId, 'id2' => $visitorId]);
        $count = (int) $stmt->fetchColumn();

        // Vymaž iba ak nemá žiadne aktivity
        if ($count === 0) {
            $this->db->prepare("DELETE FROM visitors WHERE id = :id")
                ->execute(['id' => $visitorId]);
        }
    }

    /**
     * Nájdi visitora podľa fingerprintu (FingerprintJS)
     *
     * @param string $fingerprint
     * @return string|null Visitor UUID alebo NULL ak nenájde
     */
    public function findByFingerprint(string $fingerprint): ?string
    {
        // Prázdny fingerprint ignorujeme - ešte nebol nastavený cez visitor.js
        if (empty($fingerprint)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM visitors
            WHERE fingerprint = :fingerprint
            ORDER BY last_seen DESC
            LIMIT 1
        ");
        $stmt->execute(['fingerprint' => $fingerprint]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['id'] : null;
    }

    /**
     * Aktualizuj fingerprint visitora (FingerprintJS hodnota je presnejšia ako server-side)
     *
     * @param string $visitorId
     * @param string $fingerprint
     */
    public function updateFingerprint(string $visitorId, string $fingerprint): void
    {
        $stmt = $this->db->prepare("
            UPDATE visitors
            SET fingerprint = :fingerprint
            WHERE id = :id
        ");
        $stmt->execute([
            'fingerprint' => $fingerprint,
            'id'          => $visitorId,
        ]);
    }

    /**
     * Nastav visitor cookie (public - používa aj identify.php na obnovenie cookie)
     *
     * @param string $visitorId
     */
    public function setCookie(string $visitorId): void
    {
        $cookieLifetime = $this->appConfig['visitor']['cookie_lifetime'];

        setcookie(
            $this->appConfig['visitor']['cookie_name'],
            $visitorId,
            time() + $cookieLifetime,
            '/',
            '',
            false,
            true
        );
    }

    /**
     * Konverzia visitora na registrovaného usera
     *
     * @param string $visitorId
     * @param string $userId
     */
    public function convertToUser(string $visitorId, string $userId): void
    {
        $check = $this->db->prepare("
            SELECT COUNT(*) FROM visitor_user_links
            WHERE visitor_id = :visitor_id AND user_id = :user_id
        ");
        $check->execute(['visitor_id' => $visitorId, 'user_id' => $userId]);

        if ($check->fetchColumn() > 0) {
            return;
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
     * Fingerprint sa nastavuje cez visitor.js (client-side) po načítaní stránky.
     * Pri vytvorení visitora je prázdny - identify.php ho neskôr nastaví.
     */
    private function generateFingerprint(): string
    {
        return '';
    }

    /**
     * Generovanie UUID v4
     */
    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}