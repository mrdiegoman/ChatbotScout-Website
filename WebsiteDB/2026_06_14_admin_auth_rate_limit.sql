-- Admin auth uses a dedicated rate-limit action: admin_login.
-- Keep this column flexible so new rate-limit actions do not require enum edits.
ALTER TABLE `rate_limits`
  MODIFY COLUMN `action` VARCHAR(80) NOT NULL;
