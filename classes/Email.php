<?php
/**
 * Email Class
 *
 * Odosielanie emailov cez SMTP (Mailtrap pre testing)
 * Používa PHPMailer knižnicu
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

    // ============================================
    // PUBLIC SEND METHODS
    // ============================================

    public function sendVerification(string $toEmail, string $toName, string $verificationToken): bool
    {
        $verificationLink = $this->config['base_url'] . '/verifyEmail.php?token=' . $verificationToken;
        $subject          = 'Verify your ChatbotHub account';
        $htmlBody         = $this->getVerificationTemplate($toName, $verificationLink);
        $textBody         = "Hi $toName,\n\nPlease verify your email by clicking this link:\n$verificationLink\n\nThis link will expire in 24 hours.";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    public function sendPasswordReset(string $toEmail, string $toName, string $resetToken): bool
    {
        $resetLink = $this->config['base_url'] . '/resetPassword.php?token=' . $resetToken;
        $subject   = 'Reset your ChatbotHub password';
        $htmlBody  = $this->getPasswordResetTemplate($toName, $resetLink);
        $textBody  = "Hi $toName,\n\nYou requested a password reset. Click this link to reset your password:\n$resetLink\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    public function sendContactConfirmation(string $toEmail, string $toName, string $subject): bool
    {
        $emailSubject = 'We received your message – ChatbotHub';
        $htmlBody     = $this->getContactConfirmationTemplate($toName, $subject);
        $textBody     = "Hi $toName,\n\nThank you for reaching out! We have received your message regarding \"$subject\".\n\nWe aim to respond within a few business days.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $emailSubject, $htmlBody, $textBody);
    }

    /**
     * Admin reply to a contact message
     *
     * @param string $toEmail         Recipient email
     * @param string $toName          Recipient full name
     * @param string $originalSubject Original message subject
     * @param string $replyText       Admin reply content
     * @param string $fromEmail       Optional override for From address (z DB settings)
     * @param string $fromName        Optional override for From name (z DB settings)
     */
    public function sendAdminReply(
        string $toEmail,
        string $toName,
        string $originalSubject,
        string $replyText,
        string $fromEmail = '',
        string $fromName  = ''
    ): bool {
        $emailSubject = 'Re: ' . $originalSubject . ' – ChatbotHub';
        $htmlBody     = $this->getAdminReplyTemplate($toName, $originalSubject, $replyText);
        $textBody     = "Hi $toName,\n\nThank you for contacting us. Here is our response to your inquiry regarding \"$originalSubject\":\n\n$replyText\n\nBest regards,\nThe ChatbotHub Team";

        // Použi override ak je nastavený, inak fallback na config
        $resolvedFromEmail = !empty($fromEmail) ? $fromEmail : $this->config['from_email'];
        $resolvedFromName  = !empty($fromName)  ? $fromName  : $this->config['from_name'];

        return $this->send($toEmail, $toName, $emailSubject, $htmlBody, $textBody, $resolvedFromEmail, $resolvedFromName);
    }

    // ============================================
    // PRIVATE SEND
    // ============================================

    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $fromEmail = '',
        string $fromName  = ''
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->Port       = $this->config['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                !empty($fromEmail) ? $fromEmail : $this->config['from_email'],
                !empty($fromName)  ? $fromName  : $this->config['from_name']
            );
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;

            return $mail->send();

        } catch (Exception $e) {
            error_log('Mail error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    // ============================================
    // HTML TEMPLATES
    // ============================================

    private function getVerificationTemplate(string $name, string $link): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8">
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
                <p>Thanks for signing up! Please verify your email address to get started.</p>
                <a href="$link" class="button">Verify Email Address</a>
                <p>Or copy and paste this link into your browser:</p>
                <p><a href="$link">$link</a></p>
                <p>This link will expire in <strong>24 hours</strong>.</p>
                <div class="footer">
                    <p>If you didn't create an account, you can safely ignore this email.</p>
                    <p>© ChatbotHub</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function getPasswordResetTemplate(string $name, string $link): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .button { display: inline-block; padding: 12px 30px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .warning { background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 5px; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <div class="container">
                <h2>Password Reset Request</h2>
                <p>Hi <strong>$name</strong>,</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <a href="$link" class="button">Reset Password</a>
                <p>Or copy and paste this link into your browser:</p>
                <p><a href="$link">$link</a></p>
                <div class="warning">
                    ⚠️ This link will expire in <strong>1 hour</strong>.
                </div>
                <div class="footer">
                    <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                    <p>© ChatbotHub</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function getContactConfirmationTemplate(string $name, string $subject): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .subject-box { background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4CAF50; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <div class="container">
                <h2>We received your message ✓</h2>
                <p>Hi <strong>$name</strong>,</p>
                <p>Thank you for reaching out! We have received your message regarding:</p>
                <div class="subject-box">
                    <strong>$subject</strong>
                </div>
                <p>We aim to respond within a few business days.</p>
                <div class="footer">
                    <p>© ChatbotHub</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function getAdminReplyTemplate(string $name, string $originalSubject, string $replyText): string
    {
        $replyHtml = nl2br(htmlspecialchars($replyText));
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .subject-box { background-color: #f5f5f5; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196F3; font-size: 13px; color: #666; }
            .reply-body { background: #fff; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <div class="container">
                <h2>Response to your inquiry</h2>
                <p>Hi <strong>$name</strong>,</p>
                <div class="subject-box">Re: $originalSubject</div>
                <div class="reply-body">$replyHtml</div>
                <p style="margin-top:20px;">Best regards,<br><strong>The ChatbotHub Team</strong></p>
                <div class="footer">
                    <p>© ChatbotHub</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}