<?php
/**
 * Email Class
 *
 * Odosielanie emailov cez Gmail API (OAuth2)
 */

class Email
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/gmail.php';
    }

    // ============================================
    // PUBLIC SEND METHODS
    // ============================================

    public function sendChatbotDelisted(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $reason
    ): bool {
        $subject  = 'Chatbot Delisted - ChatbotHub';
        $htmlBody = $this->getChatbotDelistedTemplate($toName, $chatbotName, $reason);
        $textBody = "Hi $toName,\n\nYour chatbot \"$chatbotName\" has been delisted from ChatbotHub.\n\nReason: $reason\n\nIf you have any questions, please don't hesitate to contact us.\n\nBest regards,\nThe ChatbotHub Team";
 
        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

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
        $emailSubject = 'We received your message - ChatbotHub';
        $htmlBody     = $this->getContactConfirmationTemplate($toName, $subject);
        $textBody     = "Hi $toName,\n\nThank you for reaching out! We have received your message regarding \"$subject\".\n\nWe aim to respond within a few business days.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $emailSubject, $htmlBody, $textBody);
    }

    public function sendContactAdminNotification(
        string $adminEmail,
        string $firstName,
        string $lastName,
        string $senderEmail,
        string $subject,
        string $message,
        string $ipAddress = ''
    ): bool {
        $fullName    = $firstName . ' ' . $lastName;
        $emailSubject = 'New contact message: ' . $subject;
        $htmlBody     = $this->getContactAdminNotificationTemplate(
            $fullName, $senderEmail, $subject, $message, $ipAddress
        );
        $textBody = "New contact message received.\n\nFrom: $fullName ($senderEmail)\nSubject: $subject\nIP: $ipAddress\n\nMessage:\n$message";
 
        return $this->send($adminEmail, 'Admin', $emailSubject, $htmlBody, $textBody);
    }



    public function sendAdminReply(
        string $toEmail,
        string $toName,
        string $originalSubject,
        string $replyText,
        string $fromEmail = '',
        string $fromName  = ''
    ): bool {
        $emailSubject      = 'Re: ' . $originalSubject . ' - ChatbotHub';
        $htmlBody          = $this->getAdminReplyTemplate($toName, $originalSubject, $replyText);
        $textBody          = "Hi $toName,\n\nThank you for contacting us. Here is our response to your inquiry regarding \"$originalSubject\":\n\n$replyText\n\nBest regards,\nThe ChatbotHub Team";
        $resolvedFromEmail = !empty($fromEmail) ? $fromEmail : $this->config['from_email'];
        $resolvedFromName  = !empty($fromName)  ? $fromName  : $this->config['from_name'];

        return $this->send($toEmail, $toName, $emailSubject, $htmlBody, $textBody, $resolvedFromEmail, $resolvedFromName);
    }

    public function sendChatbotSubmissionConfirmation(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $websiteUrl
    ): bool {
        $subject  = 'We received your chatbot submission - ChatbotHub';
        $htmlBody = $this->getChatbotSubmissionConfirmationTemplate($toName, $chatbotName, $websiteUrl);
        $textBody = "Hi $toName,\n\nThank you for submitting \"$chatbotName\" to ChatbotHub!\n\nWe have received your partnership request and will review your chatbot shortly. Our team will contact you to discuss the affiliate revenue deal.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    public function sendReviewAdminNotification(
        string $adminEmail,
        string $userName,
        string $userEmail,
        string $chatbotName,
        int    $rating,
        string $title,
        string $reviewText,
        string $reviewId
    ): bool {
        $stars        = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $subject      = 'New review pending approval: ' . $chatbotName;
        $htmlBody     = $this->getReviewAdminNotificationTemplate(
            $userName, $userEmail, $chatbotName, $rating, $stars, $title, $reviewText, $reviewId
        );
        $textBody = "New review pending approval.\n\nChatbot: $chatbotName\nRating: $stars ($rating/5)\nSubmitted by: $userName ($userEmail)\nTitle: $title\n\nReview:\n$reviewText\n\nReview ID: $reviewId";
 
        return $this->send($adminEmail, 'Admin', $subject, $htmlBody, $textBody);
    }

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

    public function sendChatbotApproved(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $pricingUrl = ''
    ): bool {
        $subject  = '🎉 Your chatbot has been approved - ChatbotHub';
        $htmlBody = $this->getChatbotApprovedTemplate($toName, $chatbotName);
        $textBody = "Hi $toName,\n\n\"$chatbotName\" has been approved for listing on ChatbotHub.\n\nOur team will be in touch soon to finalise the affiliate partnership details.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    public function sendChatbotRejected(
        string $toEmail,
        string $toName,
        string $chatbotName,
        string $reason
    ): bool {
        $subject  = 'Update on your chatbot submission - ChatbotHub';
        $htmlBody = $this->getChatbotRejectedTemplate($toName, $chatbotName, $reason);
        $textBody = "Hi $toName,\n\nThank you for submitting \"$chatbotName\" to ChatbotHub.\n\nAfter reviewing your submission, we were unable to approve it at this time.\n\nReason: $reason\n\nIf you have any questions, please contact us.\n\nBest regards,\nThe ChatbotHub Team";

        return $this->send($toEmail, $toName, $subject, $htmlBody, $textBody);
    }

    // ============================================
    // PRIVATE SEND — Gmail API
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
        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                error_log('Gmail API: Failed to get access token');
                return false;
            }

            $resolvedFromEmail = !empty($fromEmail) ? $fromEmail : $this->config['from_email'];
            $resolvedFromName  = !empty($fromName)  ? $fromName  : $this->config['from_name'];

            $rawMessage = $this->buildRawMessage(
                $resolvedFromEmail,
                $resolvedFromName,
                $toEmail,
                $toName,
                $subject,
                $htmlBody,
                $textBody
            );

            $response = $this->callGmailApi($accessToken, $rawMessage);

            if (!$response || isset($response['error'])) {
                $errorMsg = $response['error']['message'] ?? 'Unknown Gmail API error';
                error_log('Gmail API send error: ' . $errorMsg);
                return false;
            }

            return true;

        } catch (Exception $e) {
            error_log('Gmail API exception: ' . $e->getMessage());
            return false;
        }
    }

    // ============================================
    // GMAIL API HELPERS
    // ============================================

    /**
     * Získa access token pomocou refresh tokenu
     */
    private function getAccessToken(): ?string
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'refresh_token' => $this->config['refresh_token'],
                'grant_type'    => 'refresh_token',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = json_decode(curl_exec($ch), true);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('Gmail API cURL error (getAccessToken): ' . $curlError);
            return null;
        }

        return $response['access_token'] ?? null;
    }

    /**
     * Zostaví raw RFC 2822 správu a zakóduje ju do base64url
     */
    private function buildRawMessage(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): string {
        $boundary = 'boundary_' . bin2hex(random_bytes(8));

        $from    = $fromName  ? "\"$fromName\" <$fromEmail>" : $fromEmail;
        $to      = $toName    ? "\"$toName\" <$toEmail>"     : $toEmail;
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message  = "MIME-Version: 1.0\r\n";
        $message .= "From: $from\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $message .= "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($textBody)) . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $message .= "--$boundary--";

        // base64url encoding (Gmail API vyžaduje toto namiesto štandardného base64)
        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }

    /**
     * Odošle správu cez Gmail API
     */
    private function callGmailApi(string $accessToken, string $rawMessage): ?array
    {
        $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['raw' => $rawMessage]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response  = json_decode(curl_exec($ch), true);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('Gmail API cURL error (send): ' . $curlError);
            return null;
        }

        return $response;
    }

    // ============================================
    // HTML TEMPLATES
    // ============================================

    private function baseTemplate(string $content): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="color-scheme" content="light only">
            <meta name="supported-color-schemes" content="light only">
        <style>
            :root { color-scheme: light only; }
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .wrapper { padding: 32px 16px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #2d1b69 0%, #1a3a6e 100%); padding: 32px 32px 24px; }
            .header h1 { color: #fafafa !important; margin: 0; font-size: 22px;}
            .header p { color: rgba(255,255,255,0.75); margin: 6px 0 0; font-size: 14px; }
            .body { padding: 32px; }
            .button { display: inline-block; padding: 14px 32px; background: #6B3FF6; color: white !important; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 700; font-size: 15px; }
            .button-green { background: #22C55E; }
            .info-box { background: #f8f9ff; border: 1px solid #e0d9ff; border-left: 4px solid #6B3FF6; border-radius: 6px; padding: 14px 18px; margin: 18px 0; }
            /* .warning-box { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #F59E0B; border-radius: 6px; padding: 14px 18px; margin: 18px 0; } */
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
        <div class="header" style="background: linear-gradient(135deg, #2d1b69 0%, #1a3a6e 100%); padding: 32px 32px 24px;">
            <h1>Welcome to ChatbotHub! 🎉</h1>
            <p>Please verify your email address to get started</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Thanks for signing up! Please verify your email address to activate your account.</p>
            <a href="$link" class="button">Verify Email Address</a>
            <p style="font-size:13px;color:#6B7280;">Or copy this link into your browser:<br><a href="$link" style="color:#6B3FF6;">$link</a></p>
            <div>This link will expire in <strong>24 hours</strong>.</div>
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
            <div> This link will expire in <strong>1 hour</strong>.</div>
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
            <div>
                <strong>What happens next?</strong><br><br>
                Our team will review your chatbot and reach out to discuss the affiliate partnership terms - revenue share tailored to your product.<br><br>
                Once we agree on terms, your chatbot will be listed on ChatbotHub.
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
        $accessNoteHtml = nl2br(htmlspecialchars($accessNote));
        $companyDisplay = $companyName ? $companyName : '<em style="color:#9CA3AF;">Not provided</em>';

        $adminUrl     = $this->config['base_url'] . '/admin.php';

        $content        = <<<HTML
        <div class="header">
            <h1>New Chatbot Submission 🚀</h1>
            <p>A new affiliate partnership request has been submitted</p>
        </div>
        <div class="body">
            <div class="info-box">
                <div class="label">Chatbot Name</div>
                <div class="value">$chatbotName</div>
                <div class="label" style="margin-top:12px;">Company</div>
                <div class="value">$companyDisplay</div>
                <div class="label" style="margin-top:12px;">Website</div>
                <div class="value"><a href="$websiteUrl" style="color:#6B3FF6;">$websiteUrl</a></div>
                <div class="label" style="margin-top:12px;">Submitted By</div>
                <div class="value">$userName ($userEmail)</div>
                <div class="label" style="margin-top:12px;">Chatbot ID</div>
                <div class="value" style="font-size:13px;color:#6B7280;">$chatbotId</div>
            </div>
            <div class="label">Access / Notes</div>
            <div style="background:#fff;padding:16px;border:1px solid #e0e0e0;border-radius:6px;margin-top:6px;">$accessNoteHtml</div>
            <a href="$adminUrl" class="button" style="margin-top:24px;">Open Admin Panel</a>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getChatbotApprovedTemplate(string $name, string $chatbotName): string
    {
        $content = <<<HTML
        <div class="header" style="color">
            <h1>Your Chatbot is Approved! 🎉</h1>
            <p>Welcome to the ChatbotHub affiliate program</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <div class="success-box">
                <strong>$chatbotName</strong> has been approved for listing on ChatbotHub.
            </div>
            <p>Your chatbot is now live on ChatbotHub. We're excited to have you on board.</p>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getChatbotRejectedTemplate(string $name, string $chatbotName, string $reason): string
    {
        $reasonHtml = nl2br(htmlspecialchars($reason));
        $content    = <<<HTML
        <div class="header">
            <h1>Update on Your Submission</h1>
            <p>Regarding your chatbot submission to ChatbotHub</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>Thank you for submitting <strong>$chatbotName</strong> to ChatbotHub.</p>
            <p>After reviewing your submission, we were unable to approve it at this time.</p>
            <div>
                <strong>Reason:</strong><br><br>$reasonHtml
            </div>
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getChatbotDelistedTemplate(string $name, string $chatbotName, string $reason): string
    {
        $reasonHtml = nl2br(htmlspecialchars($reason));
        $content    = <<<HTML
        <div class="header">
            <h1>Chatbot Delisted</h1>
            <p>An update regarding your listing on ChatbotHub</p>
        </div>
        <div class="body">
            <p>Hi <strong>$name</strong>,</p>
            <p>We're writing to let you know that <strong>$chatbotName</strong> has been delisted from ChatbotHub.</p>
            <div class="info-box">
                <div class="label">Reason</div>
                <div class="value">$reasonHtml</div>
            </div>
            <p>If you have any questions or would like to discuss this further, please don't hesitate to contact us.</p>
            <p>Best regards,<br><strong>The ChatbotHub Team</strong></p>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }

    private function getContactAdminNotificationTemplate(
        string $fullName,
        string $senderEmail,
        string $subject,
        string $message,
        string $ipAddress = ''
    ): string {
        $messageHtml  = nl2br(htmlspecialchars($message));
        $ipBlock      = $ipAddress
            ? "<div class=\"label\" style=\"margin-top:12px;\">IP Address</div><div class=\"value\" style=\"font-size:13px;color:#6B7280;\">$ipAddress</div>"
            : '';
        $adminUrl     = $this->config['base_url'] . '/admin.php';
 
        $content = <<<HTML
        <div class="header">
            <h1>New Contact Message 📬</h1>
            <p>Someone submitted a message via the contact form</p>
        </div>
        <div class="body">
            <div class="info-box">
                <div class="label">From</div>
                <div class="value">$fullName</div>
                <div class="label" style="margin-top:12px;">Email</div>
                <div class="value"><a href="mailto:$senderEmail" style="color:#6B3FF6;">$senderEmail</a></div>
                <div class="label" style="margin-top:12px;">Subject</div>
                <div class="value">$subject</div>
                $ipBlock
            </div>
            <div class="label" style="margin-top:18px;">Message</div>
            <div style="background:#fff;padding:16px;border:1px solid #e0e0e0;border-radius:6px;margin-top:6px;">$messageHtml</div>
            <a href="$adminUrl" class="button" style="margin-top:24px;">Open Admin Panel</a>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }
 
    private function getReviewAdminNotificationTemplate(
        string $userName,
        string $userEmail,
        string $chatbotName,
        int    $rating,
        string $stars,
        string $title,
        string $reviewText,
        string $reviewId
    ): string {
        $reviewHtml   = nl2br(htmlspecialchars($reviewText));
        $titleDisplay = $title ?: '<em style="color:#9CA3AF;">No title</em>';
        $adminUrl     = $this->config['base_url'] . '/admin.php';
 
        $content = <<<HTML
        <div class="header">
            <h1>New Review Pending ⭐</h1>
            <p>A new review is waiting for your approval</p>
        </div>
        <div class="body">
            <div class="info-box">
                <div class="label">Chatbot</div>
                <div class="value">$chatbotName</div>
                <div class="label" style="margin-top:12px;">Rating</div>
                <div class="value" style="font-size:18px;letter-spacing:2px;">$stars <span style="font-size:13px;color:#6B7280;">($rating / 5)</span></div>
                <div class="label" style="margin-top:12px;">Submitted By</div>
                <div class="value">$userName (<a href="mailto:$userEmail" style="color:#6B3FF6;">$userEmail</a>)</div>
                <div class="label" style="margin-top:12px;">Title</div>
                <div class="value">$titleDisplay</div>
                <div class="label" style="margin-top:12px;">Review ID</div>
                <div class="value" style="font-size:13px;color:#6B7280;">$reviewId</div>
            </div>
            <div class="label" style="margin-top:18px;">Review Text</div>
            <div style="background:#fff;padding:16px;border:1px solid #e0e0e0;border-radius:6px;margin-top:6px;">$reviewHtml</div>
            <a href="$adminUrl" class="button" style="margin-top:24px;">Review in Admin Panel</a>
        </div>
        HTML;
        return $this->baseTemplate($content);
    }
}