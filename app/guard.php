<?php

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }

    if ($role === 'admin' && empty($_SESSION['user']['is_admin'])) {
        header('Location: index.php');
        exit;
    }
}