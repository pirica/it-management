-- Migration: Move Price Per Night to Room Type Base Prices
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `hotel_booking_room_type_base_prices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `price_per_night` decimal(12,2) NOT NULL DEFAULT '0.00',
  `active` tinyint DEFAULT '1',
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

-- Copy any existing price_per_night to the new base prices table if we are upgrading a live DB and column exists
SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'hotel_booking_rooms'
    AND column_name = 'price_per_night'
);

SET @sql_copy = IF(@column_exists > 0,
  'INSERT IGNORE INTO `hotel_booking_room_type_base_prices` (`company_id`, `hotel_id`, `room_type_id`, `price_per_night`, `active`) SELECT DISTINCT `company_id`, `hotel_id`, `room_type_id`, `price_per_night`, 1 FROM `hotel_booking_rooms` WHERE `deleted_at` IS NULL',
  'SELECT 1'
);
PREPARE stmt_copy FROM @sql_copy;
EXECUTE stmt_copy;
DEALLOCATE PREPARE stmt_copy;

-- Remove price_per_night from hotel_booking_rooms
SET @sql_drop = IF(@column_exists > 0, 'ALTER TABLE `hotel_booking_rooms` DROP COLUMN `price_per_night`', 'SELECT 1');
PREPARE stmt_drop FROM @sql_drop;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET FOREIGN_KEY_CHECKS = 1;
