-- Explorer QR share sessions (destructive: back up data before applying on live DB).
-- Canonical definition: db/01_schema.sql → explorer_share_sessions

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `explorer_share_sessions`;

CREATE TABLE `explorer_share_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `scope_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_path_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `share_code` CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `access_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_explorer_share_access_token` (`access_token`),
  KEY `idx_explorer_share_code_active` (`share_code`, `expires_at`),
  KEY `idx_explorer_share_scope` (`company_id`, `employee_id`, `scope_path_hash`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `explorer_share_sessions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `explorer_share_sessions_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
