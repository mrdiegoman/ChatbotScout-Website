<?php
/**
 * Separate admin authentication.
 *
 * Admin access is intentionally independent from normal user accounts and the
 * users.is_admin column. Credentials live in .env and are protected by the
 * shared RateLimiter (Redis primary, database fallback).
 */
class AdminAuth
{
    private const SESSION_KEY = 'admin_auth';
    private const RATE_ACTION_IP = 'admin_login_ip';
    private const RATE_ACTION_USER = 'admin_login_user';

    public static function accessPath(): string
    {
        return trim(self::env('ADMIN_ACCESS_PATH'), " \t\n\r\0\x0B/");
    }

    public static function pathMatches(string $path): bool
    {
        $configured = self::accessPath();
        $path = trim($path, " \t\n\r\0\x0B/");

        return $configured !== '' && hash_equals($configured, $path);
    }

    public static function loginUrl(string $baseUrl = ''): string
    {
        $path = self::accessPath();
        if ($path === '') {
            $path = 'admin-access-not-configured';
        }

        if ($baseUrl === '') {
            return rawurlencode($path);
        }

        return rtrim($baseUrl, '/') . '/' . rawurlencode($path);
    }

    public static function isAuthenticated(): bool
    {
        return self::current() !== null;
    }

    public static function current(): ?array
    {
        $session = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($session) || empty($session['username'])) {
            return null;
        }

        $username = self::normalizeUsername((string)$session['username']);
        $user = self::configuredUsers()[$username] ?? null;
        if (!$user) {
            self::logout();
            return null;
        }

        if (!hash_equals($user['fingerprint'], (string)($session['fingerprint'] ?? ''))) {
            self::logout();
            return null;
        }

        $now = time();
        $absoluteLifetime = self::envInt('ADMIN_SESSION_LIFETIME_SECONDS', 8 * 60 * 60);
        $idleLifetime = self::envInt('ADMIN_SESSION_IDLE_SECONDS', 60 * 60);

        $loginAt = (int)($session['login_at'] ?? 0);
        $lastSeen = (int)($session['last_seen'] ?? 0);

        if ($loginAt <= 0 || $now - $loginAt > $absoluteLifetime) {
            self::logout();
            return null;
        }

        if ($lastSeen <= 0 || $now - $lastSeen > $idleLifetime) {
            self::logout();
            return null;
        }

        $_SESSION[self::SESSION_KEY]['last_seen'] = $now;

