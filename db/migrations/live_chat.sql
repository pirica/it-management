-- Live Chat module migration (back up before applying on live DB).
-- Canonical definitions: db/01_schema.sql

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `live_chat_typing`;
DROP TABLE IF EXISTS `live_chat_messages`;
DROP TABLE IF EXISTS `live_chat_participants`;
DROP TABLE IF EXISTS `live_chat_conversations`;
DROP TABLE IF EXISTS `ticket_comments`;
DROP TABLE IF EXISTS `ticket_activity`;
DROP TABLE IF EXISTS `ticket_sla_policies`;

-- Existing DBs only (fresh import uses CREATE TABLE in db/01_schema.sql):
-- ALTER TABLE `tickets` ADD COLUMN `first_response_at` timestamp NULL DEFAULT NULL AFTER `due_date`;
-- ALTER TABLE `tickets` ADD COLUMN `resolved_at` timestamp NULL DEFAULT NULL AFTER `first_response_at`;
-- ALTER TABLE `tickets` ADD COLUMN `sla_response_due_at` timestamp NULL DEFAULT NULL AFTER `resolved_at`;
-- ALTER TABLE `tickets` ADD COLUMN `sla_resolve_due_at` timestamp NULL DEFAULT NULL AFTER `sla_response_due_at`;

CREATE TABLE IF NOT EXISTS `employee_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `action_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_notifications_inbox` (`company_id`,`employee_id`,`is_read`,`created_at`),
  KEY `idx_employee_notifications_module` (`company_id`,`module_slug`,`record_id`),
  CONSTRAINT `fk_employee_notifications_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_employee_notifications_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_sla_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `priority_id` int NOT NULL,
  `response_minutes` int NOT NULL,
  `resolve_minutes` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_sla_policies_company_priority` (`company_id`,`priority_id`),
  CONSTRAINT `fk_ticket_sla_policies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_sla_policies_priority` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `actor_employee_id` int DEFAULT NULL,
  `event_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_activity_ticket` (`company_id`,`ticket_id`,`created_at`),
  KEY `idx_ticket_activity_type` (`company_id`,`event_type`),
  CONSTRAINT `fk_ticket_activity_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_activity_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_ticket_activity_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_comments_ticket` (`company_id`,`ticket_id`,`created_at`),
  CONSTRAINT `fk_ticket_comments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_comments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_ticket_comments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `live_chat_conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_type` enum('live_agent','chat_with') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_id` int DEFAULT NULL,
  `requester_employee_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `status` enum('waiting','active','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `rating` tinyint unsigned DEFAULT NULL,
  `storage_rel_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_chat_conv_company` (`company_id`,`status`,`created_at`),
  KEY `idx_live_chat_conv_ticket` (`company_id`,`ticket_id`),
  KEY `requester_employee_id` (`requester_employee_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  CONSTRAINT `fk_live_chat_conv_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_conv_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_live_chat_conv_requester` FOREIGN KEY (`requester_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_live_chat_conv_assigned` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `live_chat_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `role` enum('requester','agent','peer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'peer',
  `last_read_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_chat_participant` (`conversation_id`,`employee_id`),
  KEY `idx_live_chat_part_employee` (`company_id`,`employee_id`),
  CONSTRAINT `fk_live_chat_part_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_part_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_part_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `live_chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `sender_employee_id` int NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachments_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_chat_msg_conv` (`conversation_id`,`created_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_live_chat_msg_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_msg_sender` FOREIGN KEY (`sender_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `live_chat_typing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_chat_typing` (`conversation_id`,`employee_id`),
  KEY `idx_live_chat_typing_expires` (`expires_at`),
  CONSTRAINT `fk_live_chat_typing_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_typing_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_typing_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Re-seed SLA policies for company 1 when table was empty after DROP/CREATE (idempotent).
INSERT IGNORE INTO `ticket_sla_policies` (`company_id`, `priority_id`, `response_minutes`, `resolve_minutes`, `active`, `created_at`) VALUES
(1, 1, 480, 2880, 1, '2026-01-01 00:00:01'),
(1, 2, 240, 1440, 1, '2026-01-01 00:00:01'),
(1, 3, 60, 480, 1, '2026-01-01 00:00:01'),
(1, 4, 30, 240, 1, '2026-01-01 00:00:01'),
(1, 5, 15, 120, 1, '2026-01-01 00:00:01');
