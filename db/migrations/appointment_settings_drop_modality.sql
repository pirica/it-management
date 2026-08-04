-- Drops allow_in_person, allow_remote, and in_person_only from appointment_settings.
-- Modality is configured only on appointment_business_hours (per weekday).
-- Back up appointment_settings before applying on production.

DROP TABLE IF EXISTS `_itm_appointment_settings_backup`;

CREATE TABLE `_itm_appointment_settings_backup` AS
SELECT `id`, `company_id`, `timezone`, `slot_duration_minutes`, `bookable_start_time`, `bookable_end_time`,
       `check_in_end_buffer_minutes`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`,
       `updated_by`, `updated_at`
FROM `appointment_settings`;

DROP TABLE IF EXISTS `appointment_settings`;

CREATE TABLE `appointment_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US/Central',
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

INSERT INTO `appointment_settings` (
  `id`, `company_id`, `timezone`, `slot_duration_minutes`, `bookable_start_time`, `bookable_end_time`,
  `check_in_end_buffer_minutes`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `timezone`, `slot_duration_minutes`, `bookable_start_time`, `bookable_end_time`,
  `check_in_end_buffer_minutes`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `_itm_appointment_settings_backup`;

DROP TABLE IF EXISTS `_itm_appointment_settings_backup`;

DROP TRIGGER IF EXISTS `trg_appointment_settings_audit_insert`;
DROP TRIGGER IF EXISTS `trg_appointment_settings_audit_update`;
DROP TRIGGER IF EXISTS `trg_appointment_settings_audit_delete`;

DELIMITER $$

CREATE TRIGGER `trg_appointment_settings_audit_insert` AFTER INSERT ON `appointment_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_settings', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'timezone', NEW.`timezone`, 'slot_duration_minutes', NEW.`slot_duration_minutes`, 'bookable_start_time', NEW.`bookable_start_time`, 'bookable_end_time', NEW.`bookable_end_time`, 'check_in_end_buffer_minutes', NEW.`check_in_end_buffer_minutes`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_settings_audit_update` AFTER UPDATE ON `appointment_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_settings', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'timezone', OLD.`timezone`, 'slot_duration_minutes', OLD.`slot_duration_minutes`, 'bookable_start_time', OLD.`bookable_start_time`, 'bookable_end_time', OLD.`bookable_end_time`, 'check_in_end_buffer_minutes', OLD.`check_in_end_buffer_minutes`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'timezone', NEW.`timezone`, 'slot_duration_minutes', NEW.`slot_duration_minutes`, 'bookable_start_time', NEW.`bookable_start_time`, 'bookable_end_time', NEW.`bookable_end_time`, 'check_in_end_buffer_minutes', NEW.`check_in_end_buffer_minutes`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_appointment_settings_audit_delete` AFTER DELETE ON `appointment_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'appointment_settings', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'timezone', OLD.`timezone`, 'slot_duration_minutes', OLD.`slot_duration_minutes`, 'bookable_start_time', OLD.`bookable_start_time`, 'bookable_end_time', OLD.`bookable_end_time`, 'check_in_end_buffer_minutes', OLD.`check_in_end_buffer_minutes`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
