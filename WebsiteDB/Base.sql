-- ============================================
-- ChatbotDirectory Platform - MySQL Database Schema
-- Version: 1.1 - WITH EXAMPLES
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
# SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================
-- USER MANAGEMENT
-- ============================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` CHAR(36) NOT NULL COMMENT 'UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `email` VARCHAR(255) NOT NULL COMMENT 'User email, e.g., "john.doe@gmail.com", "maria.kovacova@azet.sk", "business@chatbotcompany.com"',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Argon2id hashed password, e.g., "$argon2id$v=19$m=65536,t=3,p=4$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG", "$argon2id$v=19$m=19456,t=2,p=1$kL7kJRMI3JxWD5mEZLzBsQ$jWY6NJ3aqNJzCXLBcE0nLg"',
  `google_id` VARCHAR(255) NULL UNIQUE COMMENT 'Google OAuth user ID',
  `terms_accepted` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'User accepted Terms of Service',
  `terms_accepted_at` TIMESTAMP NULL COMMENT 'When user accepted Terms of Service',
  `first_name` VARCHAR(100) NULL COMMENT 'First name, e.g., "John", "Mária", "Peter"',
  `last_name` VARCHAR(100) NULL COMMENT 'Last name, e.g., "Doe", "Kováčová", "Novák"',
  `verified_reviewer` BOOLEAN NOT NULL DEFAULT FALSE,
  `is_admin` BOOLEAN NOT NULL DEFAULT FALSE,
  `is_review_blocked` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'User is blocked from submitting/editing reviews',
  `is_submission_blocked` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'User is blocked from submitting chatbots',
  `stripe_customer_id` VARCHAR(255) NULL,

  -- Email Verification
  `is_email_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Email verified?, e.g., TRUE, FALSE',
  `email_verification_token` VARCHAR(255) NULL COMMENT 'Verification token, e.g., "a3f5d8c9e2b1f4a7d6c3e8b5a2f9d1c4", "7b9e3f2a8d5c1e4b6f0a9c3d7e2b8f5a"',
  `email_verification_expires_at` TIMESTAMP NULL COMMENT 'Token expiration, e.g., "2025-01-30 14:30:00", "2025-02-01 10:00:00"',

  -- Password Reset
  `password_reset_token` VARCHAR(255) NULL COMMENT 'Reset token, e.g., "r3s3t-t0k3n-2025-abc123def456", "pwd_reset_9f8e7d6c5b4a3f2e1d0c"',
  `password_reset_expires_at` TIMESTAMP NULL COMMENT 'Reset expiration, e.g., "2025-01-29 16:45:00", "2025-01-30 09:30:00"',

  -- Profile
  `profile_picture_url` VARCHAR(500) NULL COMMENT 'Profile picture URL, e.g., "https://cdn.chatbotdir.com/users/john-doe.jpg", "https://storage.googleapis.com/profiles/maria_k.png", "https://imgur.com/abc123.jpg"',
  `bio` TEXT NULL COMMENT 'User bio, e.g., "Digital marketing specialist with 5 years of experience", "Chatbot enthusiast and AI developer from Slovakia", "Helping businesses automate customer support"',
  `marketing_emails` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Marketing emails enabled?, e.g., TRUE, FALSE',
  `unsubscribe_token` VARCHAR(64) NULL UNIQUE COMMENT 'Token pre one-click unsubscribe z marketing emailov',
  `website_url` VARCHAR(500) NULL COMMENT 'Personal website, e.g., "https://johndoe.com", "https://mariakovacova.sk", "https://techblog.io"',

  -- Account Status
  `status` ENUM('active', 'suspended', 'pending_verification', 'disabled') NOT NULL DEFAULT 'pending_verification' COMMENT 'Account status, e.g., "active", "suspended", "pending_verification"',
  `suspension_reason` TEXT NULL COMMENT 'Suspension reason, e.g., "Spamming reviews", "Violated terms of service", "Fraudulent affiliate activity"',

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Account created, e.g., "2025-01-15 10:30:00", "2024-12-01 08:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update, e.g., "2025-01-29 12:45:00", "2025-01-28 14:20:00"',
  `last_login_at` TIMESTAMP NULL COMMENT 'Last login, e.g., "2025-01-29 09:15:00", "2025-01-28 18:30:00"',
  `email_verified_at` TIMESTAMP NULL COMMENT 'Email verified at, e.g., "2025-01-15 11:00:00", "2024-12-02 09:45:00"',
  `disabled_at` TIMESTAMP NULL COMMENT 'Account disabled at, e.g., "2025-01-20 16:00:00", "2025-01-25 10:30:00"',

  PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email` (`email`),
#     UNIQUE KEY `idx_username` (`username`),
    KEY `idx_status_active` (`status`, `is_email_verified`),
    KEY `idx_marketing` (`marketing_emails`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` CHAR(36) NOT NULL COMMENT 'Session UUID, e.g., "650e8400-e29b-41d4-a716-446655440001", "8d1f2a3b-4c5e-6f7g-8h9i-0j1k2l3m4n5o"',
  `user_id` CHAR(36) NOT NULL COMMENT 'User UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `session_token` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Unique session token, e.g., "sess_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6", "token_2025_abc123def456ghi789jkl012"',
  `ip_address` VARCHAR(45) NULL COMMENT 'Support IPv6, e.g., "192.168.1.100", "2001:0db8:85a3:0000:0000:8a2e:0370:7334", "10.0.0.25"',
  `user_agent` VARCHAR(500) NULL COMMENT 'Browser info, e.g., "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0", "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Firefox/121.0"',
  `device_type` ENUM('desktop', 'mobile', 'tablet', 'other') NULL COMMENT 'Device type, e.g., "desktop", "mobile", "tablet"',
  `expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Session expiration, e.g., "2025-02-28 23:59:59", "2025-03-15 10:00:00"',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Session start, e.g., "2025-01-29 10:00:00", "2025-01-28 14:30:00"',
  `last_activity_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity, e.g., "2025-01-29 12:45:00", "2025-01-29 11:20:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_token` (`session_token`),
  KEY `idx_user_sessions` (`user_id`, `expires_at`),
  KEY `idx_cleanup` (`expires_at`),

  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE IF NOT EXISTS `visitors` (
  `id` CHAR(36) NOT NULL COMMENT 'Visitor UUID, e.g., "750e8400-e29b-41d4-a716-446655440002", "9e2f3b4c-5d6e-7f8g-9h0i-1j2k3l4m5n6o"',
  `fingerprint` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 browser fingerprint hash, e.g., "ec523d037249f514096cff40a45559b7cd4f3e68eed204c3f4c312bf491cecbf"',
  `ip_address` VARCHAR(45) NULL COMMENT 'IP address, e.g., "88.212.34.56", "2001:718:1c01:1111::2", "195.113.20.150"',
  `user_agent` VARCHAR(500) NULL COMMENT 'Browser string, e.g., "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0", "Mozilla/5.0 (Android 14; Mobile) Chrome/120.0.0.0"',

  `country_code` CHAR(2) NULL COMMENT 'ISO country code, e.g., "SK", "CZ", "US"',
  `country_name` VARCHAR(100) NULL COMMENT 'Country name, e.g., "Slovakia", "Czech Republic", "United States"',
  `city` VARCHAR(100) NULL COMMENT 'City name, e.g., "Bratislava", "Praha", "New York"',
  `region` VARCHAR(100) NULL COMMENT 'Region/state, e.g., "Bratislavský kraj", "Prague", "New York"',

  `referrer_url` VARCHAR(1000) NULL COMMENT 'Referrer URL, e.g., "https://www.google.com/search?q=chatbot+widget", "https://facebook.com/ads/click", "https://reddit.com/r/chatbots"',
  `landing_page` VARCHAR(1000) NULL COMMENT 'First page visited, e.g., "/chatbots/intercom", "/pricing", "/chatbots?filter=ecommerce"',

  `utm_source` VARCHAR(255) NULL COMMENT 'Traffic source, e.g., "google", "facebook", "newsletter"',
  `utm_medium` VARCHAR(255) NULL COMMENT 'Marketing medium, e.g., "cpc", "email", "social"',
  `utm_campaign` VARCHAR(255) NULL COMMENT 'Campaign name, e.g., "spring_sale_2025", "chatbot_launch", "black_friday"',
  `utm_term` VARCHAR(255) NULL COMMENT 'Paid keywords, e.g., "ai chatbot", "customer support bot", "live chat widget"',
  `utm_content` VARCHAR(255) NULL COMMENT 'Ad variation, e.g., "banner_blue", "text_ad_v2", "sidebar_widget"',

  `first_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'First visit, e.g., "2025-01-20 08:00:00", "2025-01-15 12:30:00"',
  `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last visit, e.g., "2025-01-29 12:45:00", "2025-01-29 09:20:00"',
  `expires_at` TIMESTAMP NULL DEFAULT NULL ,

  PRIMARY KEY (`id`),
  KEY `idx_fingerprint` (`fingerprint`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_utm_source_campaign` (`utm_source`, `utm_campaign`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Väzobná tabuľka visitors a user ucty
CREATE TABLE IF NOT EXISTS `visitor_user_links` (
  `id` CHAR(36) NOT NULL,
  `visitor_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `converted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_visitor_user` (`visitor_id`, `user_id`),
  KEY `idx_visitor` (`visitor_id`),
  KEY `idx_user` (`user_id`),

  CONSTRAINT `fk_vul_visitor` FOREIGN KEY (`visitor_id`)
    REFERENCES `visitors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vul_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Settings table
-- ============================================

CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         DEFAULT NULL,
    `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default hodnoty
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
    ('reply_from_email',         'ChatbotScout@gmail.com'),
    ('reply_from_name',          'ChatbotScout Support'),
    ('noreply_email',            NULL),
    ('noreply_name',             NULL),
    ('admin_notification_email', NULL);

-- ============================================
-- CHATBOT LISTINGS (WEBSITES)
-- ============================================

CREATE TABLE IF NOT EXISTS `chatbots` (
  `id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `owner_user_id` CHAR(36) NULL COMMENT 'Owner UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `verified_reviewer` BOOLEAN NULL default FALSE,

  -- Basic Info
  `name` VARCHAR(200) NOT NULL COMMENT 'Chatbot/company name, e.g., "Intercom", "Drift", "Tidio"',
  `website_url` VARCHAR(500) NOT NULL COMMENT 'Official website, e.g., "https://www.intercom.com", "https://www.drift.com", "https://www.tidio.com"',
  `description` VARCHAR(150) NULL COMMENT 'Full description, e.g., "Intercom is a complete customer service solution that provides seamless communication...", "AI-powered conversational platform for sales and marketing teams", "Live chat widget with chatbot automation for customer support"',

  -- Media
  `logo_url` VARCHAR(500) NULL COMMENT 'Logo image, e.g., "https://cdn.chatbotdir.com/logos/intercom.png", "https://storage.googleapis.com/chatbots/drift-logo.svg", "https://assets.tidio.com/logo.png"',

  -- Affiliate Program
  `has_affiliate_program` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Has affiliate?, e.g., TRUE, FALSE',
  `affiliate_link` VARCHAR(1000) NULL COMMENT 'Affiliate URL with tracking, e.g., "https://www.intercom.com?ref=chatbotdir_affiliate123", "https://drift.com/signup?aff=chatdir2025", "https://tidio.com?utm_source=chatbotdir&aff_id=ABC123"',
  `affiliate_commission_rate` DECIMAL(5,2) NULL COMMENT 'Commission percentage, e.g., 20.00, 30.00, 15.50',
  `affiliate_commission_type` ENUM('percentage', 'flat', 'cpa') NULL COMMENT 'Commission type, e.g., "percentage", "flat", "cpa"',
  `affiliate_cookie_duration` INT NULL COMMENT 'Cookie days, e.g., 30, 60, 90',

  -- Pricing Info
  `has_free_trial` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Free trial available?, e.g., TRUE, FALSE',
  `free_trial_days` INT NULL COMMENT 'Trial length, e.g., 14, 30, 7',
  `starting_price` DECIMAL(10,2) NOT NULL COMMENT 'Starting price, e.g., 39.00, 99.00, 19.99',
  `pricing_model` ENUM('free', 'freemium', 'recurring', 'one-time', 'usage-based', 'custom') NOT NULL COMMENT 'Pricing model, e.g., "freemium", "subscription", "usage_based"',
  `pricing_currency` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'Currency code, e.g., "USD", "EUR", "GBP"',
  `pricing_url` VARCHAR(500) NULL COMMENT 'Pricing page, e.g., "https://www.intercom.com/pricing", "https://drift.com/plans", "https://tidio.com/pricing"',

  -- Listing Status
  `status` ENUM('pending','active','reviewed','suspended','rejected','archived','removed') NOT NULL DEFAULT 'pending' COMMENT 'Listing status',
  `is_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Manually verified by admin, e.g., TRUE, FALSE',
  `is_featured` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Featured globally, e.g., TRUE, FALSE',
  `is_sponsored` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Paid promotion, e.g., TRUE, FALSE',
  `rejection_reason` TEXT NULL COMMENT 'Rejection reason, e.g., "Incomplete information provided", "Duplicate listing", "Website not accessible"',

  -- Denormalized Stats (updated via triggers/scheduled jobs)
  `click_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total clicks, e.g., 456, 12345, 89',
  `favorite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total favorites, e.g., 78, 1234, 12',
  `rating_average` DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Average rating, e.g., 4.50, 3.80, 4.95',
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of ratings, e.g., 234, 56, 1892',
  `conversion_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Affiliate conversions, e.g., 45, 123, 8',

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record created, e.g., "2025-01-15 10:00:00", "2024-12-01 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modified, e.g., "2025-01-29 12:45:00", "2025-01-28 14:20:00"',
  `submitted_at` TIMESTAMP NULL COMMENT 'Submitted for review, e.g., "2025-01-15 10:30:00", "2024-12-01 09:00:00"',
  `approved_at` TIMESTAMP NULL COMMENT 'Approved by admin, e.g., "2025-01-16 14:00:00", "2024-12-02 11:30:00"',
  `last_verified_at` TIMESTAMP NULL COMMENT 'Last verification, e.g., "2025-01-20 16:00:00", "2025-01-10 10:00:00"',
  `verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When admin accepted chatbot via Accept action',
  `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete time, e.g., "2025-01-25 18:00:00", NULL',

  PRIMARY KEY (`id`),
  KEY `idx_owner` (`owner_user_id`),
  KEY `idx_listing_status` (`status`, `is_featured`, `rating_average`),
  KEY `idx_active_listings` (`status`, `deleted_at`, `created_at`),
  KEY `idx_affiliate_program` (`has_affiliate_program`, `status`),
  KEY `idx_pricing_filter` (`pricing_model`),
  FULLTEXT KEY `idx_search` (`name`, `description`),

  CONSTRAINT `fk_chatbots_owner` FOREIGN KEY (`owner_user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT,

  CONSTRAINT `chk_affiliate_link` CHECK (
    (`has_affiliate_program` = FALSE) OR
    (`has_affiliate_program` = TRUE AND `affiliate_link` IS NOT NULL)
  ),
  CONSTRAINT `chk_free_trial` CHECK (
    (`has_free_trial` = FALSE) OR
    (`has_free_trial` = TRUE AND `free_trial_days` > 0)
  ),
  CONSTRAINT `chk_rating` CHECK (`rating_average` BETWEEN 0 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- recording unique visitors clicks on chatbot
CREATE TABLE IF NOT EXISTS `chatbot_clicks` (
  `id` CHAR(36) NOT NULL,
  `chatbot_id` CHAR(36) NOT NULL,
  `visitor_id` CHAR(36) NOT NULL,
  `source_page` VARCHAR(1000) NULL COMMENT 'Stránka odkiaľ user klikol, e.g., "/MainWebsite/public/index.php"',
  `clicked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_chatbot` (`chatbot_id`),
  KEY `idx_visitor` (`visitor_id`),
  KEY `idx_clicked_at` (`clicked_at`),

  CONSTRAINT `fk_clicks_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clicks_visitor` FOREIGN KEY (`visitor_id`)
    REFERENCES `visitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- TAGS MIGRATION
-- ============================================

CREATE TABLE IF NOT EXISTS `tags` (
  `id` CHAR(36) NOT NULL COMMENT 'Tag UUID',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Tag name, e.g., "Popular", "Affiliate"',
  `is_system` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE IF NOT EXISTS `chatbot_tags` (
  `id` CHAR(36) NOT NULL COMMENT 'Record UUID',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID',
  `tag_id` CHAR(36) NOT NULL COMMENT 'Tag UUID',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_chatbot_tag` (`chatbot_id`, `tag_id`),
  KEY `idx_tag` (`tag_id`),

  CONSTRAINT `fk_chatbot_tags_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chatbot_tags_tag` FOREIGN KEY (`tag_id`)
    REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- FEATURES & INTEGRATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS `features` (
  `id` CHAR(36) NOT NULL COMMENT 'Feature UUID, e.g., "d50e8400-e29b-41d4-a716-446655440008", "6f7g8h9i-0j1k-2l3m-4n5o-6p7q8r9s0t1u"',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Feature name, e.g., "Live Chat", "AI Responses", "Multi-language Support"',
  `advanced` BOOLEAN NOT NULL DEFAULT FALSE,

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-10 08:00:00", "2024-11-20 10:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 14:00:00", "2025-01-25 11:15:00"',

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- SEED DATA - Default features
-- ============================================
INSERT IGNORE INTO `features` (`id`, `name`, `advanced`) VALUES
(UUID(), 'Mobile application',    FALSE),
(UUID(), 'Desktop application',   FALSE),
(UUID(), 'Website widget',        FALSE),
(UUID(), 'Multilingual',          FALSE),
(UUID(), 'Custom design',         FALSE),
(UUID(), 'AI agent',              FALSE),
(UUID(), 'Live agent support',    FALSE),
(UUID(), 'Website application',   FALSE),
(UUID(), 'Ticket management',     FALSE),
(UUID(), 'Dashboard',             FALSE),
(UUID(), 'Interaction stats',     FALSE),
(UUID(), 'Performance overview',  FALSE),
(UUID(), 'File uploads',          FALSE),
(UUID(), 'Quick replies',         FALSE),
(UUID(), 'User feedback',         TRUE),
(UUID(), 'Location tracking',     TRUE),
(UUID(), 'Automation workflows',  TRUE),
(UUID(), 'Session logs',          TRUE);

-- ============================================

CREATE TABLE IF NOT EXISTS `chatbot_features` (
  `id` CHAR(36) NOT NULL COMMENT 'Record UUID, e.g., "e50e8400-e29b-41d4-a716-446655440009", "7g8h9i0j-1k2l-3m4n-5o6p-7q8r9s0t1u2v"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `feature_id` CHAR(36) NOT NULL COMMENT 'Feature UUID, e.g., "d50e8400-e29b-41d4-a716-446655440008", "6f7g8h9i-0j1k-2l3m-4n5o-6p7q8r9s0t1u"',

  -- Flexible value storage
  `boolean_value` BOOLEAN NULL DEFAULT TRUE COMMENT 'For yes/no features, e.g., TRUE, FALSE, NULL',
  `text_value` VARCHAR(500) NULL COMMENT 'For text features, e.g., "50+ languages supported", "GPT-4 and Claude models", "Widget, API, SDK"',
  `number_value` DECIMAL(10,2) NULL COMMENT 'For numeric features, e.g., 100.00, 50.00, 999.99',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Assigned at, e.g., "2025-01-15 13:00:00", "2024-12-20 09:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 14:30:00", "2025-01-28 10:00:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_chatbot_feature` (`chatbot_id`, `feature_id`),
  KEY `idx_feature_value` (`feature_id`, `boolean_value`),

  CONSTRAINT `fk_chatbot_features_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chatbot_features_feature` FOREIGN KEY (`feature_id`)
    REFERENCES `features` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE IF NOT EXISTS `integrations` (
  `id` CHAR(36) NOT NULL COMMENT 'Integration UUID, e.g., "f50e8400-e29b-41d4-a716-446655440010", "8h9i0j1k-2l3m-4n5o-6p7q-8r9s0t1u2v3w"',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Integration name, e.g., "Shopify", "WordPress", "Salesforce"',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-10 09:00:00", "2024-11-25 11:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 15:00:00", "2025-01-22 13:30:00"',

  PRIMARY KEY (`id`)
#   UNIQUE KEY `idx_slug` (`slug`),
#   KEY `idx_active` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE IF NOT EXISTS `chatbot_integrations` (
  `id` CHAR(36) NOT NULL COMMENT 'Record UUID, e.g., "050e8400-e29b-41d4-a716-446655440011", "9i0j1k2l-3m4n-5o6p-7q8r-9s0t1u2v3w4x"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `integration_id` CHAR(36) NOT NULL COMMENT 'Integration UUID, e.g., "f50e8400-e29b-41d4-a716-446655440010", "8h9i0j1k-2l3m-4n5o-6p7q-8r9s0t1u2v3w"',
  `is_supported` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is supported?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Added at, e.g., "2025-01-15 14:00:00", "2024-12-22 10:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 15:30:00", "2025-01-28 11:45:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_chatbot_integration` (`chatbot_id`, `integration_id`),
#   KEY `idx_integration_supported` (`integration_id`, `is_supported`),

  CONSTRAINT `fk_chatbot_integrations_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chatbot_integrations_integration` FOREIGN KEY (`integration_id`)
    REFERENCES `integrations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- RATINGS & REVIEWS
-- ============================================

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` CHAR(36) NOT NULL COMMENT 'Review UUID, e.g., "150e8400-e29b-41d4-a716-446655440012", "0j1k2l3m-4n5o-6p7q-8r9s-0t1u2v3w4x5y"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `user_id` CHAR(36) NOT NULL COMMENT 'Reviewer UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',

  -- Rating & Review
  `rating` TINYINT NOT NULL COMMENT 'Star rating (1-5), e.g., 5, 4, 3',
  `title` VARCHAR(200) NULL COMMENT 'Review title, e.g., "Great customer support tool!", "Not worth the price", "Perfect for small businesses"',
  `review_text` TEXT NULL COMMENT 'Review content, e.g., "We\'ve been using Intercom for 6 months and it has transformed our customer support...", "The AI features are impressive but the learning curve is steep", "Easy to set up and integrate with our website"',

  -- Moderation
  `status` ENUM('pending', 'approved', 'rejected', 'flagged', 'removed') NOT NULL DEFAULT 'pending' COMMENT 'Review status, e.g., "approved", "pending", "rejected"',
  `moderation_notes` TEXT NULL COMMENT 'Admin notes, e.g., "Approved after verification", "Rejected - contains spam links", "Flagged for inappropriate language"',
  `moderated_by_user_id` CHAR(36) NULL COMMENT 'Moderator UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", NULL',
  `moderated_at` TIMESTAMP NULL COMMENT 'Moderated at, e.g., "2025-01-16 10:00:00", "2025-01-20 14:30:00"',

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Review posted, e.g., "2025-01-15 16:00:00", "2024-12-28 11:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last edited, e.g., "2025-01-29 16:00:00", "2025-01-25 09:45:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_chatbot` (`chatbot_id`, `user_id`),
  KEY `idx_chatbot_status` (`chatbot_id`, `status`, `created_at`),
  KEY `idx_chatbot_rating` (`chatbot_id`, `rating`, `status`),
  KEY `idx_user_reviews` (`user_id`),
  KEY `idx_moderation` (`status`, `created_at`),

  CONSTRAINT `fk_reviews_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_moderator` FOREIGN KEY (`moderated_by_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_rating_range` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- FAVORITES
-- ============================================

CREATE TABLE IF NOT EXISTS `favorites` (
  `id` CHAR(36) NOT NULL COMMENT 'Favorite UUID, e.g., "350e8400-e29b-41d4-a716-446655440014", "2l3m4n5o-6p7q-8r9s-0t1u-2v3w4x5y6z7a"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `user_id` CHAR(36) NOT NULL COMMENT 'User UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `notes` TEXT NULL COMMENT 'Personal notes, e.g., "Want to try for our e-commerce store", "Great for customer support team", "Comparing with Drift and Zendesk"',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Favorited at, e.g., "2025-01-18 10:00:00", "2025-01-25 14:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Notes updated, e.g., "2025-01-29 16:30:00", "2025-01-28 12:15:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_chatbot` (`user_id`, `chatbot_id`),
  KEY `idx_chatbot` (`chatbot_id`),

  CONSTRAINT `fk_favorites_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================
-- CORE PAYMENT TABLES - WITH EXAMPLES
-- ============================================

CREATE TABLE IF NOT EXISTS `currencies` (
  `id` CHAR(36) NOT NULL COMMENT 'Currency UUID, e.g., "450e8400-e29b-41d4-a716-446655440015", "3m4n5o6p-7q8r-9s0t-1u2v-3w4x5y6z7a8b"',
  `code` CHAR(3) NOT NULL COMMENT 'ISO 4217, e.g., "USD", "EUR", "GBP"',
  `name` VARCHAR(100) NOT NULL COMMENT 'Currency name, e.g., "US Dollar", "Euro", "British Pound"',
  `symbol` VARCHAR(10) NOT NULL COMMENT 'Currency symbol, e.g., "$", "€", "£"',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-01 10:00:00", "2024-12-15 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 12:00:00", "2025-01-20 14:15:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE IF NOT EXISTS `billing_periods` (
  `id` CHAR(36) NOT NULL COMMENT 'Period UUID, e.g., "550e8400-e29b-41d4-a716-446655440016", "4n5o6p7q-8r9s-0t1u-2v3w-4x5y6z7a8b9c"',
  `type` ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
  `duration_days` INT NOT NULL COMMENT 'Duration in days',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-01 10:00:00", "2024-12-01 08:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 12:30:00", "2025-01-25 09:15:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_type_duration` (`type`, `duration_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LISTING PLANS (for chatbots without affiliate)
-- ============================================

CREATE TABLE IF NOT EXISTS `listing_plans` (
  `id` CHAR(36) NOT NULL COMMENT 'Plan UUID, e.g., "650e8400-e29b-41d4-a716-446655440017", "5o6p7q8r-9s0t-1u2v-3w4x-5y6z7a8b9c0d"',
  `name` VARCHAR(100) NOT NULL COMMENT 'Plan name, e.g., "Basic Listing", "Premium Listing", "Pro Listing"',

  `billing_period_id` CHAR(36) NOT NULL,
  `currency_id` CHAR(36) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount percentage, e.g., 0.00, 20.00, 15.50',

  -- Display
  `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',
#   `is_popular` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Popular badge?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-05 10:00:00", "2024-12-10 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 13:00:00", "2025-01-26 10:20:00"',
  `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete, e.g., "2025-01-28 16:00:00", NULL',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_active_order` (`deleted_at`, `is_active`, `display_order`),
  KEY `idx_billing_period` (`billing_period_id`),
  KEY `idx_currency` (`currency_id`),
  CONSTRAINT `chk_price` CHECK (`price` >= 0.00),
  CONSTRAINT `chk_discount` CHECK (`discount_percentage` BETWEEN 0.00 AND 100.00),
  CONSTRAINT `fk_listing_prices_period` FOREIGN KEY (`billing_period_id`)
    REFERENCES `billing_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_listing_prices_currency` FOREIGN KEY (`currency_id`)
    REFERENCES `currencies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROMOTIONS (active promotion campaigns)
-- ============================================

CREATE TABLE IF NOT EXISTS `promotions` (
  `id` CHAR(36) NOT NULL COMMENT 'Plan UUID, e.g., "650e8400-e29b-41d4-a716-446655440017", "5o6p7q8r-9s0t-1u2v-3w4x-5y6z7a8b9c0d"',
  `slot_type` ENUM('Homepage', 'Chatbots page') NOT NULL,

  `billing_period_id` CHAR(36) NOT NULL,
  `currency_id` CHAR(36) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount percentage, e.g., 0.00, 20.00, 15.50',

  -- Display
  `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-05 10:00:00", "2024-12-10 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 13:00:00", "2025-01-26 10:20:00"',
  `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete, e.g., "2025-01-28 16:00:00", NULL',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slot_period_currency` (`slot_type`, `billing_period_id`, `currency_id`),
  KEY `idx_active_order` (`deleted_at`, `is_active`, `display_order`),
  KEY `idx_billing_period` (`billing_period_id`),
  KEY `idx_currency` (`currency_id`),
  CONSTRAINT `chk_discount` CHECK (`discount_percentage` BETWEEN 0.00 AND 100.00),
  CONSTRAINT `chk_promotions_price` CHECK (`price` >= 0.00),
  CONSTRAINT `fk_promotions_period` FOREIGN KEY (`billing_period_id`)
    REFERENCES `billing_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_promotions_currency` FOREIGN KEY (`currency_id`)
    REFERENCES `currencies` (`id`) ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUBSCRIPTIONS (for both listings and promotions)
-- ============================================

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` CHAR(36) NOT NULL COMMENT 'Subscription UUID, e.g., "a50e8400-e29b-41d4-a716-446655440021", "9s0t1u2v-3w4x-5y6z-7a8b-9c0d1e2f3g4h"',
  `user_id` CHAR(36) NOT NULL COMMENT 'User UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',

  -- Type: listing OR promotion
  `subscription_type` ENUM('promotion') NOT NULL COMMENT 'Subscription type, only promotion supported',
  `promotions_id` CHAR(36) NULL,

  -- Status
  `status` ENUM('active', 'cancelled', 'expired', 'pending', 'past_due', 'trialing') NOT NULL DEFAULT 'pending',
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `trial_end_date` DATETIME NULL,
  `auto_renew` BOOLEAN NOT NULL DEFAULT TRUE,

  -- Cancellation
  `cancel_at_period_end` BOOLEAN NOT NULL DEFAULT FALSE,
  `cancelled_at` DATETIME NULL,
  `cancellation_reason` TEXT NULL COMMENT 'Cancellation reason, e.g., "Too expensive", "Not getting enough traffic", "Found better alternative"',

  -- Stripe Integration
  `stripe_subscription_id` VARCHAR(255) NULL UNIQUE COMMENT 'Stripe subscription ID, e.g., "sub_1OXKqYAbCD1234567890", "sub_1PQRSTab1234567890AB"',
  `stripe_customer_id` VARCHAR(255) NULL COMMENT 'Stripe customer ID, e.g., "cus_P1Q2R3S4T5U6V7W8", "cus_AbCdEfGhIjKlMnOp"',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-15 10:00:00", "2025-02-01 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 15:00:00", "2025-01-28 12:45:00"',

  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_chatbot_type_status` (`chatbot_id`, `subscription_type`, `status`),
  KEY `idx_expiring` (`status`, `end_date`),
  KEY `idx_active_chatbot` (`chatbot_id`, `status`, `end_date`),
  KEY `idx_stripe_customer` (`stripe_customer_id`),
  KEY `idx_promotions` (`promotions_id`),

  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subscriptions_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_promotion` FOREIGN KEY (`promotions_id`)
    REFERENCES `promotions` (`id`) ON DELETE RESTRICT,

  CONSTRAINT `chk_dates` CHECK (`end_date` > `start_date`),
  CONSTRAINT `chk_trial` CHECK (trial_end_date IS NULL OR trial_end_date > start_date),
  CONSTRAINT `chk_cancellation` CHECK (
    (status = 'cancelled' AND cancelled_at IS NOT NULL) OR
    (status != 'cancelled' AND cancelled_at IS NULL) OR
    (cancel_at_period_end = TRUE)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYMENTS
-- ============================================

CREATE TABLE IF NOT EXISTS `payments` (
  `id` CHAR(36) NOT NULL COMMENT 'Payment UUID, e.g., "c50e8400-e29b-41d4-a716-446655440023", "1u2v3w4x-5y6z-7a8b-9c0d-1e2f3g4h5i6j"',
  `user_id` CHAR(36) NOT NULL COMMENT 'User UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `subscription_id` CHAR(36) NULL COMMENT 'Subscription UUID (NULL for one-time), e.g., "a50e8400-e29b-41d4-a716-446655440021", NULL',
  `chatbot_id` CHAR(36) NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", NULL',

  -- Payment Details
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Payment amount, e.g., 29.99, 299.00, 49.99',
  `currency_id` CHAR(36) NOT NULL COMMENT 'Currency UUID, e.g., "450e8400-e29b-41d4-a716-446655440015", "3m4n5o6p-7q8r-9s0t-1u2v-3w4x5y6z7a8b"',
  `payment_method` ENUM('stripe', 'paypal', 'bank_transfer', 'crypto', 'other') NOT NULL COMMENT 'Payment method, e.g., "stripe", "paypal", "bank_transfer"',
  `status` ENUM('pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded', 'partially_refunded') NOT NULL DEFAULT 'pending' COMMENT 'Payment status, e.g., "succeeded", "pending", "failed"',

  -- Stripe Integration
  `stripe_payment_intent_id` VARCHAR(255) NULL COMMENT 'Stripe payment intent, e.g., "pi_3OXKqYAbCD1234567890", "pi_1PQRSTab1234567890AB"',
  `stripe_charge_id` VARCHAR(255) NULL COMMENT 'Stripe charge, e.g., "ch_3OXKqYAbCD1234567890", "ch_1PQRSTab1234567890AB"',
  `stripe_invoice_id` VARCHAR(255) NULL COMMENT 'Stripe invoice, e.g., "in_1OXKqYAbCD1234567890", "in_1PQRSTab1234567890AB"',

  -- Additional Info
  `transaction_id` VARCHAR(100) NULL COMMENT 'Internal transaction ID, e.g., "TXN_2025_001234", "PAY_20250129_ABC123", "INV_20250115_XYZ789"',
  `invoice_url` VARCHAR(500) NULL COMMENT 'Invoice PDF URL, e.g., "https://cdn.chatbotdir.com/invoices/2025/01/invoice_001234.pdf", "https://invoice.stripe.com/i/acct_1ABC/test_YWNjdF8x"',
  `receipt_url` VARCHAR(500) NULL COMMENT 'Receipt URL, e.g., "https://cdn.chatbotdir.com/receipts/2025/01/receipt_001234.pdf", "https://receipt.stripe.com/r/pi_3OXKqY"',

  -- Refund Information
  `refund_amount` DECIMAL(10,2) NULL COMMENT 'Refunded amount, e.g., 29.99, 14.99, NULL',
  `refund_reason` TEXT NULL COMMENT 'Refund reason, e.g., "Customer requested cancellation", "Service not delivered", "Duplicate payment"',
  `refunded_at` TIMESTAMP NULL COMMENT 'Refunded at, e.g., "2025-01-20 14:30:00", NULL',

  -- Failure Information
  `failure_code` VARCHAR(100) NULL COMMENT 'Failure code, e.g., "card_declined", "insufficient_funds", "expired_card"',
  `failure_message` TEXT NULL COMMENT 'Failure message, e.g., "Your card was declined", "Insufficient funds in account", "Card has expired"',

  -- Metadata
  `metadata` JSON NULL COMMENT 'Additional payment metadata, e.g., {"invoice_number": "INV-2025-001234", "customer_note": "Annual subscription"}, {"promo_code": "SAVE20", "campaign": "new_year_2025"}',
  `customer_ip` VARCHAR(45) NULL COMMENT 'Customer IP, e.g., "88.212.34.56", "192.168.1.100", "2001:718:1c01:1111::2"',
  `user_agent` TEXT NULL COMMENT 'User agent, e.g., "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0", "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)"',

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-15 12:00:00", "2025-02-01 10:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 16:00:00", "2025-01-28 14:15:00"',
  `succeeded_at` TIMESTAMP NULL COMMENT 'Succeeded at, e.g., "2025-01-15 12:05:00", "2025-02-01 10:35:00"',
  `failed_at` TIMESTAMP NULL COMMENT 'Failed at, e.g., "2025-01-15 12:02:00", NULL',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_stripe_payment_intent` (`stripe_payment_intent_id`),
  UNIQUE KEY `idx_transaction` (`transaction_id`),
  KEY `idx_user_status_date` (`user_id`, `status`, `created_at`),
  KEY `idx_subscription` (`subscription_id`),
  KEY `idx_chatbot` (`chatbot_id`),
  KEY `idx_status_date` (`status`, `created_at`),
  KEY `idx_currency` (`currency_id`),
  KEY `idx_stripe_invoice` (`stripe_invoice_id`),

  CONSTRAINT `chk_amount` CHECK (`amount` > 0.00),
  CONSTRAINT `chk_refund_amount` CHECK (
    `refund_amount` IS NULL OR
    (`refund_amount` > 0.00 AND `refund_amount` <= `amount`)
  ),
  CONSTRAINT `chk_refund_consistency` CHECK (
    (status IN ('refunded', 'partially_refunded')
      AND refund_amount IS NOT NULL
      AND refunded_at IS NOT NULL) OR
    (status NOT IN ('refunded', 'partially_refunded')
      AND refund_amount IS NULL
      AND refunded_at IS NULL)
  ),
  CONSTRAINT `chk_failure_consistency` CHECK (
    (status = 'failed' AND failure_code IS NOT NULL) OR
    (status != 'failed' AND failure_code IS NULL)
  ),
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`)
    REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_currency` FOREIGN KEY (`currency_id`)
    REFERENCES `currencies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- COMMUNICATION
-- ============================================

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` CHAR(36) NOT NULL COMMENT 'Message UUID, e.g., "f50e8400-e29b-41d4-a716-446655440026"',
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL COMMENT 'Sender email, e.g., "john.doe@gmail.com"',
  `subject` VARCHAR(300) NULL COMMENT 'Message subject, e.g., "Question about pricing"',
  `message` TEXT NOT NULL COMMENT 'Message content, e.g., "I would like to know more about your premium plans..."',


  -- Relations
  `user_id` CHAR(36) NULL COMMENT 'User UUID (if logged in), NULL if guest',


  -- Status & Assignment
  `status` ENUM('new', 'resolved', 'replied', 'spam') NOT NULL DEFAULT 'new',
  `helpful` BOOLEAN NOT NULL DEFAULT FALSE,
  `assigned_to_admin_id` CHAR(36) NULL COMMENT 'Assigned admin UUID, NULL if unassigned',

  -- Response
  `response` TEXT NULL COMMENT 'Admin response',
  `internal_notes` TEXT NULL COMMENT 'Internal notes',

  -- Tracking
  `ip_address` VARCHAR(45) NULL COMMENT 'Sender IP',

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `first_responded_at` TIMESTAMP NULL,
  `resolved_at` TIMESTAMP NULL,

  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`, `created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_assigned` (`assigned_to_admin_id`, `status`),
  KEY `idx_email` (`email`),

  CONSTRAINT `fk_contact_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contact_assigned` FOREIGN KEY (`assigned_to_admin_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT & LOGGING
-- ============================================

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` CHAR(36) NOT NULL COMMENT 'Log UUID, e.g., "250e8400-e29b-41d4-a716-446655440029", "7a8b9c0d-1e2f-3g4h-5i6j-7k8l9m0n1o2p"',
  `user_id` CHAR(36) NULL COMMENT 'User UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", NULL',

  -- Entity Information
  `entity_type` VARCHAR(50) NOT NULL COMMENT 'Entity type, e.g., "chatbot", "user", "payment", "review", "subscription"',
  `entity_id` VARCHAR(36) NOT NULL COMMENT 'Entity UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "550e8400-e29b-41d4-a716-446655440000"',
  `action` ENUM('created', 'updated', 'deleted', 'approved', 'rejected', 'suspended', 'restored', 'login', 'logout') NOT NULL COMMENT 'Action performed, e.g., "created", "updated", "approved"',

  -- Change Details
  `old_values` JSON NULL COMMENT 'Previous values, e.g., {"status": "pending", "is_verified": false}, {"price": 29.99, "discount": 0}',
  `new_values` JSON NULL COMMENT 'New values, e.g., {"status": "active", "is_verified": true}, {"price": 24.99, "discount": 20}',
  `changed_fields` JSON NULL COMMENT 'Changed field names, e.g., ["status", "is_verified"], ["price", "discount", "updated_at"]',

  -- Request Context
  `ip_address` VARCHAR(45) NULL COMMENT 'Request IP, e.g., "88.212.34.56", "195.113.20.150", "192.168.1.100"',
  `user_agent` VARCHAR(500) NULL COMMENT 'User agent, e.g., "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)"',
  `request_method` VARCHAR(10) NULL COMMENT 'HTTP method, e.g., "POST", "PUT", "DELETE"',
  `request_url` VARCHAR(1000) NULL COMMENT 'Request URL, e.g., "/api/chatbots/850e8400-e29b-41d4-a716-446655440003", "/admin/users/approve", "/api/subscriptions/create"',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Log timestamp, e.g., "2025-01-15 20:00:00", "2025-01-29 18:30:00"',

  PRIMARY KEY (`id`, `created_at`),
  KEY `idx_entity` (`entity_type`, `entity_id`, `created_at`),
  KEY `idx_user` (`user_id`, `created_at`),
  KEY `idx_action` (`action`, `created_at`),
  KEY `idx_date` (`created_at`)

  -- NOTE: Foreign keys removed due to partitioning constraint
  -- Application must handle referential integrity
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
  PARTITION p_2026_01 VALUES LESS THAN (UNIX_TIMESTAMP('2026-02-01')) COMMENT 'January 2025 partition, e.g., logs from 2025-01-01 to 2025-01-31',
  PARTITION p_2026_02 VALUES LESS THAN (UNIX_TIMESTAMP('2026-03-01')) COMMENT 'February 2025 partition, e.g., logs from 2025-02-01 to 2025-02-28',
  PARTITION p_2026_03 VALUES LESS THAN (UNIX_TIMESTAMP('2026-04-01')),
  PARTITION p_2026_04 VALUES LESS THAN (UNIX_TIMESTAMP('2026-05-01')),
  PARTITION p_2026_05 VALUES LESS THAN (UNIX_TIMESTAMP('2026-06-01')),
  PARTITION p_2026_06 VALUES LESS THAN (UNIX_TIMESTAMP('2026-07-01')),
  PARTITION p_2026_07 VALUES LESS THAN (UNIX_TIMESTAMP('2026-08-01')),
  PARTITION p_2026_08 VALUES LESS THAN (UNIX_TIMESTAMP('2026-09-01')),
  PARTITION p_2026_09 VALUES LESS THAN (UNIX_TIMESTAMP('2026-10-01')),
  PARTITION p_2026_10 VALUES LESS THAN (UNIX_TIMESTAMP('2026-11-01')),
  PARTITION p_2026_11 VALUES LESS THAN (UNIX_TIMESTAMP('2026-12-01')),
  PARTITION p_2026_12 VALUES LESS THAN (UNIX_TIMESTAMP('2027-01-01')),
  PARTITION p_2027_01 VALUES LESS THAN (UNIX_TIMESTAMP('2027-02-01')),
  PARTITION p_future VALUES LESS THAN MAXVALUE COMMENT 'Future logs partition, e.g., logs from 2025-03-01 onwards'
);

-- Pridanie marca
# ALTER TABLE audit_log REORGANIZE PARTITION p_future INTO (
#   PARTITION p_2025_03 VALUES LESS THAN (UNIX_TIMESTAMP('2025-04-01')),
#   PARTITION p_future VALUES LESS THAN MAXVALUE
# );

-- ============================================
-- RATE LIMITING
-- ============================================

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NULL,
  `email` VARCHAR(255) NULL,
  `action` VARCHAR(50) NOT NULL DEFAULT 'login',
  `attempts_count` INT NOT NULL DEFAULT 0,
  `first_attempt_at` TIMESTAMP NULL,
  `last_attempt_at` TIMESTAMP NULL,
  `locked_until` TIMESTAMP NULL,
  KEY `idx_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert system tags
INSERT IGNORE INTO `tags` (`id`, `name`, `is_system`) VALUES
('f5164f01-1cbc-11f1-85f8-8c8caa573ed8', 'Popular',   1),
('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'Sponsored', 1),
('49212cbd-1fbf-11f1-b158-8c8caa573ed8', 'Affiliate', 0);

-- Insert default currencies
INSERT IGNORE INTO `currencies` (`id`, `code`, `name`, `symbol`) VALUES
(UUID(), 'USD', 'US Dollar', '$'),
(UUID(), 'EUR', 'Euro', '€'),
(UUID(), 'GBP', 'British Pound', '£');

-- Insert billing periods
INSERT IGNORE INTO `billing_periods` (`id`, `type`, `duration_days`) VALUES
(UUID(), 'monthly', 30),
(UUID(), 'yearly', 365);

-- Insert listing plans
INSERT IGNORE INTO `listing_plans` (`id`, `name`, `billing_period_id`, `currency_id`, `price`) VALUES
(UUID(), 'Basic Listing',
  (SELECT `id` FROM `billing_periods` WHERE `type` = 'monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'USD'),
  9.99
),
(UUID(), 'Premium Listing',
  (SELECT `id` FROM `billing_periods` WHERE `type` = 'monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'USD'),
  29.99
);

INSERT IGNORE INTO `promotions` (`id`, `slot_type`, `billing_period_id`, `currency_id`, `price`) VALUES
(UUID(), 'Homepage',
  (SELECT `id` FROM `billing_periods` WHERE `type` = 'monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'EUR'),
  300
),
(UUID(), 'Chatbots page',
  (SELECT `id` FROM `billing_periods` WHERE `type` = 'monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'EUR'),
  300
);



-- ============================================
-- VIEWS AND TRIGGERS
-- ============================================

DELIMITER //

-- ============================================
-- FAVORITE COUNT TRIGGERS
-- ============================================

CREATE TRIGGER `trg_favorite_insert` AFTER INSERT ON `favorites`
FOR EACH ROW
BEGIN
  UPDATE `chatbots`
  SET `favorite_count` = `favorite_count` + 1,
      `updated_at` = CURRENT_TIMESTAMP
  WHERE `id` = NEW.chatbot_id;
END//

CREATE TRIGGER `trg_favorite_delete` AFTER DELETE ON `favorites`
FOR EACH ROW
BEGIN
  UPDATE `chatbots`
  SET `favorite_count` = GREATEST(`favorite_count` - 1, 0),
      `updated_at` = CURRENT_TIMESTAMP
  WHERE `id` = OLD.chatbot_id;
END//

-- ============================================
-- REVIEW / RATING TRIGGERS
-- ============================================

CREATE TRIGGER `trg_review_insert` AFTER INSERT ON `reviews`
FOR EACH ROW
BEGIN
  DECLARE new_count INT;
  DECLARE new_avg DECIMAL(3,2);

  IF NEW.status = 'approved' THEN
    -- Calculate new stats in one query
    SELECT COUNT(*), COALESCE(AVG(rating), 0)
    INTO new_count, new_avg
    FROM `reviews`
    WHERE chatbot_id = NEW.chatbot_id AND status = 'approved';

    -- Update chatbot
    UPDATE `chatbots`
    SET `rating_count` = new_count,
        `rating_average` = new_avg,
        `updated_at` = CURRENT_TIMESTAMP
    WHERE id = NEW.chatbot_id;
  END IF;
END//

CREATE TRIGGER `trg_review_update` AFTER UPDATE ON `reviews`
FOR EACH ROW
BEGIN
  DECLARE new_count INT;
  DECLARE new_avg DECIMAL(3,2);

  -- Only recalculate if status or rating changed
  IF OLD.status != NEW.status OR OLD.rating != NEW.rating THEN
    SELECT COUNT(*), COALESCE(AVG(rating), 0)
    INTO new_count, new_avg
    FROM `reviews`
    WHERE chatbot_id = NEW.chatbot_id AND status = 'approved';

    UPDATE `chatbots`
    SET `rating_count` = new_count,
        `rating_average` = new_avg,
        `updated_at` = CURRENT_TIMESTAMP
    WHERE id = NEW.chatbot_id;
  END IF;
END//

CREATE TRIGGER `trg_review_delete` AFTER DELETE ON `reviews`
FOR EACH ROW
BEGIN
  DECLARE new_count INT;
  DECLARE new_avg DECIMAL(3,2);

  IF OLD.status = 'approved' THEN
    SELECT COUNT(*), COALESCE(AVG(rating), 0)
    INTO new_count, new_avg
    FROM `reviews`
    WHERE chatbot_id = OLD.chatbot_id AND status = 'approved';

    UPDATE `chatbots`
    SET `rating_count` = new_count,
        `rating_average` = new_avg,
        `updated_at` = CURRENT_TIMESTAMP
    WHERE id = OLD.chatbot_id;
  END IF;
END//

-- ============================================
-- SUBSCRIPTION LIFECYCLE TRIGGERS
-- ============================================

-- Deactivate chatbot when listing subscription expires
CREATE TRIGGER `trg_subscription_expired` AFTER UPDATE ON `subscriptions`
FOR EACH ROW
BEGIN
  IF OLD.status != 'expired' AND NEW.status = 'expired' THEN
    IF NEW.subscription_type = 'listing' THEN
      UPDATE `chatbots`
      SET `status` = 'inactive',
          `updated_at` = CURRENT_TIMESTAMP
      WHERE `id` = NEW.chatbot_id
        AND `has_affiliate_program` = FALSE;
    END IF;
  END IF;
END//


DELIMITER ;

CREATE TABLE IF NOT EXISTS `admin_notification_emails` (
    `id`         VARCHAR(36)  NOT NULL,
    `email`      VARCHAR(254) NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- END OF SCHEMA
-- ============================================