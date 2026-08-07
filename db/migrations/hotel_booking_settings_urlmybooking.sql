-- hotel_booking_settings: urlmybooking (default value https://localhost/it-management/booking/users/bookings.php)
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
