-- audit_logs: widen id + record_id to BIGINT (append-only log + generic record pointer).
-- Back up before apply on production. Preserves rows via temporary backup table.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `_itm_audit_logs_backup`;

CREATE TABLE `_itm_audit_logs_backup` AS
SELECT
  `id`, `company_id`, `employee_id`, `actor_username`, `actor_email`, `module_name`, `entity_name`,
  `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `audit_logs`;

DROP TABLE IF EXISTS `audit_logs`;

CREATE TABLE `audit_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `actor_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_company` (`company_id`),
  KEY `idx_audit_logs_user` (`employee_id`),
  KEY `idx_audit_logs_actor_username` (`actor_username`),
  KEY `idx_audit_logs_actor_email` (`actor_email`),
  KEY `idx_audit_logs_module_name` (`module_name`),
  KEY `idx_audit_logs_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_updated_at` (`updated_at`),
  KEY `idx_audit_logs_company_updated` (`company_id`,`updated_at`),
  CONSTRAINT `audit_logs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_logs_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_logs` (
  `id`, `company_id`, `employee_id`, `actor_username`, `actor_email`, `module_name`, `entity_name`,
  `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `employee_id`, `actor_username`, `actor_email`, `module_name`, `entity_name`,
  `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `_itm_audit_logs_backup`;

DROP TABLE IF EXISTS `_itm_audit_logs_backup`;

SET FOREIGN_KEY_CHECKS = 1;
