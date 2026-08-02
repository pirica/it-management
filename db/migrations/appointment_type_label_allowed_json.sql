-- appointment_type.label + appointment_business_hours.allowed_types_json (back up before apply).
-- Preserves appointment_type.id values for existing appointments FKs.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `appointment_type_new`;

CREATE TABLE `appointment_type_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
  CONSTRAINT `fk_appointment_type_new_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointment_type_new` (`id`, `company_id`, `name`, `label`, `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`)
SELECT
  `id`,
  `company_id`,
  `name`,
  CASE `name`
    WHEN 'in_person' THEN 'In-person'
    WHEN 'remote' THEN 'Remote'
    ELSE ''
  END,
  `active`,
  `deleted_by`,
  `deleted_at`,
  `created_by`,
  `created_at`,
  `updated_by`,
  `updated_at`
FROM `appointment_type`;

DROP TABLE IF EXISTS `appointment_type`;

RENAME TABLE `appointment_type_new` TO `appointment_type`;

ALTER TABLE `appointment_type` DROP FOREIGN KEY `fk_appointment_type_new_company`;
ALTER TABLE `appointment_type` ADD CONSTRAINT `fk_appointment_type_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

DROP TABLE IF EXISTS `appointment_business_hours_new`;

CREATE TABLE `appointment_business_hours_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `day_of_week` tinyint NOT NULL COMMENT '0=Sunday … 6=Saturday',
  `display_label` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  `allows_in_person` tinyint(1) NOT NULL DEFAULT '0',
  `allows_remote` tinyint(1) NOT NULL DEFAULT '0',
  `allowed_types_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Per-type flags keyed by appointment_type.name',
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
  CONSTRAINT `fk_appointment_business_hours_new_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointment_business_hours_new` (
  `id`, `company_id`, `day_of_week`, `display_label`, `open_time`, `close_time`, `is_closed`,
  `allows_in_person`, `allows_remote`, `allowed_types_json`, `active`, `deleted_by`, `deleted_at`,
  `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `day_of_week`, `display_label`, `open_time`, `close_time`, `is_closed`,
  `allows_in_person`, `allows_remote`,
  CONCAT('{"in_person":', `allows_in_person`, ',"remote":', `allows_remote`, '}'),
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `appointment_business_hours`;

DROP TABLE IF EXISTS `appointment_business_hours`;

RENAME TABLE `appointment_business_hours_new` TO `appointment_business_hours`;

ALTER TABLE `appointment_business_hours` DROP FOREIGN KEY `fk_appointment_business_hours_new_company`;
ALTER TABLE `appointment_business_hours` ADD CONSTRAINT `fk_appointment_business_hours_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
