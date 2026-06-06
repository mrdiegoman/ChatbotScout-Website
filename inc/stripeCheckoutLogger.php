<?php

function stripeCheckoutLog(string $event, array $context = []): void {
    $logFile = __DIR__ . '/../logs/stripe_checkout.log';
    $requestId = stripeCheckoutRequestId();

    $entry = [
        'time' => date('c'),
        'request_id' => $requestId,
        'event' => $event,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'context' => stripeCheckoutSanitizeLogContext($context),
    ];

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($line === false) {
        $line = json_encode([
            'time' => date('c'),
            'request_id' => $requestId,
            'event' => 'logger_encode_failed',
        ]);
    }

    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function stripeCheckoutRequestId(): string {
    static $requestId = null;

    if ($requestId !== null) {
        return $requestId;
    }

    try {
        $requestId = bin2hex(random_bytes(8));
    } catch (Exception $e) {
        $requestId = uniqid('req_', true);
    }

    return $requestId;
}

function stripeCheckoutExceptionContext(Throwable $e): array {
    return [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ];
}

function stripeCheckoutSanitizeLogContext(array $context): array {
    $safe = [];

    foreach ($context as $key => $value) {
        $key = (string) $key;
        if (preg_match('/secret|password|token|client_secret|card|email/i', $key)) {
            $safe[$key] = '[redacted]';
            continue;
        }

        if (is_array($value)) {
            $safe[$key] = stripeCheckoutSanitizeLogContext($value);
            continue;
        }

        if (is_object($value)) {
            $safe[$key] = method_exists($value, '__toString') ? (string) $value : get_class($value);
            continue;
        }

        $safe[$key] = $value;
    }

    return $safe;
}
