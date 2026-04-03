-- IT Management SQL Backup
-- Generated at: 2026-03-28 19:52:18 UTC
-- Complete IT Management System Database
DROP DATABASE IF EXISTS `itmanagement`;
CREATE DATABASE `itmanagement` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `itmanagement`;
SET FOREIGN_KEY_CHECKS=0;


-- Table structure for `access_levels`
DROP TABLE IF EXISTS `access_levels`;
CREATE TABLE `access_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `access_levels_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `access_levels`
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Full');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Limited');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Read Only');

-- Table structure for `assignment_types`
DROP TABLE IF EXISTS `assignment_types`;
CREATE TABLE `assignment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `assignment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `assignment_types`
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Department');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Individual');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Pool');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Shared');

-- Table structure for `companies`
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `incode` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company` (`company`),
  UNIQUE KEY `incode` (`incode`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `companies`
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('1', 'TechCorp Global', 'TC001', 'New York', 'USA', '+1-212-555-0101', 'info@techcorp.example', 'https://techcorp.example', 'US-TC-1001', 'Head office company profile', '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('2', 'DataCenter Plus', 'DCP001', 'Dallas', 'USA', '+1-972-555-0102', 'contact@datacenterplus.example', 'https://datacenterplus.example', 'US-DCP-1002', '', '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('3', 'Network Solutions', 'NSI001', 'San Francisco', 'USA', '+1-415-555-0103', 'hello@networksolutions.example', 'https://networksolutions.example', 'US-NSI-1003', '', '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('4', 'CloudTech Services', 'CTS001', 'Seattle', 'USA', '+1-206-555-0104', 'support@cloudtech.example', 'https://cloudtech.example', 'US-CTS-1004', '', '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('5', 'Enterprise IT', 'EIT001', 'Boston', 'USA', '+1-617-555-0105', 'office@enterpriseit.example', 'https://enterpriseit.example', 'US-EIT-1005', '', '1', '2026-03-28 19:43:17', NULL);

-- Table structure for `departments`
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `departments`
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('1', '1', 'IT Operations', 'ITOPS', 'Core IT operations team', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('2', '1', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('3', '1', 'Human Resources', 'HR', 'Human resources department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('4', '1', 'Housekeeping', 'HK', 'Housekeeping operations', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('5', '4', 'Front Office', '', '', '1');

-- Table structure for `employee_onboarding_requests`
DROP TABLE IF EXISTS `employee_onboarding_requests`;
CREATE TABLE `employee_onboarding_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `network_access` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `micros_emc` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opera` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `micros_card` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `synergy_mms` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_account` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landline_phone` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hu_the_lobby` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `navision` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_email` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onq_ri` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birchstreet` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delphi` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omina` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vingcard_system` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digital_rev` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_key_card` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `starting_date` date DEFAULT NULL,
  `requested_by` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_on` date DEFAULT NULL,
  `hod_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hrd_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ism_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_onboarding_requests_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_onboarding_requests_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_onboarding_requests`
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `first_name`, `last_name`, `position_title`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `comments`, `starting_date`, `requested_by`, `requested_on`, `hod_approval`, `hrd_approval`, `ism_approval`, `created_at`) VALUES ('1', '1', '4', 'NICKY', 'SCHOUTEN', 'TRAINEE', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', 'Starting date: 16/03/2026 || 302325432@student.rocmondriaan.nl', '2026-03-16', 'ALEXANDRANUNES', '2026-03-24', 'Sonia Costa', 'Pedro Mendes', 'Kenneth Starreveld', '2026-03-28 19:43:17');

-- Table structure for `employee_statuses`
DROP TABLE IF EXISTS `employee_statuses`;
CREATE TABLE `employee_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_statuses`
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Contractor');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inactive');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'On Leave');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Terminated');

-- Table structure for `employee_system_access`
DROP TABLE IF EXISTS `employee_system_access`;
CREATE TABLE `employee_system_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `network_access` tinyint(1) NOT NULL DEFAULT '0',
  `micros_emc` tinyint(1) NOT NULL DEFAULT '0',
  `opera_username` tinyint(1) NOT NULL DEFAULT '0',
  `micros_card` tinyint(1) NOT NULL DEFAULT '0',
  `pms_id` tinyint(1) NOT NULL DEFAULT '0',
  `synergy_mms` tinyint(1) NOT NULL DEFAULT '0',
  `hu_the_lobby` tinyint(1) NOT NULL DEFAULT '0',
  `navision` tinyint(1) NOT NULL DEFAULT '0',
  `onq_ri` tinyint(1) NOT NULL DEFAULT '0',
  `birchstreet` tinyint(1) NOT NULL DEFAULT '0',
  `delphi` tinyint(1) NOT NULL DEFAULT '0',
  `omina` tinyint(1) NOT NULL DEFAULT '0',
  `vingcard_system` tinyint(1) NOT NULL DEFAULT '0',
  `digital_rev` tinyint(1) NOT NULL DEFAULT '0',
  `office_key_card` tinyint(1) NOT NULL DEFAULT '0',
  `changed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_system_access_company_employee` (`company_id`,`employee_id`),
  KEY `idx_employee_system_access_company` (`company_id`),
  KEY `fk_employee_system_access_employee` (`employee_id`),
  CONSTRAINT `fk_employee_system_access_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `employee_system_access_relations`
DROP TABLE IF EXISTS `employee_system_access_relations`;
CREATE TABLE `employee_system_access_relations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `system_access_id` int NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_esa_rel_company_employee_system` (`company_id`,`employee_id`,`system_access_id`),
  KEY `idx_esa_rel_company_employee` (`company_id`,`employee_id`),
  KEY `idx_esa_rel_system_access` (`system_access_id`),
  KEY `fk_esa_rel_employee` (`employee_id`),
  CONSTRAINT `fk_esa_rel_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esa_rel_system_access` FOREIGN KEY (`system_access_id`) REFERENCES `system_access` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `employees`
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `duplicate` tinyint(1) NOT NULL DEFAULT '0',
  `company_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hilton_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `job_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `request_date` date DEFAULT NULL,
  `requested_by` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termination_requested_by` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `network_access` tinyint(1) DEFAULT '0',
  `micros_emc` tinyint(1) DEFAULT '0',
  `opera_username` tinyint(1) DEFAULT '0',
  `micros_card` tinyint(1) DEFAULT '0',
  `pms_id` tinyint(1) DEFAULT '0',
  `synergy_mms` tinyint(1) DEFAULT '0',
  `hu_the_lobby` tinyint(1) DEFAULT '0',
  `navision` tinyint(1) DEFAULT '0',
  `onq_ri` tinyint(1) DEFAULT '0',
  `birchstreet` tinyint(1) DEFAULT '0',
  `delphi` tinyint(1) DEFAULT '0',
  `omina` tinyint(1) DEFAULT '0',
  `vingcard_system` tinyint(1) DEFAULT '0',
  `digital_rev` tinyint(1) DEFAULT '0',
  `office_key_card` tinyint(1) DEFAULT '0',
  `office_key_card_department_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `employment_status_id` int NOT NULL,
  `raw_status_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `idx_employees_hilton_id` (`hilton_id`),
  KEY `idx_employees_username` (`username`),
  KEY `employment_status_id` (`employment_status_id`),
  KEY `idx_employees_office_key_department` (`office_key_card_department_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `employees_ibfk_5` FOREIGN KEY (`office_key_card_department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_6` FOREIGN KEY (`employment_status_id`) REFERENCES `employee_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `equipment`
DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_type_id` int NOT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `rack_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hostname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `patch_port` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `certificate_expiry` date DEFAULT NULL,
  `warranty_type_id` int DEFAULT NULL,
  `is_printer` tinyint DEFAULT '0',
  `printer_device_type_id` int DEFAULT NULL,
  `printer_color_capable` tinyint DEFAULT '0',
  `printer_scan` tinyint DEFAULT NULL,
  `is_workstation` tinyint DEFAULT '0',
  `is_server` tinyint DEFAULT '0',
  `is_pos` tinyint DEFAULT '0',
  `is_switch` tinyint DEFAULT '0',
  `workstation_device_type_id` int DEFAULT NULL,
  `workstation_os_type_id` int DEFAULT NULL,
  `workstation_processor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_storage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_os_installed_on` date DEFAULT NULL,
  `workstation_ram_id` int DEFAULT NULL,
  `workstation_os_version_id` int DEFAULT NULL,
  `switch_rj45_id` int DEFAULT NULL,
  `switch_port_numbering_layout_id` int DEFAULT '1',
  `switch_fiber_id` int DEFAULT NULL,
  `switch_fiber_patch_id` int DEFAULT NULL,
  `switch_fiber_rack_id` int DEFAULT NULL,
  `switch_fiber_count_id` int DEFAULT NULL,
  `switch_fiber_ports_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `switch_fiber_port_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `switch_poe_id` int DEFAULT NULL,
  `switch_environment_id` int DEFAULT NULL,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `photo_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `equipment_type_id` (`equipment_type_id`),
  KEY `manufacturer_id` (`manufacturer_id`),
  KEY `location_id` (`location_id`),
  KEY `rack_id` (`rack_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  KEY `is_printer` (`is_printer`),
  KEY `is_workstation` (`is_workstation`),
  KEY `is_server` (`is_server`),
  KEY `is_pos` (`is_pos`),
  KEY `is_switch` (`is_switch`),
  KEY `warranty_type_id` (`warranty_type_id`),
  KEY `printer_device_type_id` (`printer_device_type_id`),
  KEY `workstation_device_type_id` (`workstation_device_type_id`),
  KEY `workstation_os_type_id` (`workstation_os_type_id`),
  KEY `workstation_ram_id` (`workstation_ram_id`),
  KEY `workstation_os_version_id` (`workstation_os_version_id`),
  KEY `switch_rj45_id` (`switch_rj45_id`),
  KEY `switch_port_numbering_layout_id` (`switch_port_numbering_layout_id`),
  KEY `switch_fiber_id` (`switch_fiber_id`),
  KEY `switch_fiber_patch_id` (`switch_fiber_patch_id`),
  KEY `switch_fiber_rack_id` (`switch_fiber_rack_id`),
  KEY `switch_fiber_count_id` (`switch_fiber_count_id`),
  KEY `switch_poe_id` (`switch_poe_id`),
  KEY `switch_environment_id` (`switch_environment_id`),
  CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipment_ibfk_10` FOREIGN KEY (`workstation_os_type_id`) REFERENCES `workstation_os_types` (`id`),
  CONSTRAINT `equipment_ibfk_17` FOREIGN KEY (`workstation_ram_id`) REFERENCES `workstation_ram` (`id`),
  CONSTRAINT `equipment_ibfk_19` FOREIGN KEY (`workstation_os_version_id`) REFERENCES `workstation_os_versions` (`id`),
  CONSTRAINT `equipment_ibfk_11` FOREIGN KEY (`switch_rj45_id`) REFERENCES `equipment_rj45` (`id`),
  CONSTRAINT `equipment_ibfk_12` FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`),
  CONSTRAINT `equipment_ibfk_13` FOREIGN KEY (`switch_fiber_id`) REFERENCES `equipment_fiber` (`id`),
  CONSTRAINT `equipment_ibfk_20` FOREIGN KEY (`switch_fiber_patch_id`) REFERENCES `equipment_fiber_patch` (`id`),
  CONSTRAINT `equipment_ibfk_21` FOREIGN KEY (`switch_fiber_rack_id`) REFERENCES `equipment_fiber_rack` (`id`),
  CONSTRAINT `equipment_ibfk_14` FOREIGN KEY (`switch_poe_id`) REFERENCES `equipment_poe` (`id`),
  CONSTRAINT `equipment_ibfk_15` FOREIGN KEY (`switch_environment_id`) REFERENCES `equipment_environment` (`id`),
  CONSTRAINT `equipment_ibfk_16` FOREIGN KEY (`switch_fiber_count_id`) REFERENCES `equipment_fiber_count` (`id`),
  CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`),
  CONSTRAINT `equipment_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `equipment_ibfk_5` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_6` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`),
  CONSTRAINT `equipment_ibfk_7` FOREIGN KEY (`warranty_type_id`) REFERENCES `warranty_types` (`id`),
  CONSTRAINT `equipment_ibfk_8` FOREIGN KEY (`printer_device_type_id`) REFERENCES `printer_device_types` (`id`),
  CONSTRAINT `equipment_ibfk_9` FOREIGN KEY (`workstation_device_type_id`) REFERENCES `workstation_device_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment`
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `is_printer`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `is_workstation`, `is_server`, `is_pos`, `is_switch`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '2', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, '1', '2025-01-10', '8500.00', NULL, '2027-01-10', '4', '0', NULL, '0', NULL, '1', '0', '0', '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', '2026-03-31 00:39:19');

-- Table structure for `equipment_environment`
DROP TABLE IF EXISTS `equipment_environment`;
CREATE TABLE `equipment_environment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_environment_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_environment`
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Managed');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Unmanaged');

-- Table structure for `equipment_fiber`
DROP TABLE IF EXISTS `equipment_fiber`;
CREATE TABLE `equipment_fiber` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber`
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('1', '3', 'QSFP 40 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('1', '1', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('1', '2', 'SFP+ 10 Gbps');

-- Table structure for `equipment_fiber_patch`
DROP TABLE IF EXISTS `equipment_fiber_patch`;
CREATE TABLE `equipment_fiber_patch` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_patch_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_patch`
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Patch Panel A');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Patch Panel B');

-- Table structure for `equipment_fiber_rack`
DROP TABLE IF EXISTS `equipment_fiber_rack`;
CREATE TABLE `equipment_fiber_rack` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_rack_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_rack`
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Rack A');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Rack B');

-- Table structure for `equipment_fiber_count`
DROP TABLE IF EXISTS `equipment_fiber_count`;
CREATE TABLE `equipment_fiber_count` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_count_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_count`
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '1', '1');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '2', '2');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '3', '3');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '4', '4');

