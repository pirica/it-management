-- CMDB Lite: configuration item types, items, and relationships.
-- Apply on existing databases via: php scripts/migrate.php --apply
-- Fresh installs use db/01_schema.sql directly.

DROP TABLE IF EXISTS `configuration_item_relationships`;
DROP TABLE IF EXISTS `configuration_items`;
DROP TABLE IF EXISTS `configuration_item_types`;

CREATE TABLE `configuration_item_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuration_item_types_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `idx_configuration_item_types_source_slug` (`company_id`,`source_slug`),
  CONSTRAINT `configuration_item_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `configuration_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ci_type_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_ref` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuration_items_external_ref` (`company_id`,`external_ref`),
  KEY `company_id` (`company_id`),
  KEY `ci_type_id` (`ci_type_id`),
  KEY `idx_configuration_items_record` (`company_id`,`record_module_slug`,`record_id`),
  CONSTRAINT `configuration_items_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `configuration_items_ibfk_ci_type` FOREIGN KEY (`ci_type_id`) REFERENCES `configuration_item_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `configuration_item_relationships` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `parent_ci_id` int NOT NULL,
  `child_ci_id` int NOT NULL,
  `relationship_type` enum('depends_on','hosts','connects_to','runs_on') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'depends_on',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuration_item_relationships_edge` (`company_id`,`parent_ci_id`,`child_ci_id`,`relationship_type`),
  KEY `company_id` (`company_id`),
  KEY `parent_ci_id` (`parent_ci_id`),
  KEY `child_ci_id` (`child_ci_id`),
  CONSTRAINT `configuration_item_relationships_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `configuration_item_relationships_ibfk_parent` FOREIGN KEY (`parent_ci_id`) REFERENCES `configuration_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `configuration_item_relationships_ibfk_child` FOREIGN KEY (`child_ci_id`) REFERENCES `configuration_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
