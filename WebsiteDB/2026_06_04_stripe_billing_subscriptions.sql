-- Stripe Billing subscription migration.
-- Allows one Stripe subscription / invoice to back multiple local promotion rows
-- for Full Bundle purchases.

SET @idx := (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscriptions'
      AND COLUMN_NAME = 'stripe_subscription_id'
      AND NON_UNIQUE = 0
    LIMIT 1
);
SET @sql := IF(
    @idx IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE subscriptions DROP INDEX `', REPLACE(@idx, '`', '``'), '`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscriptions'
      AND INDEX_NAME = 'idx_stripe_subscription'
);
SET @sql := IF(
    @idx_exists > 0,
    'SELECT 1',
    'ALTER TABLE subscriptions ADD INDEX idx_stripe_subscription (stripe_subscription_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND COLUMN_NAME = 'stripe_payment_intent_id'
      AND NON_UNIQUE = 0
    LIMIT 1
);
SET @sql := IF(
    @idx IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE payments DROP INDEX `', REPLACE(@idx, '`', '``'), '`')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payments'
      AND INDEX_NAME = 'idx_stripe_payment_intent'
);
SET @sql := IF(
    @idx_exists > 0,
    'SELECT 1',
    'ALTER TABLE payments ADD INDEX idx_stripe_payment_intent (stripe_payment_intent_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
