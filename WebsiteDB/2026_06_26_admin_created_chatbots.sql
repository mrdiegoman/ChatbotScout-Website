-- Admin-created chatbot listings.
-- Allows admins to create a chatbot without a user submission, then assign it
-- to an existing account later by email.

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chatbots'
      AND COLUMN_NAME = 'created_by_admin_id'
);
SET @sql := IF(
    @col_exists > 0,
    'SELECT 1',
    'ALTER TABLE `chatbots` ADD COLUMN `created_by_admin_id` CHAR(36) NULL COMMENT ''Admin audit UUID that manually created this chatbot, NULL for user submissions'' AFTER `owner_user_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chatbots'
      AND COLUMN_NAME = 'created_source'
);
SET @sql := IF(
    @col_exists > 0,
    'SELECT 1',
    'ALTER TABLE `chatbots` ADD COLUMN `created_source` ENUM(''submission'',''admin'') NOT NULL DEFAULT ''submission'' COMMENT ''How the listing was created'' AFTER `created_by_admin_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chatbots'
      AND INDEX_NAME = 'idx_created_by_admin'
);
SET @sql := IF(
    @idx_exists > 0,
    'SELECT 1',
    'ALTER TABLE `chatbots` ADD INDEX `idx_created_by_admin` (`created_by_admin_id`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chatbots'
      AND INDEX_NAME = 'idx_created_source_owner'
);
SET @sql := IF(
    @idx_exists > 0,
    'SELECT 1',
    'ALTER TABLE `chatbots` ADD INDEX `idx_created_source_owner` (`created_source`, `owner_user_id`, `status`, `created_at`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
