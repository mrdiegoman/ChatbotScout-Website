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
                             ? mb_substr($_SERVER['REQUEST_URI'], 0, 1000)
                             : null;

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
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy / load balancer
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // HTTP_X_FORWARDED_FOR môže obsahovať viac IP oddelených čiarkou
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return mb_substr($ip, 0, 45);
                }
            }
        }

        return null;
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
