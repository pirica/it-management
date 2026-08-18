-- Align hotel_booking_room_type_base_prices.active with scaffold tinyint(1) boolean audit.
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Destructive: DROP removes all base price rows for every tenant.

DROP TABLE IF EXISTS `hotel_booking_room_type_base_prices`;

CREATE TABLE `hotel_booking_room_type_base_prices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `price_per_night` decimal(12,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_room_type_base_prices` (`company_id`,`hotel_id`,`room_type_id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `hb_room_type_base_prices_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_room_type_base_prices_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_room_type_base_prices_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
