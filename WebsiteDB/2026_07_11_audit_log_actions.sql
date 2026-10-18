-- Expand audit action names without requiring an ALTER TABLE for every new event.
-- AuditLog::ALLOWED_ACTIONS remains the application-level allowlist.
ALTER TABLE `audit_log`
    MODIFY COLUMN `action` VARCHAR(50) NOT NULL
    COMMENT 'Validated action name, e.g., "created", "password_changed", "login_failed"';
