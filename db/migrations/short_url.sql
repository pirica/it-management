-- Short URL module tables + qr_codes.short_url_id back-link column.
-- Destructive to qr_codes / qr_code_scans rows on apply — back up first.

DROP TABLE IF EXISTS `short_url_clicks`;
DROP TABLE IF EXISTS `short_urls`;
DROP TABLE IF EXISTS `short_url_settings`;
DROP TABLE IF EXISTS `qr_code_scans`;
DROP TABLE IF EXISTS `qr_codes`;

CREATE TABLE `qr_codes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_slug` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `encoding_mode` ENUM('static', 'dynamic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dynamic',
  `payload_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `encoded_payload` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `design_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_count` INT NOT NULL DEFAULT 0,
  `short_url_id` INT DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_codes_company_employee_title` (`company_id`, `employee_id`, `title`),
  UNIQUE KEY `uq_qr_codes_access_token` (`access_token`),
  KEY `idx_qr_codes_employee` (`company_id`, `employee_id`, `deleted_at`),
  KEY `idx_qr_codes_short_url` (`short_url_id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `qr_codes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qr_codes_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `qr_code_scans` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `qr_code_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `scanned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `user_agent` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qr_code_scans_code` (`qr_code_id`, `scanned_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `qr_code_scans_ibfk_code` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qr_code_scans_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `short_urls` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_url` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `access_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `password_hash` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `click_count` INT NOT NULL DEFAULT 0,
  `qr_code_id` INT DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_short_urls_company_code` (`company_id`, `short_code`),
  UNIQUE KEY `uq_short_urls_access_token` (`access_token`),
  KEY `idx_short_urls_employee` (`company_id`, `employee_id`, `deleted_at`),
  KEY `qr_code_id` (`qr_code_id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `short_urls_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `short_urls_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `short_urls_ibfk_qr_code` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `short_url_clicks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `short_url_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `clicked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `user_agent` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_short_url_clicks_url` (`short_url_id`, `clicked_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `short_url_clicks_ibfk_url` FOREIGN KEY (`short_url_id`) REFERENCES `short_urls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `short_url_clicks_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
