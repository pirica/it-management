-- Phase 2 global command-palette search index (denormalized FULLTEXT).
-- Phase 1 uses per-module SQL LIKE via includes/itm_command_palette_search.php.
-- Apply on existing DBs: DROP + CREATE replacement (destructive to search_index rows only).

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `search_index`;

CREATE TABLE `search_index` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_search` (`company_id`,`module_slug`,`record_id`),
  FULLTEXT KEY `ft_search` (`title`,`subtitle`,`keywords`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `search_index_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