        return [
            'username'      => $username,
            'display_name'  => $user['display_name'],
            'audit_user_id' => $user['audit_user_id'],
            'login_at'      => $loginAt,
            'last_seen'     => $now,
        ];
    }

    public static function asLegacyUser(): array
    {
        $admin = self::current();

        return [
            'id'       => $admin['audit_user_id'] ?? null,
            'email'    => ($admin['username'] ?? 'admin') . '@admin.local',
            'is_admin' => true,
        ];
    }

    public static function authenticate(string $username, string $password, string $totpCode = ''): array
    {
        $username = self::normalizeUsername($username);
        $clientIp = self::clientIp();
        $identifier = $username !== '' ? $username : 'unknown-admin';
        $ipBucket = 'ip:' . self::bucketValue($clientIp);
        $userBucket = 'user:' . self::bucketValue($identifier);
        $rateLimiter = new RateLimiter();

        if (!self::rateLimitAllows($rateLimiter, $ipBucket, $userBucket)) {
            return [
                'success' => false,
                'message' => self::lockoutMessage($rateLimiter, $ipBucket, $userBucket),
            ];
        }

        $users = self::configuredUsers();
        $user = $users[$username] ?? null;

        $passwordOk = $user ? self::verifyPassword($password, $user) : self::constantTimeMiss($password);
        $totpOk = true;

        if ($user && $user['require_totp']) {
            $totpOk = $user['totp_secret'] !== ''
                && self::verifyTotp($user['totp_secret'], $totpCode);
        }

        if (!$user || !$passwordOk || !$totpOk) {
            $remaining = self::recordFailedAttempt($rateLimiter, $ipBucket, $userBucket);
            if ($remaining > 0) {
                return ['success' => false, 'message' => self::formatLockout($remaining)];
            }

            return ['success' => false, 'message' => 'Invalid admin credentials.'];
        }

        self::resetRateLimit($rateLimiter, $ipBucket, $userBucket);
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'username'    => $username,
            'fingerprint' => $user['fingerprint'],
            'login_at'    => time(),
            'last_seen'   => time(),
        ];

        return ['success' => true, 'message' => 'Signed in.'];
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function isAdminRequest(): bool
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $target = $script . ' ' . $uri;

        return preg_match('/admin|blockUser|getOldestNewItem/i', $target) === 1;
    }

    private static function configuredUsers(): array
    {
        $users = [];
        $listedUsers = array_filter(array_map('trim', explode(',', self::env('ADMIN_USERS'))));

        foreach ($listedUsers as $listedUser) {
            $user = self::buildNamedUser($listedUser, false);
            if ($user) {
                $users[$user['username']] = $user;
            }
        }

        if (!$users) {
            $singleUser = self::env('ADMIN_AUTH_USER');
            if ($singleUser === '') {
                $singleUser = self::env('ADMIN_USERNAME');
            }

            if ($singleUser !== '') {
                $user = self::buildNamedUser($singleUser, true);
                if ($user) {
                    $users[$user['username']] = $user;
                }
            }
        }

        return $users;
    }

    private static function buildNamedUser(string $username, bool $allowGlobalFallback): ?array
    {
        $normalized = self::normalizeUsername($username);
        if ($normalized === '') {
            return null;
        }

        $key = self::envKey($normalized);
        $password = self::env("ADMIN_{$key}_PASSWORD");
        $passwordHash = self::env("ADMIN_{$key}_PASSWORD_HASH");
        $totpSecret = self::env("ADMIN_{$key}_TOTP_SECRET");
        $displayName = self::env("ADMIN_{$key}_DISPLAY_NAME");
        $auditUserId = self::env("ADMIN_{$key}_AUDIT_USER_ID");
        $requireTotpValue = self::env("ADMIN_{$key}_REQUIRE_TOTP", '__missing__');

        if ($allowGlobalFallback) {
            $password = $password !== '' ? $password : self::env('ADMIN_PASSWORD');
            $passwordHash = $passwordHash !== '' ? $passwordHash : self::env('ADMIN_PASSWORD_HASH');
            $totpSecret = $totpSecret !== '' ? $totpSecret : self::env('ADMIN_TOTP_SECRET');
            $displayName = $displayName !== '' ? $displayName : self::env('ADMIN_DISPLAY_NAME');
            $auditUserId = $auditUserId !== '' ? $auditUserId : self::env('ADMIN_AUDIT_USER_ID');
            if ($requireTotpValue === '__missing__') {
                $requireTotpValue = self::env('ADMIN_REQUIRE_TOTP', '__missing__');
            }
        } elseif ($requireTotpValue === '__missing__') {
            $requireTotpValue = self::env('ADMIN_REQUIRE_TOTP', '__missing__');
        }

        if ($password === '' && $passwordHash === '') {
            return null;
        }

        $requireTotp = $requireTotpValue === '__missing__'
            ? $totpSecret !== ''
            : self::boolValue($requireTotpValue);

        $fingerprint = hash('sha256', implode('|', [
            $normalized,
            $password,
            $passwordHash,
            preg_replace('/\s+/', '', strtoupper($totpSecret)),
            $requireTotp ? '1' : '0',
            $auditUserId,
        ]));

        return [
            'username'      => $normalized,
            'password'      => $password,
            'password_hash' => $passwordHash,
            'totp_secret'   => $totpSecret,
            'require_totp'  => $requireTotp,
            'display_name'  => $displayName !== '' ? $displayName : $normalized,
            'audit_user_id' => self::isUuid($auditUserId) ? $auditUserId : null,
            'fingerprint'   => $fingerprint,
        ];
    }

    private static function verifyPassword(string $password, array $user): bool
    {
        if ($user['password_hash'] !== '') {
            return password_verify($password, $user['password_hash']);
        }

        return $user['password'] !== '' && hash_equals($user['password'], $password);
    }

    private static function constantTimeMiss(string $password): bool
    {
        hash_equals(hash('sha256', 'missing-admin-user'), hash('sha256', $password));
        return false;
    }

    private static function verifyTotp(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $key = self::base32Decode($secret);
        if ($key === '') {
            return false;
        }

        $counter = intdiv(time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals(self::hotp($key, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function hotp(string $key, int $counter): string
    {
        $binaryCounter = pack('N*', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $secret = preg_replace('/[^A-Z2-7]/', '', strtoupper($secret));
        if ($secret === '') {
            return '';
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $output = '';

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }

    private static function clientIp(): string
    {
        return ClientIpResolver::current();
    }

    private static function rateLimitAllows(RateLimiter $rateLimiter, string $ipBucket, string $userBucket): bool
    {
        return $rateLimiter->check(self::RATE_ACTION_IP, $ipBucket)
            && $rateLimiter->check(self::RATE_ACTION_USER, $userBucket);
    }

    private static function recordFailedAttempt(RateLimiter $rateLimiter, string $ipBucket, string $userBucket): int
    {
        $remainingIp = $rateLimiter->recordFailedAttempt(self::RATE_ACTION_IP, $ipBucket);
        $remainingUser = $rateLimiter->recordFailedAttempt(self::RATE_ACTION_USER, $userBucket);

        return max($remainingIp, $remainingUser);
    }

    private static function resetRateLimit(RateLimiter $rateLimiter, string $ipBucket, string $userBucket): void
    {
        $rateLimiter->reset(self::RATE_ACTION_IP, $ipBucket);
        $rateLimiter->reset(self::RATE_ACTION_USER, $userBucket);
    }

    private static function lockoutMessage(RateLimiter $rateLimiter, string $ipBucket, string $userBucket): string
    {
        $remaining = max(
            $rateLimiter->getRemainingLockoutTime(self::RATE_ACTION_IP, $ipBucket),
            $rateLimiter->getRemainingLockoutTime(self::RATE_ACTION_USER, $userBucket)
        );
        return self::formatLockout($remaining);
    }

    private static function formatLockout(int $remaining): string
    {
        if ($remaining >= 60) {
            $minutes = (int)ceil($remaining / 60);
            return "Too many attempts. Please try again in {$minutes} minutes.";
        }

        return 'Too many attempts. Please try again shortly.';
    }

    private static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    private static function bucketValue(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '_', $value);

        return $value !== '' ? $value : 'unknown';
    }

    private static function envKey(string $username): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($username)), '_');
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return trim((string)$value);
    }

    private static function envInt(string $key, int $default): int
    {
        $value = self::env($key);
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }

        return max(60, (int)$value);
    }

    private static function boolValue(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
