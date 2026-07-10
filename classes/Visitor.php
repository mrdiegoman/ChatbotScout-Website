<?php
/**
 * Visitor Class
 *
 * Consent-gated visitor tracking for analytics.
 */

class Visitor
{
    private PDO $db;
    private array $appConfig;
    private ?bool $fingerprintHistoryTableExists = null;

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private const FINGERPRINT_PATTERN = '/^[a-f0-9]{64}$/i';

    public function __construct()
    {
        $this->db        = Database::getInstance()->getConnection();
        $this->appConfig = require __DIR__ . '/../config/app.php';
    }

    /**
     * Get an existing visitor from cookie or create a new cookie-backed visitor.
     */
    public function getOrCreate(array $visitContext = []): string
    {
        $cookieName = $this->appConfig['visitor']['cookie_name'];
        $cookieId   = $_COOKIE[$cookieName] ?? null;

        if (is_string($cookieId) && $cookieId !== '') {
            $existing = $this->getExisting($cookieId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->create($visitContext);
    }

    /**
     * Return an existing visitor ID. Does not create a new visitor.
     */
    public function getExisting(string $visitorId): ?string
    {
        if (!$this->exists($visitorId)) {
            return null;
        }

        $this->updateLastSeen($visitorId);
        return $visitorId;
    }

    /**
     * Create a visitor and immediately attach the current browser fingerprint.
     */
    public function createWithFingerprint(string $fingerprint, array $visitContext = []): string
    {
        $visitorId = $this->create($visitContext, $fingerprint);
        $this->recordFingerprint($visitorId, $fingerprint);

        return $visitorId;
    }

    private function create(array $visitContext = [], ?string $fingerprint = null): string
    {
        $visitorId   = $this->generateUuid();
        $ipAddress   = ClientIpResolver::currentOrNull();
        $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer    = $this->cleanContextValue($visitContext['referrer_url'] ?? null, 1000)
            ?? ($_SERVER['HTTP_REFERER'] ?? null);
        $landingPage = $this->cleanContextValue($visitContext['landing_page'] ?? null, 1000)
            ?? ($_SERVER['REQUEST_URI'] ?? null);

        $cookieLifetime = $this->appConfig['visitor']['cookie_lifetime'];
        $expiresAt      = date('Y-m-d H:i:s', time() + $cookieLifetime);
        $fingerprint    = $this->normalizeFingerprint($fingerprint);

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
            'utm_source'   => $this->cleanContextValue($visitContext['utm_source'] ?? null, 255),
            'utm_medium'   => $this->cleanContextValue($visitContext['utm_medium'] ?? null, 255),
            'utm_campaign' => $this->cleanContextValue($visitContext['utm_campaign'] ?? null, 255),
            'utm_term'     => $this->cleanContextValue($visitContext['utm_term'] ?? null, 255),
            'utm_content'  => $this->cleanContextValue($visitContext['utm_content'] ?? null, 255),
            'expires'      => $expiresAt,
        ]);

        $this->setCookie($visitorId);

        return $visitorId;
    }

