-- Live DB: hotel_booking_housekeeping_statuses code column (departments-style short code; destructive — back up before apply).
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `hotel_booking_housekeeping_statuses`;
CREATE TABLE `hotel_booking_housekeeping_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_hex` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_hk_status_company_name` (`company_id`,`name`),
  UNIQUE KEY `uq_hotel_booking_hk_status_company_code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_booking_hk_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-run hotel_booking_housekeeping_statuses seeds from db/02_data.sql after apply.
