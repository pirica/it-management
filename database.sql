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
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `access_levels`
INSERT INTO `access_levels` (`id`, `name`) VALUES ('1', 'full');
INSERT INTO `access_levels` (`id`, `name`) VALUES ('3', 'limited');
INSERT INTO `access_levels` (`id`, `name`) VALUES ('2', 'read_only');

-- Table structure for `assignment_types`
DROP TABLE IF EXISTS `assignment_types`;
CREATE TABLE `assignment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `assignment_types`
INSERT INTO `assignment_types` (`id`, `name`) VALUES ('2', 'Department');
INSERT INTO `assignment_types` (`id`, `name`) VALUES ('1', 'Individual');
INSERT INTO `assignment_types` (`id`, `name`) VALUES ('4', 'Pool');
INSERT INTO `assignment_types` (`id`, `name`) VALUES ('3', 'Shared');

-- Table structure for `companies`
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incode` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
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
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `manager_user_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `manager_user_id` (`manager_user_id`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `departments_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `departments`
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `manager_user_id`, `location_id`, `active`) VALUES ('1', '1', 'IT Operations', 'ITOPS', 'Core IT operations team', '1', '1', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `manager_user_id`, `location_id`, `active`) VALUES ('2', '1', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1', '1', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `manager_user_id`, `location_id`, `active`) VALUES ('3', '1', 'Human Resources', 'HR', 'Human resources department', '1', '1', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `manager_user_id`, `location_id`, `active`) VALUES ('4', '1', 'Housekeeping', 'HK', 'Housekeeping operations', '1', '1', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `manager_user_id`, `location_id`, `active`) VALUES ('5', '4', 'Front Office', '', '', NULL, NULL, '1');

-- Table structure for `employee_onboarding_requests`
DROP TABLE IF EXISTS `employee_onboarding_requests`;
CREATE TABLE `employee_onboarding_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `network_access` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `micros_emc` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opera` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `micros_card` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `synergy_mms` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_account` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landline_phone` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hu_the_lobby` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `navision` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onq_ri` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birchstreet` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delphi` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `omina` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vingcard_system` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digital_rev` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_key_card` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `starting_date` date DEFAULT NULL,
  `requested_by` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_on` date DEFAULT NULL,
  `hod_approval` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hrd_approval` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ism_approval` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_statuses`
INSERT INTO `employee_statuses` (`id`, `name`) VALUES ('1', 'Active');
INSERT INTO `employee_statuses` (`id`, `name`) VALUES ('5', 'Contractor');
INSERT INTO `employee_statuses` (`id`, `name`) VALUES ('2', 'Inactive');
INSERT INTO `employee_statuses` (`id`, `name`) VALUES ('3', 'On Leave');
INSERT INTO `employee_statuses` (`id`, `name`) VALUES ('4', 'Terminated');

-- Table structure for `employees`
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `duplicate` tinyint(1) NOT NULL DEFAULT '0',
  `company_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hilton_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `job_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `request_date` date DEFAULT NULL,
  `requested_by` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termination_requested_by` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `raw_status_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hostname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `warranty_type_id` int NOT NULL,
  `is_printer` tinyint DEFAULT '0',
  `printer_device_type_id` int DEFAULT NULL,
  `printer_color_capable` tinyint DEFAULT '0',
  `printer_print_speed_ppm` int DEFAULT NULL,
  `is_workstation` tinyint DEFAULT '0',
  `workstation_device_type_id` int DEFAULT NULL,
  `workstation_os_type_id` int DEFAULT NULL,
  `workstation_processor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_memory_gb` int DEFAULT NULL,
  `switch_rj45_id` int DEFAULT NULL,
  `switch_port_numbering_layout_id` int DEFAULT '1',
  `switch_fiber_id` int DEFAULT NULL,
  `switch_fiber_count_id` int DEFAULT NULL,
  `switch_poe_id` int DEFAULT NULL,
  `switch_environment_id` int DEFAULT NULL,
  `notes` longtext COLLATE utf8mb4_unicode_ci,
  `photo_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `warranty_type_id` (`warranty_type_id`),
  KEY `printer_device_type_id` (`printer_device_type_id`),
  KEY `workstation_device_type_id` (`workstation_device_type_id`),
  KEY `workstation_os_type_id` (`workstation_os_type_id`),
  KEY `switch_rj45_id` (`switch_rj45_id`),
  KEY `switch_port_numbering_layout_id` (`switch_port_numbering_layout_id`),
  KEY `switch_fiber_id` (`switch_fiber_id`),
  KEY `switch_fiber_count_id` (`switch_fiber_count_id`),
  KEY `switch_poe_id` (`switch_poe_id`),
  KEY `switch_environment_id` (`switch_environment_id`),
  CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipment_ibfk_10` FOREIGN KEY (`workstation_os_type_id`) REFERENCES `workstation_os_types` (`id`),
  CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`),
  CONSTRAINT `equipment_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `equipment_ibfk_5` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_6` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`),
  CONSTRAINT `equipment_ibfk_7` FOREIGN KEY (`warranty_type_id`) REFERENCES `warranty_types` (`id`),
  CONSTRAINT `equipment_ibfk_8` FOREIGN KEY (`printer_device_type_id`) REFERENCES `printer_device_types` (`id`),
  CONSTRAINT `equipment_ibfk_9` FOREIGN KEY (`workstation_device_type_id`) REFERENCES `workstation_device_types` (`id`),
  CONSTRAINT `equipment_ibfk_11` FOREIGN KEY (`switch_rj45_id`) REFERENCES `equipment_rj45` (`id`),
  CONSTRAINT `equipment_ibfk_12` FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`),
  CONSTRAINT `equipment_ibfk_13` FOREIGN KEY (`switch_fiber_id`) REFERENCES `equipment_fiber` (`id`),
  CONSTRAINT `equipment_ibfk_14` FOREIGN KEY (`switch_poe_id`) REFERENCES `equipment_poe` (`id`),
  CONSTRAINT `equipment_ibfk_15` FOREIGN KEY (`switch_environment_id`) REFERENCES `equipment_environment` (`id`),
  CONSTRAINT `equipment_ibfk_16` FOREIGN KEY (`switch_fiber_count_id`) REFERENCES `equipment_fiber_count` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment`
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `warranty_type_id`, `is_printer`, `printer_device_type_id`, `printer_color_capable`, `printer_print_speed_ppm`, `is_workstation`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_memory_gb`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_count_id`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '2', '2', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, '1', '2025-01-10', '8500.00', NULL, '4', '0', NULL, '0', NULL, '0', NULL, NULL, NULL, NULL, NULL, '1', NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', NULL);


-- Table structure for `equipment_environment`
DROP TABLE IF EXISTS `equipment_environment`;
CREATE TABLE `equipment_environment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_environment`
INSERT INTO `equipment_environment` (`id`, `name`) VALUES ('1', 'Managed');
INSERT INTO `equipment_environment` (`id`, `name`) VALUES ('2', 'Unmanaged');

-- Table structure for `equipment_fiber`
DROP TABLE IF EXISTS `equipment_fiber`;
CREATE TABLE `equipment_fiber` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber`
INSERT INTO `equipment_fiber` (`id`, `name`) VALUES ('1', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`id`, `name`) VALUES ('2', 'SFP+ 10 Gbps');
INSERT INTO `equipment_fiber` (`id`, `name`) VALUES ('3', 'QSFP 40 Gbps');


