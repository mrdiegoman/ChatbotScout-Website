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

CREATE TABLE `users` (
  `id` CHAR(36) NOT NULL COMMENT 'UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `email` VARCHAR(255) NOT NULL COMMENT 'User email, e.g., "john.doe@gmail.com", "maria.kovacova@azet.sk", "business@chatbotcompany.com"',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Argon2id hashed password, e.g., "$argon2id$v=19$m=65536,t=3,p=4$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG", "$argon2id$v=19$m=19456,t=2,p=1$kL7kJRMI3JxWD5mEZLzBsQ$jWY6NJ3aqNJzCXLBcE0nLg"',
  `google_id` VARCHAR(255) NULL UNIQUE COMMENT 'Google OAuth user ID',
  `terms_accepted` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'User accepted Terms of Service',
  `terms_accepted_at` TIMESTAMP NULL COMMENT 'When user accepted Terms of Service',
  `username` VARCHAR(50) NULL UNIQUE COMMENT 'Unique username, e.g., "johndoe123", "maria_k", "tech_guru_2025"',
  `first_name` VARCHAR(100) NULL COMMENT 'First name, e.g., "John", "Mária", "Peter"',
  `last_name` VARCHAR(100) NULL COMMENT 'Last name, e.g., "Doe", "Kováčová", "Novák"',
  `is_admin` BOOLEAN NOT NULL DEFAULT FALSE,


#   -- Affiliate Program
#   `is_affiliate` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Is user an affiliate?, e.g., TRUE, FALSE',
#   `affiliate_code` VARCHAR(20) NULL UNIQUE COMMENT 'User own affiliate code, e.g., "JOHN2025", "MARIA-REF", "TECH50"',
#   `referred_by_affiliate_code` VARCHAR(20) NULL COMMENT 'Affiliate code used during signup, e.g., "PETER123", "PROMO2025", "FRIEND-CODE"',

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
    UNIQUE KEY `idx_username` (`username`),
    KEY `idx_status_active` (`status`, `is_email_verified`),
    KEY `idx_marketing` (`marketing_emails`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE `user_sessions` (
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

CREATE TABLE `visitors` (
  `id` CHAR(36) NOT NULL COMMENT 'Visitor UUID, e.g., "750e8400-e29b-41d4-a716-446655440002", "9e2f3b4c-5d6e-7f8g-9h0i-1j2k3l4m5n6o"',
  `fingerprint` CHAR(64) NOT NULL COMMENT 'SHA-256 browser fingerprint hash, e.g., "ec523d037249f514096cff40a45559b7cd4f3e68eed204c3f4c312bf491cecbf"',
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

  `user_id` CHAR(36) NULL COMMENT 'Set when visitor registers, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `converted_at` TIMESTAMP NULL COMMENT 'Registration time, e.g., "2025-01-29 14:30:00", "2025-01-28 10:15:00"',

  `first_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'First visit, e.g., "2025-01-20 08:00:00", "2025-01-15 12:30:00"',
  `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last visit, e.g., "2025-01-29 12:45:00", "2025-01-29 09:20:00"',
  `expires_at` TIMESTAMP NULL DEFAULT NULL ,

  PRIMARY KEY (`id`),
  KEY `idx_fingerprint` (`fingerprint`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_utm_source_campaign` (`utm_source`, `utm_campaign`),

  CONSTRAINT `fk_visitors_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Settings table
-- Global key-value store pre site-wide nastavenia
-- ============================================

CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         DEFAULT NULL,
    `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default hodnoty
INSERT INTO `settings` (`key`, `value`) VALUES
    ('reply_from_email', 'noreply@chatbothub.com'),
    ('reply_from_name',  'ChatbotHub Support')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ============================================
-- CHATBOT LISTINGS (WEBSITES)
-- ============================================

CREATE TABLE `chatbots` (
  `id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `owner_user_id` CHAR(36) NULL COMMENT 'Owner UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `verified_reviewer` BOOLEAN NULL default FALSE,

  -- Basic Info
  `name` VARCHAR(200) NOT NULL COMMENT 'Chatbot/company name, e.g., "Intercom", "Drift", "Tidio"',
#   `slug` VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "intercom", "drift-conversational-ai", "tidio-live-chat"',
  `website_url` VARCHAR(500) NOT NULL COMMENT 'Official website, e.g., "https://www.intercom.com", "https://www.drift.com", "https://www.tidio.com"',
  `description` VARCHAR(150) NULL COMMENT 'Full description, e.g., "Intercom is a complete customer service solution that provides seamless communication...", "AI-powered conversational platform for sales and marketing teams", "Live chat widget with chatbot automation for customer support"',
#   `tagline` VARCHAR(200) NULL COMMENT 'Short tagline, e.g., "The complete customer service solution", "Conversational AI for modern teams", "Chat with your customers in real-time"',

  -- Media
  `logo_url` VARCHAR(500) NULL COMMENT 'Logo image, e.g., "https://cdn.chatbotdir.com/logos/intercom.png", "https://storage.googleapis.com/chatbots/drift-logo.svg", "https://assets.tidio.com/logo.png"',
#   `cover_image_url` VARCHAR(500) NULL COMMENT 'Cover/hero image, e.g., "https://cdn.chatbotdir.com/covers/intercom-dashboard.jpg", "https://images.drift.com/cover-hero.png", "https://tidio.com/images/dashboard-screenshot.jpg"',
#   `demo_url` VARCHAR(500) NULL COMMENT 'Demo link, e.g., "https://www.intercom.com/demo", "https://app.drift.com/try-demo", "https://www.tidio.com/panel/demo"',

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
  `status` ENUM('pending', 'active', 'reviewed', 'suspended', 'rejected', 'archived') NOT NULL DEFAULT 'pending' COMMENT 'Listing status, e.g., "active", "pending", "suspended"',
  `is_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Manually verified by admin, e.g., TRUE, FALSE',
  `is_featured` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Featured globally, e.g., TRUE, FALSE',
  `is_sponsored` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Paid promotion, e.g., TRUE, FALSE',
  `rejection_reason` TEXT NULL COMMENT 'Rejection reason, e.g., "Incomplete information provided", "Duplicate listing", "Website not accessible"',

  -- Denormalized Stats (updated via triggers/scheduled jobs)
#   `view_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total views, e.g., 1523, 45892, 234',
  `click_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total clicks, e.g., 456, 12345, 89',
  `favorite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total favorites, e.g., 78, 1234, 12',
  `rating_average` DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Average rating, e.g., 4.50, 3.80, 4.95',
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of ratings, e.g., 234, 56, 1892',
  `conversion_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Affiliate conversions, e.g., 45, 123, 8',

  -- SEO
#   `meta_title` VARCHAR(255) NULL COMMENT 'SEO title, e.g., "Intercom - Best Customer Service Chatbot Platform", "Drift AI Chatbot for Sales Teams | ChatbotDirectory", "Tidio Live Chat Widget Review"',
#   `meta_description` VARCHAR(500) NULL COMMENT 'SEO description, e.g., "Discover why Intercom is the #1 customer service solution with AI-powered chat...", "Learn about Drift\'s conversational AI platform for sales and marketing automation", "Compare Tidio features, pricing, and integrations"',
#   `meta_keywords` VARCHAR(500) NULL COMMENT 'SEO keywords, e.g., "customer service chatbot, ai support, live chat", "conversational ai, sales automation, marketing chatbot", "live chat widget, customer support, chatbot integration"',

  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record created, e.g., "2025-01-15 10:00:00", "2024-12-01 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modified, e.g., "2025-01-29 12:45:00", "2025-01-28 14:20:00"',
  `submitted_at` TIMESTAMP NULL COMMENT 'Submitted for review, e.g., "2025-01-15 10:30:00", "2024-12-01 09:00:00"',
  `approved_at` TIMESTAMP NULL COMMENT 'Approved by admin, e.g., "2025-01-16 14:00:00", "2024-12-02 11:30:00"',
  `last_verified_at` TIMESTAMP NULL COMMENT 'Last verification, e.g., "2025-01-20 16:00:00", "2025-01-10 10:00:00"',
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


-- ============================================
-- TAGS MIGRATION
-- ============================================

CREATE TABLE IF NOT EXISTS `tags` (
  `id` CHAR(36) NOT NULL COMMENT 'Tag UUID',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Tag name, e.g., "Popular", "Affiliate"',
#   `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "popular", "affiliate"',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`)
#   UNIQUE KEY `idx_slug` (`slug`)
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
-- SEED DATA - Default tags
-- ============================================

INSERT IGNORE INTO `tags` (`id`, `name`) VALUES
  (UUID(), 'Popular'),
  (UUID(), 'Affiliate');

-- ============================================
-- COMMENTED OUT - CATEGORIES TABLE
-- ============================================

# CREATE TABLE `categories` (
#   `id` CHAR(36) NOT NULL COMMENT 'Category UUID, e.g., "950e8400-e29b-41d4-a716-446655440004", "2b3c4d5e-6f7g-8h9i-0j1k-2l3m4n5o6p7q"',
#   `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Category name, e.g., "Customer Support", "E-commerce", "Sales & Marketing"',
#   `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "customer-support", "ecommerce", "sales-marketing"',
#   `description` TEXT NULL COMMENT 'Category description, e.g., "Chatbots focused on customer service and support automation", "AI assistants for online stores and shopping experiences", "Conversational tools for sales teams and lead generation"',
#   `icon_name` VARCHAR(50) NULL COMMENT 'Icon identifier, e.g., "headset", "shopping-cart", "trending-up"',
#   `color_hex` CHAR(7) NULL COMMENT 'Category color #RRGGBB, e.g., "#3B82F6", "#10B981", "#F59E0B"',
#   `parent_category_id` CHAR(36) NULL COMMENT 'For subcategories, e.g., "950e8400-e29b-41d4-a716-446655440004", NULL',
#   `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',
#   `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',
#   `chatbot_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Denormalized count, e.g., 45, 128, 7',
#
#   `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-10 10:00:00", "2024-11-15 08:30:00"',
#   `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 12:00:00", "2025-01-20 14:15:00"',
#
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_slug` (`slug`),
#   KEY `idx_active` (`is_active`, `display_order`),
#   KEY `idx_parent` (`parent_category_id`),
#
#   CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_category_id`)
#     REFERENCES `categories` (`id`) ON DELETE SET NULL
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- COMMENTED OUT - CHATBOT CATEGORIES TABLE
-- ============================================

# CREATE TABLE `chatbot_categories` (
#   `id` CHAR(36) NOT NULL COMMENT 'Record UUID, e.g., "a50e8400-e29b-41d4-a716-446655440005", "3c4d5e6f-7g8h-9i0j-1k2l-3m4n5o6p7q8r"',
#   `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
#   `category_id` CHAR(36) NOT NULL COMMENT 'Category UUID, e.g., "950e8400-e29b-41d4-a716-446655440004", "2b3c4d5e-6f7g-8h9i-0j1k-2l3m4n5o6p7q"',
#   `is_primary` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Is primary category?, e.g., TRUE, FALSE',
#   `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Assigned at, e.g., "2025-01-15 11:00:00", "2024-12-05 09:30:00"',
#
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_chatbot_category` (`chatbot_id`, `category_id`),
#   KEY `idx_category_listings` (`category_id`, `is_primary`),
#
#   CONSTRAINT `fk_chatbot_categories_chatbot` FOREIGN KEY (`chatbot_id`)
#     REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
#   CONSTRAINT `fk_chatbot_categories_category` FOREIGN KEY (`category_id`)
#     REFERENCES `categories` (`id`) ON DELETE RESTRICT
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE `chatbot_cards` (
  `id` CHAR(36) NOT NULL COMMENT 'Card UUID, e.g., "b50e8400-e29b-41d4-a716-446655440006", "4d5e6f7g-8h9i-0j1k-2l3m-4n5o6p7q8r9s"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `image_url` VARCHAR(500) NOT NULL COMMENT 'Screenshot/card image, e.g., "https://cdn.chatbotdir.com/screens/intercom-chat-1.jpg", "https://storage.googleapis.com/chatbots/drift-demo-screen.png", "https://assets.tidio.com/gallery/widget-preview.jpg"',
  `caption` VARCHAR(255) NULL COMMENT 'Image caption, e.g., "Live chat interface with AI responses", "Dashboard analytics view", "Mobile widget customization"',
  `alt_text` VARCHAR(255) NULL COMMENT 'Alt text for accessibility, e.g., "Intercom live chat widget showing conversation", "Drift analytics dashboard screenshot", "Tidio mobile chat interface"',
  `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',
  `is_primary` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Primary image?, e.g., TRUE, FALSE',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Uploaded at, e.g., "2025-01-15 12:00:00", "2024-12-10 10:30:00"',

  PRIMARY KEY (`id`),
  KEY `idx_chatbot_order` (`chatbot_id`, `display_order`),

  CONSTRAINT `fk_cards_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE `Social_links` (
  `id` CHAR(36) NOT NULL COMMENT 'Link UUID, e.g., "c50e8400-e29b-41d4-a716-446655440007", "5e6f7g8h-9i0j-1k2l-3m4n-5o6p7q8r9s0t"',
#   `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `platform` ENUM('twitter', 'facebook', 'linkedin', 'instagram', 'youtube', 'github', 'discord', 'other') NOT NULL COMMENT 'Social platform, e.g., "twitter", "linkedin", "github"',
  `url` VARCHAR(500) NOT NULL COMMENT 'Social profile URL, e.g., "https://twitter.com/intercom", "https://www.linkedin.com/company/drift", "https://github.com/tidio"',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Added at, e.g., "2025-01-15 12:30:00", "2024-12-15 11:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 13:00:00", "2025-01-20 09:45:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_chatbot_platform` (`platform`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FEATURES & INTEGRATIONS
-- ============================================

CREATE TABLE `features` (
  `id` CHAR(36) NOT NULL COMMENT 'Feature UUID, e.g., "d50e8400-e29b-41d4-a716-446655440008", "6f7g8h9i-0j1k-2l3m-4n5o-6p7q8r9s0t1u"',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Feature name, e.g., "Live Chat", "AI Responses", "Multi-language Support"',
  `advanced` BOOLEAN NOT NULL DEFAULT FALSE,
#   `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "live-chat", "ai-responses", "multi-language-support"',
#   `description` TEXT NULL COMMENT 'Feature description, e.g., "Real-time chat with customers through widget", "Automated AI-powered response generation", "Support for 50+ languages with auto-translation"',
#   `category` ENUM('core_features', 'ai_capabilities', 'integrations', 'support', 'pricing', 'security', 'analytics', 'customization') NOT NULL COMMENT 'Feature category, e.g., "core_features", "ai_capabilities", "customization"',
#   `feature_type` ENUM('boolean', 'text', 'number', 'multi_select') NOT NULL DEFAULT 'boolean' COMMENT 'Value type, e.g., "boolean", "text", "number"',
#   `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',
#   `is_filterable` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Show in filters?, e.g., TRUE, FALSE',
#   `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',
#   `icon_name` VARCHAR(50) NULL COMMENT 'Icon identifier, e.g., "message-circle", "brain", "globe"',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-10 08:00:00", "2024-11-20 10:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 14:00:00", "2025-01-25 11:15:00"',

  PRIMARY KEY (`id`)
#   UNIQUE KEY `idx_slug` (`slug`),
#   KEY `idx_active_filterable` (`is_active`, `is_filterable`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- SEED DATA - Default features
-- ============================================
Insert IGNORE into features (id, name, description,advanced)
value (UUID(),'Mobile application',NULL,FALSE),
    (UUID(),'Desktop application',NULL,FALSE),
    (UUID(),'Website widget',NULL,FALSE),
    (UUID(),'Multilingual',NULL,FALSE),
    (UUID(),'Custom design',NULL,FALSE),
    (UUID(),'AI agent',NULL,FALSE),
    (UUID(),'Live agent support',NULL,FALSE),
    (UUID(),'Website application',NULL,FALSE),
    (UUID(),'Ticket management',NULL,FALSE),
    (UUID(),'Dashboard',NULL,FALSE),
    (UUID(),'Interaction stats',NULL,FALSE),
    (UUID(),'Performance overview',NULL,FALSE),
    (UUID(),'File uploads',NULL,FALSE),
    (UUID(),'Quick replies',NULL,FALSE),
    (UUID(),'User feedback',NULL, TRUE),
    (UUID(),'Location tracking',NULL, TRUE),
    (UUID(),'Automation workflows',NULL, TRUE),
    (UUID(),'Session logs',NULL, TRUE);

-- ============================================

CREATE TABLE `chatbot_features` (
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

CREATE TABLE `integrations` (
  `id` CHAR(36) NOT NULL COMMENT 'Integration UUID, e.g., "f50e8400-e29b-41d4-a716-446655440010", "8h9i0j1k-2l3m-4n5o-6p7q-8r9s0t1u2v3w"',
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Integration name, e.g., "Shopify", "WordPress", "Salesforce"',
#   `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "shopify", "wordpress", "salesforce"',
#   `logo_url` VARCHAR(500) NULL COMMENT 'Logo image, e.g., "https://cdn.chatbotdir.com/integrations/shopify.png", "https://assets.wordpress.org/logo.svg", "https://cdn.salesforce.com/logo.png"',
#   `website_url` VARCHAR(500) NULL COMMENT 'Official website, e.g., "https://www.shopify.com", "https://wordpress.org", "https://www.salesforce.com"',
#   `description` TEXT NULL COMMENT 'Integration description, e.g., "E-commerce platform for online stores", "Open-source content management system", "CRM and sales automation platform"',
#   `category` ENUM('cms', 'ecommerce', 'crm', 'marketing', 'analytics', 'communication', 'productivity', 'other') NULL COMMENT 'Integration category, e.g., "ecommerce", "cms", "crm"',
#   `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',
#   `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-10 09:00:00", "2024-11-25 11:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 15:00:00", "2025-01-22 13:30:00"',

  PRIMARY KEY (`id`)
#   UNIQUE KEY `idx_slug` (`slug`),
#   KEY `idx_active` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE `chatbot_integrations` (
  `id` CHAR(36) NOT NULL COMMENT 'Record UUID, e.g., "050e8400-e29b-41d4-a716-446655440011", "9i0j1k2l-3m4n-5o6p-7q8r-9s0t1u2v3w4x"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `integration_id` CHAR(36) NOT NULL COMMENT 'Integration UUID, e.g., "f50e8400-e29b-41d4-a716-446655440010", "8h9i0j1k-2l3m-4n5o-6p7q-8r9s0t1u2v3w"',
  `is_supported` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is supported?, e.g., TRUE, FALSE',
#   `is_native` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Native vs third-party integration, e.g., TRUE (native/built-in), FALSE (via Zapier/API)',
#   `difficulty` ENUM('easy', 'medium', 'advanced') NULL COMMENT 'Setup difficulty, e.g., "easy", "medium", "advanced"',
#   `setup_time_minutes` INT NULL COMMENT 'Setup time estimate, e.g., 5, 30, 120',
#   `documentation_url` VARCHAR(500) NULL COMMENT 'Setup guide URL, e.g., "https://help.intercom.com/shopify-integration", "https://docs.drift.com/wordpress-setup", "https://support.tidio.com/salesforce-guide"',
#   `notes` TEXT NULL COMMENT 'Additional notes, e.g., "Requires Pro plan or higher", "Supports custom fields mapping", "Real-time sync available"',

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

CREATE TABLE `reviews` (
  `id` CHAR(36) NOT NULL COMMENT 'Review UUID, e.g., "150e8400-e29b-41d4-a716-446655440012", "0j1k2l3m-4n5o-6p7q-8r9s-0t1u2v3w4x5y"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
  `user_id` CHAR(36) NOT NULL COMMENT 'Reviewer UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',

  -- Rating & Review
  `rating` TINYINT NOT NULL COMMENT 'Star rating (1-5), e.g., 5, 4, 3',
  `title` VARCHAR(200) NULL COMMENT 'Review title, e.g., "Great customer support tool!", "Not worth the price", "Perfect for small businesses"',
  `review_text` TEXT NULL COMMENT 'Review content, e.g., "We\'ve been using Intercom for 6 months and it has transformed our customer support...", "The AI features are impressive but the learning curve is steep", "Easy to set up and integrate with our website"',
#   `pros` TEXT NULL COMMENT 'Positive points, e.g., "Easy to use, Great AI responses, Excellent mobile app", "Fast setup, Good integrations", "Affordable pricing, Helpful support team"',
#   `cons` TEXT NULL COMMENT 'Negative points, e.g., "Expensive for small teams, Limited customization", "Complex pricing structure", "Occasional bugs in mobile app"',

  -- Verification
#   `is_verified_purchase` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Verified buyer?, e.g., TRUE, FALSE',
#   `purchase_verification_token` VARCHAR(255) NULL COMMENT 'Verification token, e.g., "verify_purchase_abc123def456", "token_verified_2025_xyz789"',

  -- Engagement
#   `helpful_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Helpful votes, e.g., 45, 123, 7',
#   `not_helpful_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Not helpful votes, e.g., 3, 12, 1',

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

# CREATE TABLE `review_helpfulness` (
#   `id` CHAR(36) NOT NULL COMMENT 'Vote UUID, e.g., "250e8400-e29b-41d4-a716-446655440013", "1k2l3m4n-5o6p-7q8r-9s0t-1u2v3w4x5y6z"',
#   `review_id` CHAR(36) NOT NULL COMMENT 'Review UUID, e.g., "150e8400-e29b-41d4-a716-446655440012", "0j1k2l3m-4n5o-6p7q-8r9s-0t1u2v3w4x5y"',
#   `user_id` CHAR(36) NOT NULL COMMENT 'Voter UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
#   `is_helpful` BOOLEAN NOT NULL COMMENT 'Vote value, e.g., TRUE (helpful), FALSE (not helpful)',
#   `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Voted at, e.g., "2025-01-16 12:00:00", "2025-01-22 15:30:00"',
#
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_review_user` (`review_id`, `user_id`),
#   KEY `idx_user` (`user_id`),
#
#   CONSTRAINT `fk_helpfulness_review` FOREIGN KEY (`review_id`)
#     REFERENCES `reviews` (`id`) ON DELETE CASCADE,
#   CONSTRAINT `fk_helpfulness_user` FOREIGN KEY (`user_id`)
#     REFERENCES `users` (`id`) ON DELETE CASCADE
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FAVORITES
-- ============================================

CREATE TABLE `favorites` (
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

CREATE TABLE `currencies` (
  `id` CHAR(36) NOT NULL COMMENT 'Currency UUID, e.g., "450e8400-e29b-41d4-a716-446655440015", "3m4n5o6p-7q8r-9s0t-1u2v-3w4x5y6z7a8b"',
  `code` CHAR(3) NOT NULL COMMENT 'ISO 4217, e.g., "USD", "EUR", "GBP"',
  `name` VARCHAR(100) NOT NULL COMMENT 'Currency name, e.g., "US Dollar", "Euro", "British Pound"',
  `symbol` VARCHAR(10) NOT NULL COMMENT 'Currency symbol, e.g., "$", "€", "£"',
#   `decimal_places` TINYINT NOT NULL DEFAULT 2 COMMENT 'Decimal places, e.g., 2, 0, 3',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-01 10:00:00", "2024-12-15 08:30:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 12:00:00", "2025-01-20 14:15:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================

CREATE TABLE `billing_periods` (
  `id` CHAR(36) NOT NULL COMMENT 'Period UUID, e.g., "550e8400-e29b-41d4-a716-446655440016", "4n5o6p7q-8r9s-0t1u-2v3w-4x5y6z7a8b9c"',
#   `name` VARCHAR(50) NOT NULL COMMENT 'Period name, e.g., "Monthly", "Annually", "Quarterly"',
#   `slug` VARCHAR(50) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "monthly", "annually", "quarterly"',
  `type` ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
  `duration_days` INT NOT NULL COMMENT 'Duration in days',
#   `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount percentage, e.g., 0.00, 20.00, 15.50',
#   `display_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, e.g., 1, 2, 3',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-01 10:00:00", "2024-12-01 08:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 12:30:00", "2025-01-25 09:15:00"',

  PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_name` (`name`),
  UNIQUE KEY `idx_type_duration` (`type`, `duration`)
#   CONSTRAINT `chk_discount` CHECK (`discount_percentage` BETWEEN 0.00 AND 100.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- LISTING PLANS (for chatbots without affiliate)
-- ============================================

CREATE TABLE `listing_plans` (
  `id` CHAR(36) NOT NULL COMMENT 'Plan UUID, e.g., "650e8400-e29b-41d4-a716-446655440017", "5o6p7q8r-9s0t-1u2v-3w4x-5y6z7a8b9c0d"',
  `name` VARCHAR(100) NOT NULL COMMENT 'Plan name, e.g., "Basic Listing", "Premium Listing", "Pro Listing"',
#   `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "basic-listing", "premium-listing", "pro-listing"',
#   `description` TEXT NULL COMMENT 'Plan description, e.g., "Get your chatbot listed on our directory with basic features", "Premium visibility with featured badge and priority placement", "Maximum exposure with homepage banner and verified badge"',

  -- Listing Features
#   `max_screenshots` TINYINT NOT NULL DEFAULT 3 COMMENT 'Max screenshot images, e.g., 3, 5, 10',
#   `allows_video` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Video allowed?, e.g., TRUE, FALSE',
#   `priority_support` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Priority support?, e.g., TRUE, FALSE',
#   `verified_badge` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Verified badge?, e.g., TRUE, FALSE',
#   `featured_in_category` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Featured in category?, e.g., TRUE, FALSE',
#   `homepage_visibility` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Homepage visibility?, e.g., TRUE, FALSE',

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

# CREATE TABLE `listing_plan_prices` (
#   `id` CHAR(36) NOT NULL COMMENT 'Price UUID, e.g., "750e8400-e29b-41d4-a716-446655440018", "6p7q8r9s-0t1u-2v3w-4x5y-6z7a8b9c0d1e"',
#   `plan_id` CHAR(36) NOT NULL COMMENT 'Plan UUID, e.g., "650e8400-e29b-41d4-a716-446655440017", "5o6p7q8r-9s0t-1u2v-3w4x-5y6z7a8b9c0d"',
#   `billing_period_id` CHAR(36) NOT NULL COMMENT 'Period UUID, e.g., "550e8400-e29b-41d4-a716-446655440016", "4n5o6p7q-8r9s-0t1u-2v3w-4x5y6z7a8b9c"',
#   `currency_id` CHAR(36) NOT NULL COMMENT 'Currency UUID, e.g., "450e8400-e29b-41d4-a716-446655440015", "3m4n5o6p-7q8r-9s0t-1u2v-3w4x5y6z7a8b"',
#   `price` DECIMAL(10,2) NOT NULL COMMENT 'Current price, e.g., 29.99, 299.00, 49.99',
#   `original_price` DECIMAL(10,2) NULL COMMENT 'Strikethrough pricing, e.g., 39.99, 399.00, 69.99',
#
#   `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-05 11:00:00", "2024-12-10 09:00:00"',
#   `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 13:30:00", "2025-01-27 11:45:00"',
#
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_plan_period_currency` (`plan_id`, `billing_period_id`, `currency_id`),
#   KEY `idx_plan` (`plan_id`),
#   KEY `idx_billing_period` (`billing_period_id`),
#   KEY `idx_currency` (`currency_id`),
#
#   CONSTRAINT `fk_listing_prices_plan` FOREIGN KEY (`plan_id`)
#     REFERENCES `listing_plans` (`id`) ON DELETE CASCADE,
#   CONSTRAINT `fk_listing_prices_period` FOREIGN KEY (`billing_period_id`)
#     REFERENCES `billing_periods` (`id`) ON DELETE RESTRICT,
#   CONSTRAINT `fk_listing_prices_currency` FOREIGN KEY (`currency_id`)
#     REFERENCES `currencies` (`id`) ON DELETE RESTRICT
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROMOTIONS (active promotion campaigns)
-- ============================================

CREATE TABLE `promotions` (
  `id` CHAR(36) NOT NULL COMMENT 'Plan UUID, e.g., "650e8400-e29b-41d4-a716-446655440017", "5o6p7q8r-9s0t-1u2v-3w4x-5y6z7a8b9c0d"',
#   `name` VARCHAR(100) NOT NULL COMMENT 'Plan name, e.g., "Basic Listing", "Premium Listing", "Pro Listing"',
  `slot_type` ENUM('Homepage', 'Chatbots page') NOT NULL,

#   `slug` VARCHAR(150) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "basic-listing", "premium-listing", "pro-listing"',
#   `description` TEXT NULL COMMENT 'Plan description, e.g., "Get your chatbot listed on our directory with basic features", "Premium visibility with featured badge and priority placement", "Maximum exposure with homepage banner and verified badge"',

  -- Listing Features
#   `max_screenshots` TINYINT NOT NULL DEFAULT 3 COMMENT 'Max screenshot images, e.g., 3, 5, 10',
#   `allows_video` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Video allowed?, e.g., TRUE, FALSE',
#   `priority_support` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Priority support?, e.g., TRUE, FALSE',
#   `verified_badge` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Verified badge?, e.g., TRUE, FALSE',
#   `featured_in_category` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Featured in category?, e.g., TRUE, FALSE',
#   `homepage_visibility` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Homepage visibility?, e.g., TRUE, FALSE',

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
-- PROMOTION SLOTS
-- ============================================

CREATE TABLE `promotion_slots` (
  `id` CHAR(36) NOT NULL COMMENT 'Slot UUID, e.g., "850e8400-e29b-41d4-a716-446655440019", "7q8r9s0t-1u2v-3w4x-5y6z-7a8b9c0d1e2f"',
  `name` VARCHAR(100) NOT NULL COMMENT 'Slot name, e.g., "Homepage Hero", "Category Top Banner", "Sidebar Featured"',
  `subscription_id` CHAR(36) NOT NULL,
#   `identifier` VARCHAR(150) NOT NULL UNIQUE COMMENT 'Unique identifier, e.g., "homepage_hero", "category_top", "sidebar_featured"',
#   `page_location` VARCHAR(100) NULL COMMENT 'Page location, e.g., "homepage", "category_customer_support", "search_results"',
  `position` INT NOT NULL COMMENT 'Display position (1 = top), e.g., 1, 2, 3',
#   `max_concurrent` TINYINT NOT NULL DEFAULT 1 COMMENT 'Max concurrent promotions, e.g., 1, 3, 5',
#   `description` TEXT NULL COMMENT 'Slot description, e.g., "Large hero banner at the top of homepage", "Featured position in category listing", "Sidebar widget visible on all pages"',
#   `dimensions` VARCHAR(50) NULL COMMENT 'Recommended dimensions, e.g., "1200x300", "300x250", "728x90"',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Is active?, e.g., TRUE, FALSE',

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-05 12:00:00", "2024-12-12 10:00:00"',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 14:00:00", "2025-01-28 09:30:00"',

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_promotion_position` (`subscription_id`, `position`),
  KEY `idx_type_active_position` (`is_active`, `position`),
  KEY `idx_promotions` (`subscription_id`),
  CONSTRAINT `chk_position` CHECK (`position` >= 1),
  CONSTRAINT `fk_promotion_slots_promotion` FOREIGN KEY (`subscription_id`)
    REFERENCES `promotions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUBSCRIPTIONS (for both listings and promotions)
-- ============================================

CREATE TABLE `subscriptions` (
  `id` CHAR(36) NOT NULL COMMENT 'Subscription UUID, e.g., "a50e8400-e29b-41d4-a716-446655440021", "9s0t1u2v-3w4x-5y6z-7a8b-9c0d1e2f3g4h"',
  `user_id` CHAR(36) NOT NULL COMMENT 'User UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
  `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',

  -- Type: listing OR promotion
  `subscription_type` ENUM('promotion') NOT NULL COMMENT 'Subscription type, only promotion supported',
#   `listing_plan_id` CHAR(36) NULL,
  `promotions_id` CHAR(36) NULL,
  -- For listing subscriptions
#   `listing_price_id` CHAR(36) NULL COMMENT 'Listing price UUID (NULL if promotion), e.g., "750e8400-e29b-41d4-a716-446655440018", NULL',



  -- For promotion subscriptions
#   `promotion_slot_price_id` CHAR(36) NULL COMMENT 'Promotion price UUID (NULL if listing), e.g., "950e8400-e29b-41d4-a716-446655440020", NULL',

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
  KEY `idx_stripe_customer` (`stripe_customer_id`),
#   KEY `idx_listing_plan` (`listing_plan_id`),
  KEY `idx_promotions` (`promotions_id`),

  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subscriptions_chatbot` FOREIGN KEY (`chatbot_id`)
    REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
#   CONSTRAINT `fk_subscriptions_listing_plan` FOREIGN KEY (`listing_plan_id`)
#     REFERENCES `listing_plans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subscriptions_promotion` FOREIGN KEY (`promotions_id`)
    REFERENCES `promotions` (`id`) ON DELETE RESTRICT,

  CONSTRAINT `chk_dates` CHECK (`end_date` > `start_date`),
  CONSTRAINT `chk_trial` CHECK (trial_end_date IS NULL OR trial_end_date > start_date),
#   CONSTRAINT `chk_subscription_type_ids` CHECK (
#     (`subscription_type` = 'listing' AND `listing_plan_id` IS NOT NULL AND `promotions_id` IS NULL) OR
#     (`subscription_type` = 'promotion' AND `promotions_id` IS NOT NULL AND `listing_plan_id` IS NULL)
#   ),
  CONSTRAINT `chk_cancellation` CHECK (
    (status = 'cancelled' AND cancelled_at IS NOT NULL) OR
    (status != 'cancelled' AND cancelled_at IS NULL) OR
    (cancel_at_period_end = TRUE)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYMENTS
-- ============================================

CREATE TABLE `payments` (
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
-- AFFILIATE TRACKING
-- ============================================

# CREATE TABLE `affiliate_clicks` (
#   `id` CHAR(36) NOT NULL COMMENT 'Click UUID, e.g., "d50e8400-e29b-41d4-a716-446655440024", "2v3w4x5y-6z7a-8b9c-0d1e-2f3g4h5i6j7k"',
#   `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
#   `user_id` CHAR(36) NULL COMMENT 'User UUID (NULL for anonymous), e.g., "550e8400-e29b-41d4-a716-446655440000", NULL',
#
#   -- Click Information
#   `clicked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Clicked at, e.g., "2025-01-15 14:30:00", "2025-01-28 16:45:00"',
#   `ip_address` VARCHAR(45) NULL COMMENT 'IP address, e.g., "88.212.34.56", "195.113.20.150", "192.168.1.100"',
#   `user_agent` TEXT NULL COMMENT 'User agent, e.g., "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0", "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)"',
#   `referrer` VARCHAR(500) NULL COMMENT 'Referrer URL, e.g., "https://chatbotdirectory.com/chatbots/intercom", "https://google.com/search?q=chatbot", "https://facebook.com"',
#   `country_code` CHAR(2) NULL COMMENT 'Country code, e.g., "SK", "CZ", "US"',
#
#   -- Tracking
#   `session_id` VARCHAR(100) NULL COMMENT 'Session ID, e.g., "sess_a1b2c3d4e5f6g7h8", "session_20250115_abc123", "sid_xyz789def456"',
#   `cookie_id` VARCHAR(100) NULL COMMENT 'Cookie ID for conversion tracking, e.g., "cookie_a1b2c3d4e5f6", "aff_track_abc123", "ck_xyz789"',
#
#   -- Conversion tracking
#   `converted` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Has converted?, e.g., TRUE, FALSE',
#   `conversion_date` TIMESTAMP NULL COMMENT 'Converted at, e.g., "2025-01-20 10:30:00", NULL',
#   `estimated_commission` DECIMAL(10,2) NULL COMMENT 'Estimated commission, e.g., 25.00, 49.99, 100.00',
#
#   PRIMARY KEY (`id`),
#   KEY `idx_chatbot_date` (`chatbot_id`, `clicked_at`),
#   KEY `idx_user` (`user_id`),
#   KEY `idx_cookie` (`cookie_id`),
#   KEY `idx_converted` (`converted`, `conversion_date`),
#
#   CONSTRAINT `fk_affiliate_clicks_chatbot` FOREIGN KEY (`chatbot_id`)
#     REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
#   CONSTRAINT `fk_affiliate_clicks_user` FOREIGN KEY (`user_id`)
#     REFERENCES `users` (`id`) ON DELETE SET NULL
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
#
# -- ============================================
#
# CREATE TABLE `affiliate_commissions` (
#   `id` CHAR(36) NOT NULL COMMENT 'Commission UUID, e.g., "e50e8400-e29b-41d4-a716-446655440025", "3w4x5y6z-7a8b-9c0d-1e2f-3g4h5i6j7k8l"',
#   `chatbot_id` CHAR(36) NOT NULL COMMENT 'Chatbot UUID, e.g., "850e8400-e29b-41d4-a716-446655440003", "1a2b3c4d-5e6f-7g8h-9i0j-1k2l3m4n5o6p"',
#   `affiliate_click_id` CHAR(36) NULL COMMENT 'Click UUID, e.g., "d50e8400-e29b-41d4-a716-446655440024", NULL',
#
#   -- Commission Details
#   `commission_amount` DECIMAL(10,2) NOT NULL COMMENT 'Commission amount, e.g., 25.00, 49.99, 100.00',
#   `currency_id` CHAR(36) NOT NULL COMMENT 'Currency UUID, e.g., "450e8400-e29b-41d4-a716-446655440015", "3m4n5o6p-7q8r-9s0t-1u2v-3w4x5y6z7a8b"',
#   `commission_date` TIMESTAMP NOT NULL COMMENT 'Commission date, e.g., "2025-01-20 10:30:00", "2025-02-05 14:15:00"',
#   `commission_type` ENUM('sale', 'lead', 'click', 'recurring') NOT NULL COMMENT 'Commission type, e.g., "sale", "recurring", "lead"',
#
#   -- Status
#   `status` ENUM('pending', 'confirmed', 'paid', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Commission status, e.g., "pending", "confirmed", "paid"',
#   `paid_at` TIMESTAMP NULL COMMENT 'Paid at, e.g., "2025-02-15 10:00:00", NULL',
#
#   -- Affiliate Program Info
#   `affiliate_program_name` VARCHAR(100) NULL COMMENT 'Program name, e.g., "Intercom Affiliate Program", "Drift Partner Network", "Tidio Referral Program"',
#   `external_transaction_id` VARCHAR(255) NULL COMMENT 'External transaction ID, e.g., "INTCOM_TXN_ABC123", "DRIFT_REF_XYZ789", "TIDIO_COMM_2025_001"',
#
#   -- Notes
#   `notes` TEXT NULL COMMENT 'Admin notes, e.g., "First commission from this chatbot", "Payment pending verification", "Customer upgraded to enterprise plan"',
#
#   `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-20 10:30:00", "2025-02-05 14:15:00"',
#   `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 16:30:00", "2025-02-10 11:20:00"',
#
#   PRIMARY KEY (`id`),
#   KEY `idx_chatbot_status` (`chatbot_id`, `status`),
#   KEY `idx_click` (`affiliate_click_id`),
#   KEY `idx_date_status` (`commission_date`, `status`),
#
#   CONSTRAINT `fk_commissions_chatbot` FOREIGN KEY (`chatbot_id`)
#     REFERENCES `chatbots` (`id`) ON DELETE CASCADE,
#   CONSTRAINT `fk_commissions_click` FOREIGN KEY (`affiliate_click_id`)
#     REFERENCES `affiliate_clicks` (`id`) ON DELETE SET NULL,
#   CONSTRAINT `fk_commissions_currency` FOREIGN KEY (`currency_id`)
#     REFERENCES `currencies` (`id`) ON DELETE RESTRICT
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- COMMUNICATION
-- ============================================

CREATE TABLE `contact_messages` (
  `id` CHAR(36) NOT NULL COMMENT 'Message UUID, e.g., "f50e8400-e29b-41d4-a716-446655440026"',
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL COMMENT 'Sender email, e.g., "john.doe@gmail.com"',
  `subject` VARCHAR(300) NULL COMMENT 'Message subject, e.g., "Question about pricing"',
  `message` TEXT NOT NULL COMMENT 'Message content, e.g., "I would like to know more about your premium plans..."',

  -- Classification
--   `message_type` ENUM('general', 'support', 'business_inquiry', 'report_abuse', 'partnership', 'feedback', 'bug_report') NOT NULL DEFAULT 'general',
--   `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',

  -- Relations
  `user_id` CHAR(36) NULL COMMENT 'User UUID (if logged in), NULL if guest',
--   `chatbot_id` CHAR(36) NULL COMMENT 'Chatbot UUID (if reporting)',

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

# CREATE TABLE `newsletter_subscribers` (
#   `id` CHAR(36) NOT NULL COMMENT 'Subscriber UUID, e.g., "050e8400-e29b-41d4-a716-446655440027", "5y6z7a8b-9c0d-1e2f-3g4h-5i6j7k8l9m0n"',
#   `email` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Subscriber email, e.g., "john.doe@gmail.com", "maria.kovacova@azet.sk", "business@company.com"',
#   `status` ENUM('pending', 'active', 'unsubscribed', 'bounced', 'complained') NOT NULL DEFAULT 'pending' COMMENT 'Subscription status, e.g., "active", "pending", "unsubscribed"',
#   `source` ENUM('homepage', 'footer', 'popup', 'chatbot_page', 'api', 'import', 'other') NULL COMMENT 'Signup source, e.g., "homepage", "footer", "popup"',
#
#   -- User Association
#   `user_id` CHAR(36) NULL COMMENT 'User UUID (if registered), e.g., "550e8400-e29b-41d4-a716-446655440000", NULL',
#
#   -- Preferences
#   `preferences` JSON NULL COMMENT 'Email preferences, e.g., {"topics": ["ai_chatbots", "new_listings"], "frequency": "weekly"}, {"interests": ["customer_support", "ecommerce"], "format": "html"}',
#   `frequency` ENUM('daily', 'weekly', 'monthly', 'never') NOT NULL DEFAULT 'weekly' COMMENT 'Email frequency, e.g., "weekly", "monthly", "daily"',
#
#   -- Verification
#   `verification_token` VARCHAR(255) NULL UNIQUE COMMENT 'Email verification token, e.g., "verify_email_abc123def456", "token_2025_xyz789ghi012"',
#   `is_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Is verified?, e.g., TRUE, FALSE',
#
#   -- Unsubscribe
#   `unsubscribe_token` VARCHAR(255) NULL UNIQUE COMMENT 'Unsubscribe token, e.g., "unsub_abc123def456ghi789", "unsubscribe_2025_xyz123"',
#
#   -- Timestamps
#   `subscribed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Subscribed at, e.g., "2025-01-15 18:00:00", "2025-01-28 12:30:00"',
#   `verified_at` TIMESTAMP NULL COMMENT 'Verified at, e.g., "2025-01-15 18:30:00", NULL',
#   `unsubscribed_at` TIMESTAMP NULL COMMENT 'Unsubscribed at, e.g., "2025-01-25 10:00:00", NULL',
#   `last_email_sent_at` TIMESTAMP NULL COMMENT 'Last email sent, e.g., "2025-01-29 08:00:00", "2025-01-22 09:00:00"',
#
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_email` (`email`),
#   KEY `idx_status_verified` (`status`, `is_verified`),
#   KEY `idx_user` (`user_id`),
#   KEY `idx_verification` (`verification_token`),
#   KEY `idx_unsubscribe` (`unsubscribe_token`),
#
#   CONSTRAINT `fk_newsletter_user` FOREIGN KEY (`user_id`)
#     REFERENCES `users` (`id`) ON DELETE SET NULL
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CONTENT MANAGEMENT
-- ============================================

# CREATE TABLE `static_pages` (
#   `id` CHAR(36) NOT NULL COMMENT 'Page UUID, e.g., "150e8400-e29b-41d4-a716-446655440028", "6z7a8b9c-0d1e-2f3g-4h5i-6j7k8l9m0n1o"',
#   `slug` VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL slug, e.g., "about-us", "privacy-policy", "terms-of-service"',
#   `title` VARCHAR(255) NOT NULL COMMENT 'Page title, e.g., "About Us", "Privacy Policy", "Terms of Service"',
#   `content` LONGTEXT NOT NULL COMMENT 'Page content (HTML), e.g., "<h1>About ChatbotDirectory</h1><p>We help businesses find...</p>", "<h2>Privacy Policy</h2><p>Last updated: January 2025...</p>"',
#
#   -- SEO
#   `meta_title` VARCHAR(255) NULL COMMENT 'SEO title, e.g., "About ChatbotDirectory - Your AI Chatbot Guide", "Privacy Policy | ChatbotDirectory", "Terms of Service - ChatbotDirectory"',
#   `meta_description` VARCHAR(500) NULL COMMENT 'SEO description, e.g., "Learn about our mission to help businesses discover the best chatbot solutions", "Read our privacy policy to understand how we protect your data", "Our terms of service explain how to use ChatbotDirectory"',
#   `meta_keywords` VARCHAR(500) NULL COMMENT 'SEO keywords, e.g., "about us, chatbot directory, ai chatbots", "privacy policy, data protection, gdpr", "terms of service, user agreement, legal"',
#
#   -- Publishing
#   `is_published` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Is published?, e.g., TRUE, FALSE',
#   `published_at` TIMESTAMP NULL COMMENT 'Published at, e.g., "2025-01-10 10:00:00", NULL',
#
#   -- Audit
#   `created_by_user_id` CHAR(36) NULL COMMENT 'Creator UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", NULL',
#   `updated_by_user_id` CHAR(36) NULL COMMENT 'Last editor UUID, e.g., "550e8400-e29b-41d4-a716-446655440000", "7c9e6679-7425-40de-944b-e07fc1f90ae7"',
#
#   `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created at, e.g., "2025-01-10 10:00:00", "2024-12-15 08:00:00"',
#   `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated at, e.g., "2025-01-29 17:30:00", "2025-01-28 14:45:00"',
#
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `idx_slug` (`slug`),
#   KEY `idx_published` (`is_published`, `published_at`),
#
#   CONSTRAINT `fk_pages_creator` FOREIGN KEY (`created_by_user_id`)
#     REFERENCES `users` (`id`) ON DELETE SET NULL,
#   CONSTRAINT `fk_pages_updater` FOREIGN KEY (`updated_by_user_id`)
#     REFERENCES `users` (`id`) ON DELETE SET NULL
# ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT & LOGGING
-- ============================================

CREATE TABLE `audit_log` (
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
  PARTITION p_2025_01 VALUES LESS THAN (UNIX_TIMESTAMP('2025-02-01')) COMMENT 'January 2025 partition, e.g., logs from 2025-01-01 to 2025-01-31',
  PARTITION p_2025_02 VALUES LESS THAN (UNIX_TIMESTAMP('2025-03-01')) COMMENT 'February 2025 partition, e.g., logs from 2025-02-01 to 2025-02-28',
  PARTITION p_future VALUES LESS THAN MAXVALUE COMMENT 'Future logs partition, e.g., logs from 2025-03-01 onwards'
);

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default currencies
INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`) VALUES
(UUID(), 'USD', 'US Dollar', '$'),
(UUID(), 'EUR', 'Euro', '€'),
(UUID(), 'GBP', 'British Pound', '£');

-- Insert billing periods
INSERT INTO `billing_periods` (`id`, `name`, `type`, `duration`) VALUES
(UUID(), 'Monthly', 'monthly', 1),
(UUID(), 'Annually', 'yearly', 12);

-- Insert listing plans
INSERT INTO `listing_plans` (`id`, `name`, `billing_period_id`, `currency_id`, `price`) VALUES
(UUID(), 'Basic Listing',
  (SELECT `id` FROM `billing_periods` WHERE `name` = 'Monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'USD'),
  9.99
),
(UUID(), 'Premium Listing',
  (SELECT `id` FROM `billing_periods` WHERE `name` = 'Monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'USD'),
  29.99
);

INSERT INTO `promotions` (`id`, `name`,`slot_type` ,`billing_period_id`, `currency_id`, `price`) VALUES
(UUID(), 'Basic Listing', 'homepage_hero',
  (SELECT `id` FROM `billing_periods` WHERE `name` = 'Monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'USD'),
  300
),
(UUID(), 'Basic Listing', 'category_featured',
  (SELECT `id` FROM `billing_periods` WHERE `name` = 'Monthly'),
  (SELECT `id` FROM `currencies` WHERE `code` = 'USD'),
  300
);



-- Insert promotion slots
# INSERT INTO `promotion_slots` (`id`, `name`, `identifier`, `slot_type`, `page_location`, `position`, `max_concurrent`, `dimensions`) VALUES
# (UUID(), 'Homepage Hero', 'homepage_hero', 'homepage_hero', 'homepage', 1, 1, '1200x400'),
# (UUID(), 'Category Top Banner', 'category_top', 'category_featured', 'category', 1, 2, '728x90'),
# (UUID(), 'Sidebar Featured', 'sidebar_featured', 'sidebar', 'all', 1, 3, '300x250');

-- Insert default categories
# INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `display_order`) VALUES
# (UUID(), 'Customer Support', 'customer-support', 'Chatbots specialized in customer service and support', 1),
# (UUID(), 'Sales & Marketing', 'sales-marketing', 'Chatbots for lead generation and sales automation', 2),
# (UUID(), 'E-commerce', 'ecommerce', 'Shopping assistants and product recommendation bots', 3),
# (UUID(), 'Healthcare', 'healthcare', 'Medical assistance and health-related chatbots', 4),
# (UUID(), 'Education', 'education', 'Learning assistants and educational chatbots', 5);

-- ============================================
-- VIEWS AND TRIGGERS - OPTIMIZED VERSION
-- ============================================
-- Only necessary views and triggers for your chatbot directory
-- ============================================

-- ============================================
-- TRIGGERS FOR DENORMALIZATION
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
-- PROMOTION ANALYTICS TRIGGERS
-- ============================================

-- Update promotion CTR when clicks change
CREATE TRIGGER `trg_promotion_update_ctr` BEFORE UPDATE ON `promotions`
FOR EACH ROW
BEGIN
  -- Recalculate CTR if impressions or clicks changed
  IF NEW.impressions != OLD.impressions OR NEW.clicks != OLD.clicks THEN
    IF NEW.impressions > 0 THEN
      SET NEW.ctr = (NEW.clicks / NEW.impressions) * 100;
    ELSE
      SET NEW.ctr = 0;
    END IF;
  END IF;
END//

-- ============================================
-- SUBSCRIPTION LIFECYCLE TRIGGERS
-- ============================================

-- Handle subscription expiration
CREATE TRIGGER `trg_subscription_expired` AFTER UPDATE ON `subscriptions`
FOR EACH ROW
BEGIN
  -- Check if subscription just expired
  IF OLD.status != 'expired' AND NEW.status = 'expired' THEN

    -- If listing subscription expired, deactivate chatbot
    -- (unless it has an affiliate program - then it stays active for free)
    IF NEW.subscription_type = 'listing' THEN
      UPDATE `chatbots`
      SET `status` = 'inactive',
          `updated_at` = CURRENT_TIMESTAMP
      WHERE `id` = NEW.chatbot_id
        AND `has_affiliate_program` = FALSE;  -- Only deactivate if no affiliate
    END IF;

    -- If promotion subscription expired, expire the promotion
    IF NEW.subscription_type = 'promotion' THEN
      UPDATE `promotions`
      SET `status` = 'expired',
          `updated_at` = CURRENT_TIMESTAMP
      WHERE `subscription_id` = NEW.id
        AND `status` = 'active';
    END IF;
  END IF;

  -- Check if subscription was cancelled
  IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
    -- Mark promotions as cancelled (not expired)
    IF NEW.subscription_type = 'promotion' THEN
      UPDATE `promotions`
      SET `status` = 'cancelled',
          `updated_at` = CURRENT_TIMESTAMP
      WHERE `subscription_id` = NEW.id
        AND `status` = 'active';
    END IF;
  END IF;
END//

-- ============================================
-- PAYMENT SUCCESS TRIGGER
-- ============================================

-- Activate subscription when payment succeeds
CREATE TRIGGER `trg_payment_succeeded` AFTER UPDATE ON `payments`
FOR EACH ROW
BEGIN
  -- Check if payment just succeeded
  IF OLD.status != 'succeeded' AND NEW.status = 'succeeded' THEN

    -- Activate the related subscription
    IF NEW.subscription_id IS NOT NULL THEN
      UPDATE `subscriptions`
      SET `status` = 'active',
          `updated_at` = CURRENT_TIMESTAMP
      WHERE `id` = NEW.subscription_id
        AND `status` = 'pending';

      -- If it's a promotion subscription, activate the promotion
      UPDATE `promotions` p
      JOIN `subscriptions` s ON p.subscription_id = s.id
      SET p.status = 'active',
          p.activated_at = CURRENT_TIMESTAMP,
          p.updated_at = CURRENT_TIMESTAMP
      WHERE s.id = NEW.subscription_id
        AND p.status = 'pending';

      -- If it's a listing subscription, activate the chatbot
      UPDATE `chatbots` c
      JOIN `subscriptions` s ON c.id = s.chatbot_id
      SET c.status = 'active',
          c.updated_at = CURRENT_TIMESTAMP
      WHERE s.id = NEW.subscription_id
        AND s.subscription_type = 'listing'
        AND c.status = 'pending';
    END IF;
  END IF;
END//

-- ============================================
-- AFFILIATE CONVERSION TRIGGER
-- ============================================

-- Mark affiliate click as converted
# CREATE TRIGGER `trg_affiliate_commission_insert` AFTER INSERT ON `affiliate_commissions`
# FOR EACH ROW
# BEGIN
#   -- If commission has a related click, mark it as converted
#   IF NEW.affiliate_click_id IS NOT NULL THEN
#     UPDATE `affiliate_clicks`
#     SET `converted` = TRUE,
#         `conversion_date` = NEW.commission_date,
#         `estimated_commission` = NEW.commission_amount
#     WHERE `id` = NEW.affiliate_click_id
#       AND `converted` = FALSE;
#   END IF;
# END//

DELIMITER ;

-- ============================================
-- OPTIONAL VIEWS (use sparingly!)
-- ============================================

-- View for admin dashboard - subscription overview
CREATE OR REPLACE VIEW `v_subscription_overview` AS
SELECT
  DATE(s.start_date) as subscription_date,
  s.subscription_type,
  s.status,
  COUNT(*) as count,
  COUNT(DISTINCT s.user_id) as unique_users,
  SUM(
    CASE s.subscription_type
      WHEN 'listing' THEN lpp.price
    END
  ) as total_revenue
FROM `subscriptions` s
LEFT JOIN `listing_plan_prices` lpp ON s.listing_price_id = lpp.id
GROUP BY DATE(s.start_date), s.subscription_type, s.status;

-- View for admin dashboard - affiliate performance
# CREATE OR REPLACE VIEW `v_affiliate_performance` AS
# SELECT
#   c.id as chatbot_id,
#   c.name as chatbot_name,
#   COUNT(DISTINCT ac.id) as total_clicks,
#   COUNT(DISTINCT CASE WHEN ac.converted = TRUE THEN ac.id END) as conversions,
#   COALESCE(
#     COUNT(DISTINCT CASE WHEN ac.converted = TRUE THEN ac.id END) /
#     NULLIF(COUNT(DISTINCT ac.id), 0) * 100,
#     0
#   ) as conversion_rate,
#   COUNT(DISTINCT acom.id) as total_commissions,
#   COALESCE(SUM(acom.commission_amount), 0) as total_commission_amount,
#   COALESCE(SUM(CASE WHEN acom.status = 'paid' THEN acom.commission_amount ELSE 0 END), 0) as paid_commission_amount
# FROM `chatbots` c
# LEFT JOIN `affiliate_clicks` ac ON c.id = ac.chatbot_id
# LEFT JOIN `affiliate_commissions` acom ON c.id = acom.chatbot_id
# WHERE c.has_affiliate_program = TRUE
# GROUP BY c.id, c.name;

-- View for active promotions (for displaying on site)
CREATE OR REPLACE VIEW `v_active_promotions` AS
SELECT
  p.*,
  c.name as chatbot_name,
  c.logo_url as chatbot_logo,
  c.affiliate_link as chatbot_link,
FROM `promotions` p
JOIN `chatbots` c ON p.chatbot_id = c.id
WHERE p.status = 'active'
  AND NOW() BETWEEN p.start_date AND p.end_date
ORDER BY p.display_order;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Additional composite indexes for common queries

-- Find active subscriptions for a chatbot
CREATE INDEX `idx_subscriptions_active_chatbot`
  ON `subscriptions` (`chatbot_id`, `status`, `end_date`);

-- Find active promotions by slot
# CREATE INDEX `idx_promotions_active_slot`
#   ON `promotions` (`promotion_slot_id`, `status`, `display_order`);

-- Find recent payments by user
CREATE INDEX `idx_payments_recent_user`
  ON `payments` (`user_id`, `created_at`, `status`);

-- Find affiliate clicks for conversion tracking
# CREATE INDEX `idx_affiliate_clicks_tracking`
#   ON `affiliate_clicks` (`cookie_id`, `converted`, `clicked_at`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- END OF SCHEMA
-- ============================================