    /**
     * Delete empty visitors that were left behind by an older two-step flow.
     */
    public function deleteOrphanedEmpty(string $excludeVisitorId): void
    {
        if (!preg_match(self::UUID_PATTERN, $excludeVisitorId)) {
            return;
        }

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
     * Delete a visitor if it has no tracked activity.
     */
    public function deleteIfEmpty(string $visitorId): void
    {
        if (!preg_match(self::UUID_PATTERN, $visitorId)) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM chatbot_clicks WHERE visitor_id = :id) +
                (SELECT COUNT(*) FROM visitor_user_links WHERE visitor_id = :id2)
            AS activity_count
        ");
        $stmt->execute(['id' => $visitorId, 'id2' => $visitorId]);
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            $this->db->prepare("DELETE FROM visitors WHERE id = :id")
                ->execute(['id' => $visitorId]);
        }
    }

    /**
     * Find a visitor by any known fingerprint. The history table is optional so
     * deploys remain safe before the DB migration is applied.
     */
    public function findByFingerprint(string $fingerprint): ?string
    {
        $fingerprint = $this->normalizeFingerprint($fingerprint);
        if ($fingerprint === null) {
            return null;
        }

        if ($this->hasFingerprintHistoryTable()) {
            $stmt = $this->db->prepare("
                SELECT vf.visitor_id
                FROM visitor_fingerprints vf
                INNER JOIN visitors v ON v.id = vf.visitor_id
                WHERE vf.fingerprint = :fingerprint
                ORDER BY vf.last_seen DESC
                LIMIT 1
            ");
            $stmt->execute(['fingerprint' => $fingerprint]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row['visitor_id'];
            }
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
     * Attach/update the latest fingerprint for the canonical cookie visitor.
     */
    public function recordFingerprint(string $visitorId, string $fingerprint): void
    {
        $fingerprint = $this->normalizeFingerprint($fingerprint);
        if ($fingerprint === null || !$this->exists($visitorId)) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE visitors
            SET fingerprint = :fingerprint, last_seen = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'fingerprint' => $fingerprint,
            'id'          => $visitorId,
        ]);

        if (!$this->hasFingerprintHistoryTable()) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO visitor_fingerprints (id, visitor_id, fingerprint, first_seen, last_seen)
            VALUES (:id, :visitor_id, :fingerprint, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                visitor_id = VALUES(visitor_id),
                last_seen = NOW()
        ");
        $stmt->execute([
            'id'          => $this->generateUuid(),
            'visitor_id'  => $visitorId,
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * Backward-compatible wrapper for old call sites.
     */
    public function updateFingerprint(string $visitorId, string $fingerprint): void
    {
        $this->recordFingerprint($visitorId, $fingerprint);
    }

    /**
     * Merge an older duplicate visitor into the canonical cookie visitor.
     */
    public function mergeVisitors(string $fromVisitorId, string $toVisitorId): void
    {
        if (
            $fromVisitorId === $toVisitorId
            || !$this->exists($fromVisitorId)
            || !$this->exists($toVisitorId)
        ) {
            return;
        }

        $startedTransaction = !$this->db->inTransaction();

        try {
            if ($startedTransaction) {
                $this->db->beginTransaction();
            }

            $this->db->prepare("
                UPDATE chatbot_clicks
                SET visitor_id = :to_id
                WHERE visitor_id = :from_id
            ")->execute([
                'to_id'   => $toVisitorId,
                'from_id' => $fromVisitorId,
            ]);

            $this->db->prepare("
                DELETE vul_from
                FROM visitor_user_links vul_from
                INNER JOIN visitor_user_links vul_to
                    ON vul_to.user_id = vul_from.user_id
                   AND vul_to.visitor_id = :to_id
                WHERE vul_from.visitor_id = :from_id
            ")->execute([
                'to_id'   => $toVisitorId,
                'from_id' => $fromVisitorId,
            ]);

            $this->db->prepare("
                UPDATE visitor_user_links
                SET visitor_id = :to_id
                WHERE visitor_id = :from_id
            ")->execute([
                'to_id'   => $toVisitorId,
                'from_id' => $fromVisitorId,
            ]);

            if ($this->hasFingerprintHistoryTable()) {
                $this->db->prepare("
                    UPDATE visitor_fingerprints
                    SET visitor_id = :to_id, last_seen = NOW()
                    WHERE visitor_id = :from_id
                ")->execute([
                    'to_id'   => $toVisitorId,
                    'from_id' => $fromVisitorId,
                ]);
            }

            $stmt = $this->db->prepare("
                UPDATE visitors target
                INNER JOIN visitors source ON source.id = ?
                SET
                    target.first_seen = LEAST(target.first_seen, source.first_seen),
                    target.last_seen = GREATEST(target.last_seen, source.last_seen),
                    target.referrer_url = COALESCE(target.referrer_url, source.referrer_url),
                    target.landing_page = COALESCE(target.landing_page, source.landing_page),
                    target.utm_source = COALESCE(target.utm_source, source.utm_source),
                    target.utm_medium = COALESCE(target.utm_medium, source.utm_medium),
                    target.utm_campaign = COALESCE(target.utm_campaign, source.utm_campaign),
                    target.utm_term = COALESCE(target.utm_term, source.utm_term),
                    target.utm_content = COALESCE(target.utm_content, source.utm_content)
                WHERE target.id = ?
            ");
            $stmt->execute([$fromVisitorId, $toVisitorId]);

            $this->db->prepare("DELETE FROM visitors WHERE id = :from_id")
                ->execute(['from_id' => $fromVisitorId]);

            if ($startedTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function findLatestForUser(string $userId): ?string
    {
        if (!preg_match(self::UUID_PATTERN, $userId)) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT vul.visitor_id
            FROM visitor_user_links vul
            INNER JOIN visitors v ON v.id = vul.visitor_id
            WHERE vul.user_id = :user_id
            ORDER BY vul.converted_at DESC, v.last_seen DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $this->updateLastSeen($row['visitor_id']);
        return $row['visitor_id'];
    }

    /**
     * Set the analytics visitor cookie. This cookie is only called after
     * statistics consent has been checked by the caller.
     */
    public function setCookie(string $visitorId): void
    {
        if (!preg_match(self::UUID_PATTERN, $visitorId)) {
            return;
        }

        $cookieName     = $this->appConfig['visitor']['cookie_name'];
        $cookieLifetime = $this->appConfig['visitor']['cookie_lifetime'];

        setcookie($cookieName, $visitorId, [
            'expires'  => time() + $cookieLifetime,
            'path'     => '/',
            'secure'   => (bool)$this->appConfig['visitor']['cookie_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[$cookieName] = $visitorId;
    }

    public function clearCookie(): void
    {
        $cookieName = $this->appConfig['visitor']['cookie_name'];

        setcookie($cookieName, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (bool)$this->appConfig['visitor']['cookie_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[$cookieName]);
    }

    /**
     * Link a consented visitor to a registered user.
     */
    public function convertToUser(string $visitorId, string $userId): void
    {
        if (!$this->exists($visitorId) || !preg_match(self::UUID_PATTERN, $userId)) {
            return;
        }

        $check = $this->db->prepare("
            SELECT COUNT(*) FROM visitor_user_links
            WHERE visitor_id = :visitor_id AND user_id = :user_id
        ");
        $check->execute(['visitor_id' => $visitorId, 'user_id' => $userId]);

        if ((int)$check->fetchColumn() > 0) {
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

    private function updateLastSeen(string $visitorId): void
    {
        if (!preg_match(self::UUID_PATTERN, $visitorId)) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE visitors
            SET last_seen = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $visitorId]);
    }

    private function exists(string $visitorId): bool
    {
        if (!preg_match(self::UUID_PATTERN, $visitorId)) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM visitors WHERE id = :id");
        $stmt->execute(['id' => $visitorId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function normalizeFingerprint(?string $fingerprint): ?string
    {
        if (!is_string($fingerprint)) {
            return null;
        }

        $fingerprint = strtolower(trim($fingerprint));

        return preg_match(self::FINGERPRINT_PATTERN, $fingerprint) ? $fingerprint : null;
    }

    private function cleanContextValue(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '');
        if ($value === '') {
            return null;
        }

        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    private function hasFingerprintHistoryTable(): bool
    {
        if ($this->fingerprintHistoryTableExists !== null) {
            return $this->fingerprintHistoryTableExists;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'visitor_fingerprints'");
            $this->fingerprintHistoryTableExists = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->fingerprintHistoryTableExists = false;
        }

        return $this->fingerprintHistoryTableExists;
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
