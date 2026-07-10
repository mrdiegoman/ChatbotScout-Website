<?php

/**
 * Resolves the real client IP without trusting spoofable proxy headers by default.
 */
class ClientIpResolver
{
    private const DEFAULT_TRUSTED_PROXY_CIDRS = [
        '127.0.0.1/32',
        '::1/128',
    ];

    public static function current(): string
    {
        return self::fromServer($_SERVER);
    }

    public static function currentOrNull(): ?string
    {
        $ip = self::current();
        return $ip === 'unknown' ? null : $ip;
    }

    public static function fromServer(array $server): string
    {
        $remoteAddr = self::validIp($server['REMOTE_ADDR'] ?? '');
        if ($remoteAddr === null) {
            return 'unknown';
        }

        if (self::isTrustedProxy($remoteAddr)) {
            $cloudflareIp = self::validIp($server['HTTP_CF_CONNECTING_IP'] ?? '');
            if ($cloudflareIp !== null) {
                return $cloudflareIp;
            }

            $forwardedIp = self::firstForwardedIp($server['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($forwardedIp !== null) {
                return $forwardedIp;
            }

            $realIp = self::validIp($server['HTTP_X_REAL_IP'] ?? '');
            if ($realIp !== null) {
                return $realIp;
            }
        }

        return $remoteAddr;
    }

    public static function fromServerOrNull(array $server): ?string
    {
        $ip = self::fromServer($server);
        return $ip === 'unknown' ? null : $ip;
    }

    public static function isTrustedProxyRequest(?array $server = null): bool
    {
        $remoteAddr = self::validIp(($server ?? $_SERVER)['REMOTE_ADDR'] ?? '');
        return $remoteAddr !== null && self::isTrustedProxy($remoteAddr);
    }

    private static function firstForwardedIp(mixed $header): ?string
    {
        if (!is_string($header) || trim($header) === '') {
            return null;
        }

        foreach (explode(',', $header) as $candidate) {
            $ip = self::validIp($candidate);
            if ($ip !== null) {
                return $ip;
            }
        }

        return null;
    }

    private static function validIp(mixed $candidate): ?string
    {
        if (!is_string($candidate) && !is_numeric($candidate)) {
            return null;
        }

        $candidate = trim((string)$candidate);
        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) {
            return null;
        }

        return substr($candidate, 0, 45);
    }

    private static function isTrustedProxy(string $ip): bool
    {
        foreach (self::trustedProxyCidrs() as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function trustedProxyCidrs(): array
    {
        $envValue = $_ENV['TRUSTED_PROXY_CIDRS'] ?? getenv('TRUSTED_PROXY_CIDRS');
        $configuredCidrs = is_string($envValue) ? explode(',', $envValue) : [];

        if ($configuredCidrs === []) {
            $configuredCidrs = self::DEFAULT_TRUSTED_PROXY_CIDRS;
        }

        $cidrs = [];
        foreach ($configuredCidrs as $cidr) {
            $cidr = trim((string)$cidr);
            if ($cidr !== '') {
                $cidrs[] = $cidr;
            }
        }

        return $cidrs !== [] ? $cidrs : self::DEFAULT_TRUSTED_PROXY_CIDRS;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return false;
        }

        [$range, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $range = trim($range);
        $rangeBinary = @inet_pton($range);

        if ($rangeBinary === false || strlen($rangeBinary) !== strlen($ipBinary)) {
            return false;
        }

        $maxBits = strlen($ipBinary) * 8;
        $bits = $bits === null ? $maxBits : (int)$bits;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($rangeBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($rangeBinary[$fullBytes]) & $mask);
    }
}
