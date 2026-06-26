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
    header('Location: ./');
    exit;
}

// Načítaj Google config
$googleConfig = $appConfig['google'];

if (
    empty($googleConfig['client_id'])
    || empty($googleConfig['client_secret'])
    || empty($googleConfig['redirect_uri'])
    || !filter_var($googleConfig['redirect_uri'], FILTER_VALIDATE_URL)
) {
    error_log('Google OAuth is not configured correctly.');
    $_SESSION['login_errors'] = ['general' => 'Google sign-in is temporarily unavailable. Please use email and password.'];
    header('Location: login');
    exit;
}

// CSRF ochrana - náhodný state token
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_redirect_uri'] = $googleConfig['redirect_uri'];

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
