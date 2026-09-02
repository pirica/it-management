DROP TABLE IF EXISTS `api_key_scopes`;

CREATE TABLE `api_key_scopes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ui_configuration_id` int NOT NULL,
  `scope_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_key_scopes_config_slug` (`company_id`,`ui_configuration_id`,`scope_slug`),
  KEY `idx_api_key_scopes_company` (`company_id`),
  KEY `ui_configuration_id` (`ui_configuration_id`),
  CONSTRAINT `fk_api_key_scopes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_api_key_scopes_ui_configuration` FOREIGN KEY (`ui_configuration_id`) REFERENCES `ui_configuration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
