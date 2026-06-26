-- ============================================
-- Visitor identity hardening for consent-gated analytics
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `visitor_fingerprints` (
  `id` CHAR(36) NOT NULL,
  `visitor_id` CHAR(36) NOT NULL,
  `fingerprint` VARCHAR(64) NOT NULL,
  `first_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_visitor_fingerprint` (`visitor_id`, `fingerprint`),
  UNIQUE KEY `uq_fingerprint` (`fingerprint`),
  KEY `idx_visitor_last_seen` (`visitor_id`, `last_seen`),

  CONSTRAINT `fk_visitor_fingerprints_visitor` FOREIGN KEY (`visitor_id`)
    REFERENCES `visitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `visitor_fingerprints` (
  `id`, `visitor_id`, `fingerprint`, `first_seen`, `last_seen`
)
SELECT UUID(), `id`, LOWER(`fingerprint`), `first_seen`, `last_seen`
FROM `visitors`
WHERE `fingerprint` IS NOT NULL
  AND `fingerprint` <> ''
  AND `fingerprint` REGEXP '^[A-Fa-f0-9]{64}$';

SET FOREIGN_KEY_CHECKS = 1;
