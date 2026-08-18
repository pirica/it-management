-- Add search_index.active tinyint(1) DEFAULT 1 for scaffold boolean audit (hard DELETE only — no soft-delete columns).
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Destructive: DROP removes all palette index rows — run apply_search_index_backfill.php --apply after.

DROP TABLE IF EXISTS `search_index`;

CREATE TABLE `search_index` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_search` (`company_id`,`module_slug`,`record_id`),
  FULLTEXT KEY `ft_search` (`title`,`subtitle`,`keywords`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `search_index_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