-- Table structure for `equipment_poe`
DROP TABLE IF EXISTS `equipment_poe`;
CREATE TABLE `equipment_poe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_poe_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_poe`
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('1', '1', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('1', '2', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('1', '3', 'PoE++ (802.3bt) - up to 60-90W');

-- Table structure for `equipment_rj45`
DROP TABLE IF EXISTS `equipment_rj45`;
CREATE TABLE `equipment_rj45` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_rj45_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_rj45`
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '2', '16 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '3', '24 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '4', '48 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '1', '8 ports');

-- Table structure for `equipment_statuses`
DROP TABLE IF EXISTS `equipment_statuses`;
CREATE TABLE `equipment_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_statuses`
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Decommissioned');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Faulty');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inactive');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Maintenance');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '7', 'On-Order');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Other');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Reserved');

-- Table structure for `equipment_types`
DROP TABLE IF EXISTS `equipment_types`;
CREATE TABLE `equipment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_types`
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Switch', 'SWITCH', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Server', 'SRV', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'Router', 'RTR', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Firewall', 'FW', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Port Patch Panel', 'PORT', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '6', 'Access Point', 'AP', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '7', 'Workstation', 'WS', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '8', 'POS', 'POS', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '9', 'Printer', 'PRN', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '10', 'Phone', 'PHONE', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '11', 'CCTV', 'CCCTV', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '12', 'Other', 'OTHER', '1');

-- Table structure for `idf_links`
DROP TABLE IF EXISTS `idf_links`;
CREATE TABLE `idf_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `port_id_a` int NOT NULL,
  `port_id_b` int NOT NULL,
  `cable_color` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yellow',
  `cable_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`company_id`,`port_id_a`,`port_id_b`),
  KEY `port_id_a` (`port_id_a`),
  KEY `port_id_b` (`port_id_b`),
  CONSTRAINT `idf_links_ibfk_a` FOREIGN KEY (`port_id_a`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_b` FOREIGN KEY (`port_id_b`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  KEY `company_id` (`company_id`),
  CONSTRAINT `idf_links_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `idf_ports`
DROP TABLE IF EXISTS `idf_ports`;
CREATE TABLE `idf_ports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `position_id` int NOT NULL,
  `port_no` smallint NOT NULL,
  `port_type` enum('RJ45','SFP','SFP+','LC','SC','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RJ45',
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('free','used','reserved','down','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `connected_to` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vlan` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `speed` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poe` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_port_unique` (`company_id`,`position_id`,`port_no`),
  KEY `company_id` (`company_id`),
  KEY `position_id` (`position_id`),
  CONSTRAINT `idf_ports_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_ports_ibfk_position` FOREIGN KEY (`position_id`) REFERENCES `idf_positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idf_ports`
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('1', '1', '1', '1', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('2', '1', '1', '2', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('3', '1', '1', '3', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('4', '1', '1', '4', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('5', '1', '1', '5', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('6', '1', '1', '6', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('7', '1', '1', '7', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('8', '1', '1', '8', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('9', '1', '1', '9', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('10', '1', '1', '10', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('11', '1', '1', '11', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('12', '1', '1', '12', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('13', '1', '1', '13', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('14', '1', '1', '14', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('15', '1', '1', '15', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('16', '1', '1', '16', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('17', '1', '1', '17', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('18', '1', '1', '18', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('19', '1', '1', '19', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('20', '1', '1', '20', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('21', '1', '1', '21', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('22', '1', '1', '22', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('23', '1', '1', '23', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `idf_ports` (`id`, `company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) VALUES ('24', '1', '1', '24', 'RJ45', NULL, 'unknown', NULL, NULL, NULL, NULL, NULL, NULL);

-- Table structure for `idf_positions`
DROP TABLE IF EXISTS `idf_positions`;
CREATE TABLE `idf_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `idf_id` int NOT NULL,
  `position_no` tinyint NOT NULL,
  `device_type` enum('switch','patch_panel','ups','server','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `device_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `port_count` smallint NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_pos_unique` (`company_id`,`idf_id`,`position_no`),
  KEY `company_id` (`company_id`),
  KEY `idf_id` (`idf_id`),
  KEY `equipment_id` (`equipment_id`),
  CONSTRAINT `idf_positions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_positions_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_positions_ibfk_idf` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idf_positions`
INSERT INTO `idf_positions` (`id`, `company_id`, `idf_id`, `position_no`, `device_type`, `device_name`, `equipment_id`, `port_count`, `notes`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '2', 'switch', 'cxcz', '1', '24', NULL, '2026-03-31 00:35:24', '2026-03-31 00:35:39');

-- Table structure for `idfs`
DROP TABLE IF EXISTS `idfs`;
CREATE TABLE `idfs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `location_id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `idf_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_code` (`company_id`,`idf_code`),
  KEY `company_id` (`company_id`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `idfs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idfs_ibfk_location` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idfs`
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `name`, `idf_code`, `notes`, `created_at`) VALUES ('1', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '2026-03-31 00:25:58');

-- Table structure for `inventory_categories`
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `inventory_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_categories`
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '6', 'Other', 'OTH', '1');

-- Table structure for `inventory_items`
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `quantity_minimum` int DEFAULT '5',
  `price_eur` decimal(10,2) DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`company_id`,`item_code`),
  KEY `category_id` (`category_id`),
  KEY `manufacturer_id` (`manufacturer_id`),
  KEY `location_id` (`location_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`),
  CONSTRAINT `inventory_items_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `inventory_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `inventory_items_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_items`
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`) VALUES ('1', '1', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '1', '1', '50', '10', '4.99', 'Stock for patching and desktop setups', '1', '1', '1');

-- Table structure for `it_locations`
DROP TABLE IF EXISTS `it_locations`;
CREATE TABLE `it_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `type_id` (`type_id`),
  CONSTRAINT `it_locations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_locations_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `location_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `it_locations`
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`) VALUES ('1', '1', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1');

-- Table structure for `location_types`
DROP TABLE IF EXISTS `location_types`;
CREATE TABLE `location_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `location_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `location_types`
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Branch');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'DataCenter');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Headquarters');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Office');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'Other');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Remote');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Warehouse');

-- Table structure for `manufacturers`
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE `manufacturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `manufacturers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `manufacturers`
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '6', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '7', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '8', 'Microsoft', 'MSFT', '1');

-- Table structure for `printer_device_types`
DROP TABLE IF EXISTS `printer_device_types`;
CREATE TABLE `printer_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `printer_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `printer_device_types`
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'All-in-One');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Dotmatrix');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inkjet');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'Label');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Laser');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '9', 'Other');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Photo');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Thermal');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Wide-Format');

-- Table structure for `rack_statuses`
DROP TABLE IF EXISTS `rack_statuses`;
CREATE TABLE `rack_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `rack_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `rack_statuses`
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Decommissioned');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Full');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Maintenance');

-- Table structure for `racks`
DROP TABLE IF EXISTS `racks`;
CREATE TABLE `racks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `location_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rack_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rack_code` (`company_id`,`rack_code`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `racks_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `rack_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `racks`
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('1', '1', '1', 'Main Rack A', 'RACK-A', '1', '1');

-- Table structure for `sidebar_layout`
DROP TABLE IF EXISTS `sidebar_layout`;
CREATE TABLE `sidebar_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `entry_type` enum('section','item') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sidebar_layout_entry` (`company_id`,`entry_type`,`entry_id`),
  KEY `idx_sidebar_layout_company_type_order` (`company_id`,`entry_type`,`display_order`),
  CONSTRAINT `fk_sidebar_layout_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `supplier_statuses`
DROP TABLE IF EXISTS `supplier_statuses`;
CREATE TABLE `supplier_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `supplier_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `supplier_statuses`
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Backup');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inactive');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Other');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Preferred');

-- Table structure for `suppliers`
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`company_id`,`supplier_code`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `supplier_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `suppliers`
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('1', '1', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '1', '1');

-- Table structure for `switch_cablecolors`
DROP TABLE IF EXISTS `switch_cablecolors`;
CREATE TABLE `switch_cablecolors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grey',
  PRIMARY KEY (`id`),
  UNIQUE KEY `color` (`company_id`,`color`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_cablecolors_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_cablecolors`
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '5', 'black');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '6', 'blue');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '2', 'green');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '1', 'grey');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '8', 'orange');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '10', 'other');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '9', 'purple');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '3', 'red');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '7', 'white');
INSERT INTO `switch_cablecolors` (`company_id`, `id`, `color`) VALUES ('1', '4', 'yellow');

-- Table structure for `switch_port_numbering_layout`
DROP TABLE IF EXISTS `switch_port_numbering_layout`;
CREATE TABLE `switch_port_numbering_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_port_numbering_layout_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_numbering_layout`
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Horizontal');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Vertical');

-- Table structure for `switch_port_types`
DROP TABLE IF EXISTS `switch_port_types`;
CREATE TABLE `switch_port_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`company_id`,`type`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_port_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_types`
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '1', 'RJ45');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '2', 'SFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '3', 'SFP+');

-- Table structure for `switch_ports`
DROP TABLE IF EXISTS `switch_ports`;
CREATE TABLE `switch_ports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RJ45',
  `port_number` int NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `color_id` int NOT NULL,
  `vlan_id` int DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_switch_port` (`company_id`,`equipment_id`,`port_type`,`port_number`),
  KEY `company_id` (`company_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `idx_switch_ports_company_port_type` (`company_id`,`port_type`),
  KEY `status_id` (`status_id`),
  KEY `color_id` (`color_id`),
  KEY `vlan_id` (`vlan_id`),
  CONSTRAINT `switch_ports_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`),
  CONSTRAINT `switch_ports_ibfk_4` FOREIGN KEY (`color_id`) REFERENCES `switch_cablecolors` (`id`),
  CONSTRAINT `switch_ports_ibfk_5` FOREIGN KEY (`company_id`,`port_type`) REFERENCES `switch_port_types` (`company_id`,`type`),
  CONSTRAINT `switch_ports_ibfk_6` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_ports`
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('1', '1', '1', NULL, 'RJ45', '1', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('2', '1', '1', NULL, 'RJ45', '2', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('3', '1', '1', NULL, 'RJ45', '3', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('4', '1', '1', NULL, 'RJ45', '4', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('5', '1', '1', NULL, 'RJ45', '5', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('6', '1', '1', NULL, 'RJ45', '6', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('7', '1', '1', NULL, 'RJ45', '7', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('8', '1', '1', NULL, 'RJ45', '8', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('9', '1', '1', NULL, 'RJ45', '9', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('10', '1', '1', NULL, 'RJ45', '10', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('11', '1', '1', NULL, 'RJ45', '11', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('12', '1', '1', NULL, 'RJ45', '12', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('13', '1', '1', NULL, 'RJ45', '13', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('14', '1', '1', NULL, 'RJ45', '14', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('15', '1', '1', NULL, 'RJ45', '15', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('16', '1', '1', NULL, 'RJ45', '16', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('17', '1', '1', NULL, 'RJ45', '17', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('18', '1', '1', NULL, 'RJ45', '18', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('19', '1', '1', NULL, 'RJ45', '19', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('20', '1', '1', NULL, 'RJ45', '20', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('21', '1', '1', NULL, 'RJ45', '21', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('22', '1', '1', NULL, 'RJ45', '22', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('23', '1', '1', NULL, 'RJ45', '23', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`) VALUES ('24', '1', '1', NULL, 'RJ45', '24', '0', '5', '1', NULL, '', '2026-03-31 00:39:19');

-- Table structure for `switch_status`
DROP TABLE IF EXISTS `switch_status`;
CREATE TABLE `switch_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  PRIMARY KEY (`id`),
  UNIQUE KEY `status` (`company_id`,`status`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_status`
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '4', 'Disabled');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '2', 'Down');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '6', 'Err-Disabled');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '8', 'Faulty');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '3', 'Free');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '9', 'Reserved');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '7', 'Testing');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '5', 'Unknown');
INSERT INTO `switch_status` (`company_id`, `id`, `status`) VALUES ('1', '1', 'Up');

-- Table structure for `system_access`
DROP TABLE IF EXISTS `system_access`;
CREATE TABLE `system_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_access_company_code` (`company_id`,`code`),
  UNIQUE KEY `uq_system_access_company_name` (`company_id`,`name`),
  KEY `idx_system_access_company` (`company_id`),
  CONSTRAINT `fk_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `system_access`
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('1', '1', 'network_access', 'Network Access', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('2', '1', 'micros_emc', 'Micros Emc', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('3', '1', 'opera_username', 'Opera Username', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('4', '1', 'micros_card', 'Micros Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('5', '1', 'pms_id', 'Pms Id', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('6', '1', 'synergy_mms', 'Synergy Mms', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('7', '1', 'hu_the_lobby', 'Hu The Lobby', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('8', '1', 'navision', 'Navision', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('9', '1', 'onq_ri', 'Onq Ri', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('10', '1', 'birchstreet', 'Birchstreet', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('11', '1', 'delphi', 'Delphi', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('12', '1', 'omina', 'Omina', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('13', '1', 'vingcard_system', 'Vingcard System', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('14', '1', 'digital_rev', 'Digital Rev', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('15', '1', 'office_key_card', 'Office Key Card', '1');

-- Table structure for `ticket_categories`
DROP TABLE IF EXISTS `ticket_categories`;
CREATE TABLE `ticket_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_categories`
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Other', 'OTHER', '1');

-- Table structure for `ticket_priorities`
DROP TABLE IF EXISTS `ticket_priorities`;
CREATE TABLE `ticket_priorities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_priorities_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_priorities`
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '1', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '2', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '3', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '4', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '5', 'Critical', '5', '#8B0000', '1');

-- Table structure for `ticket_statuses`
DROP TABLE IF EXISTS `ticket_statuses`;
CREATE TABLE `ticket_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_closed` tinyint DEFAULT '0',
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_statuses`
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '2', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '3', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '4', 'Closed', '#808080', '1', '1');

-- Table structure for `tickets`
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_external_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` int DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  `priority_id` int DEFAULT NULL,
  `created_by_user_id` int NOT NULL,
  `assigned_to_user_id` int DEFAULT NULL,
  `asset_id` int DEFAULT NULL,
  `ui_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#0969da',
  `tickets_photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_external_code` (`company_id`,`ticket_external_code`),
  KEY `category_id` (`category_id`),
  KEY `status_id` (`status_id`),
  KEY `priority_id` (`priority_id`),
  KEY `created_by_user_id` (`created_by_user_id`),
  KEY `assigned_to_user_id` (`assigned_to_user_id`),
  KEY `asset_id` (`asset_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses` (`id`),
  CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`),
  CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_ibfk_6` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `tickets_ibfk_7` FOREIGN KEY (`asset_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `tickets`
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`) VALUES ('1', '1', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '4', '1', '2', '1', '1', '1', '#0969da', NULL, '2026-03-28 19:43:17');

-- Table structure for `ui_configuration`
DROP TABLE IF EXISTS `ui_configuration`;
CREATE TABLE `ui_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `table_actions_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `new_button_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `export_buttons_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `back_save_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `enable_all_error_reporting` tinyint(1) NOT NULL DEFAULT '1',
  `enable_audit_logs` tinyint(1) NOT NULL DEFAULT '1',
  `records_per_page` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '25',
  `sidebar_visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sidebar_main_order` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sidebar_submenu_order` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ui_configuration_company` (`company_id`),
  CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ui_configuration`
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('1', '1', 'left_right', 'left_right', 'left_right', 'left_right', '1', '1', '25', NULL, NULL, NULL, '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('2', '2', 'left_right', 'left_right', 'left_right', 'left_right', '1', '1', '25', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:44:25');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('3', '3', 'left_right', 'left_right', 'left_right', 'left_right', '1', '1', '25', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:44:25');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('4', '4', 'left', 'left_right', 'left_right', 'left_right', '1', '1', '25', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:51:29');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('5', '5', 'left_right', 'left_right', 'left_right', 'left_right', '1', '1', '25', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:44:25');

-- Table structure for `user_roles`
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `user_roles_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_roles`
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Admin');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Helpdesk');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '3', 'IT Assistant');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '2', 'IT Manager');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '5', 'User');

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `username` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `access_level_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username_per_company` (`company_id`,`username`),
  UNIQUE KEY `email` (`company_id`,`email`),
  UNIQUE KEY `reset_token` (`reset_token`),
  KEY `company_id` (`company_id`),
  KEY `role_id` (`role_id`),
  KEY `access_level_id` (`access_level_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`),
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `company_id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `role_id`, `access_level_id`, `active`, `created_at`) VALUES ('1', '1', 'admin', 'admin@techcorp.example', '$2y$12$r6nU8WO3jAsWGvJYIFdIAOOAPDRmBQfEpltxD5UoIwTx3k.K2KPIO', 'System', 'Admin', NULL, '1', '1', '1', '2026-03-28 19:43:17');

-- Table structure for `user_companies`
DROP TABLE IF EXISTS `user_companies`;
CREATE TABLE `user_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `company_id` int NOT NULL,
  `granted_by_user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_companies_user_company` (`user_id`,`company_id`),
  KEY `idx_user_companies_company` (`company_id`),
  KEY `idx_user_companies_granted_by` (`granted_by_user_id`),
  CONSTRAINT `fk_user_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_companies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_companies_granted_by` FOREIGN KEY (`granted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_companies`
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`) VALUES ('1', '1', NULL);

-- Table structure for `role_hierarchy`
DROP TABLE IF EXISTS `role_hierarchy`;
CREATE TABLE `role_hierarchy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `hierarchy_order` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_hierarchy_company_role` (`company_id`,`role_id`),
  UNIQUE KEY `uq_role_hierarchy_company_order` (`company_id`,`hierarchy_order`),
  CONSTRAINT `fk_role_hierarchy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_hierarchy_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_hierarchy`
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '1', '1');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '2', '2');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '3', '3');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '4', '4');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '5', '5');

-- Table structure for `role_module_permissions`
DROP TABLE IF EXISTS `role_module_permissions`;
CREATE TABLE `role_module_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `module_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT '1',
  `can_create` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module_permissions` (`company_id`,`role_id`,`module_name`),
  CONSTRAINT `fk_role_module_permissions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_module_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_module_permissions`
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES ('1', '1', 'ALL', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES ('1', '4', 'Tickets', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES ('1', '5', 'Tickets', '1', '1', '1', '1');

-- Table structure for `role_assignment_rights`
DROP TABLE IF EXISTS `role_assignment_rights`;
CREATE TABLE `role_assignment_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `can_assign_role_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_assignment_rights` (`company_id`,`role_id`,`can_assign_role_id`),
  CONSTRAINT `fk_role_assignment_rights_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_target_role` FOREIGN KEY (`can_assign_role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_assignment_rights`
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '2');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '3');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '4');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '5');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '2', '3');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '2', '4');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '2', '5');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '3', '4');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '3', '5');

-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `actor_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_company` (`company_id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_actor_username` (`actor_username`),
  KEY `idx_audit_logs_actor_email` (`actor_email`),
  KEY `idx_audit_logs_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_changed_at` (`changed_at`),
  KEY `idx_audit_logs_company_changed` (`company_id`,`changed_at`),
  CONSTRAINT `audit_logs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_logs_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `vlans`
DROP TABLE IF EXISTS `vlans`;
CREATE TABLE `vlans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `vlan_number` int DEFAULT NULL,
  `vlan_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vlan_color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subnet` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gateway_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `vlans_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `vlans`
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) VALUES ('1', '1', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1');

-- Table structure for `warranty_types`
DROP TABLE IF EXISTS `warranty_types`;
CREATE TABLE `warranty_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `warranty_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `warranty_types`
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Enterprise');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Extended');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'None');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Other');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Premium');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Standard');

-- Table structure for `workstation_device_types`
DROP TABLE IF EXISTS `workstation_device_types`;
CREATE TABLE `workstation_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_device_types`
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'All-in-One');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Desktop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Laptop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Mobile');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Other');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'POS');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Tablet');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Thin-Client');

-- Table structure for `workstation_modes`
DROP TABLE IF EXISTS `workstation_modes`;
CREATE TABLE `workstation_modes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `mode_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `monitor_count` int DEFAULT '0',
  `has_keyboard_mouse` int DEFAULT '1',
  `pos` int DEFAULT '0',
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mode_name` (`company_id`,`mode_name`),
  UNIQUE KEY `mode_code` (`company_id`,`mode_code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_modes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_modes`
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '1', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '2', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '3', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '4', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '5', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '6', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '7', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '8', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '9', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '10', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '11', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1');

-- Table structure for `workstation_office`
DROP TABLE IF EXISTS `workstation_office`;
CREATE TABLE `workstation_office` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_office_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_office`
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '1', 'None');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Office 2024 Pro');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Office 2024 STD');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Office 365');

-- Table structure for `workstation_os_types`
DROP TABLE IF EXISTS `workstation_os_types`;
CREATE TABLE `workstation_os_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_os_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_types`
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Windows');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Windows 11');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Windows 10');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Windows Server');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Windows Server 2012');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Windows Server 2016');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'Windows Server 2019');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Windows Server 2022');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '9', 'Windows Server 2025');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '10', 'Android');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '11', 'iOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '12', 'ChromeOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '13', 'Linux');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '14', 'macOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '15', 'Other');

-- Table structure for `workstation_os_versions`
DROP TABLE IF EXISTS `workstation_os_versions`;
CREATE TABLE `workstation_os_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_os_versions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_versions`
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '1', '24H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '2', '25H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '3', '26H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '4', '10 LTSC');

-- Table structure for `workstation_ram`
DROP TABLE IF EXISTS `workstation_ram`;
CREATE TABLE `workstation_ram` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_ram_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_ram`
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '1', '4 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '2', '8 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '3', '16 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '4', '32 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '5', '64 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '6', '128 GB');

-- Table structure for `workstations`
DROP TABLE IF EXISTS `workstations`;
CREATE TABLE `workstations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_mode_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `assigned_to_department_id` int DEFAULT NULL,
  `assignment_type_id` int NOT NULL,
  `department` int DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workstation_code` (`company_id`,`workstation_code`),
  KEY `equipment_id` (`equipment_id`),
  KEY `workstation_mode_id` (`workstation_mode_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `assigned_to_department_id` (`assigned_to_department_id`),
  KEY `company_id` (`company_id`),
  KEY `assignment_type_id` (`assignment_type_id`),
  KEY `idx_workstations_department` (`department`),
  KEY `idx_workstations_status_id` (`status_id`),
  CONSTRAINT `workstations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workstations_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workstations_ibfk_3` FOREIGN KEY (`workstation_mode_id`) REFERENCES `workstation_modes` (`id`),
  CONSTRAINT `workstations_ibfk_4` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `workstations_ibfk_5` FOREIGN KEY (`assigned_to_department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `workstations_ibfk_6` FOREIGN KEY (`assignment_type_id`) REFERENCES `assignment_types` (`id`),
  CONSTRAINT `workstations_ibfk_department` FOREIGN KEY (`department`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workstations_ibfk_status_id` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Replicate shared table data to all companies
INSERT INTO `access_levels` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `access_levels` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `assignment_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `assignment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `employee_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `employee_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_environment` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_environment` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_fiber` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_fiber_patch` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber_patch` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_fiber_rack` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber_rack` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_fiber_count` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber_count` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_poe` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_poe` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_rj45` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_rj45` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment_types` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `equipment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `inventory_categories` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `inventory_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `location_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `location_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `manufacturers` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `manufacturers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `printer_device_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `printer_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `rack_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `rack_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `supplier_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `supplier_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `switch_cablecolors` (`company_id`, `color`) SELECT c.`id`, t.`color` FROM `switch_cablecolors` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `switch_port_numbering_layout` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `switch_port_numbering_layout` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `switch_port_types` (`company_id`, `type`) SELECT c.`id`, t.`type` FROM `switch_port_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `switch_status` (`company_id`, `status`) SELECT c.`id`, t.`status` FROM `switch_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `ticket_categories` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `ticket_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `ticket_priorities` (`company_id`, `name`, `level`, `color`, `active`) SELECT c.`id`, t.`name`, t.`level`, t.`color`, t.`active` FROM `ticket_priorities` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `ticket_statuses` (`company_id`, `name`, `color`, `is_closed`, `active`) SELECT c.`id`, t.`name`, t.`color`, t.`is_closed`, t.`active` FROM `ticket_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `user_roles` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `user_roles` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `warranty_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `warranty_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstation_device_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstation_modes` (`company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) SELECT c.`id`, t.`mode_name`, t.`mode_code`, t.`description`, t.`monitor_count`, t.`has_keyboard_mouse`, t.`pos`, t.`active` FROM `workstation_modes` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstation_office` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_office` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstation_os_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_os_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstation_os_versions` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_os_versions` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstation_ram` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_ram` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `departments` (`company_id`, `name`, `code`, `description`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`description`, t.`active` FROM `departments` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `employee_onboarding_requests` (`company_id`, `employee_id`, `first_name`, `last_name`, `position_title`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `comments`, `starting_date`, `requested_by`, `requested_on`, `hod_approval`, `hrd_approval`, `ism_approval`, `created_at`) SELECT c.`id`, t.`employee_id`, t.`first_name`, t.`last_name`, t.`position_title`, t.`department_name`, t.`request_date`, t.`termination_date`, t.`network_access`, t.`micros_emc`, t.`opera`, t.`micros_card`, t.`pms_id`, t.`synergy_mms`, t.`email_account`, t.`landline_phone`, t.`hu_the_lobby`, t.`mobile_phone`, t.`navision`, t.`mobile_email`, t.`onq_ri`, t.`birchstreet`, t.`delphi`, t.`omina`, t.`vingcard_system`, t.`digital_rev`, t.`office_key_card`, t.`comments`, t.`starting_date`, t.`requested_by`, t.`requested_on`, t.`hod_approval`, t.`hrd_approval`, t.`ism_approval`, t.`created_at` FROM `employee_onboarding_requests` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `equipment` (`company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `is_printer`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `is_workstation`, `is_server`, `is_pos`, `is_switch`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`)
SELECT
    c.`id`,
    COALESCE(et_target.`id`, et_fallback.`id`),
    m_target.`id`,
    l_target.`id`,
    r_target.`id`,
    t.`name`, t.`serial_number`, t.`model`, t.`hostname`, t.`ip_address`, t.`patch_port`, t.`mac_address`,
    COALESCE(es_target.`id`, es_fallback.`id`),
    t.`purchase_date`, t.`purchase_cost`, t.`warranty_expiry`, t.`certificate_expiry`,
    wt_target.`id`,
    t.`is_printer`,
    pdt_target.`id`,
    t.`printer_color_capable`,
    t.`printer_scan`,
    t.`is_workstation`, t.`is_server`, t.`is_pos`, t.`is_switch`,
    wdt_target.`id`,
    wot_target.`id`,
    t.`workstation_processor`, t.`workstation_storage`, t.`workstation_os_installed_on`,
    wr_target.`id`,
    wov_target.`id`,
    rj45_target.`id`,
    spnl_target.`id`,
    fiber_target.`id`,
    fiber_patch_target.`id`,
    fiber_rack_target.`id`,
    fiber_count_target.`id`,
    t.`switch_fiber_ports_number`,
    t.`switch_fiber_port_label`,
    poe_target.`id`,
    env_target.`id`,
    t.`notes`, t.`photo_filename`, t.`active`, t.`created_at`, t.`updated_at`
FROM `equipment` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `equipment_types` et_source ON et_source.`id` = t.`equipment_type_id`
LEFT JOIN `equipment_types` et_target ON et_target.`company_id` = c.`id` AND et_target.`name` = et_source.`name`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `equipment_types`
    GROUP BY `company_id`
) et_fallback ON et_fallback.`company_id` = c.`id`
LEFT JOIN `manufacturers` m_source ON m_source.`id` = t.`manufacturer_id`
LEFT JOIN `manufacturers` m_target ON m_target.`company_id` = c.`id` AND m_target.`name` = m_source.`name`
LEFT JOIN `it_locations` l_source ON l_source.`id` = t.`location_id`
LEFT JOIN `it_locations` l_target ON l_target.`company_id` = c.`id` AND l_target.`name` = l_source.`name`
LEFT JOIN `racks` r_source ON r_source.`id` = t.`rack_id`
LEFT JOIN `racks` r_target ON r_target.`company_id` = c.`id` AND r_target.`name` = r_source.`name`
LEFT JOIN `equipment_statuses` es_source ON es_source.`id` = t.`status_id`
LEFT JOIN `equipment_statuses` es_target ON es_target.`company_id` = c.`id` AND es_target.`name` = es_source.`name`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `equipment_statuses`
    GROUP BY `company_id`
) es_fallback ON es_fallback.`company_id` = c.`id`
LEFT JOIN `warranty_types` wt_source ON wt_source.`id` = t.`warranty_type_id`
LEFT JOIN `warranty_types` wt_target ON wt_target.`company_id` = c.`id` AND wt_target.`name` = wt_source.`name`
LEFT JOIN `printer_device_types` pdt_source ON pdt_source.`id` = t.`printer_device_type_id`
LEFT JOIN `printer_device_types` pdt_target ON pdt_target.`company_id` = c.`id` AND pdt_target.`name` = pdt_source.`name`
LEFT JOIN `workstation_device_types` wdt_source ON wdt_source.`id` = t.`workstation_device_type_id`
LEFT JOIN `workstation_device_types` wdt_target ON wdt_target.`company_id` = c.`id` AND wdt_target.`name` = wdt_source.`name`
LEFT JOIN `workstation_os_types` wot_source ON wot_source.`id` = t.`workstation_os_type_id`
LEFT JOIN `workstation_os_types` wot_target ON wot_target.`company_id` = c.`id` AND wot_target.`name` = wot_source.`name`
LEFT JOIN `workstation_ram` wr_source ON wr_source.`id` = t.`workstation_ram_id`
LEFT JOIN `workstation_ram` wr_target ON wr_target.`company_id` = c.`id` AND wr_target.`name` = wr_source.`name`
LEFT JOIN `workstation_os_versions` wov_source ON wov_source.`id` = t.`workstation_os_version_id`
LEFT JOIN `workstation_os_versions` wov_target ON wov_target.`company_id` = c.`id` AND wov_target.`name` = wov_source.`name`
LEFT JOIN `equipment_rj45` rj45_source ON rj45_source.`id` = t.`switch_rj45_id`
LEFT JOIN `equipment_rj45` rj45_target ON rj45_target.`company_id` = c.`id` AND rj45_target.`name` = rj45_source.`name`
LEFT JOIN `switch_port_numbering_layout` spnl_source ON spnl_source.`id` = t.`switch_port_numbering_layout_id`
LEFT JOIN `switch_port_numbering_layout` spnl_target ON spnl_target.`company_id` = c.`id` AND spnl_target.`name` = spnl_source.`name`
LEFT JOIN `equipment_fiber` fiber_source ON fiber_source.`id` = t.`switch_fiber_id`
LEFT JOIN `equipment_fiber` fiber_target ON fiber_target.`company_id` = c.`id` AND fiber_target.`name` = fiber_source.`name`
LEFT JOIN `equipment_fiber_patch` fiber_patch_source ON fiber_patch_source.`id` = t.`switch_fiber_patch_id`
LEFT JOIN `equipment_fiber_patch` fiber_patch_target ON fiber_patch_target.`company_id` = c.`id` AND fiber_patch_target.`name` = fiber_patch_source.`name`
LEFT JOIN `equipment_fiber_rack` fiber_rack_source ON fiber_rack_source.`id` = t.`switch_fiber_rack_id`
LEFT JOIN `equipment_fiber_rack` fiber_rack_target ON fiber_rack_target.`company_id` = c.`id` AND fiber_rack_target.`name` = fiber_rack_source.`name`
LEFT JOIN `equipment_fiber_count` fiber_count_source ON fiber_count_source.`id` = t.`switch_fiber_count_id`
LEFT JOIN `equipment_fiber_count` fiber_count_target ON fiber_count_target.`company_id` = c.`id` AND fiber_count_target.`name` = fiber_count_source.`name`
LEFT JOIN `equipment_poe` poe_source ON poe_source.`id` = t.`switch_poe_id`
LEFT JOIN `equipment_poe` poe_target ON poe_target.`company_id` = c.`id` AND poe_target.`name` = poe_source.`name`
LEFT JOIN `equipment_environment` env_source ON env_source.`id` = t.`switch_environment_id`
LEFT JOIN `equipment_environment` env_target ON env_target.`company_id` = c.`id` AND env_target.`name` = env_source.`name`
WHERE t.`company_id` = 1
  AND COALESCE(et_target.`id`, et_fallback.`id`) IS NOT NULL
  AND COALESCE(es_target.`id`, es_fallback.`id`) IS NOT NULL;
INSERT INTO `idf_ports` (`company_id`, `position_id`, `port_no`, `port_type`, `label`, `status`, `connected_to`, `vlan`, `speed`, `poe`, `notes`, `updated_at`) SELECT c.`id`, t.`position_id`, t.`port_no`, t.`port_type`, t.`label`, t.`status`, t.`connected_to`, t.`vlan`, t.`speed`, t.`poe`, t.`notes`, t.`updated_at` FROM `idf_ports` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `idf_positions` (`company_id`, `idf_id`, `position_no`, `device_type`, `device_name`, `equipment_id`, `port_count`, `notes`, `created_at`, `updated_at`) SELECT c.`id`, t.`idf_id`, t.`position_no`, t.`device_type`, t.`device_name`, t.`equipment_id`, t.`port_count`, t.`notes`, t.`created_at`, t.`updated_at` FROM `idf_positions` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `idfs` (`company_id`, `location_id`, `name`, `idf_code`, `notes`, `created_at`) SELECT c.`id`, t.`location_id`, t.`name`, t.`idf_code`, t.`notes`, t.`created_at` FROM `idfs` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `inventory_items` (`company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`)
SELECT
    c.`id`,
    t.`name`,
    t.`item_code`,
    t.`serial`,
    ic_target.`id`,
    m_target.`id`,
    t.`quantity_on_hand`,
    t.`quantity_minimum`,
    t.`price_eur`,
    t.`comments`,
    l_target.`id`,
    s_target.`id`,
    t.`active`
FROM `inventory_items` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `inventory_categories` ic_source ON ic_source.`id` = t.`category_id`
LEFT JOIN `inventory_categories` ic_target ON ic_target.`company_id` = c.`id` AND ic_target.`name` = ic_source.`name`
LEFT JOIN `manufacturers` m_source ON m_source.`id` = t.`manufacturer_id`
LEFT JOIN `manufacturers` m_target ON m_target.`company_id` = c.`id` AND m_target.`name` = m_source.`name`
LEFT JOIN `it_locations` l_source ON l_source.`id` = t.`location_id`
LEFT JOIN `it_locations` l_target ON l_target.`company_id` = c.`id` AND l_target.`name` = l_source.`name`
LEFT JOIN `suppliers` s_source ON s_source.`id` = t.`supplier_id`
LEFT JOIN `suppliers` s_target ON s_target.`company_id` = c.`id` AND s_target.`name` = s_source.`name`
WHERE t.`company_id` = 1;
INSERT INTO `it_locations` (`company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`) SELECT c.`id`, t.`name`, t.`location_code`, t.`address`, t.`city`, t.`state`, t.`country`, t.`postal_code`, t.`phone`, t.`type_id`, t.`active` FROM `it_locations` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `racks` (`company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) SELECT c.`id`, t.`location_id`, t.`name`, t.`rack_code`, t.`status_id`, t.`active` FROM `racks` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `suppliers` (`company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) SELECT c.`id`, t.`name`, t.`supplier_code`, t.`contact_person`, t.`email`, t.`phone`, t.`status_id`, t.`active` FROM `suppliers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `switch_ports` (`company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`)
SELECT
    c.`id`,
    e_target.`id`,
    t.`hostname`,
    t.`port_type`,
    t.`port_number`,
    t.`label`,
    COALESCE(ss_target.`id`, ss_fallback.`id`),
    COALESCE(sc_target.`id`, sc_fallback.`id`),
    v_target.`id`,
    t.`comments`,
    t.`updated_at`
FROM `switch_ports` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `equipment` e_source ON e_source.`id` = t.`equipment_id`
LEFT JOIN `equipment` e_target ON e_target.`company_id` = c.`id` AND e_target.`name` = e_source.`name`
LEFT JOIN `switch_status` ss_source ON ss_source.`id` = t.`status_id`
LEFT JOIN `switch_status` ss_target ON ss_target.`company_id` = c.`id` AND ss_target.`status` = ss_source.`status`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `switch_status`
    GROUP BY `company_id`
) ss_fallback ON ss_fallback.`company_id` = c.`id`
LEFT JOIN `switch_cablecolors` sc_source ON sc_source.`id` = t.`color_id`
LEFT JOIN `switch_cablecolors` sc_target ON sc_target.`company_id` = c.`id` AND sc_target.`color` = sc_source.`color`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `switch_cablecolors`
    GROUP BY `company_id`
) sc_fallback ON sc_fallback.`company_id` = c.`id`
LEFT JOIN `vlans` v_source ON v_source.`id` = t.`vlan_id`
LEFT JOIN `vlans` v_target ON v_target.`company_id` = c.`id` AND v_target.`vlan_number` = v_source.`vlan_number`
WHERE t.`company_id` = 1
  AND COALESCE(ss_target.`id`, ss_fallback.`id`) IS NOT NULL
  AND COALESCE(sc_target.`id`, sc_fallback.`id`) IS NOT NULL;
INSERT INTO `system_access` (`company_id`, `code`, `name`, `active`) SELECT c.`id`, t.`code`, t.`name`, t.`active` FROM `system_access` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) SELECT c.`id`, ur_target.`id`, rh.`hierarchy_order` FROM `role_hierarchy` rh JOIN `companies` c ON c.`id` <> rh.`company_id` JOIN `user_roles` ur_source ON ur_source.`id` = rh.`role_id` JOIN `user_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rh.`company_id` = 1;
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`) SELECT c.`id`, ur_target.`id`, rmp.`module_name`, rmp.`can_view`, rmp.`can_create`, rmp.`can_edit`, rmp.`can_delete` FROM `role_module_permissions` rmp JOIN `companies` c ON c.`id` <> rmp.`company_id` JOIN `user_roles` ur_source ON ur_source.`id` = rmp.`role_id` JOIN `user_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rmp.`company_id` = 1;
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) SELECT c.`id`, ur_granter_target.`id`, ur_target_target.`id` FROM `role_assignment_rights` rar JOIN `companies` c ON c.`id` <> rar.`company_id` JOIN `user_roles` ur_granter_source ON ur_granter_source.`id` = rar.`role_id` JOIN `user_roles` ur_target_source ON ur_target_source.`id` = rar.`can_assign_role_id` JOIN `user_roles` ur_granter_target ON ur_granter_target.`company_id` = c.`id` AND ur_granter_target.`name` = ur_granter_source.`name` JOIN `user_roles` ur_target_target ON ur_target_target.`company_id` = c.`id` AND ur_target_target.`name` = ur_target_source.`name` WHERE rar.`company_id` = 1;
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`)
SELECT u.`id`, c.`id`, NULL
FROM `users` u
JOIN `companies` c ON c.`active` = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM `user_companies` uc
    WHERE uc.`user_id` = u.`id` AND uc.`company_id` = c.`id`
);
INSERT INTO `tickets` (`company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `created_at`)
SELECT
    c.`id`,
    t.`ticket_external_code`,
    t.`title`,
    t.`description`,
    tc_target.`id`,
    ts_target.`id`,
    tp_target.`id`,
    COALESCE(u_creator_target.`id`, u_fallback.`id`),
    u_assignee_target.`id`,
    e_target.`id`,
    t.`ui_color`,
    t.`created_at`
FROM `tickets` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `ticket_categories` tc_source ON tc_source.`id` = t.`category_id`
LEFT JOIN `ticket_categories` tc_target ON tc_target.`company_id` = c.`id` AND tc_target.`name` = tc_source.`name`
LEFT JOIN `ticket_statuses` ts_source ON ts_source.`id` = t.`status_id`
LEFT JOIN `ticket_statuses` ts_target ON ts_target.`company_id` = c.`id` AND ts_target.`name` = ts_source.`name`
LEFT JOIN `ticket_priorities` tp_source ON tp_source.`id` = t.`priority_id`
LEFT JOIN `ticket_priorities` tp_target ON tp_target.`company_id` = c.`id` AND tp_target.`name` = tp_source.`name`
LEFT JOIN `users` u_creator_source ON u_creator_source.`id` = t.`created_by_user_id`
LEFT JOIN `users` u_creator_target ON u_creator_target.`company_id` = c.`id` AND u_creator_target.`username` = u_creator_source.`username`
LEFT JOIN `users` u_assignee_source ON u_assignee_source.`id` = t.`assigned_to_user_id`
LEFT JOIN `users` u_assignee_target ON u_assignee_target.`company_id` = c.`id` AND u_assignee_target.`username` = u_assignee_source.`username`
LEFT JOIN (
    SELECT u1.`company_id`, MIN(u1.`id`) AS `id`
    FROM `users` u1
    GROUP BY u1.`company_id`
) u_fallback ON u_fallback.`company_id` = c.`id`
LEFT JOIN `equipment` e_source ON e_source.`id` = t.`asset_id`
LEFT JOIN `equipment` e_target ON e_target.`company_id` = c.`id` AND e_target.`name` = e_source.`name`
WHERE t.`company_id` = 1
  AND COALESCE(u_creator_target.`id`, u_fallback.`id`) IS NOT NULL;
INSERT INTO `ui_configuration` (`company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) SELECT c.`id`, t.`table_actions_position`, t.`new_button_position`, t.`export_buttons_position`, t.`back_save_position`, t.`enable_all_error_reporting`, t.`enable_audit_logs`, t.`records_per_page`, t.`sidebar_visibility`, t.`sidebar_main_order`, t.`sidebar_submenu_order`, t.`created_at`, t.`updated_at` FROM `ui_configuration` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1 AND NOT EXISTS (SELECT 1 FROM `ui_configuration` u WHERE u.`company_id` = c.`id`);
INSERT INTO `vlans` (`company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) SELECT c.`id`, t.`vlan_number`, t.`vlan_name`, t.`vlan_color`, t.`subnet`, t.`ip`, t.`comments`, t.`gateway_ip`, t.`active` FROM `vlans` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;
INSERT INTO `workstations` (`company_id`, `equipment_id`, `hostname`, `workstation_code`, `workstation_mode_id`, `assigned_to_employee_id`, `assigned_to_department_id`, `assignment_type_id`, `department`, `status_id`) SELECT c.`id`, t.`equipment_id`, t.`hostname`, t.`workstation_code`, t.`workstation_mode_id`, t.`assigned_to_employee_id`, t.`assigned_to_department_id`, t.`assignment_type_id`, t.`department`, t.`status_id` FROM `workstations` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = 1;



-- Build database-level audit triggers for every application table.
DROP TRIGGER IF EXISTS `trg_access_levels_audit_insert`;
DROP TRIGGER IF EXISTS `trg_access_levels_audit_update`;
DROP TRIGGER IF EXISTS `trg_access_levels_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_access_levels_audit_insert` AFTER INSERT ON `access_levels` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'access_levels', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_access_levels_audit_update` AFTER UPDATE ON `access_levels` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'access_levels', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_access_levels_audit_delete` AFTER DELETE ON `access_levels` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'access_levels', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_assignment_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_assignment_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_assignment_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_assignment_types_audit_insert` AFTER INSERT ON `assignment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'assignment_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_assignment_types_audit_update` AFTER UPDATE ON `assignment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'assignment_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_assignment_types_audit_delete` AFTER DELETE ON `assignment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'assignment_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_companies_audit_insert`;
DROP TRIGGER IF EXISTS `trg_companies_audit_update`;
DROP TRIGGER IF EXISTS `trg_companies_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_companies_audit_insert` AFTER INSERT ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_user_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_companies_audit_update` AFTER UPDATE ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_user_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company', OLD.`company`, 'incode', OLD.`incode`, 'city', OLD.`city`, 'country', OLD.`country`, 'phone', OLD.`phone`, 'email', OLD.`email`, 'website', OLD.`website`, 'vat', OLD.`vat`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_companies_audit_delete` AFTER DELETE ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_user_id, @app_username, @app_email, 'companies', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company', OLD.`company`, 'incode', OLD.`incode`, 'city', OLD.`city`, 'country', OLD.`country`, 'phone', OLD.`phone`, 'email', OLD.`email`, 'website', OLD.`website`, 'vat', OLD.`vat`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_departments_audit_insert`;
DROP TRIGGER IF EXISTS `trg_departments_audit_update`;
DROP TRIGGER IF EXISTS `trg_departments_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_departments_audit_insert` AFTER INSERT ON `departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'departments', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'description', NEW.`description`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_departments_audit_update` AFTER UPDATE ON `departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'departments', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'description', OLD.`description`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'description', NEW.`description`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_departments_audit_delete` AFTER DELETE ON `departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'departments', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'description', OLD.`description`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_employee_onboarding_requests_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_onboarding_requests_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_onboarding_requests_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_insert` AFTER INSERT ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'position_title', NEW.`position_title`, 'department_name', NEW.`department_name`, 'request_date', NEW.`request_date`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera', NEW.`opera`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'email_account', NEW.`email_account`, 'landline_phone', NEW.`landline_phone`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'mobile_phone', NEW.`mobile_phone`, 'navision', NEW.`navision`, 'mobile_email', NEW.`mobile_email`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'comments', NEW.`comments`, 'starting_date', NEW.`starting_date`, 'requested_by', NEW.`requested_by`, 'requested_on', NEW.`requested_on`, 'hod_approval', NEW.`hod_approval`, 'hrd_approval', NEW.`hrd_approval`, 'ism_approval', NEW.`ism_approval`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_update` AFTER UPDATE ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'position_title', OLD.`position_title`, 'department_name', OLD.`department_name`, 'request_date', OLD.`request_date`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera', OLD.`opera`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'email_account', OLD.`email_account`, 'landline_phone', OLD.`landline_phone`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'mobile_phone', OLD.`mobile_phone`, 'navision', OLD.`navision`, 'mobile_email', OLD.`mobile_email`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'comments', OLD.`comments`, 'starting_date', OLD.`starting_date`, 'requested_by', OLD.`requested_by`, 'requested_on', OLD.`requested_on`, 'hod_approval', OLD.`hod_approval`, 'hrd_approval', OLD.`hrd_approval`, 'ism_approval', OLD.`ism_approval`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'position_title', NEW.`position_title`, 'department_name', NEW.`department_name`, 'request_date', NEW.`request_date`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera', NEW.`opera`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'email_account', NEW.`email_account`, 'landline_phone', NEW.`landline_phone`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'mobile_phone', NEW.`mobile_phone`, 'navision', NEW.`navision`, 'mobile_email', NEW.`mobile_email`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'comments', NEW.`comments`, 'starting_date', NEW.`starting_date`, 'requested_by', NEW.`requested_by`, 'requested_on', NEW.`requested_on`, 'hod_approval', NEW.`hod_approval`, 'hrd_approval', NEW.`hrd_approval`, 'ism_approval', NEW.`ism_approval`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_delete` AFTER DELETE ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'position_title', OLD.`position_title`, 'department_name', OLD.`department_name`, 'request_date', OLD.`request_date`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera', OLD.`opera`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'email_account', OLD.`email_account`, 'landline_phone', OLD.`landline_phone`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'mobile_phone', OLD.`mobile_phone`, 'navision', OLD.`navision`, 'mobile_email', OLD.`mobile_email`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'comments', OLD.`comments`, 'starting_date', OLD.`starting_date`, 'requested_by', OLD.`requested_by`, 'requested_on', OLD.`requested_on`, 'hod_approval', OLD.`hod_approval`, 'hrd_approval', OLD.`hrd_approval`, 'ism_approval', OLD.`ism_approval`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_employee_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_statuses_audit_insert` AFTER INSERT ON `employee_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_statuses_audit_update` AFTER UPDATE ON `employee_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_statuses_audit_delete` AFTER DELETE ON `employee_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_employee_system_access_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_system_access_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_system_access_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_system_access_audit_insert` AFTER INSERT ON `employee_system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_system_access', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'changed_at', NEW.`changed_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_system_access_audit_update` AFTER UPDATE ON `employee_system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_system_access', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'changed_at', OLD.`changed_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'changed_at', NEW.`changed_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_system_access_audit_delete` AFTER DELETE ON `employee_system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_system_access', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'changed_at', OLD.`changed_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_employee_system_access_relations_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_system_access_relations_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_system_access_relations_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_system_access_relations_audit_insert` AFTER INSERT ON `employee_system_access_relations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_system_access_relations', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'system_access_id', NEW.`system_access_id`, 'granted', NEW.`granted`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_system_access_relations_audit_update` AFTER UPDATE ON `employee_system_access_relations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_system_access_relations', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'system_access_id', OLD.`system_access_id`, 'granted', OLD.`granted`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'system_access_id', NEW.`system_access_id`, 'granted', NEW.`granted`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_system_access_relations_audit_delete` AFTER DELETE ON `employee_system_access_relations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_system_access_relations', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'system_access_id', OLD.`system_access_id`, 'granted', OLD.`granted`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_employees_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employees_audit_update`;
DROP TRIGGER IF EXISTS `trg_employees_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employees_audit_insert` AFTER INSERT ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employees', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'duplicate', NEW.`duplicate`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'display_name', NEW.`display_name`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'mobile_phone', NEW.`mobile_phone`, 'work_phone', NEW.`work_phone`, 'deck', NEW.`deck`, 'extension', NEW.`extension`, 'employee_code', NEW.`employee_code`, 'hilton_id', NEW.`hilton_id`, 'username', NEW.`username`, 'department_id', NEW.`department_id`, 'job_code', NEW.`job_code`, 'job_title', NEW.`job_title`, 'comments', NEW.`comments`, 'request_date', NEW.`request_date`, 'requested_by', NEW.`requested_by`, 'termination_requested_by', NEW.`termination_requested_by`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_department_id', NEW.`office_key_card_department_id`, 'location_id', NEW.`location_id`, 'employment_status_id', NEW.`employment_status_id`, 'raw_status_code', NEW.`raw_status_code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employees_audit_update` AFTER UPDATE ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employees', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'duplicate', OLD.`duplicate`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'display_name', OLD.`display_name`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'mobile_phone', OLD.`mobile_phone`, 'work_phone', OLD.`work_phone`, 'deck', OLD.`deck`, 'extension', OLD.`extension`, 'employee_code', OLD.`employee_code`, 'hilton_id', OLD.`hilton_id`, 'username', OLD.`username`, 'department_id', OLD.`department_id`, 'job_code', OLD.`job_code`, 'job_title', OLD.`job_title`, 'comments', OLD.`comments`, 'request_date', OLD.`request_date`, 'requested_by', OLD.`requested_by`, 'termination_requested_by', OLD.`termination_requested_by`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_department_id', OLD.`office_key_card_department_id`, 'location_id', OLD.`location_id`, 'employment_status_id', OLD.`employment_status_id`, 'raw_status_code', OLD.`raw_status_code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'duplicate', NEW.`duplicate`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'display_name', NEW.`display_name`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'mobile_phone', NEW.`mobile_phone`, 'work_phone', NEW.`work_phone`, 'deck', NEW.`deck`, 'extension', NEW.`extension`, 'employee_code', NEW.`employee_code`, 'hilton_id', NEW.`hilton_id`, 'username', NEW.`username`, 'department_id', NEW.`department_id`, 'job_code', NEW.`job_code`, 'job_title', NEW.`job_title`, 'comments', NEW.`comments`, 'request_date', NEW.`request_date`, 'requested_by', NEW.`requested_by`, 'termination_requested_by', NEW.`termination_requested_by`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_department_id', NEW.`office_key_card_department_id`, 'location_id', NEW.`location_id`, 'employment_status_id', NEW.`employment_status_id`, 'raw_status_code', NEW.`raw_status_code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employees_audit_delete` AFTER DELETE ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employees', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'duplicate', OLD.`duplicate`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'display_name', OLD.`display_name`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'mobile_phone', OLD.`mobile_phone`, 'work_phone', OLD.`work_phone`, 'deck', OLD.`deck`, 'extension', OLD.`extension`, 'employee_code', OLD.`employee_code`, 'hilton_id', OLD.`hilton_id`, 'username', OLD.`username`, 'department_id', OLD.`department_id`, 'job_code', OLD.`job_code`, 'job_title', OLD.`job_title`, 'comments', OLD.`comments`, 'request_date', OLD.`request_date`, 'requested_by', OLD.`requested_by`, 'termination_requested_by', OLD.`termination_requested_by`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_department_id', OLD.`office_key_card_department_id`, 'location_id', OLD.`location_id`, 'employment_status_id', OLD.`employment_status_id`, 'raw_status_code', OLD.`raw_status_code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_audit_insert` AFTER INSERT ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_type_id', NEW.`equipment_type_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'location_id', NEW.`location_id`, 'rack_id', NEW.`rack_id`, 'name', NEW.`name`, 'serial_number', NEW.`serial_number`, 'model', NEW.`model`, 'hostname', NEW.`hostname`, 'ip_address', NEW.`ip_address`, 'patch_port', NEW.`patch_port`, 'mac_address', NEW.`mac_address`, 'status_id', NEW.`status_id`, 'purchase_date', NEW.`purchase_date`, 'purchase_cost', NEW.`purchase_cost`, 'warranty_expiry', NEW.`warranty_expiry`, 'certificate_expiry', NEW.`certificate_expiry`, 'warranty_type_id', NEW.`warranty_type_id`, 'is_printer', NEW.`is_printer`, 'printer_device_type_id', NEW.`printer_device_type_id`, 'printer_color_capable', NEW.`printer_color_capable`, 'printer_scan', NEW.`printer_scan`, 'is_workstation', NEW.`is_workstation`, 'is_server', NEW.`is_server`, 'is_pos', NEW.`is_pos`, 'is_switch', NEW.`is_switch`, 'workstation_device_type_id', NEW.`workstation_device_type_id`, 'workstation_os_type_id', NEW.`workstation_os_type_id`, 'workstation_processor', NEW.`workstation_processor`, 'workstation_storage', NEW.`workstation_storage`, 'workstation_os_installed_on', NEW.`workstation_os_installed_on`, 'workstation_ram_id', NEW.`workstation_ram_id`, 'workstation_os_version_id', NEW.`workstation_os_version_id`, 'switch_rj45_id', NEW.`switch_rj45_id`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'switch_fiber_id', NEW.`switch_fiber_id`, 'switch_fiber_patch_id', NEW.`switch_fiber_patch_id`, 'switch_fiber_rack_id', NEW.`switch_fiber_rack_id`, 'switch_fiber_count_id', NEW.`switch_fiber_count_id`, 'switch_fiber_ports_number', NEW.`switch_fiber_ports_number`, 'switch_fiber_port_label', NEW.`switch_fiber_port_label`, 'switch_poe_id', NEW.`switch_poe_id`, 'switch_environment_id', NEW.`switch_environment_id`, 'notes', NEW.`notes`, 'photo_filename', NEW.`photo_filename`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_audit_update` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_type_id', OLD.`equipment_type_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'location_id', OLD.`location_id`, 'rack_id', OLD.`rack_id`, 'name', OLD.`name`, 'serial_number', OLD.`serial_number`, 'model', OLD.`model`, 'hostname', OLD.`hostname`, 'ip_address', OLD.`ip_address`, 'patch_port', OLD.`patch_port`, 'mac_address', OLD.`mac_address`, 'status_id', OLD.`status_id`, 'purchase_date', OLD.`purchase_date`, 'purchase_cost', OLD.`purchase_cost`, 'warranty_expiry', OLD.`warranty_expiry`, 'certificate_expiry', OLD.`certificate_expiry`, 'warranty_type_id', OLD.`warranty_type_id`, 'is_printer', OLD.`is_printer`, 'printer_device_type_id', OLD.`printer_device_type_id`, 'printer_color_capable', OLD.`printer_color_capable`, 'printer_scan', OLD.`printer_scan`, 'is_workstation', OLD.`is_workstation`, 'is_server', OLD.`is_server`, 'is_pos', OLD.`is_pos`, 'is_switch', OLD.`is_switch`, 'workstation_device_type_id', OLD.`workstation_device_type_id`, 'workstation_os_type_id', OLD.`workstation_os_type_id`, 'workstation_processor', OLD.`workstation_processor`, 'workstation_storage', OLD.`workstation_storage`, 'workstation_os_installed_on', OLD.`workstation_os_installed_on`, 'workstation_ram_id', OLD.`workstation_ram_id`, 'workstation_os_version_id', OLD.`workstation_os_version_id`, 'switch_rj45_id', OLD.`switch_rj45_id`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'switch_fiber_id', OLD.`switch_fiber_id`, 'switch_fiber_patch_id', OLD.`switch_fiber_patch_id`, 'switch_fiber_rack_id', OLD.`switch_fiber_rack_id`, 'switch_fiber_count_id', OLD.`switch_fiber_count_id`, 'switch_fiber_ports_number', OLD.`switch_fiber_ports_number`, 'switch_fiber_port_label', OLD.`switch_fiber_port_label`, 'switch_poe_id', OLD.`switch_poe_id`, 'switch_environment_id', OLD.`switch_environment_id`, 'notes', OLD.`notes`, 'photo_filename', OLD.`photo_filename`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_type_id', NEW.`equipment_type_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'location_id', NEW.`location_id`, 'rack_id', NEW.`rack_id`, 'name', NEW.`name`, 'serial_number', NEW.`serial_number`, 'model', NEW.`model`, 'hostname', NEW.`hostname`, 'ip_address', NEW.`ip_address`, 'patch_port', NEW.`patch_port`, 'mac_address', NEW.`mac_address`, 'status_id', NEW.`status_id`, 'purchase_date', NEW.`purchase_date`, 'purchase_cost', NEW.`purchase_cost`, 'warranty_expiry', NEW.`warranty_expiry`, 'certificate_expiry', NEW.`certificate_expiry`, 'warranty_type_id', NEW.`warranty_type_id`, 'is_printer', NEW.`is_printer`, 'printer_device_type_id', NEW.`printer_device_type_id`, 'printer_color_capable', NEW.`printer_color_capable`, 'printer_scan', NEW.`printer_scan`, 'is_workstation', NEW.`is_workstation`, 'is_server', NEW.`is_server`, 'is_pos', NEW.`is_pos`, 'is_switch', NEW.`is_switch`, 'workstation_device_type_id', NEW.`workstation_device_type_id`, 'workstation_os_type_id', NEW.`workstation_os_type_id`, 'workstation_processor', NEW.`workstation_processor`, 'workstation_storage', NEW.`workstation_storage`, 'workstation_os_installed_on', NEW.`workstation_os_installed_on`, 'workstation_ram_id', NEW.`workstation_ram_id`, 'workstation_os_version_id', NEW.`workstation_os_version_id`, 'switch_rj45_id', NEW.`switch_rj45_id`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'switch_fiber_id', NEW.`switch_fiber_id`, 'switch_fiber_patch_id', NEW.`switch_fiber_patch_id`, 'switch_fiber_rack_id', NEW.`switch_fiber_rack_id`, 'switch_fiber_count_id', NEW.`switch_fiber_count_id`, 'switch_fiber_ports_number', NEW.`switch_fiber_ports_number`, 'switch_fiber_port_label', NEW.`switch_fiber_port_label`, 'switch_poe_id', NEW.`switch_poe_id`, 'switch_environment_id', NEW.`switch_environment_id`, 'notes', NEW.`notes`, 'photo_filename', NEW.`photo_filename`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_audit_delete` AFTER DELETE ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_type_id', OLD.`equipment_type_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'location_id', OLD.`location_id`, 'rack_id', OLD.`rack_id`, 'name', OLD.`name`, 'serial_number', OLD.`serial_number`, 'model', OLD.`model`, 'hostname', OLD.`hostname`, 'ip_address', OLD.`ip_address`, 'patch_port', OLD.`patch_port`, 'mac_address', OLD.`mac_address`, 'status_id', OLD.`status_id`, 'purchase_date', OLD.`purchase_date`, 'purchase_cost', OLD.`purchase_cost`, 'warranty_expiry', OLD.`warranty_expiry`, 'certificate_expiry', OLD.`certificate_expiry`, 'warranty_type_id', OLD.`warranty_type_id`, 'is_printer', OLD.`is_printer`, 'printer_device_type_id', OLD.`printer_device_type_id`, 'printer_color_capable', OLD.`printer_color_capable`, 'printer_scan', OLD.`printer_scan`, 'is_workstation', OLD.`is_workstation`, 'is_server', OLD.`is_server`, 'is_pos', OLD.`is_pos`, 'is_switch', OLD.`is_switch`, 'workstation_device_type_id', OLD.`workstation_device_type_id`, 'workstation_os_type_id', OLD.`workstation_os_type_id`, 'workstation_processor', OLD.`workstation_processor`, 'workstation_storage', OLD.`workstation_storage`, 'workstation_os_installed_on', OLD.`workstation_os_installed_on`, 'workstation_ram_id', OLD.`workstation_ram_id`, 'workstation_os_version_id', OLD.`workstation_os_version_id`, 'switch_rj45_id', OLD.`switch_rj45_id`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'switch_fiber_id', OLD.`switch_fiber_id`, 'switch_fiber_patch_id', OLD.`switch_fiber_patch_id`, 'switch_fiber_rack_id', OLD.`switch_fiber_rack_id`, 'switch_fiber_count_id', OLD.`switch_fiber_count_id`, 'switch_fiber_ports_number', OLD.`switch_fiber_ports_number`, 'switch_fiber_port_label', OLD.`switch_fiber_port_label`, 'switch_poe_id', OLD.`switch_poe_id`, 'switch_environment_id', OLD.`switch_environment_id`, 'notes', OLD.`notes`, 'photo_filename', OLD.`photo_filename`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_environment_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_environment_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_environment_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_environment_audit_insert` AFTER INSERT ON `equipment_environment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_environment', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_environment_audit_update` AFTER UPDATE ON `equipment_environment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_environment', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_environment_audit_delete` AFTER DELETE ON `equipment_environment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_environment', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_fiber_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_audit_insert` AFTER INSERT ON `equipment_fiber` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_audit_update` AFTER UPDATE ON `equipment_fiber` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_audit_delete` AFTER DELETE ON `equipment_fiber` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_fiber_patch_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_patch_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_patch_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_patch_audit_insert` AFTER INSERT ON `equipment_fiber_patch` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_patch', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_patch_audit_update` AFTER UPDATE ON `equipment_fiber_patch` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_patch', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_patch_audit_delete` AFTER DELETE ON `equipment_fiber_patch` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_patch', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_fiber_rack_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_rack_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_rack_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_rack_audit_insert` AFTER INSERT ON `equipment_fiber_rack` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_rack', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_rack_audit_update` AFTER UPDATE ON `equipment_fiber_rack` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_rack', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_rack_audit_delete` AFTER DELETE ON `equipment_fiber_rack` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_rack', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_fiber_count_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_count_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_count_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_count_audit_insert` AFTER INSERT ON `equipment_fiber_count` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_count', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_count_audit_update` AFTER UPDATE ON `equipment_fiber_count` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_count', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_count_audit_delete` AFTER DELETE ON `equipment_fiber_count` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_fiber_count', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_poe_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_poe_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_poe_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_poe_audit_insert` AFTER INSERT ON `equipment_poe` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_poe', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_poe_audit_update` AFTER UPDATE ON `equipment_poe` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_poe', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_poe_audit_delete` AFTER DELETE ON `equipment_poe` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_poe', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_rj45_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_rj45_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_rj45_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_rj45_audit_insert` AFTER INSERT ON `equipment_rj45` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_rj45', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_rj45_audit_update` AFTER UPDATE ON `equipment_rj45` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_rj45', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_rj45_audit_delete` AFTER DELETE ON `equipment_rj45` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_rj45', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_statuses_audit_insert` AFTER INSERT ON `equipment_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_statuses_audit_update` AFTER UPDATE ON `equipment_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_statuses_audit_delete` AFTER DELETE ON `equipment_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_types_audit_insert` AFTER INSERT ON `equipment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_types_audit_update` AFTER UPDATE ON `equipment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_types_audit_delete` AFTER DELETE ON `equipment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idf_links_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_links_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_links_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_links_audit_insert` AFTER INSERT ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_links', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'port_id_a', NEW.`port_id_a`, 'port_id_b', NEW.`port_id_b`, 'cable_color', NEW.`cable_color`, 'cable_label', NEW.`cable_label`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_links_audit_update` AFTER UPDATE ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_links', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'port_id_a', OLD.`port_id_a`, 'port_id_b', OLD.`port_id_b`, 'cable_color', OLD.`cable_color`, 'cable_label', OLD.`cable_label`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'port_id_a', NEW.`port_id_a`, 'port_id_b', NEW.`port_id_b`, 'cable_color', NEW.`cable_color`, 'cable_label', NEW.`cable_label`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_links_audit_delete` AFTER DELETE ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_links', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'port_id_a', OLD.`port_id_a`, 'port_id_b', OLD.`port_id_b`, 'cable_color', OLD.`cable_color`, 'cable_label', OLD.`cable_label`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idf_ports_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_ports_audit_insert` AFTER INSERT ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_ports', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'position_id', NEW.`position_id`, 'port_no', NEW.`port_no`, 'port_type', NEW.`port_type`, 'label', NEW.`label`, 'status', NEW.`status`, 'connected_to', NEW.`connected_to`, 'vlan', NEW.`vlan`, 'speed', NEW.`speed`, 'poe', NEW.`poe`, 'notes', NEW.`notes`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_ports_audit_update` AFTER UPDATE ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_ports', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'position_id', OLD.`position_id`, 'port_no', OLD.`port_no`, 'port_type', OLD.`port_type`, 'label', OLD.`label`, 'status', OLD.`status`, 'connected_to', OLD.`connected_to`, 'vlan', OLD.`vlan`, 'speed', OLD.`speed`, 'poe', OLD.`poe`, 'notes', OLD.`notes`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'position_id', NEW.`position_id`, 'port_no', NEW.`port_no`, 'port_type', NEW.`port_type`, 'label', NEW.`label`, 'status', NEW.`status`, 'connected_to', NEW.`connected_to`, 'vlan', NEW.`vlan`, 'speed', NEW.`speed`, 'poe', NEW.`poe`, 'notes', NEW.`notes`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_ports_audit_delete` AFTER DELETE ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_ports', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'position_id', OLD.`position_id`, 'port_no', OLD.`port_no`, 'port_type', OLD.`port_type`, 'label', OLD.`label`, 'status', OLD.`status`, 'connected_to', OLD.`connected_to`, 'vlan', OLD.`vlan`, 'speed', OLD.`speed`, 'poe', OLD.`poe`, 'notes', OLD.`notes`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idf_positions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_positions_audit_insert` AFTER INSERT ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_positions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idf_id', NEW.`idf_id`, 'position_no', NEW.`position_no`, 'device_type', NEW.`device_type`, 'device_name', NEW.`device_name`, 'equipment_id', NEW.`equipment_id`, 'port_count', NEW.`port_count`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_positions_audit_update` AFTER UPDATE ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_positions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idf_id', OLD.`idf_id`, 'position_no', OLD.`position_no`, 'device_type', OLD.`device_type`, 'device_name', OLD.`device_name`, 'equipment_id', OLD.`equipment_id`, 'port_count', OLD.`port_count`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idf_id', NEW.`idf_id`, 'position_no', NEW.`position_no`, 'device_type', NEW.`device_type`, 'device_name', NEW.`device_name`, 'equipment_id', NEW.`equipment_id`, 'port_count', NEW.`port_count`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_positions_audit_delete` AFTER DELETE ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_positions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idf_id', OLD.`idf_id`, 'position_no', OLD.`position_no`, 'device_type', OLD.`device_type`, 'device_name', OLD.`device_name`, 'equipment_id', OLD.`equipment_id`, 'port_count', OLD.`port_count`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idfs_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idfs_audit_update`;
DROP TRIGGER IF EXISTS `trg_idfs_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idfs_audit_insert` AFTER INSERT ON `idfs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idfs', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'idf_code', NEW.`idf_code`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idfs_audit_update` AFTER UPDATE ON `idfs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idfs', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'idf_code', OLD.`idf_code`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'idf_code', NEW.`idf_code`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idfs_audit_delete` AFTER DELETE ON `idfs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idfs', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'idf_code', OLD.`idf_code`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_inventory_categories_audit_insert`;
DROP TRIGGER IF EXISTS `trg_inventory_categories_audit_update`;
DROP TRIGGER IF EXISTS `trg_inventory_categories_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_inventory_categories_audit_insert` AFTER INSERT ON `inventory_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_categories', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_categories_audit_update` AFTER UPDATE ON `inventory_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_categories', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_categories_audit_delete` AFTER DELETE ON `inventory_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_categories', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_inventory_items_audit_insert`;
DROP TRIGGER IF EXISTS `trg_inventory_items_audit_update`;
DROP TRIGGER IF EXISTS `trg_inventory_items_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_inventory_items_audit_insert` AFTER INSERT ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_items', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'item_code', NEW.`item_code`, 'serial', NEW.`serial`, 'category_id', NEW.`category_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'quantity_on_hand', NEW.`quantity_on_hand`, 'quantity_minimum', NEW.`quantity_minimum`, 'price_eur', NEW.`price_eur`, 'comments', NEW.`comments`, 'location_id', NEW.`location_id`, 'supplier_id', NEW.`supplier_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_items_audit_update` AFTER UPDATE ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_items', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'item_code', OLD.`item_code`, 'serial', OLD.`serial`, 'category_id', OLD.`category_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'quantity_on_hand', OLD.`quantity_on_hand`, 'quantity_minimum', OLD.`quantity_minimum`, 'price_eur', OLD.`price_eur`, 'comments', OLD.`comments`, 'location_id', OLD.`location_id`, 'supplier_id', OLD.`supplier_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'item_code', NEW.`item_code`, 'serial', NEW.`serial`, 'category_id', NEW.`category_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'quantity_on_hand', NEW.`quantity_on_hand`, 'quantity_minimum', NEW.`quantity_minimum`, 'price_eur', NEW.`price_eur`, 'comments', NEW.`comments`, 'location_id', NEW.`location_id`, 'supplier_id', NEW.`supplier_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_items_audit_delete` AFTER DELETE ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_items', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'item_code', OLD.`item_code`, 'serial', OLD.`serial`, 'category_id', OLD.`category_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'quantity_on_hand', OLD.`quantity_on_hand`, 'quantity_minimum', OLD.`quantity_minimum`, 'price_eur', OLD.`price_eur`, 'comments', OLD.`comments`, 'location_id', OLD.`location_id`, 'supplier_id', OLD.`supplier_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_it_locations_audit_insert`;
DROP TRIGGER IF EXISTS `trg_it_locations_audit_update`;
DROP TRIGGER IF EXISTS `trg_it_locations_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_it_locations_audit_insert` AFTER INSERT ON `it_locations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'it_locations', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'location_code', NEW.`location_code`, 'address', NEW.`address`, 'city', NEW.`city`, 'state', NEW.`state`, 'country', NEW.`country`, 'postal_code', NEW.`postal_code`, 'phone', NEW.`phone`, 'type_id', NEW.`type_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_it_locations_audit_update` AFTER UPDATE ON `it_locations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'it_locations', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'location_code', OLD.`location_code`, 'address', OLD.`address`, 'city', OLD.`city`, 'state', OLD.`state`, 'country', OLD.`country`, 'postal_code', OLD.`postal_code`, 'phone', OLD.`phone`, 'type_id', OLD.`type_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'location_code', NEW.`location_code`, 'address', NEW.`address`, 'city', NEW.`city`, 'state', NEW.`state`, 'country', NEW.`country`, 'postal_code', NEW.`postal_code`, 'phone', NEW.`phone`, 'type_id', NEW.`type_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_it_locations_audit_delete` AFTER DELETE ON `it_locations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'it_locations', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'location_code', OLD.`location_code`, 'address', OLD.`address`, 'city', OLD.`city`, 'state', OLD.`state`, 'country', OLD.`country`, 'postal_code', OLD.`postal_code`, 'phone', OLD.`phone`, 'type_id', OLD.`type_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_location_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_location_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_location_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_location_types_audit_insert` AFTER INSERT ON `location_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'location_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_location_types_audit_update` AFTER UPDATE ON `location_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'location_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_location_types_audit_delete` AFTER DELETE ON `location_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'location_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_manufacturers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_manufacturers_audit_update`;
DROP TRIGGER IF EXISTS `trg_manufacturers_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_manufacturers_audit_insert` AFTER INSERT ON `manufacturers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'manufacturers', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_manufacturers_audit_update` AFTER UPDATE ON `manufacturers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'manufacturers', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_manufacturers_audit_delete` AFTER DELETE ON `manufacturers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'manufacturers', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_printer_device_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_printer_device_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_printer_device_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_printer_device_types_audit_insert` AFTER INSERT ON `printer_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'printer_device_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_printer_device_types_audit_update` AFTER UPDATE ON `printer_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'printer_device_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_printer_device_types_audit_delete` AFTER DELETE ON `printer_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'printer_device_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_rack_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_rack_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_rack_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_rack_statuses_audit_insert` AFTER INSERT ON `rack_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'rack_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rack_statuses_audit_update` AFTER UPDATE ON `rack_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'rack_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rack_statuses_audit_delete` AFTER DELETE ON `rack_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'rack_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_racks_audit_insert`;
DROP TRIGGER IF EXISTS `trg_racks_audit_update`;
DROP TRIGGER IF EXISTS `trg_racks_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_racks_audit_insert` AFTER INSERT ON `racks` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'racks', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'rack_code', NEW.`rack_code`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_racks_audit_update` AFTER UPDATE ON `racks` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'racks', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'rack_code', OLD.`rack_code`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'rack_code', NEW.`rack_code`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_racks_audit_delete` AFTER DELETE ON `racks` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'racks', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'rack_code', OLD.`rack_code`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_sidebar_layout_audit_insert`;
DROP TRIGGER IF EXISTS `trg_sidebar_layout_audit_update`;
DROP TRIGGER IF EXISTS `trg_sidebar_layout_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_sidebar_layout_audit_insert` AFTER INSERT ON `sidebar_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'sidebar_layout', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_sidebar_layout_audit_update` AFTER UPDATE ON `sidebar_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'sidebar_layout', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_sidebar_layout_audit_delete` AFTER DELETE ON `sidebar_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'sidebar_layout', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_supplier_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_supplier_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_supplier_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_supplier_statuses_audit_insert` AFTER INSERT ON `supplier_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'supplier_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_supplier_statuses_audit_update` AFTER UPDATE ON `supplier_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'supplier_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_supplier_statuses_audit_delete` AFTER DELETE ON `supplier_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'supplier_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_suppliers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_suppliers_audit_update`;
DROP TRIGGER IF EXISTS `trg_suppliers_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_suppliers_audit_insert` AFTER INSERT ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'suppliers', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'supplier_code', NEW.`supplier_code`, 'contact_person', NEW.`contact_person`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_suppliers_audit_update` AFTER UPDATE ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'suppliers', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'supplier_code', OLD.`supplier_code`, 'contact_person', OLD.`contact_person`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'supplier_code', NEW.`supplier_code`, 'contact_person', NEW.`contact_person`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_suppliers_audit_delete` AFTER DELETE ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'suppliers', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'supplier_code', OLD.`supplier_code`, 'contact_person', OLD.`contact_person`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_switch_cablecolors_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_cablecolors_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_cablecolors_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_cablecolors_audit_insert` AFTER INSERT ON `switch_cablecolors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_cablecolors', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'color', NEW.`color`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_cablecolors_audit_update` AFTER UPDATE ON `switch_cablecolors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_cablecolors', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'color', OLD.`color`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'color', NEW.`color`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_cablecolors_audit_delete` AFTER DELETE ON `switch_cablecolors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_cablecolors', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'color', OLD.`color`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_switch_port_numbering_layout_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_port_numbering_layout_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_port_numbering_layout_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_port_numbering_layout_audit_insert` AFTER INSERT ON `switch_port_numbering_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_port_numbering_layout', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_numbering_layout_audit_update` AFTER UPDATE ON `switch_port_numbering_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_port_numbering_layout', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_numbering_layout_audit_delete` AFTER DELETE ON `switch_port_numbering_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_port_numbering_layout', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_switch_port_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_port_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_port_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_port_types_audit_insert` AFTER INSERT ON `switch_port_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_port_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'type', NEW.`type`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_types_audit_update` AFTER UPDATE ON `switch_port_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_port_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'type', OLD.`type`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'type', NEW.`type`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_types_audit_delete` AFTER DELETE ON `switch_port_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_port_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'type', OLD.`type`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_switch_ports_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_ports_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_ports_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_ports_audit_insert` AFTER INSERT ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_ports', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'port_type', NEW.`port_type`, 'port_number', NEW.`port_number`, 'label', NEW.`label`, 'status_id', NEW.`status_id`, 'color_id', NEW.`color_id`, 'vlan_id', NEW.`vlan_id`, 'comments', NEW.`comments`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_ports_audit_update` AFTER UPDATE ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_ports', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'port_type', OLD.`port_type`, 'port_number', OLD.`port_number`, 'label', OLD.`label`, 'status_id', OLD.`status_id`, 'color_id', OLD.`color_id`, 'vlan_id', OLD.`vlan_id`, 'comments', OLD.`comments`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'port_type', NEW.`port_type`, 'port_number', NEW.`port_number`, 'label', NEW.`label`, 'status_id', NEW.`status_id`, 'color_id', NEW.`color_id`, 'vlan_id', NEW.`vlan_id`, 'comments', NEW.`comments`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_ports_audit_delete` AFTER DELETE ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_ports', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'port_type', OLD.`port_type`, 'port_number', OLD.`port_number`, 'label', OLD.`label`, 'status_id', OLD.`status_id`, 'color_id', OLD.`color_id`, 'vlan_id', OLD.`vlan_id`, 'comments', OLD.`comments`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_switch_status_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_status_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_status_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_status_audit_insert` AFTER INSERT ON `switch_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_status', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'status', NEW.`status`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_status_audit_update` AFTER UPDATE ON `switch_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_status', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'status', OLD.`status`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'status', NEW.`status`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_status_audit_delete` AFTER DELETE ON `switch_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_status', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'status', OLD.`status`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_system_access_audit_insert`;
DROP TRIGGER IF EXISTS `trg_system_access_audit_update`;
DROP TRIGGER IF EXISTS `trg_system_access_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_system_access_audit_insert` AFTER INSERT ON `system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'system_access', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'code', NEW.`code`, 'name', NEW.`name`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_system_access_audit_update` AFTER UPDATE ON `system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'system_access', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'code', OLD.`code`, 'name', OLD.`name`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'code', NEW.`code`, 'name', NEW.`name`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_system_access_audit_delete` AFTER DELETE ON `system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'system_access', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'code', OLD.`code`, 'name', OLD.`name`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_ticket_categories_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ticket_categories_audit_update`;
DROP TRIGGER IF EXISTS `trg_ticket_categories_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ticket_categories_audit_insert` AFTER INSERT ON `ticket_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_categories', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_categories_audit_update` AFTER UPDATE ON `ticket_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_categories', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_categories_audit_delete` AFTER DELETE ON `ticket_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_categories', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_ticket_priorities_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ticket_priorities_audit_update`;
DROP TRIGGER IF EXISTS `trg_ticket_priorities_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ticket_priorities_audit_insert` AFTER INSERT ON `ticket_priorities` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_priorities', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'level', NEW.`level`, 'color', NEW.`color`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_priorities_audit_update` AFTER UPDATE ON `ticket_priorities` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_priorities', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'level', OLD.`level`, 'color', OLD.`color`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'level', NEW.`level`, 'color', NEW.`color`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_priorities_audit_delete` AFTER DELETE ON `ticket_priorities` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_priorities', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'level', OLD.`level`, 'color', OLD.`color`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_ticket_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ticket_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_ticket_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ticket_statuses_audit_insert` AFTER INSERT ON `ticket_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_statuses_audit_update` AFTER UPDATE ON `ticket_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_statuses_audit_delete` AFTER DELETE ON `ticket_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ticket_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_tickets_audit_insert`;
DROP TRIGGER IF EXISTS `trg_tickets_audit_update`;
DROP TRIGGER IF EXISTS `trg_tickets_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_tickets_audit_insert` AFTER INSERT ON `tickets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'tickets', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'ticket_external_code', NEW.`ticket_external_code`, 'title', NEW.`title`, 'description', NEW.`description`, 'category_id', NEW.`category_id`, 'status_id', NEW.`status_id`, 'priority_id', NEW.`priority_id`, 'created_by_user_id', NEW.`created_by_user_id`, 'assigned_to_user_id', NEW.`assigned_to_user_id`, 'asset_id', NEW.`asset_id`, 'ui_color', NEW.`ui_color`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_tickets_audit_update` AFTER UPDATE ON `tickets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'tickets', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'ticket_external_code', OLD.`ticket_external_code`, 'title', OLD.`title`, 'description', OLD.`description`, 'category_id', OLD.`category_id`, 'status_id', OLD.`status_id`, 'priority_id', OLD.`priority_id`, 'created_by_user_id', OLD.`created_by_user_id`, 'assigned_to_user_id', OLD.`assigned_to_user_id`, 'asset_id', OLD.`asset_id`, 'ui_color', OLD.`ui_color`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'ticket_external_code', NEW.`ticket_external_code`, 'title', NEW.`title`, 'description', NEW.`description`, 'category_id', NEW.`category_id`, 'status_id', NEW.`status_id`, 'priority_id', NEW.`priority_id`, 'created_by_user_id', NEW.`created_by_user_id`, 'assigned_to_user_id', NEW.`assigned_to_user_id`, 'asset_id', NEW.`asset_id`, 'ui_color', NEW.`ui_color`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_tickets_audit_delete` AFTER DELETE ON `tickets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'tickets', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'ticket_external_code', OLD.`ticket_external_code`, 'title', OLD.`title`, 'description', OLD.`description`, 'category_id', OLD.`category_id`, 'status_id', OLD.`status_id`, 'priority_id', OLD.`priority_id`, 'created_by_user_id', OLD.`created_by_user_id`, 'assigned_to_user_id', OLD.`assigned_to_user_id`, 'asset_id', OLD.`asset_id`, 'ui_color', OLD.`ui_color`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_update`;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ui_configuration_audit_insert` AFTER INSERT ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ui_configuration', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'table_actions_position', NEW.`table_actions_position`, 'new_button_position', NEW.`new_button_position`, 'export_buttons_position', NEW.`export_buttons_position`, 'back_save_position', NEW.`back_save_position`, 'enable_all_error_reporting', NEW.`enable_all_error_reporting`, 'records_per_page', NEW.`records_per_page`, 'sidebar_visibility', NEW.`sidebar_visibility`, 'sidebar_main_order', NEW.`sidebar_main_order`, 'sidebar_submenu_order', NEW.`sidebar_submenu_order`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ui_configuration_audit_update` AFTER UPDATE ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ui_configuration', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'table_actions_position', OLD.`table_actions_position`, 'new_button_position', OLD.`new_button_position`, 'export_buttons_position', OLD.`export_buttons_position`, 'back_save_position', OLD.`back_save_position`, 'enable_all_error_reporting', OLD.`enable_all_error_reporting`, 'records_per_page', OLD.`records_per_page`, 'sidebar_visibility', OLD.`sidebar_visibility`, 'sidebar_main_order', OLD.`sidebar_main_order`, 'sidebar_submenu_order', OLD.`sidebar_submenu_order`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'table_actions_position', NEW.`table_actions_position`, 'new_button_position', NEW.`new_button_position`, 'export_buttons_position', NEW.`export_buttons_position`, 'back_save_position', NEW.`back_save_position`, 'enable_all_error_reporting', NEW.`enable_all_error_reporting`, 'records_per_page', NEW.`records_per_page`, 'sidebar_visibility', NEW.`sidebar_visibility`, 'sidebar_main_order', NEW.`sidebar_main_order`, 'sidebar_submenu_order', NEW.`sidebar_submenu_order`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ui_configuration_audit_delete` AFTER DELETE ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ui_configuration', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'table_actions_position', OLD.`table_actions_position`, 'new_button_position', OLD.`new_button_position`, 'export_buttons_position', OLD.`export_buttons_position`, 'back_save_position', OLD.`back_save_position`, 'enable_all_error_reporting', OLD.`enable_all_error_reporting`, 'records_per_page', OLD.`records_per_page`, 'sidebar_visibility', OLD.`sidebar_visibility`, 'sidebar_main_order', OLD.`sidebar_main_order`, 'sidebar_submenu_order', OLD.`sidebar_submenu_order`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_user_roles_audit_insert`;
DROP TRIGGER IF EXISTS `trg_user_roles_audit_update`;
DROP TRIGGER IF EXISTS `trg_user_roles_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_user_roles_audit_insert` AFTER INSERT ON `user_roles` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_roles', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_user_roles_audit_update` AFTER UPDATE ON `user_roles` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_roles', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_user_roles_audit_delete` AFTER DELETE ON `user_roles` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_roles', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_users_audit_insert`;
DROP TRIGGER IF EXISTS `trg_users_audit_update`;
DROP TRIGGER IF EXISTS `trg_users_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_users_audit_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'username', NEW.`username`, 'email', NEW.`email`, 'password', NEW.`password`, 'reset_token', NEW.`reset_token`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'phone', NEW.`phone`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_users_audit_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'username', OLD.`username`, 'email', OLD.`email`, 'password', OLD.`password`, 'reset_token', OLD.`reset_token`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'phone', OLD.`phone`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'username', NEW.`username`, 'email', NEW.`email`, 'password', NEW.`password`, 'reset_token', NEW.`reset_token`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'phone', NEW.`phone`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_users_audit_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'username', OLD.`username`, 'email', OLD.`email`, 'password', OLD.`password`, 'reset_token', OLD.`reset_token`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'phone', OLD.`phone`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_user_companies_audit_insert`;
DROP TRIGGER IF EXISTS `trg_user_companies_audit_update`;
DROP TRIGGER IF EXISTS `trg_user_companies_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_user_companies_audit_insert` AFTER INSERT ON `user_companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_companies', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'user_id', NEW.`user_id`, 'company_id', NEW.`company_id`, 'granted_by_user_id', NEW.`granted_by_user_id`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_user_companies_audit_update` AFTER UPDATE ON `user_companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_companies', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'user_id', OLD.`user_id`, 'company_id', OLD.`company_id`, 'granted_by_user_id', OLD.`granted_by_user_id`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'user_id', NEW.`user_id`, 'company_id', NEW.`company_id`, 'granted_by_user_id', NEW.`granted_by_user_id`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_user_companies_audit_delete` AFTER DELETE ON `user_companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_companies', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'user_id', OLD.`user_id`, 'company_id', OLD.`company_id`, 'granted_by_user_id', OLD.`granted_by_user_id`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_role_hierarchy_audit_insert`;
DROP TRIGGER IF EXISTS `trg_role_hierarchy_audit_update`;
DROP TRIGGER IF EXISTS `trg_role_hierarchy_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_role_hierarchy_audit_insert` AFTER INSERT ON `role_hierarchy` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_hierarchy', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'hierarchy_order', NEW.`hierarchy_order`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_hierarchy_audit_update` AFTER UPDATE ON `role_hierarchy` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_hierarchy', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'hierarchy_order', OLD.`hierarchy_order`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'hierarchy_order', NEW.`hierarchy_order`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_hierarchy_audit_delete` AFTER DELETE ON `role_hierarchy` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_hierarchy', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'hierarchy_order', OLD.`hierarchy_order`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_role_module_permissions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_role_module_permissions_audit_update`;
DROP TRIGGER IF EXISTS `trg_role_module_permissions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_role_module_permissions_audit_insert` AFTER INSERT ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_module_permissions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'module_name', NEW.`module_name`, 'can_view', NEW.`can_view`, 'can_create', NEW.`can_create`, 'can_edit', NEW.`can_edit`, 'can_delete', NEW.`can_delete`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_module_permissions_audit_update` AFTER UPDATE ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_module_permissions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'module_name', OLD.`module_name`, 'can_view', OLD.`can_view`, 'can_create', OLD.`can_create`, 'can_edit', OLD.`can_edit`, 'can_delete', OLD.`can_delete`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'module_name', NEW.`module_name`, 'can_view', NEW.`can_view`, 'can_create', NEW.`can_create`, 'can_edit', NEW.`can_edit`, 'can_delete', NEW.`can_delete`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_module_permissions_audit_delete` AFTER DELETE ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_module_permissions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'module_name', OLD.`module_name`, 'can_view', OLD.`can_view`, 'can_create', OLD.`can_create`, 'can_edit', OLD.`can_edit`, 'can_delete', OLD.`can_delete`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_role_assignment_rights_audit_insert`;
DROP TRIGGER IF EXISTS `trg_role_assignment_rights_audit_update`;
DROP TRIGGER IF EXISTS `trg_role_assignment_rights_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_role_assignment_rights_audit_insert` AFTER INSERT ON `role_assignment_rights` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_assignment_rights', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'can_assign_role_id', NEW.`can_assign_role_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_assignment_rights_audit_update` AFTER UPDATE ON `role_assignment_rights` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_assignment_rights', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'can_assign_role_id', OLD.`can_assign_role_id`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'can_assign_role_id', NEW.`can_assign_role_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_assignment_rights_audit_delete` AFTER DELETE ON `role_assignment_rights` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_assignment_rights', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'can_assign_role_id', OLD.`can_assign_role_id`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_vlans_audit_insert`;
DROP TRIGGER IF EXISTS `trg_vlans_audit_update`;
DROP TRIGGER IF EXISTS `trg_vlans_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_vlans_audit_insert` AFTER INSERT ON `vlans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'vlans', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'vlan_number', NEW.`vlan_number`, 'vlan_name', NEW.`vlan_name`, 'vlan_color', NEW.`vlan_color`, 'subnet', NEW.`subnet`, 'ip', NEW.`ip`, 'comments', NEW.`comments`, 'gateway_ip', NEW.`gateway_ip`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_vlans_audit_update` AFTER UPDATE ON `vlans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'vlans', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'vlan_number', OLD.`vlan_number`, 'vlan_name', OLD.`vlan_name`, 'vlan_color', OLD.`vlan_color`, 'subnet', OLD.`subnet`, 'ip', OLD.`ip`, 'comments', OLD.`comments`, 'gateway_ip', OLD.`gateway_ip`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'vlan_number', NEW.`vlan_number`, 'vlan_name', NEW.`vlan_name`, 'vlan_color', NEW.`vlan_color`, 'subnet', NEW.`subnet`, 'ip', NEW.`ip`, 'comments', NEW.`comments`, 'gateway_ip', NEW.`gateway_ip`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_vlans_audit_delete` AFTER DELETE ON `vlans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'vlans', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'vlan_number', OLD.`vlan_number`, 'vlan_name', OLD.`vlan_name`, 'vlan_color', OLD.`vlan_color`, 'subnet', OLD.`subnet`, 'ip', OLD.`ip`, 'comments', OLD.`comments`, 'gateway_ip', OLD.`gateway_ip`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_warranty_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_warranty_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_warranty_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_warranty_types_audit_insert` AFTER INSERT ON `warranty_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'warranty_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_warranty_types_audit_update` AFTER UPDATE ON `warranty_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'warranty_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_warranty_types_audit_delete` AFTER DELETE ON `warranty_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'warranty_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstation_device_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_device_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_device_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_device_types_audit_insert` AFTER INSERT ON `workstation_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_device_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_device_types_audit_update` AFTER UPDATE ON `workstation_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_device_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_device_types_audit_delete` AFTER DELETE ON `workstation_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_device_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstation_modes_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_modes_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_modes_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_modes_audit_insert` AFTER INSERT ON `workstation_modes` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_modes', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'mode_name', NEW.`mode_name`, 'mode_code', NEW.`mode_code`, 'description', NEW.`description`, 'monitor_count', NEW.`monitor_count`, 'has_keyboard_mouse', NEW.`has_keyboard_mouse`, 'pos', NEW.`pos`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_modes_audit_update` AFTER UPDATE ON `workstation_modes` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_modes', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'mode_name', OLD.`mode_name`, 'mode_code', OLD.`mode_code`, 'description', OLD.`description`, 'monitor_count', OLD.`monitor_count`, 'has_keyboard_mouse', OLD.`has_keyboard_mouse`, 'pos', OLD.`pos`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'mode_name', NEW.`mode_name`, 'mode_code', NEW.`mode_code`, 'description', NEW.`description`, 'monitor_count', NEW.`monitor_count`, 'has_keyboard_mouse', NEW.`has_keyboard_mouse`, 'pos', NEW.`pos`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_modes_audit_delete` AFTER DELETE ON `workstation_modes` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_modes', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'mode_name', OLD.`mode_name`, 'mode_code', OLD.`mode_code`, 'description', OLD.`description`, 'monitor_count', OLD.`monitor_count`, 'has_keyboard_mouse', OLD.`has_keyboard_mouse`, 'pos', OLD.`pos`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstation_office_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_office_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_office_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_office_audit_insert` AFTER INSERT ON `workstation_office` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_office', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_office_audit_update` AFTER UPDATE ON `workstation_office` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_office', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_office_audit_delete` AFTER DELETE ON `workstation_office` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_office', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstation_os_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_os_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_os_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_os_types_audit_insert` AFTER INSERT ON `workstation_os_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_os_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_types_audit_update` AFTER UPDATE ON `workstation_os_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_os_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_types_audit_delete` AFTER DELETE ON `workstation_os_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_os_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstation_os_versions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_os_versions_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_os_versions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_os_versions_audit_insert` AFTER INSERT ON `workstation_os_versions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_os_versions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_versions_audit_update` AFTER UPDATE ON `workstation_os_versions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_os_versions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_versions_audit_delete` AFTER DELETE ON `workstation_os_versions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_os_versions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstation_ram_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_ram_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_ram_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_ram_audit_insert` AFTER INSERT ON `workstation_ram` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_ram', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_ram_audit_update` AFTER UPDATE ON `workstation_ram` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_ram', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_ram_audit_delete` AFTER DELETE ON `workstation_ram` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstation_ram', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_workstations_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstations_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstations_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstations_audit_insert` AFTER INSERT ON `workstations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstations', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'workstation_code', NEW.`workstation_code`, 'workstation_mode_id', NEW.`workstation_mode_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'assigned_to_department_id', NEW.`assigned_to_department_id`, 'assignment_type_id', NEW.`assignment_type_id`, 'department', NEW.`department`, 'status_id', NEW.`status_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstations_audit_update` AFTER UPDATE ON `workstations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstations', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'workstation_code', OLD.`workstation_code`, 'workstation_mode_id', OLD.`workstation_mode_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'assigned_to_department_id', OLD.`assigned_to_department_id`, 'assignment_type_id', OLD.`assignment_type_id`, 'department', OLD.`department`, 'status_id', OLD.`status_id`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'workstation_code', NEW.`workstation_code`, 'workstation_mode_id', NEW.`workstation_mode_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'assigned_to_department_id', NEW.`assigned_to_department_id`, 'assignment_type_id', NEW.`assignment_type_id`, 'department', NEW.`department`, 'status_id', NEW.`status_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstations_audit_delete` AFTER DELETE ON `workstations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'workstations', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'workstation_code', OLD.`workstation_code`, 'workstation_mode_id', OLD.`workstation_mode_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'assigned_to_department_id', OLD.`assigned_to_department_id`, 'assignment_type_id', OLD.`assignment_type_id`, 'department', OLD.`department`, 'status_id', OLD.`status_id`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS=1;
