-- ticket_sla_escalation_rules.sql — SLA breach escalation rules (destructive — back up before apply)

-- Table structure for `ticket_sla_escalation_rules`
DROP TABLE IF EXISTS `ticket_sla_escalation_rules`;

CREATE TABLE `ticket_sla_escalation_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `priority_id` int NOT NULL,
  `breach_type` enum('response','resolve','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `escalate_to_employee_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_sla_escalation_scope` (`company_id`,`priority_id`,`breach_type`),
  KEY `idx_ticket_sla_escalation_assignee` (`escalate_to_employee_id`),
  CONSTRAINT `fk_ticket_sla_escalation_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_sla_escalation_priority` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`),
  CONSTRAINT `fk_ticket_sla_escalation_employee` FOREIGN KEY (`escalate_to_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
