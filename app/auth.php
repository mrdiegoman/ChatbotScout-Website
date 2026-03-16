<?php
/**
 * Authentication Helper Functions
 * 
 * Pomocné funkcie pre prácu so session
 */
if (!defined('BOOTSTRAP_LOADED')) {
    http_response_code(403);
    exit;
}

/**
 * Prihlásenie používateľa
 * 
 * @param array $userData User data z databázy
 * @param bool $rememberMe Remember me checkbox
 */
function login(array $userData, bool $rememberMe = false): void
{
    // Ochrana proti session fixation
    session_regenerate_id(true);
    
    // Ulož do PHP session
    $_SESSION['user'] = $userData;
    
    // Vytvor DB session a nastav cookie
    $sessionManager = new Session();
    $sessionData = $sessionManager->create($userData['id'], $rememberMe);
    
    // Načítaj config
    $appConfig = require __DIR__ . '/../config/app.php';
    
    // Nastav cookie lifetime
    $cookieLifetime = $rememberMe 
        ? time() + $appConfig['session']['remember_me_lifetime']
        : 0; // Session cookie (vymaže sa po zatvorení browsera)
    
    setcookie(
        $appConfig['session']['cookie_name'],
        $sessionData['token'],
        $cookieLifetime,
        $appConfig['session']['cookie_path'],
        '',
        $appConfig['session']['cookie_secure'],
        $appConfig['session']['cookie_httponly']
    );
    
    // GDPR: Konverzia visitor → user je povolená len s explicitným súhlasom.
    // Ak user dal declined alebo ešte nerozhodol, visitor_user_links sa nevytvorí.
    $cookieConsent = $_COOKIE['cookie_consent'] ?? null;
    if ($cookieConsent === 'accepted' && isset($_COOKIE[$appConfig['visitor']['cookie_name']])) {
        $visitorId = $_COOKIE[$appConfig['visitor']['cookie_name']];
        
        $visitor = new Visitor();
        $visitor->convertToUser($visitorId, $userData['id']);
    }
}

/**
 * Odhlásenie používateľa
 */
function logout(): void
{
    // Načítaj config
    $appConfig = require __DIR__ . '/../config/app.php';
    
    // Vymaž DB session
    if (isset($_COOKIE[$appConfig['session']['cookie_name']])) {
        $sessionToken = $_COOKIE[$appConfig['session']['cookie_name']];
        
        $sessionManager = new Session();
        $sessionManager->destroy($sessionToken);
        
        // Vymaž cookie
        setcookie($appConfig['session']['cookie_name'], '', time() - 3600, '/');
    }
    
    // Vyčisti PHP session
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Kontrola či je používateľ prihlásený
 * 
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

/**
 * Získanie aktuálneho používateľa
 * 
 * @return array|null User data alebo NULL ak nie je prihlásený
 */
function getCurrentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}