-- Table structure for `equipment_fiber_count`
DROP TABLE IF EXISTS `equipment_fiber_count`;
CREATE TABLE `equipment_fiber_count` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_count`
INSERT INTO `equipment_fiber_count` (`id`, `name`) VALUES ('1', '1');
INSERT INTO `equipment_fiber_count` (`id`, `name`) VALUES ('2', '2');
INSERT INTO `equipment_fiber_count` (`id`, `name`) VALUES ('3', '3');
INSERT INTO `equipment_fiber_count` (`id`, `name`) VALUES ('4', '4');

-- Table structure for `equipment_poe`
DROP TABLE IF EXISTS `equipment_poe`;
CREATE TABLE `equipment_poe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_poe`
INSERT INTO `equipment_poe` (`id`, `name`) VALUES ('1', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`id`, `name`) VALUES ('2', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`id`, `name`) VALUES ('3', 'PoE++ (802.3bt) - up to 60-90W');

-- Table structure for `equipment_rj45`
DROP TABLE IF EXISTS `equipment_rj45`;
CREATE TABLE `equipment_rj45` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_rj45`
INSERT INTO `equipment_rj45` (`id`, `name`) VALUES ('1', '8 ports');
INSERT INTO `equipment_rj45` (`id`, `name`) VALUES ('2', '16 ports');
INSERT INTO `equipment_rj45` (`id`, `name`) VALUES ('3', '24 ports');
INSERT INTO `equipment_rj45` (`id`, `name`) VALUES ('4', '48 ports');

