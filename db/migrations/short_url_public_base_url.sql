-- Add public_base_url to short_url_settings (per-company public link prefix).
-- Destructive to short_url_settings rows only — back up before apply on production.

DROP TABLE IF EXISTS `short_url_settings`;

CREATE TABLE `short_url_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `default_expiry_days` INT DEFAULT NULL,
  `custom_code_min_length` TINYINT NOT NULL DEFAULT 4,
  `require_https_destination` TINYINT(1) NOT NULL DEFAULT 0,
  `analytics_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_password_protect` TINYINT(1) NOT NULL DEFAULT 1,
  `public_base_url` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_short_url_settings_company` (`company_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `short_url_settings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
