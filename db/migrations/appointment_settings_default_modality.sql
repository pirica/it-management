-- appointment_settings: preferred default modality when both in_person and remote are allowed.
-- Back up before apply on production.

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
  `default_appointment_modality` enum('remote','in_person') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'remote' COMMENT 'Preferred type when both modalities are allowed for the selected day',
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
  `check_in_end_buffer_minutes`, `default_appointment_modality`, `active`, `deleted_by`, `deleted_at`,
  `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `timezone`, `slot_duration_minutes`, `bookable_start_time`, `bookable_end_time`,
  `check_in_end_buffer_minutes`, 'remote', `active`, `deleted_by`, `deleted_at`,
  `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `_itm_appointment_settings_backup`;

DROP TABLE IF EXISTS `_itm_appointment_settings_backup`;
