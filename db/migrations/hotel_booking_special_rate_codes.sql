-- Per-hotel special-rate codes (promo, group, corporate, member) for portal validation.
-- Apply on existing DBs: php scripts/migrate.php --apply

DROP TABLE IF EXISTS `hotel_booking_special_rate_codes`;

CREATE TABLE `hotel_booking_special_rate_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `rate_slug` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_special_rate_codes` (`company_id`,`hotel_id`,`rate_slug`,`code`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `idx_hb_special_rate_codes_lookup` (`company_id`,`hotel_id`,`rate_slug`,`code`,`active`),
  CONSTRAINT `hotel_booking_special_rate_codes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_special_rate_codes_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hotel_booking_special_rate_codes` (`company_id`, `hotel_id`, `rate_slug`, `code`, `label`, `active`, `created_at`) VALUES
(1, 1, 'promo', 'SAVE10', 'Summer save 10%', 1, '2026-01-01 00:00:01'),
(1, 1, 'promo', 'WELCOME1', 'New guest welcome', 1, '2026-01-01 00:00:01'),
(1, 1, 'group', 'GROUP01', 'Sample group block', 1, '2026-01-01 00:00:01'),
(1, 1, 'corporate', 'CORP001', 'TechCorp corporate', 1, '2026-01-01 00:00:01'),
(1, 1, 'member', 'MEMBER01', 'Loyalty member', 1, '2026-01-01 00:00:01');
