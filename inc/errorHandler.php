<?php

if (!defined('BOOTSTRAP_LOADED')) {
    http_response_code(403);
    exit;
}

// ============================================
// ERROR LOGGING
// ============================================

function sanitizeUrlForLog(string $url): string
{
    $sensitiveParams = ['token', 'email', 'key', 'secret', 'password', 'reset', 'verify'];

    $parts = parse_url($url);
    if (empty($parts['query'])) {
        return $url;
    }

    parse_str($parts['query'], $params);
    foreach ($params as $key => $value) {
        if (in_array(strtolower($key), $sensitiveParams, true)) {
            $params[$key] = '[FILTERED]';
        }
    }

    $filteredQuery = http_build_query($params);
    $base = ($parts['path'] ?? '');
    return $filteredQuery ? $base . '?' . $filteredQuery : $base;
}

function logError(string $type, string $message, string $file = '', int $line = 0): void
{
    $logDir  = __DIR__ . '/../logs';
    $logFile = $logDir . '/error.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $url       = sanitizeUrlForLog($_SERVER['REQUEST_URI'] ?? 'CLI');
    $ip        = class_exists('ClientIpResolver', false) ? ClientIpResolver::current() : ($_SERVER['REMOTE_ADDR'] ?? '-');
    $timestamp = date('Y-m-d H:i:s');
    $method    = $_SERVER['REQUEST_METHOD'] ?? '-';

    $entry = "[{$timestamp}] [{$type}] {$message}";
    if ($file) {
        $entry .= " | File: {$file}:{$line}";
    }
    $entry .= " | {$method} {$url} | IP: {$ip}" . PHP_EOL;

    error_log($entry, 3, $logFile);
}

// ============================================
// HELPERS
// ============================================

function isApiRequest(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/') || str_contains($uri, '/app/');
}

function redirectToErrorPage(int $code): void
{
    // Clean any started output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($code);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

    // If public/ appears in the path, we're in local dev (public/ is a subdir)
    // Strip everything from /public/ onward to get the base, then append /public/
    // Otherwise public/ is the docroot — error pages are at root
    if (preg_match('#^(.*)/public(?:/.*)?$#', $script, $m)) {
        $errorUrl = "{$scheme}://{$host}{$m[1]}/public/{$code}.php";
    } else {
        $errorUrl = "{$scheme}://{$host}/{$code}.php";
    }

    header("Location: {$errorUrl}");
    exit;
}

// ============================================
// PHP EXCEPTION HANDLER
// ============================================

set_exception_handler(function (Throwable $e) {
    logError(
        'EXCEPTION',
        get_class($e) . ': ' . $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );

    if (isApiRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
        exit;
    }

    redirectToErrorPage(500);
});

// ============================================
// FATAL ERROR HANDLER (shutdown function)
// ============================================

register_shutdown_function(function () {
    $error = error_get_last();

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error && in_array($error['type'], $fatalTypes, true)) {
        logError('FATAL', $error['message'], $error['file'], $error['line']);

        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
            exit;
        }

        redirectToErrorPage(500);
    }
});

// ============================================
// SECURITY: Turn off error display
// ============================================

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
