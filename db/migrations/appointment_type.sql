-- Replace appointments.appointment_type enum with appointment_type lookup + appointment_type_id FK.
-- Back up before apply; DROP appointments removes existing bookings.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `appointments`;

DROP TABLE IF EXISTS `appointment_type`;

CREATE TABLE `appointment_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment_type_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_appointment_type_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointment_type` (`company_id`, `name`, `active`, `created_at`)
SELECT c.`id`, v.`name`, 1, '2026-01-01 00:00:01'
FROM `companies` c
CROSS JOIN (
  SELECT 'in_person' AS `name`
  UNION ALL SELECT 'remote'
) v
WHERE NOT EXISTS (
  SELECT 1 FROM `appointment_type` t WHERE t.`company_id` = c.`id` AND t.`name` = v.`name`
);

CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `visit_reason_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `appointment_type_id` int NOT NULL,
  `status` enum('scheduled','cancelled','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US/Central',
  `booking_lock` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Set for scheduled rows to enforce one booking per company slot; cleared on soft-delete',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `visit_reason_id` (`visit_reason_id`),
  KEY `appointment_type_id` (`appointment_type_id`),
  KEY `idx_appointments_company_date` (`company_id`,`appointment_date`,`start_time`),
  UNIQUE KEY `uq_appointments_company_booking_lock` (`company_id`,`booking_lock`),
  CONSTRAINT `fk_appointments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_appointments_visit_reason` FOREIGN KEY (`visit_reason_id`) REFERENCES `appointment_visit_reasons` (`id`),
  CONSTRAINT `fk_appointments_appointment_type` FOREIGN KEY (`appointment_type_id`) REFERENCES `appointment_type` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Re-apply trg_appointments_audit_* and trg_appointment_type_audit_* from db/03_triggers.sql if triggers are missing.
