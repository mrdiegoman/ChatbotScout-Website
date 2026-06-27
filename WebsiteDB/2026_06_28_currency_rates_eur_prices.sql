-- Normalize chatbot prices to EUR for approximate cross-currency filtering.
-- Run once during deploy, then schedule cron/updateCurrencyRates.php daily.

CREATE TABLE IF NOT EXISTS `currency_rates` (
  `currency_code` CHAR(3) NOT NULL COMMENT 'ISO 4217 code, e.g. EUR, USD, GBP',
  `base_currency` CHAR(3) NOT NULL DEFAULT 'EUR' COMMENT 'Normalization base currency',
  `rate_to_eur` DECIMAL(18,8) NOT NULL COMMENT 'EUR value of one unit of currency_code',
  `source_rate` DECIMAL(18,8) NULL COMMENT 'Source quote, e.g. ECB units per EUR',
  `rate_date` DATE NULL COMMENT 'Date published by the source feed',
  `source` VARCHAR(100) NOT NULL DEFAULT 'fallback' COMMENT 'Exchange-rate source',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`currency_code`),
  KEY `idx_rate_date` (`rate_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `currency_rates` (`currency_code`, `base_currency`, `rate_to_eur`, `source_rate`, `rate_date`, `source`)
VALUES
  ('EUR', 'EUR', 1.00000000, 1.00000000, CURDATE(), 'fallback'),
  ('USD', 'EUR', 1.00000000, NULL, CURDATE(), 'fallback'),
  ('GBP', 'EUR', 1.00000000, NULL, CURDATE(), 'fallback')
ON DUPLICATE KEY UPDATE
  `base_currency` = VALUES(`base_currency`);

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chatbots'
      AND COLUMN_NAME = 'starting_price_eur'
);
SET @sql := IF(
    @col_exists > 0,
    'SELECT 1',
    'ALTER TABLE `chatbots` ADD COLUMN `starting_price_eur` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''Starting price normalized to EUR for approximate filtering/sorting'' AFTER `starting_price`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chatbots'
      AND INDEX_NAME = 'idx_starting_price_eur'
);
SET @sql := IF(
    @idx_exists > 0,
    'SELECT 1',
    'ALTER TABLE `chatbots` ADD INDEX `idx_starting_price_eur` (`status`, `deleted_at`, `starting_price_eur`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `chatbots` c
LEFT JOIN `currency_rates` cr
  ON cr.currency_code = c.pricing_currency
SET c.starting_price_eur = ROUND(c.starting_price * COALESCE(cr.rate_to_eur, 1), 2);
