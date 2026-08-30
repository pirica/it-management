-- Master toggle for double-click sidebar section collapse (default enabled).
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Destructive: DROP removes all ui_configuration rows — re-seed or restore from backup.

DROP TABLE IF EXISTS `ui_configuration`;

CREATE TABLE `ui_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `table_actions_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `new_button_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `export_buttons_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `back_save_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `enable_all_error_reporting` tinyint(1) NOT NULL DEFAULT '1',
  `enable_audit_logs` tinyint(1) NOT NULL DEFAULT '1',
  `enable_chatbot` tinyint(1) NOT NULL DEFAULT '1',
  `enable_sidebar_section_collapse` tinyint(1) NOT NULL DEFAULT '1',
  `enable_auto_scaffolding` tinyint(1) NOT NULL DEFAULT '0',
  `records_per_page` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '25',
  `app_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '⚙️ IT Controls',
  `favicon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `equipment_type_sidebar_visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `module_icon_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `api_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `api_key_is_active` tinyint(1) NOT NULL DEFAULT '1',
  `api_key_last_used_at` timestamp NULL DEFAULT NULL,
  `rate_limit_window_start` int NOT NULL DEFAULT '0',
  `rate_limit_request_count` int NOT NULL DEFAULT '0',
  `rate_limit_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `tier` enum('Free','Basic','Pro','Enterprise') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Free',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ui_configuration_company_employee` (`company_id`,`employee_id`),
  CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ui_configuration_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
