<?php
/**
 * User Class
 * 
 * Správa používateľov: registrácia, prihlásenie, verifikácia emailu, reset hesla
 */

class User
{
    private PDO $db;
    private array $appConfig;
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->appConfig = require __DIR__ . '/../config/app.php';
    }
    
    /**
     * Registrácia nového usera
     * 
     * @param string $email
     * @param string $password
     * @param string $firstName
     * @param string $lastName
     * @param bool $marketingEmails
     * @return array User data
     * @throws Exception
     */
    public function register(
        string $email, 
        string $password, 
        string $firstName, 
        string $lastName,
        bool $termsAccepted = false,
        bool $marketingEmails = false
    ): array {
        // ✅ Rate limiting check
        $rateLimiter = new RateLimiter();
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $errors = [];

        if (!$rateLimiter->check('registration', $clientIp)) {
            $remaining = $rateLimiter->getRemainingLockoutTime('registration', $clientIp);
            
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many registration attempts. Please try again in {$minutes} minutes.");
            }
            else {
                throw new Exception("Too many registration attempts. Please try again in {$remaining} seconds.");
            }
        }
        
        // Validácia emailu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = "Please enter a valid email address.";
            // throw new Exception("Please enter a valid email address.");
        }
        
        // Validácia hesla
        if (strlen($password) < 8) {
            $errors['password'][] = "Password must be at least 8 characters long.";
            // throw new Exception("Password must be at least 8 characters long.");
        }
        
        // Validácia mena (min/max dĺžka)
        if (empty($firstName)) {
            $errors['first_name'][] = "First name is required.";
            // throw new Exception("First and last name must be at least 2 characters long.");
        }
        
        if (empty($lastName)) {
            $errors['last_name'][] = "Last name is required.";
            // throw new Exception("First and last name must be at least 2 characters long.");
        }

        if (strlen($firstName) > 50) {
            $errors['first_name'][] = "First name is too long (maximum 50 characters).";
        }
        
        if (strlen($lastName) > 50) {
            $errors['last_name'][] = "Last name is too long (maximum 50 characters).";
        }
        
        if (!$termsAccepted) {
            $errors['terms'][] = "This field is required.";
        }

        // Kontrola či email už existuje
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetch()) {
            // ✅ Record failed attempt (skutočný problém - email exists)
            $remaining = $rateLimiter->recordFailedAttempt('registration', $clientIp);
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many registration attempts. Please try again in {$minutes} minutes.");
            }
            elseif ($remaining > 0) {
                throw new Exception("Too many registration attempts. Please try again in {$remaining} seconds.");
            }
            throw new Exception("This email address is already registered.");
        }

        if (!empty($errors)) {
            // ✅ Record failed attempt (skutočný problém - validačné chyby)
            $remaining = $rateLimiter->recordFailedAttempt('registration', $clientIp);
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many registration attempts. Please try again in {$minutes} minutes.");
            }
            elseif ($remaining > 0) {
                throw new Exception("Too many registration attempts. Please try again in {$remaining} seconds.");
            }
            return ['errors' => $errors];
        }
        
        // Hash password (Argon2id - najlepší v PHP 8)
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        
        // Generate UUID pre user
        $userId = $this->generateUuid();
        
        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', time() + $this->appConfig['email_verification']['token_lifetime']);
        
        // Kapitalizuj mená
        $firstName = mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8');
        $lastName = mb_convert_case($lastName, MB_CASE_TITLE, 'UTF-8');
        
        // Insert do databázy
        $stmt = $this->db->prepare("
            INSERT INTO users (
                id, 
                first_name, 
                last_name, 
                email, 
                password_hash,
                email_verification_token,
                email_verification_expires_at,
                marketing_emails,
                status,
                created_at
            ) VALUES (
                :id,
                :first_name,
                :last_name,
                :email,
                :password_hash,
                :token,
                :token_expires,
                :marketing_emails,
                'pending_verification',
                NOW()
            )
        ");
        
        $stmt->execute([
            'id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'token' => $verificationToken,
            'token_expires' => $tokenExpires,
            'marketing_emails' => $marketingEmails ? 1 : 0,
        ]);
        
        // Odoslanie verification emailu
        $emailService = new Email();
        $fullName = $firstName . ' ' . $lastName;
        $emailService->sendVerification($email, $fullName, $verificationToken);
        
        // ✅ Reset rate limiter (úspešná registrácia)
        // $rateLimiter->reset('registration', $clientIp, $email);
        
        // Zaloguj úspešnú registráciu
        // error_log("✅ New user registered: $email");

        // Audit log
        AuditLog::log('user', $userId, 'created',
            null,
            ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName, 'status' => 'pending_verification'],
            $userId
        );


        
        // Vráť user data (bez password hashu!)
        return [
            // 'id' => $userId,
            // 'first_name' => $firstName,
            // 'last_name' => $lastName,
            'email' => $email,
            // 'is_email_verified' => false,
            // 'status' => 'pending_verification'

            // 'errors' => $errors
        ];
    }
    
    /**
     * Prihlásenie používateľa
     * 
     * @param string $email
     * @param string $password
     * @return array User data
     * @throws Exception
     */
    public function login(string $email, string $password): array
    {
        // ✅ Rate limiting check
        $stmt = $this->db->prepare("
            SELECT id, email, password_hash, is_email_verified, email_verification_expires_at, status, first_name, last_name, profile_picture_url, is_admin
            FROM users
            WHERE email = :email
        ");

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $rateLimiter = new RateLimiter();
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $errors = [];

        // 🆕 ZMENA: check() dostáva aj $email - kontroluje IP aj email blokáciu
        if (!$rateLimiter->check('login', $clientIp, $email)) {
            // 🆕 ZMENA: getRemainingLockoutTime() dostáva aj $email
            $remaining = $rateLimiter->getRemainingLockoutTime('login', $clientIp, $email);
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many login attempts. Please try again in {$minutes} minutes.");
            }
            elseif ($remaining > 0) {
                throw new Exception("Too many login attempts. Please try again in {$remaining} seconds.");
            }
        }

        // Validácia emailu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = "Please enter a valid email address.";
        }

        if (empty($password)) {
            $errors['password'][] = "Password is required.";
        }

        if (!empty($errors)) {
            // 🆕 ZMENA: recordFailedAttempt() dostáva aj $email
            $remaining = $rateLimiter->recordFailedAttempt('login', $clientIp, $email);
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many login attempts. Please try again in {$minutes} minutes.");
            }
            elseif ($remaining > 0) {
                throw new Exception("Too many login attempts. Please try again in {$remaining} seconds.");
            }
            return ['errors' => $errors];
        }

        $stmt = $this->db->prepare("
            SELECT id, email, password_hash, is_email_verified, email_verification_expires_at, status, first_name, last_name, profile_picture_url
            FROM users
            WHERE email = :email
        ");

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            // 🆕 ZMENA: recordFailedAttempt() dostáva aj $email
            $remaining = $rateLimiter->recordFailedAttempt('login', $clientIp, $email);
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many login attempts. Please try again in {$minutes} minutes.");
            }
            elseif ($remaining > 0) {
                throw new Exception("Too many login attempts. Please try again in {$remaining} seconds.");
            }
            throw new Exception("Invalid email or password.");
        }
        
        // Overenie hesla
        if (!password_verify($password, $user['password_hash'])) {
            // 🆕 ZMENA: recordFailedAttempt() dostáva aj $email
            $remaining = $rateLimiter->recordFailedAttempt('login', $clientIp, $email);
            if ($remaining >= 60) {
                $minutes = ceil($remaining / 60);
                throw new Exception("Too many login attempts. Please try again in {$minutes} minutes.");
            }
            elseif ($remaining > 0) {
                throw new Exception("Too many login attempts. Please try again in {$remaining} seconds.");
            }
            throw new Exception("Invalid email or password.");
        }

        // Hard verification - user NEMÔŽE byť prihlásený bez verifikácie
        $rateLimiter = new RateLimiter();
        if (!$user['is_email_verified']) {
            if ($rateLimiter->check('resend_verification', $clientIp, $email)) {
                if (!$this->checkEmailVerify($user['email_verification_expires_at'])){
                    $verificationToken = bin2hex(random_bytes(32));
                    $tokenExpires = date('Y-m-d H:i:s', time() + $this->appConfig['email_verification']['token_lifetime']);

                    $updateStmt = $this->db->prepare("
                        UPDATE users 
                        SET email_verification_token = :token,
                            email_verification_expires_at = :token_expires
                        WHERE id = :id
                    ");
                    $updateStmt->execute([
                        'token' => $verificationToken,
                        'token_expires' => $tokenExpires,
                        'id' => $user['id']
                    ]);

                    $emailService = new Email();
                    $fullName = $user['first_name'] . ' ' . $user['last_name'];
                    $emailService->sendVerification($email, $fullName, $verificationToken);
                    
                    $rateLimiter->recordFailedAttempt('resend_verification', $clientIp, $email);
                    header('Location: verificationSent.php?email=' . urlencode($email));
                }
                header('Location: verificationSent.php?email=' . urlencode($email));
                exit;
            }
            else{
                $remaining = $rateLimiter->getRemainingLockoutTime('resend_verification', $clientIp);
                if ($remaining >= 60) {
                    $minutes = ceil($remaining / 60);
                    throw new Exception("Too many email verification attempts. Please try again in {$minutes} minutes.");
                }
                elseif ($remaining > 0) {
                    throw new Exception("Too many email verification attempts. Please try again in {$remaining} seconds.");
                }
            }
        }
        
        // Kontrola statusu účtu
        if ($user['status'] === 'suspended') {
            throw new Exception("Your account has been suspended. Please contact support.");
        }
        
        if ($user['status'] === 'disabled') {
            throw new Exception("Your account has been disabled.");
        }
        
        // ✅ Úspešný login - reset rate limiter
        $rateLimiter->resetByEmail('login', $email);
        
       // Update last login
        $updateStmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $user['id']]);

        // Audit log
        AuditLog::log('user', $user['id'], 'login', null, null, $user['id']);

        // Vráť user data (bez password_hash)
        unset($user['password_hash']);
        $user['is_admin'] = (bool)($user['is_admin'] ?? false);
        return $user;
    }
    
    private function checkEmailVerify(string $emailVerificationExpiresAt): bool
    {
        // Kontrola expirácie false=> token expiroval, true=> token stále platný
        if (strtotime($emailVerificationExpiresAt) < time()) {
            return false;
        }
        return true;
    }

    /**
     * Verifikácia emailu cez token z emailu
     * 
     * @param string $token
     * @return bool
     * @throws Exception
     */
    public function verifyEmail(string $token): bool
    {
        $stmt = $this->db->prepare("
            SELECT id, email_verification_expires_at
            FROM users
            WHERE email_verification_token = :token
            AND is_email_verified = 0
        ");
        
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Invalid or already used verification token.");
        }
        
        // Kontrola expirácie
        if (strtotime($user['email_verification_expires_at']) < time()) {
            throw new Exception("Verification token has expired. Please request a new one.");
        }
        
        // Aktivuj účet
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET is_email_verified = 1,
                email_verified_at = NOW(),
                status = 'active',
                email_verification_token = NULL,
                email_verification_expires_at = NULL
            WHERE id = :id
        ");
        
        $updateStmt->execute(['id' => $user['id']]);
        
        return true;
    }
    
    /**
     * Odoslanie password reset emailu
     * 
     * @param string $email
     * @return bool
     */
    public function sendPasswordReset(string $email): bool
    {
        // ✅ Rate limiting (voláme v profileActions.php)
        
        $stmt = $this->db->prepare("SELECT id, email, first_name, last_name FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        // Bezpečnostný pattern: vždy vráť success, aj keď email neexistuje
        // (zabráni zisťovaniu, ktoré emaily sú registrované)
        if (!$user) {
            return true;
        }
        
        // Generuj reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', time() + $this->appConfig['password_reset']['token_lifetime']);
        
        // Ulož do DB
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET password_reset_token = :token,
                password_reset_expires_at = :expires
            WHERE id = :id
        ");
        
        $updateStmt->execute([
            'token' => $resetToken,
            'expires' => $resetExpires,
            'id' => $user['id'],
        ]);
        
        // Odošli email
        $emailService = new Email();
        $fullName = $user['first_name'] . ' ' . $user['last_name'];
        $emailService->sendPasswordReset($user['email'], $fullName, $resetToken);
        
        return true;
    }
    
    /**
     * Reset hesla cez token z emailu
     * 
     * @param string $token
     * @param string $newPassword
     * @return bool
     * @throws Exception
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }
        
        $stmt = $this->db->prepare("
            SELECT id, password_reset_expires_at
            FROM users
            WHERE password_reset_token = :token
        ");
        
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Invalid or expired reset token.");
        }
        
        // Kontrola expirácie
        if (strtotime($user['password_reset_expires_at']) < time()) {
            throw new Exception("Reset token has expired. Please request a new one.");
        }
        
        // Hash nového hesla
        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        // Update hesla a vymaž token
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :password_hash,
                password_reset_token = NULL,
                password_reset_expires_at = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $updateStmt->execute([
            'password_hash' => $passwordHash,
            'id' => $user['id'],
        ]);
        
        return true;
    }
    
    /**
     * Získanie používateľa podľa ID
     */
    public function getById(string $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, username, first_name, last_name, 
                   profile_picture_url, bio, website_url, 
                   is_email_verified, status
            FROM users
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Získanie používateľa podľa emailu
     */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, username, first_name, last_name, 
                   profile_picture_url, is_email_verified, status
            FROM users
            WHERE email = :email
        ");
        
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Kontrola či email už existuje
     */
    private function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Generovanie UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}