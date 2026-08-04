-- live_chat_typing: add standard audit/soft-delete meta columns.
-- Ephemeral presence rows remain private-data exempt from audit_logs triggers.
-- Canonical: db/01_schema.sql. Destructive: DROP removes existing typing rows (safe; short-lived).

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `live_chat_typing`;

CREATE TABLE `live_chat_typing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `expires_at` timestamp NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_chat_typing` (`conversation_id`,`employee_id`),
  KEY `idx_live_chat_typing_expires` (`expires_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_live_chat_typing_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_typing_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_typing_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
