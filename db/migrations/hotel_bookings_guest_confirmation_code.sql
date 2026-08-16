-- hotel_bookings.guest_confirmation_code: opaque 10-char guest-facing confirmation (not sequential id).
-- Preserves rows via temporary backup. Run php scripts/verify_hotel_booking.php after apply to backfill legacy auth2.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `_itm_hotel_bookings_backup`;

CREATE TABLE `_itm_hotel_bookings_backup` AS
SELECT
  `id`, `company_id`, `customer_id`, `room_id`, `check_in`, `check_out`, `payment_amount`, `auth2`,
  `future_status_id`, `present_status_id`, `history_status_id`, `portal_rate_plan_id`, `notes`, `booking_color`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `hotel_bookings`;

DROP TABLE IF EXISTS `hotel_bookings`;

CREATE TABLE `hotel_bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `room_id` int NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `guest_confirmation_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth2` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `future_status_id` int DEFAULT NULL,
  `present_status_id` int DEFAULT NULL,
  `history_status_id` int DEFAULT NULL,
  `portal_rate_plan_id` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `booking_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `customer_id` (`customer_id`),
  KEY `room_id` (`room_id`),
  KEY `idx_hotel_bookings_dates` (`company_id`,`check_in`,`check_out`),
  KEY `future_status_id` (`future_status_id`),
  KEY `present_status_id` (`present_status_id`),
  KEY `history_status_id` (`history_status_id`),
  KEY `portal_rate_plan_id` (`portal_rate_plan_id`),
  UNIQUE KEY `uq_hotel_bookings_company_guest_confirmation` (`company_id`,`guest_confirmation_code`),
  CONSTRAINT `hotel_bookings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_bookings_ibfk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hotel_bookings_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hotel_bookings_ibfk_future` FOREIGN KEY (`future_status_id`) REFERENCES `hotel_bookings_future` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_bookings_ibfk_present` FOREIGN KEY (`present_status_id`) REFERENCES `hotel_bookings_present` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_bookings_ibfk_history` FOREIGN KEY (`history_status_id`) REFERENCES `hotel_bookings_history` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_bookings_ibfk_portal_rate_plan` FOREIGN KEY (`portal_rate_plan_id`) REFERENCES `hotel_booking_portal_rate_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hotel_bookings` (
  `id`, `company_id`, `customer_id`, `room_id`, `check_in`, `check_out`, `payment_amount`, `guest_confirmation_code`, `auth2`,
  `future_status_id`, `present_status_id`, `history_status_id`, `portal_rate_plan_id`, `notes`, `booking_color`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `customer_id`, `room_id`, `check_in`, `check_out`, `payment_amount`,
  UPPER(SUBSTRING(SHA2(CONCAT('hb-guest-', `id`, '-', `company_id`), 256), 1, 10)),
  `auth2`,
  `future_status_id`, `present_status_id`, `history_status_id`, `portal_rate_plan_id`, `notes`, `booking_color`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `_itm_hotel_bookings_backup`;

DROP TABLE IF EXISTS `_itm_hotel_bookings_backup`;

SET FOREIGN_KEY_CHECKS = 1;
