-- Portal money/tax label settings + per-hotel breakfast child age band.
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Destructive: DROP removes existing hotel_booking_settings rows (re-seeded per company).

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
  `portal_max_discount_percent` decimal(5,2) NOT NULL DEFAULT '50.00',
  `portal_tourist_tax_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Tourist tax',
  `portal_price_includes_tax_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incl. tax',
  `portal_price_includes_tax_long_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incl. taxes',
  `portal_default_rate_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Best available rate',
  `portal_breakfast_rate_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Breakfast included',
  `portal_default_pet_max_weight_kg` int NOT NULL DEFAULT '30',
  `free_cancellation_days_before_check_in` int NOT NULL DEFAULT '5',
  `calendar_month_advance_days_left` int NOT NULL DEFAULT '3',
  `show_discount_strikethrough` tinyint(1) NOT NULL DEFAULT '1',
  `portal_complimentary_min_rooms_paid` int NOT NULL DEFAULT '0',
  `portal_complimentary_rooms_free` int NOT NULL DEFAULT '1',
  `portal_confirmation_email_guest` tinyint(1) NOT NULL DEFAULT '1',
  `portal_confirmation_email_reservations` tinyint(1) NOT NULL DEFAULT '1',
  `portal_show_room_number_on_confirmation` tinyint(1) NOT NULL DEFAULT '0',
  `portal_hide_upgrade_upsell_when_multi_room` tinyint(1) NOT NULL DEFAULT '1',
  `portal_money_symbol` enum('EUR','GBP','USD') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `portal_money_symbol_suffix` tinyint(1) NOT NULL DEFAULT '1',
  `portal_money_symbol_prefix` tinyint(1) NOT NULL DEFAULT '0',
  `portal_show_internal_rates` tinyint(1) NOT NULL DEFAULT '0',
  `portal_date_format` enum('european_ddmmyyyy','us_mmddyyyy','iso_yyyymmdd') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'european_ddmmyyyy',
  `portal_time_format` enum('h24','h12') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'h24',
  `portal_datetime_european1_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `portal_datetime_european2_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `portal_datetime_iso_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `portal_datetime_readable_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `portal_datetime_format_default` enum('european1','european2','iso','readable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'european2',
  `portal_accessible_banner_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `portal_accessibility_options_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `urlaccessibilitypep` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://localhost/it-management/booking/accessibility/pep.html',
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

INSERT INTO `hotel_booking_settings` (`company_id`, `public_portal_enabled`, `stripe_enabled`, `stripe_mode`, `deposit_percent`, `tourist_tax_per_person_per_night`, `portal_max_discount_percent`, `portal_tourist_tax_label`, `portal_price_includes_tax_label`, `portal_price_includes_tax_long_label`, `portal_default_rate_label`, `portal_breakfast_rate_label`, `portal_default_pet_max_weight_kg`, `free_cancellation_days_before_check_in`, `calendar_month_advance_days_left`, `show_discount_strikethrough`, `portal_accessible_banner_enabled`, `portal_accessibility_options_enabled`, `urlaccessibilitypep`, `urlmybooking`, `active`, `created_at`)
SELECT c.`id`, 0, 0, 'test', 100.00, 2.00, 50.00, 'Tourist tax', 'incl. tax', 'incl. taxes', 'Best available rate', 'Breakfast included', 30, 5, 3, 1, 1, 1, 'https://localhost/it-management/booking/accessibility/pep.html', 'https://localhost/it-management/booking/users/bookings.php', 1, NOW()
FROM `companies` c;

-- hotel_booking_hotels: additive columns (FK parent — cannot DROP/recreate safely).
SET @hb_has_child_age_min := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hotel_booking_hotels'
      AND COLUMN_NAME = 'portal_breakfast_child_age_min'
);
SET @hb_ddl := IF(@hb_has_child_age_min = 0,
    'ALTER TABLE `hotel_booking_hotels` ADD COLUMN `portal_breakfast_child_age_min` int NOT NULL DEFAULT 11 AFTER `portal_pet_daily_fee`, ADD COLUMN `portal_breakfast_child_age_max` int NOT NULL DEFAULT 17 AFTER `portal_breakfast_child_age_min`',
    'SELECT 1');
PREPARE hb_stmt FROM @hb_ddl;
EXECUTE hb_stmt;
DEALLOCATE PREPARE hb_stmt;
