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

    /**
     * Vráti dummy bcrypt hash pre timing equalization.
     * Zabraňuje zisteniu existencie emailu meraním času odpovede.
     * Static premenná sa inicializuje raz za PHP-FPM worker proces.
     */
    private static function dummyHash(): string {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash('__timing_dummy__', PASSWORD_BCRYPT);
        }
        return $hash;
    }
    
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
        $clientIp = ClientIpResolver::current();
        
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
        
        // Validácia hesla — vždy na serveri, aby sa nedala obísť cez vypnutý JavaScript.
        foreach (PasswordPolicy::validate($password) as $passwordError) {
            $errors['password'][] = $passwordError;
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
        
        // The initial verification email is not a resend, so it must not start the resend cooldown.
        $stmt = $this->db->prepare("
            INSERT INTO users (
                id,
                first_name,
                last_name,
                email,
                password_hash,
                email_verification_token,
                email_verification_expires_at,
                verification_email_sent_at,
                terms_accepted,
                marketing_emails,
                status,
                created_at,
                unsubscribe_token
            ) VALUES (
                :id,
                :first_name,
                :last_name,
                :email,
                :password_hash,
                :token,
                :token_expires,
                NULL,
                :terms_accepted,
                :marketing_emails,
                'pending_verification',
                NOW(),
                :unsubscribe_token
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
            'terms_accepted' => $termsAccepted ? 1 : 0,
            'marketing_emails' => $marketingEmails ? 1 : 0,
            'unsubscribe_token' => bin2hex(random_bytes(32)),
        ]);
        
        // Odoslanie verification emailu
        $emailService = new Email();
        $fullName = $firstName . ' ' . $lastName;
        $emailService->sendVerification($email, $fullName, $verificationToken);
        
        // Count successful registrations too; otherwise only invalid signups are limited.
        $rateLimiter->recordAttempt('registration', $clientIp);
        
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
            SELECT id, email, password_hash, is_email_verified, email_verification_expires_at, status, first_name, last_name, profile_picture_url
            FROM users
            WHERE email = :email
        ");

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $rateLimiter = new RateLimiter();
        $clientIp = ClientIpResolver::current();
        
        $errors = [];

        if (!$rateLimiter->check('login', $clientIp, $email)) {
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
            // Timing equalization — bcrypt trvá rovnako dlho aj pre neexistujúci email
            password_verify($password, self::dummyHash());
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
        if (!$user['is_email_verified']) {
            if (!$this->checkEmailVerify($user['email_verification_expires_at'] ?? null)) {
                $this->resendVerificationEmail($email, $clientIp);
            }

            return [
                'requires_email_verification' => true,
                'email' => $email,
            ];
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
        return $user;
    }
    
    private function checkEmailVerify(?string $emailVerificationExpiresAt): bool
    {
        // Kontrola expirácie false=> token expiroval, true=> token stále platný
        if (!$emailVerificationExpiresAt || strtotime($emailVerificationExpiresAt) < time()) {
            return false;
        }
        return true;
    }

    /**
     * Return the authoritative server-side resend state for one email address.
     */
    public function getVerificationResendState(string $email, string $clientIp): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'exists' => false,
                'verified' => false,
                'remainingSeconds' => 0,
                'canResend' => false,
            ];
        }

        $cooldown = max(1, (int)($this->appConfig['email_verification']['resend_cooldown'] ?? 60));
        $stmt = $this->db->prepare("
            SELECT
                is_email_verified,
                CASE
                    WHEN verification_email_sent_at IS NULL THEN 0
                    ELSE GREATEST(0, :cooldown - TIMESTAMPDIFF(SECOND, verification_email_sent_at, NOW()))
                END AS cooldown_remaining
            FROM users
            WHERE email = :email
        ");
        $stmt->execute([
            'cooldown' => $cooldown,
            'email' => $email,
        ]);
        $user = $stmt->fetch();

        if (!$user) {
            return [
                'exists' => false,
                'verified' => false,
                'remainingSeconds' => 0,
                'canResend' => false,
            ];
        }

        $rateLimiter = new RateLimiter();
        $rateLimitRemaining = $rateLimiter->getRemainingLockoutTime('resend_verification', $clientIp, $email);
        $sessionKey = 'last_verification_sent_' . hash('sha256', $email);
        $sessionLastSent = (int)($_SESSION[$sessionKey] ?? 0);
        $sessionRemaining = $sessionLastSent > 0
            ? max(0, $cooldown - (time() - $sessionLastSent))
            : 0;
        $remaining = max((int)$user['cooldown_remaining'], $rateLimitRemaining, $sessionRemaining);
        $verified = (bool)$user['is_email_verified'];

        return [
            'exists' => true,
            'verified' => $verified,
            'remainingSeconds' => $remaining,
            'canResend' => !$verified && $remaining === 0,
        ];
    }

    /**
     * Resend a verification email with database-backed cooldown and rate limiting.
     */
    public function resendVerificationEmail(string $email, string $clientIp): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'The verification request is no longer available. Please sign in again.',
                'remainingSeconds' => 0,
            ];
        }

        $rateLimiter = new RateLimiter();
        if (!$rateLimiter->check('resend_verification', $clientIp, $email)) {
            $remaining = $rateLimiter->getRemainingLockoutTime('resend_verification', $clientIp, $email);
            return [
                'success' => false,
                'message' => $remaining >= 60
                    ? 'Too many requests. Please try again in ' . (int)ceil($remaining / 60) . ' minutes.'
                    : "Please wait {$remaining} seconds before trying again.",
                'remainingSeconds' => $remaining,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT id, is_email_verified, first_name, last_name
            FROM users
            WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $rateLimiter->recordAttempt('resend_verification', $clientIp, $email);
            return [
                'success' => true,
                'message' => 'If the account still needs verification, a new email has been sent.',
                'remainingSeconds' => 0,
            ];
        }

        if ((bool)$user['is_email_verified']) {
            return [
                'success' => false,
                'message' => 'This email address is already verified. You can sign in now.',
                'remainingSeconds' => 0,
                'verified' => true,
            ];
        }

        $state = $this->getVerificationResendState($email, $clientIp);
        if ($state['remainingSeconds'] > 0) {
            $remaining = (int)$state['remainingSeconds'];
            return [
                'success' => false,
                'message' => "Please wait {$remaining} seconds before resending.",
                'remainingSeconds' => $remaining,
            ];
        }

        $cooldown = max(1, (int)($this->appConfig['email_verification']['resend_cooldown'] ?? 60));
        $token = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', time() + $this->appConfig['email_verification']['token_lifetime']);
        $cooldownThreshold = date('Y-m-d H:i:s', time() - $cooldown);

        $updateStmt = $this->db->prepare("
            UPDATE users
            SET email_verification_token = :token,
                email_verification_expires_at = :token_expires,
                verification_email_sent_at = NOW()
            WHERE id = :id
              AND is_email_verified = 0
              AND (
                  verification_email_sent_at IS NULL
                  OR verification_email_sent_at <= :cooldown_threshold
              )
        ");
        $updateStmt->execute([
            'token' => $token,
            'token_expires' => $tokenExpires,
            'id' => $user['id'],
            'cooldown_threshold' => $cooldownThreshold,
        ]);

        if ($updateStmt->rowCount() !== 1) {
            $state = $this->getVerificationResendState($email, $clientIp);
            $remaining = max(1, (int)$state['remainingSeconds']);
            return [
                'success' => false,
                'message' => "Please wait {$remaining} seconds before resending.",
                'remainingSeconds' => $remaining,
            ];
        }

        $rateLimiter->recordAttempt('resend_verification', $clientIp, $email);
        $_SESSION['last_verification_sent_' . hash('sha256', $email)] = time();

        try {
            $emailService = new Email();
            $fullName = trim((string)$user['first_name'] . ' ' . (string)$user['last_name']);
            $sent = $emailService->sendVerification($email, $fullName, $token);
        } catch (Throwable $e) {
            error_log('Verification resend failed: ' . $e->getMessage());
            $sent = false;
        }

        if (!$sent) {
            return [
                'success' => false,
                'message' => 'We could not send the email right now. Please try again when the countdown ends.',
                'remainingSeconds' => $cooldown,
            ];
        }

        return [
            'success' => true,
            'message' => 'A new verification email has been sent.',
            'remainingSeconds' => $cooldown,
        ];
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
     * Check whether a reset link can still be used without consuming it.
     */
    public function isPasswordResetTokenValid(string $token): bool
    {
        if (!$this->hasValidPasswordResetTokenFormat($token)) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM users
            WHERE password_reset_token = :token
              AND password_reset_expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);

        return (bool)$stmt->fetchColumn();
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
        if (!$this->hasValidPasswordResetTokenFormat($token)) {
            throw new Exception("Invalid or expired reset token.");
        }

        $passwordErrors = PasswordPolicy::validate($newPassword);
        if ($passwordErrors) {
            throw new Exception(implode(' ', $passwordErrors));
        }
        
        $stmt = $this->db->prepare("
            SELECT id
            FROM users
            WHERE password_reset_token = :token
              AND password_reset_expires_at > NOW()
        ");
        
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Invalid or expired reset token.");
        }
        
        // Hash nového hesla
        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        $this->db->beginTransaction();

        try {
            // Consume the token in the same atomic update that changes the password.
            $updateStmt = $this->db->prepare("
                UPDATE users
                SET password_hash = :password_hash,
                    password_reset_token = NULL,
                    password_reset_expires_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
                  AND password_reset_token = :token
                  AND password_reset_expires_at > NOW()
            ");

            $updateStmt->execute([
                'password_hash' => $passwordHash,
                'id' => $user['id'],
                'token' => $token,
            ]);

            if ($updateStmt->rowCount() !== 1) {
                throw new UnexpectedValueException("Reset token was already consumed.");
            }

            (new Session())->destroyAllForUser((string)$user['id']);
            $this->db->commit();
        } catch (UnexpectedValueException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Invalid or expired reset token.");
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Password reset failed: ' . $e->getMessage());
            throw new Exception("We could not reset your password. Please request a new link.");
        }
        
        return true;
    }

    private function hasValidPasswordResetTokenFormat(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
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
