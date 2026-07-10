ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `verification_email_sent_at` TIMESTAMP NULL
    COMMENT 'Last verification email send time used for server-side resend cooldown'
    AFTER `email_verification_expires_at`;
