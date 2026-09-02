-- Stripe Checkout columns for hotel booking portal.
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Destructive: DROP removes existing hotel_bookings rows and distribution reservation links.

DROP TABLE IF EXISTS `hotel_booking_payment_events`;
DROP TABLE IF EXISTS `hotel_booking_distribution_reservations`;
DROP TABLE IF EXISTS `hotel_bookings`;

CREATE TABLE `hotel_bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `room_id` int NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('unpaid','pending','paid','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `stripe_checkout_session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
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

CREATE TABLE `hotel_booking_payment_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `booking_id` int NOT NULL,
  `event_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `booking_id` (`booking_id`),
  KEY `idx_hb_payment_events_company_booking` (`company_id`,`booking_id`),
  CONSTRAINT `hb_payment_events_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_payment_events_ibfk_booking` FOREIGN KEY (`booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hotel_booking_settings`;

CREATE TABLE `hotel_booking_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `public_portal_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `stripe_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `stripe_mode` enum('test','live') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'test',
  `stripe_publishable_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_secret_key_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `stripe_webhook_signing_secret_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deposit_percent` decimal(5,2) NOT NULL DEFAULT '100.00',
  `welcome_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `welcome_subtitle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accessible_features_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `airport_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price_footnote` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviews_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tourist_tax_per_person_per_night` decimal(10,2) NOT NULL DEFAULT '2.00',
  `free_cancellation_days_before_check_in` int NOT NULL DEFAULT '5',
  `calendar_month_advance_days_left` int NOT NULL DEFAULT '3',
  `show_discount_strikethrough` tinyint(1) NOT NULL DEFAULT '1',
  `urlmybooking` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://localhost/it-management/booking/users/bookings.php',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_settings_company` (`company_id`),
  CONSTRAINT `hotel_booking_settings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hotel_booking_settings` (`company_id`, `public_portal_enabled`, `stripe_enabled`, `stripe_mode`, `deposit_percent`, `tourist_tax_per_person_per_night`, `free_cancellation_days_before_check_in`, `calendar_month_advance_days_left`, `show_discount_strikethrough`, `urlmybooking`, `active`, `created_at`)
SELECT c.`id`, 0, 0, 'test', 100.00, 2.00, 5, 3, 1, 'https://localhost/it-management/booking/users/bookings.php', 1, NOW()
FROM `companies` c;
