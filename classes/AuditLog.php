<?php
/**
 * AuditLog Class
 *
 * Helper trieda pre zaznamenávanie auditných logov do tabuľky audit_log.
 * Automaticky doplní user_id zo session, IP adresu, user_agent a request info.
 *
 * Použitie:
 *   AuditLog::log('chatbot', $chatbotId, 'approved', $oldValues, $newValues);
 *   AuditLog::log('user', $userId, 'login');
 */
class AuditLog
{
    private const ALLOWED_ACTIONS = [
        'created', 'updated', 'deleted', 'approved', 'rejected', 'suspended',
        'restored', 'login', 'logout', 'login_failed', 'password_changed',
        'password_reset_requested', 'password_reset_completed', 'email_verified',
        'blocked', 'unblocked', 'cancelled', 'renewed', 'reply_sent',
        'notes_updated', 'anonymized',
    ];

    private const SENSITIVE_KEYS = [
        'password', 'password_hash', 'token', 'access_token', 'refresh_token',
        'reset_token', 'password_reset_token', 'email_verification_token',
        'csrf_token', 'client_secret', 'secret', 'totp_code', 'totp_secret',
        'authorization', 'cookie', 'session_id',
    ];

    /**
     * Zapíše záznam do audit_log tabuľky.
     *
     * @param string     $entityType    Typ entity: 'user', 'chatbot', 'review', 'payment', 'subscription', 'contact_message'
     * @param string     $entityId      UUID entity
     * @param string     $action        Akcia: 'created','updated','deleted','approved','rejected','suspended','restored','login','logout'
     * @param array|null $oldValues     Staré hodnoty (pred zmenou)
     * @param array|null $newValues     Nové hodnoty (po zmene)
     * @param string|null $userId       UUID usera (ak NULL, vezme sa zo session)
     */
    public static function log(
        string $entityType,
        string $entityId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null
    ): void {
        try {
            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                throw new InvalidArgumentException('Unsupported audit action: ' . $action);
            }

            $db = Database::getInstance()->getConnection();

            // User ID zo session ak nie je explicitne zadané
            if ($userId === null) {
                if (class_exists('AdminAuth') && AdminAuth::isAdminRequest()) {
                    $admin = AdminAuth::current();
                    $userId = $admin['audit_user_id'] ?? null;
                } else {
                    $userId = $_SESSION['user']['id'] ?? null;
                }
            }

            // Vypočítaj zmenené polia automaticky
            $changedFields = null;
            if ($oldValues !== null && $newValues !== null) {
                $changed = array_keys(array_diff_assoc($newValues, $oldValues));
                $changedFields = !empty($changed) ? $changed : null;
            }

            // Request kontext
            $ipAddress     = self::getIpAddress();
            $userAgent     = isset($_SERVER['HTTP_USER_AGENT'])
                             ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                             : null;
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $requestUrl    = isset($_SERVER['REQUEST_URI'])
                             ? self::sanitizeRequestUrl((string)$_SERVER['REQUEST_URI'])
                             : null;

            $oldValues = self::redactSensitiveValues($oldValues);
            $newValues = self::redactSensitiveValues($newValues);

            $id = self::generateUuid();

            $stmt = $db->prepare("
                INSERT INTO audit_log (
                    id,
                    user_id,
                    entity_type,
                    entity_id,
                    action,
                    old_values,
                    new_values,
                    changed_fields,
                    ip_address,
                    user_agent,
                    request_method,
                    request_url,
                    created_at
                ) VALUES (
                    :id,
                    :user_id,
                    :entity_type,
                    :entity_id,
                    :action,
                    :old_values,
                    :new_values,
                    :changed_fields,
                    :ip_address,
                    :user_agent,
                    :request_method,
                    :request_url,
                    NOW()
                )
            ");

            $stmt->execute([
                'id'             => $id,
                'user_id'        => $userId,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId,
                'action'         => $action,
                'old_values'     => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values'     => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'changed_fields' => $changedFields !== null ? json_encode($changedFields) : null,
                'ip_address'     => $ipAddress,
                'user_agent'     => $userAgent,
                'request_method' => $requestMethod,
                'request_url'    => $requestUrl,
            ]);

        } catch (Exception $e) {
            // Audit log nikdy nesmie crashnúť hlavnú aplikáciu
            error_log('AuditLog::log() failed: ' . $e->getMessage());
        }
    }

    /**
     * Získa reálnu IP adresu klienta (aj za proxy/load balancerom).
     */
    private static function getIpAddress(): ?string
    {
        return ClientIpResolver::currentOrNull();
    }

    private static function redactSensitiveValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (in_array(mb_strtolower((string)$key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = self::redactSensitiveValues($value);
            }
        }

        return $values;
    }

    private static function sanitizeRequestUrl(string $requestUrl): string
    {
        $parts = parse_url($requestUrl);
        if ($parts === false || empty($parts['query'])) {
            return mb_substr($requestUrl, 0, 1000);
        }

        parse_str($parts['query'], $query);
        foreach ($query as $key => $value) {
            if (in_array(mb_strtolower((string)$key), self::SENSITIVE_KEYS, true)) {
                $query[$key] = '[REDACTED]';
            }
        }

        $path = $parts['path'] ?? '';
        $sanitized = $path . ($query ? '?' . http_build_query($query) : '');
        return mb_substr($sanitized, 0, 1000);
    }

    /**
     * Generuje UUID v4.
     */
    private static function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
