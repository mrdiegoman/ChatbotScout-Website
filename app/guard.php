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
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header('Location: index.php');
        exit;
    }
}
