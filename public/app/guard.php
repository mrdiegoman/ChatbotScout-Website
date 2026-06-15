<?php

if (!defined('BOOTSTRAP_LOADED')) {
    http_response_code(403);
    exit;
}

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: login');
        exit;
    }
}

function requireRole(string $role): void
{
    if ($role === 'admin') {
        requireAdminLogin();
        return;
    }

    if (!isset($_SESSION['user'])) {
        header('Location: login');
        exit;
    }
}

function requireAdminLogin(): void
{
    $adminUser = AdminAuth::current();

    if ($adminUser) {
        $GLOBALS['adminUser'] = $adminUser;
        $GLOBALS['user'] = AdminAuth::asLegacyUser();
        $GLOBALS['userRole'] = 'admin';
        return;
    }

    if (expectsJsonResponse()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }

    renderPublicNotFound();
}

function expectsJsonResponse(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    return str_contains($accept, 'application/json')
        || str_contains($script, '/api/')
        || str_contains($script, '/app/');
}

function renderPublicNotFound(): void
{
    global $isLoggedIn, $user, $userRole, $appConfig, $visitorId, $cspNonce;

    require __DIR__ . '/../404.php';
    exit;
}
