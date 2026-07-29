-- Live DB: booking_rooms_types room-upgrade fields (destructive — back up before apply; re-run booking_rooms_types seeds from db/02_data.sql).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `booking_rooms_types`;
CREATE TABLE `booking_rooms_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bed_summary` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_size_sqm` decimal(8,2) DEFAULT NULL,
  `max_adults` int NOT NULL DEFAULT '2',
  `max_children` int NOT NULL DEFAULT '1',
  `max_babies` int NOT NULL DEFAULT '1',
  `filter_tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_bullets` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `upgrade_to_room_type_id` int DEFAULT NULL,
  `upgrade_price_per_night` decimal(12,2) DEFAULT NULL,
  `upgrade_pitch` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_rooms_types_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `upgrade_to_room_type_id` (`upgrade_to_room_type_id`),
  CONSTRAINT `booking_rooms_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_rooms_types_ibfk_upgrade` FOREIGN KEY (`upgrade_to_room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
