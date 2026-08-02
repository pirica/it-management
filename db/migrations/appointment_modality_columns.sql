-- Appointment settings + business hours modality columns (back up before applying on live DB).
-- Replaces allows_online_booking with allows_in_person / allows_remote.
-- Adds allow_in_person / allow_remote on appointment_settings (defaults: In Person off, Remote on).
-- Drops configuration rows — re-open Appointment Settings to run ensure defaults, or restore from backup.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `appointment_business_hours`;

CREATE TABLE `appointment_business_hours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `day_of_week` tinyint NOT NULL COMMENT '0=Sunday … 6=Saturday',
  `display_label` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  `allows_in_person` tinyint(1) NOT NULL DEFAULT '0',
  `allows_remote` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment_business_hours_company_day` (`company_id`,`day_of_week`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_appointment_business_hours_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `appointment_settings`;

CREATE TABLE `appointment_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US/Central',
  `allow_in_person` tinyint(1) NOT NULL DEFAULT '0',
  `allow_remote` tinyint(1) NOT NULL DEFAULT '1',
  `in_person_only` tinyint(1) NOT NULL DEFAULT '0',
  `slot_duration_minutes` int NOT NULL DEFAULT '60',
  `bookable_start_time` time NOT NULL DEFAULT '09:00:00',
  `bookable_end_time` time NOT NULL DEFAULT '14:00:00',
  `check_in_end_buffer_minutes` int NOT NULL DEFAULT '30',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment_settings_company` (`company_id`),
  CONSTRAINT `fk_appointment_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

DROP TRIGGER IF EXISTS `trg_appointment_business_hours_audit_insert`;
DROP TRIGGER IF EXISTS `trg_appointment_business_hours_audit_update`;
DROP TRIGGER IF EXISTS `trg_appointment_business_hours_audit_delete`;
DROP TRIGGER IF EXISTS `trg_appointment_settings_audit_insert`;
DROP TRIGGER IF EXISTS `trg_appointment_settings_audit_update`;
DROP TRIGGER IF EXISTS `trg_appointment_settings_audit_delete`;

DELIMITER $$

CREATE TRIGGER `trg_appointment_business_hours_audit_insert` AFTER INSERT ON `appointment_business_hours` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_business_hours', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'day_of_week', NEW.`day_of_week`, 'display_label', NEW.`display_label`, 'open_time', NEW.`open_time`, 'close_time', NEW.`close_time`, 'is_closed', NEW.`is_closed`, 'allows_in_person', NEW.`allows_in_person`, 'allows_remote', NEW.`allows_remote`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_business_hours_audit_update` AFTER UPDATE ON `appointment_business_hours` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_business_hours', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'day_of_week', OLD.`day_of_week`, 'display_label', OLD.`display_label`, 'open_time', OLD.`open_time`, 'close_time', OLD.`close_time`, 'is_closed', OLD.`is_closed`, 'allows_in_person', OLD.`allows_in_person`, 'allows_remote', OLD.`allows_remote`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'day_of_week', NEW.`day_of_week`, 'display_label', NEW.`display_label`, 'open_time', NEW.`open_time`, 'close_time', NEW.`close_time`, 'is_closed', NEW.`is_closed`, 'allows_in_person', NEW.`allows_in_person`, 'allows_remote', NEW.`allows_remote`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_business_hours_audit_delete` AFTER DELETE ON `appointment_business_hours` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_business_hours', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'day_of_week', OLD.`day_of_week`, 'display_label', OLD.`display_label`, 'open_time', OLD.`open_time`, 'close_time', OLD.`close_time`, 'is_closed', OLD.`is_closed`, 'allows_in_person', OLD.`allows_in_person`, 'allows_remote', OLD.`allows_remote`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_settings_audit_insert` AFTER INSERT ON `appointment_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_settings', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'timezone', NEW.`timezone`, 'allow_in_person', NEW.`allow_in_person`, 'allow_remote', NEW.`allow_remote`, 'in_person_only', NEW.`in_person_only`, 'slot_duration_minutes', NEW.`slot_duration_minutes`, 'bookable_start_time', NEW.`bookable_start_time`, 'bookable_end_time', NEW.`bookable_end_time`, 'check_in_end_buffer_minutes', NEW.`check_in_end_buffer_minutes`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_settings_audit_update` AFTER UPDATE ON `appointment_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_settings', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'timezone', OLD.`timezone`, 'allow_in_person', OLD.`allow_in_person`, 'allow_remote', OLD.`allow_remote`, 'in_person_only', OLD.`in_person_only`, 'slot_duration_minutes', OLD.`slot_duration_minutes`, 'bookable_start_time', OLD.`bookable_start_time`, 'bookable_end_time', OLD.`bookable_end_time`, 'check_in_end_buffer_minutes', OLD.`check_in_end_buffer_minutes`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'timezone', NEW.`timezone`, 'allow_in_person', NEW.`allow_in_person`, 'allow_remote', NEW.`allow_remote`, 'in_person_only', NEW.`in_person_only`, 'slot_duration_minutes', NEW.`slot_duration_minutes`, 'bookable_start_time', NEW.`bookable_start_time`, 'bookable_end_time', NEW.`bookable_end_time`, 'check_in_end_buffer_minutes', NEW.`check_in_end_buffer_minutes`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_settings_audit_delete` AFTER DELETE ON `appointment_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_settings', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'timezone', OLD.`timezone`, 'allow_in_person', OLD.`allow_in_person`, 'allow_remote', OLD.`allow_remote`, 'in_person_only', OLD.`in_person_only`, 'slot_duration_minutes', OLD.`slot_duration_minutes`, 'bookable_start_time', OLD.`bookable_start_time`, 'bookable_end_time', OLD.`bookable_end_time`, 'check_in_end_buffer_minutes', OLD.`check_in_end_buffer_minutes`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
