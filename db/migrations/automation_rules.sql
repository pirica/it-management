-- Workflow automation rules (destructive replacement for existing DBs).
DROP TABLE IF EXISTS `automation_rule_runs`;
DROP TABLE IF EXISTS `automation_rules`;

CREATE TABLE `automation_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_slug` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conditions_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actions_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `enabled` tinyint(1) DEFAULT '1',
  `last_run_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_automation_rules_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `trigger_slug` (`trigger_slug`),
  CONSTRAINT `fk_automation_rules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `automation_rule_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `rule_id` int NOT NULL,
  `status` enum('pending','success','failed','skipped') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `context_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ran_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `rule_id` (`rule_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_automation_rule_runs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_automation_rule_runs_rule` FOREIGN KEY (`rule_id`) REFERENCES `automation_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
