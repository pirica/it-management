-- hotel_booking_portal_rate_plans: plan_surcharge_percent (0–50% after plan discount)
-- Preserves existing rows via backup table.

DROP TABLE IF EXISTS `_itm_hotel_booking_portal_rate_plans_backup`;

CREATE TABLE `_itm_hotel_booking_portal_rate_plans_backup` AS
SELECT `id`, `company_id`, `hotel_id`, `plan_slot`, `name`, `rate_plan_slug`,
       `cancellation_policy_url`, `cancellation_policy_html`, `pay_badge`, `price_label`,
       `cancel_template`, `plan_discount_percent`, `free_cancellation_days_before_check_in`,
       `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `hotel_booking_portal_rate_plans`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `hotel_booking_portal_rate_plans`;
CREATE TABLE `hotel_booking_portal_rate_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `plan_slot` tinyint NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_plan_slug` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_policy_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_policy_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pay_badge` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_template` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_discount_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `plan_surcharge_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `free_cancellation_days_before_check_in` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_portal_rate_plans_slot` (`company_id`,`hotel_id`,`plan_slot`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `hotel_booking_portal_rate_plans_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_portal_rate_plans_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `hotel_booking_portal_rate_plans` (
  `id`, `company_id`, `hotel_id`, `plan_slot`, `name`, `rate_plan_slug`,
  `cancellation_policy_url`, `cancellation_policy_html`, `pay_badge`, `price_label`,
  `cancel_template`, `plan_discount_percent`, `plan_surcharge_percent`, `free_cancellation_days_before_check_in`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
)
SELECT
  `id`, `company_id`, `hotel_id`, `plan_slot`, `name`, `rate_plan_slug`,
  `cancellation_policy_url`, `cancellation_policy_html`, `pay_badge`, `price_label`,
  `cancel_template`, `plan_discount_percent`, 0.00, `free_cancellation_days_before_check_in`,
  `active`, `deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`
FROM `_itm_hotel_booking_portal_rate_plans_backup`;

DROP TABLE IF EXISTS `_itm_hotel_booking_portal_rate_plans_backup`;
