<?php
/**
 * Google OAuth - Callback
 *
 * Spracuje návrat z Google po prihlásení.
 * Flow:
 *  1. Over state (CSRF ochrana)
 *  2. Vymeň code za access token
 *  3. Získaj user info z Google
 *  4. Ak email existuje → prepoj google_id + prihlás
 *  5. Ak email neexistuje → vytvor účet + prihlás
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

// Ak je už prihlásený, presmeruj na index
if ($isLoggedIn) {
    header('Location: index.php');
    exit;
}

// ============================================
// 1. KONTROLA CHÝB Z GOOGLE
// ============================================

if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    error_log("Google OAuth error: $error");
    $_SESSION['login_errors'] = ['general' => 'Google sign-in was cancelled or failed. Please try again.'];
    header('Location: login.php');
    exit;
}

// ============================================
// 2. CSRF - KONTROLA STATE TOKENU
// ============================================

$receivedState = $_GET['state'] ?? '';
$expectedState = $_SESSION['google_oauth_state'] ?? '';

// Vymaž state zo session (single-use)
unset($_SESSION['google_oauth_state']);

if (empty($receivedState) || !hash_equals($expectedState, $receivedState)) {
    error_log("Google OAuth: Invalid state token");
    $_SESSION['login_errors'] = ['general' => 'Invalid request. Please try again.'];
    header('Location: login.php');
    exit;
}

// ============================================
// 3. ZÍSKAJ CODE
// ============================================

$code = $_GET['code'] ?? '';

if (empty($code)) {
    $_SESSION['login_errors'] = ['general' => 'No authorization code received from Google.'];
    header('Location: login.php');
    exit;
}

// ============================================
// 4. VYMEŇ CODE ZA ACCESS TOKEN
// ============================================

$googleConfig = $appConfig['google'];

$tokenResponse = httpPost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $googleConfig['client_id'],
    'client_secret' => $googleConfig['client_secret'],
    'redirect_uri'  => $googleConfig['redirect_uri'],
    'grant_type'    => 'authorization_code',
]);

if (!$tokenResponse || isset($tokenResponse['error'])) {
    $errorDesc = $tokenResponse['error_description'] ?? 'Unknown error';
    error_log("Google OAuth token exchange failed: $errorDesc");
    $_SESSION['login_errors'] = ['general' => 'Failed to authenticate with Google. Please try again.'];
    header('Location: login.php');
    exit;
}

$accessToken = $tokenResponse['access_token'] ?? '';

if (empty($accessToken)) {
    error_log("Google OAuth: No access token received");
    $_SESSION['login_errors'] = ['general' => 'Failed to get access token from Google.'];
    header('Location: login.php');
    exit;
}

// ============================================
// 5. ZÍSKAJ USER INFO Z GOOGLE
// ============================================

$userInfo = httpGet('https://www.googleapis.com/oauth2/v2/userinfo', $accessToken);

if (!$userInfo || empty($userInfo['email'])) {
    error_log("Google OAuth: Failed to get user info");
    $_SESSION['login_errors'] = ['general' => 'Failed to get user information from Google.'];
    header('Location: login.php');
    exit;
}

$googleId    = $userInfo['id']             ?? '';
$email       = $userInfo['email']          ?? '';
$picture     = $userInfo['picture']        ?? null;
$isVerified  = $userInfo['verified_email'] ?? false;

// Parsovanie mena - Google niekedy dá celé meno iba do given_name
$firstName = trim($userInfo['given_name'] ?? '');
$lastName  = trim($userInfo['family_name'] ?? '');

// Ak family_name je prázdne → skús rozdeliť given_name alebo name
if (empty($lastName)) {
    $fullName = trim($userInfo['name'] ?? $firstName);
    $parts    = explode(' ', $fullName, 2);
    $firstName = $parts[0] ?? $firstName;
    $lastName  = $parts[1] ?? '';
}

// ============================================
// 6. SPRACUJ USERA V DATABÁZE
// ============================================

try {
    $db = Database::getInstance()->getConnection();

    // Skontroluj či user s týmto emailom existuje
    $stmt = $db->prepare("
        SELECT id, email, first_name, last_name, status, google_id,
               is_email_verified, profile_picture_url, is_admin, marketing_emails
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // ============================================
        // USER UŽ EXISTUJE → prepoj google_id + prihlás
        // ============================================

        // Kontrola statusu účtu
        if ($existingUser['status'] === 'suspended') {
            $_SESSION['login_errors'] = ['general' => 'Your account has been suspended. Please contact support.'];
            header('Location: login.php');
            exit;
        }

        if ($existingUser['status'] === 'disabled') {
            $_SESSION['login_errors'] = ['general' => 'This account has been disabled.'];
            header('Location: login.php');
            exit;
        }

        // Aktualizuj google_id ak ešte nie je prepojený
        $updateFields = ['last_login_at = NOW()', 'updated_at = NOW()'];
        $updateParams = ['id' => $existingUser['id']];

        if (empty($existingUser['google_id'])) {
            $updateFields[] = 'google_id = :google_id';
            $updateParams['google_id'] = $googleId;
        }

        // Ak nemal verified email, teraz ho overíme cez Google
        if (!$existingUser['is_email_verified']) {
            $updateFields[] = 'is_email_verified = 1';
            $updateFields[] = "status = 'active'";
            $updateFields[] = 'email_verified_at = NOW()';
            $updateFields[] = 'email_verification_token = NULL';
            $updateFields[] = 'email_verification_expires_at = NULL';
        }

        // Ak nemá profilovú fotku a Google ju poskytol, nastav ju
        if (empty($existingUser['profile_picture_url']) && !empty($picture)) {
            $updateFields[] = 'profile_picture_url = :picture';
            $updateParams['picture'] = $picture;
        }

        $updateStmt = $db->prepare("
            UPDATE users SET " . implode(', ', $updateFields) . "
            WHERE id = :id
        ");
        $updateStmt->execute($updateParams);

        // Priprav user data pre login()
        $userData = [
            'id'                  => $existingUser['id'],
            'email'               => $existingUser['email'],
            'first_name'          => $existingUser['first_name'],
            'last_name'           => $existingUser['last_name'],
            'profile_picture_url' => $existingUser['profile_picture_url'] ?: $picture,
            'is_email_verified'   => true,
            'is_admin'            => (bool)$existingUser['is_admin'],
            'status'              => 'active',
            'marketing_emails'    => (bool)$existingUser['marketing_emails'],
        ];

    } else {
        // ============================================
        // NOVÝ USER → vytvor účet automaticky
        // ============================================

        $userId = generateUuid();

        // Kapitalizuj mená
        $firstName = mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8');
        $lastName  = mb_convert_case($lastName, MB_CASE_TITLE, 'UTF-8');

        // Náhodný neuhádnuteľný password hash (Google users nemajú heslo)
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);

        $insertStmt = $db->prepare("
            INSERT INTO users (
                id,
                email,
                password_hash,
                google_id,
                first_name,
                last_name,
                profile_picture_url,
                is_email_verified,
                email_verified_at,
                status,
                terms_accepted,
                terms_accepted_at,
                marketing_emails,
                created_at,
                updated_at,
                last_login_at
            ) VALUES (
                :id,
                :email,
                :password_hash,
                :google_id,
                :first_name,
                :last_name,
                :picture,
                1,
                NOW(),
                'active',
                1,
                NOW(),
                0,
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $insertStmt->execute([
            'id'           => $userId,
            'email'        => $email,
            'password_hash'=> $passwordHash,
            'google_id'    => $googleId,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'picture'      => $picture,
        ]);

        $userData = [
            'id'                  => $userId,
            'email'               => $email,
            'first_name'          => $firstName,
            'last_name'           => $lastName,
            'profile_picture_url' => $picture,
            'is_email_verified'   => true,
            'is_admin'            => false,
            'status'              => 'active',
            'marketing_emails'    => false,
        ];

        error_log("✅ New user registered via Google: $email");
    }

    // ============================================
    // 7. PRIHLÁS USERA
    // ============================================

    login($userData, false);

    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log("Google OAuth DB error: " . $e->getMessage());
    $_SESSION['login_errors'] = ['general' => 'A database error occurred. Please try again.'];
    header('Location: login.php');
    exit;
} catch (Exception $e) {
    error_log("Google OAuth error: " . $e->getMessage());
    $_SESSION['login_errors'] = ['general' => 'An unexpected error occurred. Please try again.'];
    header('Location: login.php');
    exit;
}

// ============================================
// HELPER FUNKCIE
// ============================================

/**
 * HTTP POST request (na získanie tokenu z Google)
 */
function httpPost(string $url, array $data): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) {
        error_log("cURL POST error: $error");
        return null;
    }

    return json_decode($response, true);
}

/**
 * HTTP GET request (na získanie user info z Google)
 */
function httpGet(string $url, string $accessToken): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) {
        error_log("cURL GET error: $error");
        return null;
    }

    return json_decode($response, true);
}

/**
 * UUID v4 generátor
 */
function generateUuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}