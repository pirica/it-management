-- Inbound email keyword routing (priority, category, assignee) per tenant.
-- Apply on existing DBs: php scripts/migrate.php --apply

DROP TABLE IF EXISTS `ticket_inbound_email_routing_rules`;

CREATE TABLE `ticket_inbound_email_routing_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `keyword` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `priority_id` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_inbound_email_routing_rules_scope` (`company_id`,`keyword`),
  KEY `idx_ticket_inbound_email_routing_rules_sort` (`company_id`,`sort_order`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `category_id` (`category_id`),
  KEY `priority_id` (`priority_id`),
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_assignee` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_priority` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ticket_inbound_email_routing_rules` (`company_id`, `keyword`, `priority_id`, `category_id`, `assigned_to_employee_id`, `sort_order`, `active`, `created_at`)
SELECT 1, 'urgent', tp.`id`, NULL, NULL, 10, 1, '2026-01-01 00:00:01'
FROM `ticket_priorities` tp WHERE tp.`company_id` = 1 AND LOWER(tp.`name`) = 'urgent' LIMIT 1;

INSERT INTO `ticket_inbound_email_routing_rules` (`company_id`, `keyword`, `priority_id`, `category_id`, `assigned_to_employee_id`, `sort_order`, `active`, `created_at`)
SELECT 1, 'critical', tp.`id`, NULL, NULL, 20, 1, '2026-01-01 00:00:01'
FROM `ticket_priorities` tp WHERE tp.`company_id` = 1 AND LOWER(tp.`name`) = 'critical' LIMIT 1;

INSERT INTO `ticket_inbound_email_routing_rules` (`company_id`, `keyword`, `priority_id`, `category_id`, `assigned_to_employee_id`, `sort_order`, `active`, `created_at`)
SELECT 1, 'billing', NULL, tc.`id`, NULL, 30, 1, '2026-01-01 00:00:01'
FROM `ticket_categories` tc WHERE tc.`company_id` = 1 AND LOWER(tc.`name`) = 'other' LIMIT 1;

INSERT INTO `ticket_inbound_email_routing_rules` (`company_id`, `keyword`, `priority_id`, `category_id`, `assigned_to_employee_id`, `sort_order`, `active`, `created_at`)
SELECT 1, 'support', NULL, NULL, e.`id`, 40, 1, '2026-01-01 00:00:01'
FROM `employees` e WHERE e.`company_id` = 1 AND LOWER(e.`username`) = 'admin' AND e.`deleted_at` IS NULL LIMIT 1;
