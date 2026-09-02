-- IT Change Management pack: extended fields, CAB approvals, settings, reminder tracking.
-- Apply via: php scripts/migrate.php --apply (live probe) or full import bundle.

DROP TABLE IF EXISTS `change_request_approvals`;
DROP TABLE IF EXISTS `change_request_cab_members`;
DROP TABLE IF EXISTS `change_request_settings`;
DROP TABLE IF EXISTS `change_request_configuration_items`;
DROP TABLE IF EXISTS `change_requests`;

CREATE TABLE `change_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `source_configuration_item_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `change_type` enum('standard','normal','emergency') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `risk_level` enum('low','medium','high','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `rollback_plan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ticket_id` int DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected','implemented','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `scheduled_start` date DEFAULT NULL,
  `scheduled_end` date DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_requests_company_title` (`company_id`,`title`),
  KEY `company_id` (`company_id`),
  KEY `source_configuration_item_id` (`source_configuration_item_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `idx_change_requests_status` (`company_id`,`status`),
  KEY `idx_change_requests_scheduled` (`company_id`,`scheduled_start`),
  CONSTRAINT `change_requests_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_requests_ibfk_source_ci` FOREIGN KEY (`source_configuration_item_id`) REFERENCES `configuration_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `change_requests_ibfk_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `change_request_configuration_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `change_request_id` int NOT NULL,
  `configuration_item_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_configuration_items` (`company_id`,`change_request_id`,`configuration_item_id`),
  KEY `change_request_id` (`change_request_id`),
  KEY `configuration_item_id` (`configuration_item_id`),
  CONSTRAINT `change_request_configuration_items_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_configuration_items_ibfk_request` FOREIGN KEY (`change_request_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_configuration_items_ibfk_ci` FOREIGN KEY (`configuration_item_id`) REFERENCES `configuration_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `change_request_cab_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_cab_members` (`company_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `change_request_cab_members_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_cab_members_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `change_request_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `change_request_id` int NOT NULL,
  `approver_employee_id` int NOT NULL,
  `decision` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `decided_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_approvals` (`company_id`,`change_request_id`,`approver_employee_id`),
  KEY `change_request_id` (`change_request_id`),
  KEY `approver_employee_id` (`approver_employee_id`),
  CONSTRAINT `change_request_approvals_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_approvals_ibfk_request` FOREIGN KEY (`change_request_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_approvals_ibfk_approver` FOREIGN KEY (`approver_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `change_request_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `reminder_days_before` int NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_settings_company` (`company_id`),
  CONSTRAINT `change_request_settings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
