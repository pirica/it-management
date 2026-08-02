-- Live DB: adds hotel_booking_special_rates and extends booking_rooms_types for portal occupancy/filters.
-- Back up before apply. Fresh installs: use full db/01_schema.sql import instead.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `hotel_booking_special_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `rate_slug` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_special_rates_slug` (`company_id`,`hotel_id`,`rate_slug`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `hotel_booking_special_rates_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_special_rates_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- booking_rooms_types: replace table from db/01_schema.sql CREATE block when columns are missing (DROP + CREATE).
-- Then run seed rows from db/02_data.sql booking_rooms_types / hotel_booking_special_rates inserts.

SET FOREIGN_KEY_CHECKS = 1;
