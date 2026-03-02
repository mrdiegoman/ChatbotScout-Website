<?php
/**
 * Google OAuth Callback (PLACEHOLDER)
 * 
 * Tento súbor bude spracovávať návrat z Google OAuth.
 * 
 * IMPLEMENTÁCIA NESKÔR:
 * 1. Získať Google Client ID a Client Secret
 * 2. Nainštalovať: composer require google/apiclient
 * 3. Implementovať OAuth flow
 */

require '../inc/bootstrap.php';

// TODO: Implementovať Google OAuth
// Pre teraz - redirect na login s chybovou hláškou

header('Location: login.php?error=google_oauth_not_implemented');
exit;

/*
PRÍKLAD IMPLEMENTÁCIE (po získaní credentials):

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('YOUR_CLIENT_ID');
$client->setClientSecret('YOUR_CLIENT_SECRET');
$client->setRedirectUri('http://localhost/mainwebsite/public/googleCallback.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);
    
    $oauth = new Google_Service_Oauth2($client);
    $userInfo = $oauth->userinfo->get();
    
    // Získaj Google user data
    $email = $userInfo->email;
    $firstName = $userInfo->givenName;
    $lastName = $userInfo->familyName;
    $profilePicture = $userInfo->picture;
    
    // Skontroluj či user existuje
    $user = new User();
    $existingUser = $user->getByEmail($email);
    
    if ($existingUser) {
        // Prihlás
        login($existingUser);
    } else {
        // Vytvor nového usera
        // ...
    }
    
    header('Location: index.php');
    exit;
}
*/
?>