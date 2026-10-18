<?php

class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function isConfigured(): bool
    {
        return self::env('CLOUDFLARE_TURNSTILE_SITE_KEY') !== ''
            && self::env('CLOUDFLARE_TURNSTILE_SECRET_KEY') !== '';
    }

    public static function siteKey(): string
    {
        return self::env('CLOUDFLARE_TURNSTILE_SITE_KEY');
    }

    public static function verify(string $token, ?string $expectedAction = null): bool
    {
        if (!self::isConfigured()) {
            return true;
        }

        if ($token === '' || strlen($token) > 2048 || !function_exists('curl_init')) {
            return false;
        }

        $payload = [
            'secret' => self::env('CLOUDFLARE_TURNSTILE_SECRET_KEY'),
            'response' => $token,
            'remoteip' => ClientIpResolver::currentOrNull(),
            'idempotency_key' => generateUUID(),
        ];

        $curl = curl_init(self::VERIFY_URL);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array_filter($payload, static fn ($value) => $value !== null)),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($response) || $status !== 200) {
            return false;
        }

        $result = json_decode($response, true);
        if (!is_array($result) || empty($result['success'])) {
            return false;
        }

        return $expectedAction === null
            || !isset($result['action'])
            || hash_equals($expectedAction, (string)$result['action']);
    }

    private static function env(string $key): string
    {
        return trim((string)($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: ''));
    }
}
