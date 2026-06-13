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
    if (!isset($_SESSION['user'])) {
        header('Location: login');
        exit;
    }

    if ($role === 'admin' && empty($_SESSION['user']['is_admin'])) {
        header('Location: ./');
        exit;
    }
}
