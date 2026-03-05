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
     * @param string $toEmail      Recipient email
     * @param string $toName       Recipient full name
     * @param string $originalSubject  Original message subject
     * @param string $replyText    Admin reply content
     * @return bool
     */
    public function sendAdminReply(
        string $toEmail,
        string $toName,
        string $originalSubject,
        string $replyText
    ): bool {
        $emailSubject = 'Re: ' . $originalSubject . ' – ChatbotHub';
        $htmlBody     = $this->getAdminReplyTemplate($toName, $originalSubject, $replyText);
        $textBody     = "Hi $toName,\n\nThank you for contacting us. Here is our response to your inquiry regarding \"$originalSubject\":\n\n$replyText\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $emailSubject, $htmlBody, $textBody);
    }

    // ============================================
    // PRIVATE SEND
    // ============================================

    private function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
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

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
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
                <p>Thanks for signing up! Please verify your email address to activate your account.</p>
                <p><a href="$link" class="button">Verify Email Address</a></p>
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
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <div class="container">
                <h2>Reset Your Password</h2>
                <p>Hi $name,</p>
                <p>You requested to reset your ChatbotHub password. Click the button below to create a new password:</p>
                <p><a href="$link" class="button">Reset Password</a></p>
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

    private function getContactConfirmationTemplate(string $name, string $subject): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .badge { display: inline-block; padding: 6px 14px; background-color: #f0f4ff; color: #3b82f6; border-radius: 20px; font-size: 13px; font-weight: 600; margin: 8px 0 16px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <div class="container">
                <h2>We got your message! ✉️</h2>
                <p>Hi <strong>$name</strong>,</p>
                <p>Thank you for reaching out to us. We have received your message regarding:</p>
                <span class="badge">$subject</span>
                <p>Our team will review your message and get back to you within a few business days.</p>
                <p>If your matter is urgent, please note that we prioritize all incoming messages and will do our best to respond as quickly as possible.</p>
                <div class="footer">
                    <p>Please do not reply to this email — it was sent automatically.</p>
                    <p>&copy; 2025 ChatbotHub. All rights reserved.</p>
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
            .subject-badge { display: inline-block; padding: 6px 14px; background-color: #f0f4ff; color: #3b82f6; border-radius: 20px; font-size: 13px; font-weight: 600; margin: 4px 0 20px; }
            .reply-box { background-color: #f9fafb; border-left: 4px solid #3b82f6; border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 16px 0; color: #374151; font-size: 15px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
        </head>
        <body>
            <div class="container">
                <h2>We replied to your inquiry ✉️</h2>
                <p>Hi <strong>$name</strong>,</p>
                <p>Thank you for contacting us. Here is our response regarding:</p>
                <span class="subject-badge">$originalSubject</span>
                <div class="reply-box">$replyHtml</div>
                <p>If you have any further questions, feel free to contact us again via our <a href="{$this->config['base_url']}/contact.php">contact form</a>.</p>
                <div class="footer">
                    <p>Please do not reply to this email — use our contact form for further inquiries.</p>
                    <p>&copy; 2025 ChatbotHub. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}