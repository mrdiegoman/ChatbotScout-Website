<?php
require '../inc/bootstrap.php';
require 'app/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

validateCsrfToken();

// Audit log pred odhlásením (kým ešte máme user v session)
if ($isLoggedIn && isset($user['id'])) {
    AuditLog::log('user', $user['id'], 'logout');
}

// Odhlás používateľa
logout();

// Redirect na homepage
header('Location: ./');
exit;
