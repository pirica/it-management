-- hotel_booking_settings: calendar_month_advance_days_left (Select Dates auto-advance threshold)
-- Preserves existing rows via backup table.

DROP TABLE IF EXISTS `_itm_hotel_booking_settings_backup`;

CREATE TABLE `_itm_hotel_booking_settings_backup` AS
SELECT `id`, `company_id`, `public_portal_enabled`, `welcome_title`, `welcome_subtitle`,
       `accessible_features_default`, `airport_info`, `price_footnote`, `reviews_url`,
       `tourist_tax_per_person_per_night`, `free_cancellation_days_before_check_in`, `urlmybooking`,
       `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `hotel_booking_settings`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `hotel_booking_settings`;
CREATE TABLE `hotel_booking_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `public_portal_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `welcome_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `welcome_subtitle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accessible_features_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `airport_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price_footnote` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviews_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tourist_tax_per_person_per_night` decimal(10,2) NOT NULL DEFAULT '2.00',
  `free_cancellation_days_before_check_in` int NOT NULL DEFAULT '5',
  `calendar_month_advance_days_left` int NOT NULL DEFAULT '3',
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
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `hotel_booking_settings` (
  `id`, `company_id`, `public_portal_enabled`, `welcome_title`, `welcome_subtitle`,
  `accessible_features_default`, `airport_info`, `price_footnote`, `reviews_url`,
  `tourist_tax_per_person_per_night`, `free_cancellation_days_before_check_in`,
  `calendar_month_advance_days_left`, `urlmybooking`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `public_portal_enabled`, `welcome_title`, `welcome_subtitle`,
  `accessible_features_default`, `airport_info`, `price_footnote`, `reviews_url`,
  `tourist_tax_per_person_per_night`, `free_cancellation_days_before_check_in`,
  3, `urlmybooking`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `_itm_hotel_booking_settings_backup`;

DROP TABLE IF EXISTS `_itm_hotel_booking_settings_backup`;

DROP TRIGGER IF EXISTS `trg_hotel_booking_settings_audit_insert`;
DROP TRIGGER IF EXISTS `trg_hotel_booking_settings_audit_update`;
DROP TRIGGER IF EXISTS `trg_hotel_booking_settings_audit_delete`;

DELIMITER $$

CREATE TRIGGER `trg_hotel_booking_settings_audit_insert` AFTER INSERT ON `hotel_booking_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'hotel_booking_settings', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_hotel_booking_settings_audit_update` AFTER UPDATE ON `hotel_booking_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'hotel_booking_settings', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_hotel_booking_settings_audit_delete` AFTER DELETE ON `hotel_booking_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'hotel_booking_settings', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
