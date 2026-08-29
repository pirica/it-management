-- Tenant ticket module settings (surveys, SLA). Destructive to existing ticket_settings rows.
DROP TABLE IF EXISTS `ticket_settings`;

CREATE TABLE `ticket_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `auto_issue_survey_on_close` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'When 1, issue post-ticket questionnaire when status moves to closed',
  `survey_send_email_on_issue` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'When 1, email requester when a survey invite is issued (auto or manual)',
  `sla_enabled_on_create` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'When 1, stamp SLA due dates on new tickets from ticket_sla_policies',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_settings_company` (`company_id`),
  CONSTRAINT `fk_ticket_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ticket_settings` (`company_id`, `auto_issue_survey_on_close`, `survey_send_email_on_issue`, `sla_enabled_on_create`, `active`, `created_at`)
SELECT c.`id`, 0, 1, 1, 1, '2026-01-01 00:00:01'
FROM `companies` c
WHERE NOT EXISTS (
  SELECT 1 FROM `ticket_settings` ts WHERE ts.`company_id` = c.`id` AND ts.`deleted_at` IS NULL
);
