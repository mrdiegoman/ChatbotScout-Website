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

        $resolvedFromEmail = !empty($fromEmail) ? $fromEmail : $this->config['from_email'];
        $resolvedFromName  = !empty($fromName)  ? $fromName  : $this->config['from_name'];

        return $this->send($toEmail, $toName, $emailSubject, $htmlBody, $textBody, $resolvedFromEmail, $resolvedFromName);
    }

    // ── Chatbot submission confirmation to user ──────────────────────────────
    public function sendChatbotSubmissionConfirmation(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $websiteUrl
    ): bool {
        $subject  = 'We received your chatbot submission – ChatbotHub';
        $htmlBody = $this->getChatbotSubmissionConfirmationTemplate($toName, $chatbotName, $websiteUrl);
        $textBody = "Hi $toName,\n\nThank you for submitting \"$chatbotName\" to ChatbotHub!\n\nWe have received your partnership request and will review your chatbot shortly. Our team will contact you to discuss the affiliate revenue deal.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    // ── Admin notification about new submission ──────────────────────────────
    public function sendChatbotSubmissionAdminNotification(
        string $adminEmail,
        string $userName,
        string $userEmail,
        string $chatbotName,
        string $websiteUrl,
        string $accessNote,
        string $chatbotId,
        string $companyName = ''
    ): bool {
        $subject  = 'New chatbot submission: ' . $chatbotName;
        $htmlBody = $this->getChatbotAdminNotificationTemplate(
            $userName, $userEmail, $chatbotName, $websiteUrl, $accessNote, $chatbotId, $companyName
        );
        $textBody = "New affiliate partnership request:\n\nChatbot: $chatbotName\nWebsite: $websiteUrl\nCompany: $companyName\nSubmitted by: $userName ($userEmail)\n\nMessage:\n$accessNote\n\nChatbot ID: $chatbotId";

        return $this->send($adminEmail, 'Admin', $subject, $htmlBody, $textBody);
    }

    // ── Chatbot approved notification to user ────────────────────────────────
    public function sendChatbotApproved(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $pricingUrl = ''
    ): bool {
        $subject  = '🎉 Your chatbot has been approved – ChatbotHub';
        $htmlBody = $this->getChatbotApprovedTemplate($toName, $chatbotName);
        $textBody = "Hi $toName,\n\nGreat news! \"$chatbotName\" has been approved for listing on ChatbotHub.\n\nOur team will be in touch soon to finalise the affiliate partnership details.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    // ── Chatbot rejected notification to user ────────────────────────────────
    public function sendChatbotRejected(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $reason
    ): bool {
        $subject  = 'Update on your chatbot submission – ChatbotHub';
        $htmlBody = $this->getChatbotRejectedTemplate($toName, $chatbotName, $reason);
        $textBody = "Hi $toName,\n\nThank you for submitting \"$chatbotName\" to ChatbotHub.\n\nAfter reviewing your submission, we were unable to approve it at this time.\n\nReason: $reason\n\nIf you have any questions, please contact us.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
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

    private function baseTemplate(string $content): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .wrapper { padding: 32px 16px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #2d1b69 0%, #1a3a6e 100%); padding: 32px 32px 24px; }
            .header h1 { color: #fff; margin: 0; font-size: 22px; }
            .header p { color: rgba(255,255,255,0.75); margin: 6px 0 0; font-size: 14px; }
            .body { padding: 32px; }
            .button { display: inline-block; padding: 14px 32px; background: #6B3FF6; color: white !important; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 700; font-size: 15px; }
            .button-green { background: #22C55E; }
            .info-box { background: #f8f9ff; border: 1px solid #e0d9ff; border-left: 4px solid #6B3FF6; border-radius: 6px; padding: 14px 18px; margin: 18px 0; }
            .warning-box { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #F59E0B; border-radius: 6px; padding: 14px 18px; margin: 18px 0; }
            .success-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #22C55E; border-radius: 6px; padding: 14px 18px; margin: 18px 0; }
            .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6B7280; letter-spacing: 0.5px; margin-bottom: 4px; }
            .value { font-size: 15px; color: #1F2937; font-weight: 500; }
            .footer { background: #f9fafb; padding: 20px 32px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9CA3AF; text-align: center; }
        </style>
        </head>
        <body>
        <div class="wrapper">
        <div class="container">
        $content
        <div class="footer">© ChatbotHub · All rights reserved</div>
        </div>
        </div>
        </body>
        </html>
        HTML;
    }

    private function getVerificationTemplate(string $name, string $link): string
    {
        $content = <<<HTML
        <div class="header">
            <h1>Welcome to ChatbotHub! 🎉</h1>
            <p>Please verify your email address to get started</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Thanks for signing up! Please verify your email address to activate your account.</p>
            <a href="$link" class="button">Verify Email Address</a>
            <p style="font-size:13px;color:#6B7280;">Or copy this link into your browser:<br><a href="$link" style="color:#6B3FF6;">$link</a></p>
            <div class="warning-box">⏱️ This link will expire in <strong>24 hours</strong>.</div>
            <p style="font-size:13px;color:#6B7280;">If you didn't create an account, you can safely ignore this email.</p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getPasswordResetTemplate(string $name, string $link): string
    {
        $content = <<<HTML
        <div class="header">
            <h1>Password Reset Request</h1>
            <p>We received a request to reset your password</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Click the button below to create a new password:</p>
            <a href="$link" class="button">Reset Password</a>
            <p style="font-size:13px;color:#6B7280;">Or copy this link into your browser:<br><a href="$link" style="color:#6B3FF6;">$link</a></p>
            <div class="warning-box">⚠️ This link will expire in <strong>1 hour</strong>.</div>
            <p style="font-size:13px;color:#6B7280;">If you didn't request a password reset, please ignore this email.</p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getContactConfirmationTemplate(string $name, string $subject): string
    {
        $content = <<<HTML
        <div class="header">
            <h1>We received your message ✓</h1>
            <p>Thank you for reaching out to ChatbotHub</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Thank you for reaching out! We have received your message regarding:</p>
            <div class="info-box"><strong>$subject</strong></div>
            <p>We aim to respond within a few business days.</p>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getAdminReplyTemplate(string $name, string $originalSubject, string $replyText): string
    {
        $replyHtml = nl2br(htmlspecialchars($replyText));
        $content   = <<<HTML
        <div class="header">
            <h1>Response to your inquiry</h1>
            <p>Re: $originalSubject</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <div class="info-box" style="font-size:13px;color:#6B7280;">Re: $originalSubject</div>
            <div style="background:#fff;padding:20px;border:1px solid #e0e0e0;border-radius:6px;">$replyHtml</div>
            <p style="margin-top:20px;">Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getChatbotSubmissionConfirmationTemplate(
        string $name,
        string $chatbotName,
        string $websiteUrl
    ): string {
        $content = <<<HTML
        <div class="header">
            <h1>Partnership Request Received 🤝</h1>
            <p>We'll review your chatbot and be in touch soon</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Thank you for submitting your chatbot to ChatbotHub! Here's a summary of what we received:</p>
            <div class="info-box">
                <div class="label">Chatbot Name</div>
                <div class="value">$chatbotName</div>
                <div class="label" style="margin-top:12px;">Website</div>
                <div class="value"><a href="$websiteUrl" style="color:#6B3FF6;">$websiteUrl</a></div>
            </div>
            <div class="warning-box">
                <strong>⚙️ What happens next?</strong><br><br>
                Our team will review your chatbot and reach out to discuss the affiliate partnership terms — revenue share tailored to your product.<br><br>
                Once we agree on terms, your chatbot will be listed and start receiving high-intent traffic from ChatbotHub.
            </div>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getChatbotAdminNotificationTemplate(
        string $userName,
        string $userEmail,
        string $chatbotName,
        string $websiteUrl,
        string $accessNote,
        string $chatbotId,
        string $companyName = ''
    ): string {
        $accessNoteHtml  = nl2br(htmlspecialchars($accessNote));
        $companyDisplay  = $companyName ? htmlspecialchars($companyName) : '—';
        $content = <<<HTML
        <div class="header">
            <h1>New Affiliate Partnership Request</h1>
            <p>Action required: review and reach out</p>
        </div>
        <div class="body">
            <div class="info-box">
                <div class="label">Chatbot Name</div>
                <div class="value">$chatbotName</div>
                <div class="label" style="margin-top:12px;">Website</div>
                <div class="value"><a href="$websiteUrl" style="color:#6B3FF6;">$websiteUrl</a></div>
                <div class="label" style="margin-top:12px;">Company / Product</div>
                <div class="value">$companyDisplay</div>
                <div class="label" style="margin-top:12px;">Submitted By</div>
                <div class="value">$userName &lt;$userEmail&gt;</div>
                <div class="label" style="margin-top:12px;">Chatbot ID</div>
                <div class="value" style="font-size:12px;font-family:monospace;">$chatbotId</div>
            </div>
            <div class="warning-box">
                <div class="label">Message from Submitter</div>
                <div style="margin-top:8px;font-size:14px;color:#1F2937;">$accessNoteHtml</div>
            </div>
            <p style="font-size:13px;color:#6B7280;">Log in to the admin panel to review this submission and reach out to discuss affiliate terms.</p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getChatbotApprovedTemplate(
        string $name,
        string $chatbotName,
        string $pricingUrl = ''
    ): string {
        $content = <<<HTML
        <div class="header">
            <h1>Your Chatbot is Approved! 🎉</h1>
            <p>We'll be in touch about your affiliate partnership</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Great news! We've reviewed <strong>$chatbotName</strong> and it has been approved for listing on ChatbotHub.</p>
            <div class="success-box">
                ✅ <strong>$chatbotName</strong> is approved and ready to go live on ChatbotHub.
            </div>
            <div class="warning-box">
                <strong>🤝 Next step — Affiliate Partnership</strong><br><br>
                Our team will contact you shortly to finalise the affiliate revenue share terms. Once agreed, your chatbot will go live and start receiving traffic from our directory.
            </div>
            <p>If you have any questions in the meantime, feel free to reply to this email.</p>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }
    private function getChatbotRejectedTemplate(
        string $name,
        string $chatbotName,
        string $reason
    ): string {
        $reasonHtml = nl2br(htmlspecialchars($reason));
        $content    = <<<HTML
        <div class="header">
            <h1>Update on Your Submission</h1>
            <p>$chatbotName</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Thank you for submitting <strong>$chatbotName</strong> to ChatbotHub. After reviewing your submission, we were unable to approve it at this time.</p>
            <div class="warning-box">
                <div class="label">Reason</div>
                <div style="margin-top:6px;">$reasonHtml</div>
            </div>
            <p>If you have any questions or would like to resubmit after making changes, please don't hesitate to contact us.</p>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }
}