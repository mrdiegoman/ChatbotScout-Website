<?php
/**
 * Google OAuth - Initiator
 *
 * Presmeruje usera na Google prihlasovaci formular.
 * Volá sa po kliknutí na "Sign in/up with Google" tlačidlo.
 */

require '../inc/bootstrap.php';

// Ak je už prihlásený, presmeruj na index
if ($isLoggedIn) {
    header('Location: index.php');
    exit;
}

// Načítaj Google config
$googleConfig = $appConfig['google'];

// CSRF ochrana - náhodný state token
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Google OAuth URL parametre
$params = http_build_query([
    'client_id'     => $googleConfig['client_id'],
    'redirect_uri'  => $googleConfig['redirect_uri'],
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

// Presmeruj na Google
header('Location: https://accounts.google.com/o/oauth2/auth?' . $params);
exit;