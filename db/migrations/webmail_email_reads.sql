-- Webmail per-employee read tracking on shared `emails` rows (private; no audit triggers).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `webmail_email_reads`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `webmail_email_reads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `email_id` int NOT NULL,
  `read_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webmail_email_reads_scope` (`company_id`,`employee_id`,`email_id`),
  KEY `idx_webmail_email_reads_email` (`email_id`),
  KEY `idx_webmail_email_reads_employee` (`company_id`,`employee_id`),
  CONSTRAINT `webmail_email_reads_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webmail_email_reads_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webmail_email_reads_ibfk_email` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
