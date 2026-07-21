-- Unified share_sessions + company_module_share (destructive: back up data before applying on live DB).
-- Canonical definitions: db/01_schema.sql → share_sessions, company_module_share

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `share_sessions`;

CREATE TABLE `share_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` INT DEFAULT NULL,
  `scope_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope_path_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
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
  UNIQUE KEY `uq_share_sessions_access_token` (`access_token`),
  KEY `idx_share_sessions_code_active` (`share_code`, `expires_at`),
  KEY `idx_share_sessions_module_record` (`company_id`, `module_slug`, `employee_id`, `record_id`),
  KEY `idx_share_sessions_module_scope` (`company_id`, `module_slug`, `employee_id`, `scope_path_hash`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `share_sessions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `share_sessions_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'notes', `note_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `note_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'passwords', `password_entry_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `password_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'bookmarks', `bookmark_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `bookmark_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'todo', `todo_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `todo_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'events', `event_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `event_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'private_contacts', `private_contact_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `private_contact_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `scope_path`, `scope_path_hash`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'explorer', NULL, `scope_path`, `scope_path_hash`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `explorer_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'floor_plans', `floor_plan_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `floor_plan_share_sessions`;

INSERT INTO `share_sessions` (`company_id`, `employee_id`, `module_slug`, `record_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT `company_id`, `employee_id`, 'rack_planner', `rack_planner_id`, `share_code`, `access_token`, `payload_json`, `expires_at`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `rack_planner_share_sessions`;

DROP TABLE IF EXISTS `note_share_sessions`;
DROP TABLE IF EXISTS `password_share_sessions`;
DROP TABLE IF EXISTS `bookmark_share_sessions`;
DROP TABLE IF EXISTS `todo_share_sessions`;
DROP TABLE IF EXISTS `event_share_sessions`;
DROP TABLE IF EXISTS `private_contact_share_sessions`;
DROP TABLE IF EXISTS `explorer_share_sessions`;
DROP TABLE IF EXISTS `floor_plan_share_sessions`;
DROP TABLE IF EXISTS `rack_planner_share_sessions`;

DROP TABLE IF EXISTS `company_module_share`;

CREATE TABLE `company_module_share` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_id` int NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_module_share` (`company_id`,`module_id`),
  CONSTRAINT `fk_cms_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cms_module` FOREIGN KEY (`module_id`) REFERENCES `modules_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ('share_modules', 'Share Modules', 1, 1);

INSERT IGNORE INTO `company_module_share` (`company_id`, `module_id`, `enabled`)
SELECT c.`id`, mr.`id`, 1
FROM `companies` c
CROSS JOIN `modules_registry` mr
WHERE c.`active` = 1;

SET FOREIGN_KEY_CHECKS = 1;