-- Table structure for `equipment_statuses`
DROP TABLE IF EXISTS `equipment_statuses`;
CREATE TABLE `equipment_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_statuses`
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('1', 'Active');
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('6', 'Decommissioned');
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('4', 'Faulty');
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('2', 'Inactive');
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('3', 'Maintenance');
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('7', 'On-Order');
INSERT INTO `equipment_statuses` (`id`, `name`) VALUES ('5', 'Reserved');

-- Table structure for `equipment_types`
DROP TABLE IF EXISTS `equipment_types`;
CREATE TABLE `equipment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_types`
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('1', 'Switch', 'SWITCH', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('2', 'Server', 'SRV', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('3', 'Router', 'RTR', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('4', 'Firewall', 'FW', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('5', 'PDU', 'PDU', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('6', 'Access Point', 'AP', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('7', 'Workstation', 'WS', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('8', 'Printer', 'PRN', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('9', 'Phone System', 'PHONE', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('10', 'Camera', 'CAM', '1');
INSERT INTO `equipment_types` (`id`, `name`, `code`, `active`) VALUES ('11', 'Other', 'OTHER', '1');

-- Table structure for `inventory_categories`
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_categories`
INSERT INTO `inventory_categories` (`id`, `name`, `code`, `active`) VALUES ('1', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`id`, `name`, `code`, `active`) VALUES ('2', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`id`, `name`, `code`, `active`) VALUES ('3', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`id`, `name`, `code`, `active`) VALUES ('4', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`id`, `name`, `code`, `active`) VALUES ('5', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`id`, `name`, `code`, `active`) VALUES ('6', 'Other', 'OTH', '1');

-- Table structure for `inventory_items`
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `quantity_minimum` int DEFAULT '5',
  `price_eur` decimal(10,2) DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `location_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
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
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `location_id`, `supplier_id`, `comments`, `active`) VALUES ('1', '1', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '1', '1', '50', '10', '4.99', '1', '1', 'Stock for patching and desktop setups', '1');

-- Table structure for `it_locations`
DROP TABLE IF EXISTS `it_locations`;
CREATE TABLE `it_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `location_types`
INSERT INTO `location_types` (`id`, `name`) VALUES ('2', 'Branch');
INSERT INTO `location_types` (`id`, `name`) VALUES ('4', 'DataCenter');
INSERT INTO `location_types` (`id`, `name`) VALUES ('1', 'Headquarters');
INSERT INTO `location_types` (`id`, `name`) VALUES ('5', 'Office');
INSERT INTO `location_types` (`id`, `name`) VALUES ('7', 'Other');
INSERT INTO `location_types` (`id`, `name`) VALUES ('6', 'Remote');
INSERT INTO `location_types` (`id`, `name`) VALUES ('3', 'Warehouse');

-- Table structure for `manufacturers`
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE `manufacturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `manufacturers`
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('1', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('2', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('3', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('4', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('5', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('6', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('7', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`id`, `name`, `code`, `active`) VALUES ('8', 'Microsoft', 'MSFT', '1');

-- Table structure for `printer_device_types`
DROP TABLE IF EXISTS `printer_device_types`;
CREATE TABLE `printer_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `printer_device_types`
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('3', 'All-in-One');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('8', 'Dotmatrix');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('2', 'Inkjet');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('7', 'Label');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('1', 'Laser');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('9', 'Other');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('6', 'Photo');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('4', 'Thermal');
INSERT INTO `printer_device_types` (`id`, `name`) VALUES ('5', 'Wide-Format');

-- Table structure for `rack_statuses`
DROP TABLE IF EXISTS `rack_statuses`;
CREATE TABLE `rack_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `rack_statuses`
INSERT INTO `rack_statuses` (`id`, `name`) VALUES ('1', 'Active');
INSERT INTO `rack_statuses` (`id`, `name`) VALUES ('4', 'Decommissioned');
INSERT INTO `rack_statuses` (`id`, `name`) VALUES ('3', 'Full');
INSERT INTO `rack_statuses` (`id`, `name`) VALUES ('2', 'Maintenance');

-- Table structure for `racks`
DROP TABLE IF EXISTS `racks`;
CREATE TABLE `racks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `location_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rack_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rack_code` (`rack_code`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `racks_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `rack_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `racks`
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('1', '1', '1', 'Main Rack A', 'RACK-A', '1', '1');

-- Table structure for `supplier_statuses`
DROP TABLE IF EXISTS `supplier_statuses`;
CREATE TABLE `supplier_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `supplier_statuses`
INSERT INTO `supplier_statuses` (`id`, `name`) VALUES ('1', 'Active');
INSERT INTO `supplier_statuses` (`id`, `name`) VALUES ('4', 'Backup');
INSERT INTO `supplier_statuses` (`id`, `name`) VALUES ('2', 'Inactive');
INSERT INTO `supplier_statuses` (`id`, `name`) VALUES ('3', 'Preferred');

-- Table structure for `suppliers`
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `supplier_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `suppliers`
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('1', '1', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '1', '1');

-- Table structure for `switch_status`
DROP TABLE IF EXISTS `switch_status`;
CREATE TABLE `switch_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  PRIMARY KEY (`id`),
  UNIQUE KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_status`
INSERT INTO `switch_status` (`id`, `status`) VALUES ('1', 'Up');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('2', 'Down');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('3', 'Free');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('4', 'Disabled');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('5', 'Unknown');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('6', 'Err-Disabled');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('7', 'Testing');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('8', 'Faulty');
INSERT INTO `switch_status` (`id`, `status`) VALUES ('9', 'Reserved');

-- Table structure for `switch_cablecolors`
DROP TABLE IF EXISTS `switch_cablecolors`;
CREATE TABLE `switch_cablecolors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grey',
  PRIMARY KEY (`id`),
  UNIQUE KEY `color` (`color`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_cablecolors`
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('1', 'grey');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('2', 'green');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('3', 'red');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('4', 'yellow');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('5', 'black');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('6', 'blue');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('7', 'white');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('8', 'orange');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('9', 'purple');
INSERT INTO `switch_cablecolors` (`id`, `color`) VALUES ('10', 'other');

-- Table structure for `switch_port_numbering_layout`
DROP TABLE IF EXISTS `switch_port_numbering_layout`;
CREATE TABLE `switch_port_numbering_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_numbering_layout`
INSERT INTO `switch_port_numbering_layout` (`id`, `name`) VALUES ('1', 'Vertical');
INSERT INTO `switch_port_numbering_layout` (`id`, `name`) VALUES ('2', 'Horizontal');

-- Table structure for `switch_port_types`
DROP TABLE IF EXISTS `switch_port_types`;
CREATE TABLE `switch_port_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_types`
INSERT INTO `switch_port_types` (`id`, `type`) VALUES ('1', 'rj45');
INSERT INTO `switch_port_types` (`id`, `type`) VALUES ('2', 'sfp');
INSERT INTO `switch_port_types` (`id`, `type`) VALUES ('3', 'sfp_plus');

-- Table structure for `switch_ports`
DROP TABLE IF EXISTS `switch_ports`;
CREATE TABLE `switch_ports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `port_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rj45',
  `port_number` int NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `color_id` int NOT NULL,
  `vlan_id` int DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_switch_port` (`company_id`,`equipment_id`,`port_type`,`port_number`),
  KEY `company_id` (`company_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `port_type` (`port_type`),
  KEY `status_id` (`status_id`),
  KEY `color_id` (`color_id`),
  KEY `vlan_id` (`vlan_id`),
  CONSTRAINT `switch_ports_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`),
  CONSTRAINT `switch_ports_ibfk_4` FOREIGN KEY (`color_id`) REFERENCES `switch_cablecolors` (`id`),
  CONSTRAINT `switch_ports_ibfk_5` FOREIGN KEY (`port_type`) REFERENCES `switch_port_types` (`type`),
  CONSTRAINT `switch_ports_ibfk_6` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_categories`
DROP TABLE IF EXISTS `ticket_categories`;
CREATE TABLE `ticket_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_categories`
INSERT INTO `ticket_categories` (`id`, `name`, `code`, `active`) VALUES ('1', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`id`, `name`, `code`, `active`) VALUES ('2', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`id`, `name`, `code`, `active`) VALUES ('3', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`id`, `name`, `code`, `active`) VALUES ('4', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`id`, `name`, `code`, `active`) VALUES ('5', 'Other', 'OTHER', '1');

-- Table structure for `ticket_priorities`
DROP TABLE IF EXISTS `ticket_priorities`;
CREATE TABLE `ticket_priorities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_priorities`
INSERT INTO `ticket_priorities` (`id`, `name`, `level`, `color`, `active`) VALUES ('1', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`id`, `name`, `level`, `color`, `active`) VALUES ('2', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`id`, `name`, `level`, `color`, `active`) VALUES ('3', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`id`, `name`, `level`, `color`, `active`) VALUES ('4', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`id`, `name`, `level`, `color`, `active`) VALUES ('5', 'Critical', '5', '#8B0000', '1');

-- Table structure for `ticket_statuses`
DROP TABLE IF EXISTS `ticket_statuses`;
CREATE TABLE `ticket_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_closed` tinyint DEFAULT '0',
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_statuses`
INSERT INTO `ticket_statuses` (`id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`id`, `name`, `color`, `is_closed`, `active`) VALUES ('2', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`id`, `name`, `color`, `is_closed`, `active`) VALUES ('3', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`id`, `name`, `color`, `is_closed`, `active`) VALUES ('4', 'Closed', '#808080', '1', '1');

-- Table structure for `tickets`
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `category_id` int DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  `priority_id` int DEFAULT NULL,
  `created_by_user_id` int NOT NULL,
  `assigned_to_user_id` int DEFAULT NULL,
  `asset_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_code` (`ticket_code`),
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
INSERT INTO `tickets` (`id`, `company_id`, `ticket_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `created_at`) VALUES ('1', '1', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '4', '1', '2', '1', '1', '1', '2026-03-28 19:43:17');

-- Table structure for `ui_configuration`
DROP TABLE IF EXISTS `ui_configuration`;
CREATE TABLE `ui_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `table_actions_position` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `new_button_position` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `export_buttons_position` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `back_save_position` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `sidebar_visibility` longtext COLLATE utf8mb4_unicode_ci,
  `sidebar_main_order` longtext COLLATE utf8mb4_unicode_ci,
  `sidebar_submenu_order` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ui_configuration_company` (`company_id`),
  CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ui_configuration`
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('1', '1', 'left_right', 'left_right', 'left_right', 'left_right', NULL, NULL, NULL, '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('2', '2', 'left_right', 'left_right', 'left_right', 'left_right', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:44:25');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('3', '3', 'left_right', 'left_right', 'left_right', 'left_right', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:44:25');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('4', '4', 'left', 'left_right', 'left_right', 'left_right', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:51:29');
INSERT INTO `ui_configuration` (`id`, `company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `sidebar_visibility`, `sidebar_main_order`, `sidebar_submenu_order`, `created_at`, `updated_at`) VALUES ('5', '5', 'left_right', 'left_right', 'left_right', 'left_right', NULL, NULL, NULL, '2026-03-28 19:44:25', '2026-03-28 19:44:25');

-- Table structure for `sidebar_layout`
DROP TABLE IF EXISTS `sidebar_layout`;
CREATE TABLE `sidebar_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `entry_type` enum('section','item') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sidebar_layout_entry` (`company_id`,`entry_type`,`entry_id`),
  KEY `idx_sidebar_layout_company_type_order` (`company_id`,`entry_type`,`display_order`),
  CONSTRAINT `fk_sidebar_layout_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `user_roles`
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_roles`
INSERT INTO `user_roles` (`id`, `name`) VALUES ('1', 'admin');
INSERT INTO `user_roles` (`id`, `name`) VALUES ('4', 'helpdesk');
INSERT INTO `user_roles` (`id`, `name`) VALUES ('2', 'it_manager');
INSERT INTO `user_roles` (`id`, `name`) VALUES ('3', 'it_technician');
INSERT INTO `user_roles` (`id`, `name`) VALUES ('5', 'user');

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `username` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int NOT NULL,
  `access_level_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username_per_company` (`company_id`,`username`),
  UNIQUE KEY `email` (`email`),
  KEY `company_id` (`company_id`),
  KEY `role_id` (`role_id`),
  KEY `access_level_id` (`access_level_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`),
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `company_id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `role_id`, `access_level_id`, `active`, `created_at`) VALUES ('1', '1', 'admin_tc', 'admin@techcorp.example', '$2y$10$abcdefghijklmnopqrstuv', 'System', 'Admin', NULL, '1', '1', '1', '2026-03-28 19:43:17');

-- Table structure for `vlans`
DROP TABLE IF EXISTS `vlans`;
CREATE TABLE `vlans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `vlan_number` int DEFAULT NULL,
  `vlan_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vlan_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subnet` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `gateway_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `warranty_types`
INSERT INTO `warranty_types` (`id`, `name`) VALUES ('4', 'Enterprise');
INSERT INTO `warranty_types` (`id`, `name`) VALUES ('2', 'Extended');
INSERT INTO `warranty_types` (`id`, `name`) VALUES ('5', 'None');
INSERT INTO `warranty_types` (`id`, `name`) VALUES ('3', 'Premium');
INSERT INTO `warranty_types` (`id`, `name`) VALUES ('1', 'Standard');

-- Table structure for `workstation_device_types`
DROP TABLE IF EXISTS `workstation_device_types`;
CREATE TABLE `workstation_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_device_types`
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('3', 'All-in-One');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('1', 'Desktop');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('2', 'Laptop');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('6', 'Mobile');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('8', 'Other');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('7', 'POS');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('4', 'Tablet');
INSERT INTO `workstation_device_types` (`id`, `name`) VALUES ('5', 'Thin-Client');

-- Table structure for `workstation_modes`
DROP TABLE IF EXISTS `workstation_modes`;
CREATE TABLE `workstation_modes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mode_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `monitor_count` int DEFAULT '0',
  `active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mode_name` (`mode_name`),
  UNIQUE KEY `mode_code` (`mode_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_modes`
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('1', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '1');
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('2', 'Computer + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1');
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('3', 'Computer + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1');
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('4', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '1');
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('5', 'Laptop + Docking', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '2', '1');
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('6', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1');
INSERT INTO `workstation_modes` (`id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `active`) VALUES ('7', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1');

-- Table structure for `workstation_os_types`
DROP TABLE IF EXISTS `workstation_os_types`;
CREATE TABLE `workstation_os_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_types`
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('6', 'Android');
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('4', 'ChromeOS');
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('5', 'iOS');
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('3', 'Linux');
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('2', 'macOS');
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('7', 'Other');
INSERT INTO `workstation_os_types` (`id`, `name`) VALUES ('1', 'Windows');

-- Table structure for `workstations`
DROP TABLE IF EXISTS `workstations`;
CREATE TABLE `workstations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `workstation_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_mode_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `assigned_to_department_id` int DEFAULT NULL,
  `assignment_type_id` int NOT NULL,
  `department` int DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workstation_code` (`workstation_code`),
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

-- Data for `workstations`
INSERT INTO `workstations` (`id`, `company_id`, `equipment_id`, `workstation_code`, `workstation_mode_id`, `assigned_to_employee_id`, `assigned_to_department_id`, `assignment_type_id`, `department`, `status_id`) VALUES ('1', '1', '1', 'WS-001', '1', '1', '1', '1', '1', '1');

SET FOREIGN_KEY_CHECKS=1;
