-- Per-hotel portal Step 2 pricing columns on hotel_booking_hotels (destructive — back up before apply).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `hotel_booking_hotels`;
CREATE TABLE `hotel_booking_hotels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviews_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `portal_breakfast_adult_price_per_night` decimal(10,2) NOT NULL DEFAULT '30.00',
  `portal_breakfast_child_price_per_night` decimal(10,2) NOT NULL DEFAULT '20.00',
  `portal_child_nightly_supplement` decimal(10,2) NOT NULL DEFAULT '22.00',
  `portal_extra_adult_supplement_percent` decimal(5,2) NOT NULL DEFAULT '35.00',
  `portal_pet_daily_fee` decimal(10,2) NOT NULL DEFAULT '50.00',
  `parking_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pets_policy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_hotels_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_booking_hotels_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
