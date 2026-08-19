-- Last-room snapshot per reservation (cancelled / no-show planning empty row).
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Destructive: DROP removes existing last-room snapshot rows only.

DROP TABLE IF EXISTS `hotel_booking_last_rooms`;

CREATE TABLE `hotel_booking_last_rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `booking_id` int NOT NULL,
  `room_id` int DEFAULT NULL,
  `room_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hotel_id` int DEFAULT NULL,
  `hotel_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type_id` int DEFAULT NULL,
  `room_type_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_last_rooms_company_booking` (`company_id`,`booking_id`),
  KEY `company_id` (`company_id`),
  KEY `booking_id` (`booking_id`),
  KEY `room_id` (`room_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `hotel_booking_last_rooms_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_booking` FOREIGN KEY (`booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
