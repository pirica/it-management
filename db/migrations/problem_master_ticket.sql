-- Problem Management master ticket rollup (destructive replacement — back up first).
DROP TABLE IF EXISTS `master_ticket_updates`;
DROP TABLE IF EXISTS `known_errors`;
DROP TABLE IF EXISTS `problem_ticket_links`;
DROP TABLE IF EXISTS `problems`;
DROP TABLE IF EXISTS `master_tickets`;

CREATE TABLE `master_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `root_cause` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `summary_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `problems` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `root_cause` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('investigating','known_error','resolved','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'investigating',
  `owner_employee_id` int DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `knowledge_base_id` int DEFAULT NULL,
  `master_ticket_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_problems_company_status` (`company_id`,`status`,`deleted_at`),
  KEY `idx_problems_owner` (`company_id`,`owner_employee_id`),
  KEY `idx_problems_master_ticket` (`master_ticket_id`,`company_id`,`deleted_at`),
  KEY `knowledge_base_id` (`knowledge_base_id`),
  CONSTRAINT `fk_problems_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problems_owner` FOREIGN KEY (`owner_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_problems_knowledge_base` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_base` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_problems_master_ticket` FOREIGN KEY (`master_ticket_id`) REFERENCES `master_tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `problem_ticket_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `problem_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `linked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `linked_by` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_problem_ticket_links_scope` (`company_id`,`problem_id`,`ticket_id`),
  KEY `idx_problem_ticket_links_ticket` (`company_id`,`ticket_id`,`deleted_at`),
  CONSTRAINT `fk_problem_ticket_links_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problem_ticket_links_problem` FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problem_ticket_links_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problem_ticket_links_linked_by` FOREIGN KEY (`linked_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `known_errors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `problem_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workaround` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symptom_keywords` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `knowledge_base_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_known_errors_problem` (`company_id`,`problem_id`,`deleted_at`),
  KEY `knowledge_base_id` (`knowledge_base_id`),
  CONSTRAINT `fk_known_errors_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_known_errors_problem` FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_known_errors_knowledge_base` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_base` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `master_ticket_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `master_ticket_id` int NOT NULL,
  `event_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes_json` json DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `actor_employee_id` int DEFAULT NULL,
  `actor_company_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_master_ticket_updates_master` (`master_ticket_id`,`created_at`),
  CONSTRAINT `fk_master_ticket_updates_master` FOREIGN KEY (`master_ticket_id`) REFERENCES `master_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_master_ticket_updates_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_master_ticket_updates_actor_company` FOREIGN KEY (`actor_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
