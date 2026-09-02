-- software_license_links: bidirectional links between software catalog and license_management.
-- Destructive DROP+CREATE for existing databases without the junction table.

DROP TABLE IF EXISTS `software_license_links`;

CREATE TABLE `software_license_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `software_id` int NOT NULL,
  `license_management_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_software_license_links_scope` (`company_id`,`software_id`,`license_management_id`),
  KEY `software_id` (`software_id`),
  KEY `license_management_id` (`license_management_id`),
  CONSTRAINT `software_license_links_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `software_license_links_ibfk_software` FOREIGN KEY (`software_id`) REFERENCES `software` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `software_license_links_ibfk_license` FOREIGN KEY (`license_management_id`) REFERENCES `license_management` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
