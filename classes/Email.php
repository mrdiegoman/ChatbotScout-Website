<?php
/**
 * Email Class
 * 
 * Odosielanie emailov cez SMTP (Mailtrap pre testing)
 * Používa PHPMailerknižnicu - nainštaluj cez Composer alebo použij tento kód
 */

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email
{
    private array $config;
    
    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mailtrap.php';
    }
    
    /**
     * Odoslanie verification emailu
     * 
     * @param string $toEmail
     * @param string $toName
     * @param string $verificationToken
     * @return bool
     */
    public function sendVerification(string $toEmail, string $toName, string $verificationToken): bool
    {
        $verificationLink = $this->config['base_url'] . '/verifyEmail.php?token=' . $verificationToken;
        
        $subject = 'Verify your ChatbotHub account';
        
        $htmlBody = $this->getVerificationTemplate($toName, $verificationLink);
        $textBody = "Hi $toName,\n\nPlease verify your email by clicking this link:\n$verificationLink\n\nThis link will expire in 24 hours.";
        
        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Odoslanie password reset emailu
     * 
     * @param string $toEmail
     * @param string $toName
     * @param string $resetToken
     * @return bool
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetToken): bool
    {
        $resetLink = $this->config['base_url'] . '/resetPassword.php?token=' . $resetToken;
        
        $subject = 'Reset your ChatbotHub password';
        
        $htmlBody = $this->getPasswordResetTemplate($toName, $resetLink);
        $textBody = "Hi $toName,\n\nYou requested a password reset. Click this link to reset your password:\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.";
        
        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Hlavná funkcia na odoslanie emailu cez SMTP
     * 
     * POZNÁMKA: Toto je zjednodušená verzia. Pre produkciu použij PHPMailer alebo Symfony Mailer.
     * 
     * @return bool
     */

    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->Port = $this->config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            // Encoding
            $mail->CharSet = 'UTF-8';

            // From / To
            $mail->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
            );
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            return $mail->send();

        } catch (Exception $e) {
            error_log('Mail error: ' . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * SMTP command helper
     */
    // private function smtpCommand($smtp, ?string $command, int $expectedCode): void
    // {
    //     if ($command !== null) {
    //         fputs($smtp, $command . "\r\n");
    //     }
        
    //     $response = fgets($smtp, 512);
    //     $code = (int)substr($response, 0, 3);
        
    //     if ($code !== $expectedCode) {
    //         throw new Exception("SMTP error: Expected $expectedCode, got $code - $response");
    //     }
    // }
    
    /**
     * HTML Template pre verification email
     */
    private function getVerificationTemplate(string $name, string $link): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Welcome to ChatbotHub! 🎉</h2>
                <p>Hi <strong>$name</strong>,</p>
                <p>Thanks for signing up! Please verify your email address to activate your account.</p>
                <p>
                    <a href="$link" class="button">Verify Email Address</a>
                </p>
                <p>Or copy this link to your browser:</p>
                <p style="word-break: break-all; color: #666;">$link</p>
                <p><strong>This link will expire in 24 hours.</strong></p>
                <div class="footer">
                    <p>If you didn't create this account, please ignore this email.</p>
                    <p>&copy; 2025 ChatbotHub. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
            
    /**
     * HTML Template pre password reset email
     */
    private function getPasswordResetTemplate(string $name, string $link): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 12px 30px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Reset Your Password</h2>
                <p>Hi $name,</p>
                <p>You requested to reset your ChatbotHub password. Click the button below to create a new password:</p>
                <p>
                    <a href="$link" class="button">Reset Password</a>
                </p>
                <p>Or copy this link to your browser:</p>
                <p style="word-break: break-all; color: #666;">$link</p>
                <p><strong>This link will expire in 1 hour.</strong></p>
                <div class="footer">
                    <p>If you didn't request this, please ignore this email. Your password will remain unchanged.</p>
                    <p>&copy; 2025 ChatbotHub. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}