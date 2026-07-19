-- IT Management SQL Backup
-- Generated at: 2026-03-28 19:52:18 UTC
-- Complete IT Management System Database
DROP DATABASE IF EXISTS `itmanagement`;
CREATE DATABASE `itmanagement` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `itmanagement`;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';
SET FOREIGN_KEY_CHECKS=0;
-- Table structure for `access_levels`
DROP TABLE IF EXISTS `access_levels`;
CREATE TABLE `access_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `access_levels_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Full', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Full', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Full', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Full', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Full', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Limited', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Limited', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Limited', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Limited', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Limited', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Read Only', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Read Only', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Read Only', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Read Only', '2026-01-01 00:00:01');
INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Read Only', '2026-01-01 00:00:01');
-- Table structure for `assignment_types`
DROP TABLE IF EXISTS `assignment_types`;
CREATE TABLE `assignment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `assignment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Department', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Department', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Department', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Department', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Department', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Individual', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Individual', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Individual', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Individual', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Individual', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Shared', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Shared', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Shared', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Shared', '2026-01-01 00:00:01');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Shared', '2026-01-01 00:00:01');
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
  `unit_no` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company` (`company`),
  UNIQUE KEY `incode` (`incode`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `companies`
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('1', 'TechCorp Global', 'TC001', 'New York', 'USA', '+1-212-555-0101', 'info@techcorp.example', 'https://techcorp.example', 'US-TC-1001', NULL, 'Head office company profile', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('2', 'DataCenter Plus', 'DCP001', 'Dallas', 'USA', '+1-972-555-0102', 'contact@datacenterplus.example', 'https://datacenterplus.example', 'US-DCP-1002', NULL, '', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('3', 'Network Solutions', 'NSI001', 'San Francisco', 'USA', '+1-415-555-0103', 'hello@networksolutions.example', 'https://networksolutions.example', 'US-NSI-1003', NULL, '', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('4', 'CloudTech Services', 'CTS001', 'Seattle', 'USA', '+1-206-555-0104', 'support@cloudtech.example', 'https://cloudtech.example', 'US-CTS-1004', NULL, '', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('5', 'Enterprise IT', 'EIT001', 'Boston', 'USA', '+1-617-555-0105', 'office@enterpriseit.example', 'https://enterpriseit.example', 'US-EIT-1005', NULL, '', '1', '2026-01-01 00:00:01', NULL);
-- Table structure for `modules_registry`
DROP TABLE IF EXISTS `company_module_access`;
DROP TABLE IF EXISTS `modules_registry`;
CREATE TABLE `modules_registry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system_module` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modules_registry_slug` (`module_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `company_module_access`
CREATE TABLE `company_module_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_id` int NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `icon` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_module` (`company_id`,`module_id`),
  CONSTRAINT `fk_cma_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cma_module` FOREIGN KEY (`module_id`) REFERENCES `modules_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `modules_registry`
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("access_levels", "Access Levels", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("alerts", "Alerts", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("annual_budgets", "Annual Budgets", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approvals", "Approvals", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approvals_stage", "Approvals Stage", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approver_type", "Approver Type", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approvers", "Approvers", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("assignment_types", "Assignment Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("attempts", "Attempts", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("audit_logs", "Audit Logs", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("backup_tape_log", "Backup Tape Log", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("birthdays", "Birthdays", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("bookmark_folders", "Bookmark Folders", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("bookmarks", "Bookmarks", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("budget_categories", "Budget Categories", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("budget_report", "Budget Report", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("cable_colors", "Cable Colors", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("calendar", "Calendar", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("catalogs", "Catalogs", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("companies", "Companies", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("company_module_access", "Company Module Access", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("contacts", "Contacts", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("cost_centers", "Cost Centers", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("departments", "Departments", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_assignment_history", "Employee Assignment History", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_onboarding_requests", "Employee Onboarding Requests", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_positions", "Employee Positions", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_statuses", "Employee Statuses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_type", "Employee Type", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("emails", "Email Management", 0, 1, "📧");
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_system_access", "Employee System Access", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employees", "Employees", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment", "Equipment", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_environment", "Equipment Environment", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber", "Equipment Fiber", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber_count", "Equipment Fiber Count", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber_patch", "Equipment Fiber Patch", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber_rack", "Equipment Fiber Rack", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_poe", "Equipment Poe", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_rj45", "Equipment Rj45", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_statuses", "Equipment Statuses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_types", "Equipment Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("event_categories", "Event Categories", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("events", "Events", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("expenses", "Expenses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("expiring", "Expiring", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("explorer", "Explorer", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_designer", "Floor Designer", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_designer_points", "Floor Designer Points", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plan_folders", "Floor Plan Folders", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plan_item_tags", "Floor Plan Item Tags", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plan_tags", "Floor Plan Tags", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plans", "Floor Plans", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("forecast_revisions", "Forecast Revisions", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("forecast_revisions_status", "Forecast Revisions Status", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("gl_accounts", "Gl Accounts", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_device_type", "Idf Device Type", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_links", "Idf Links", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_ports", "Idf Ports", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_positions", "Idf Positions", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idfs", "Idfs", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("inventory_categories", "Inventory Categories", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("inventory_items", "Inventory Items", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ip_addresses", "Ip Addresses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ip_subnets", "Ip Subnets", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_access_point", "Is Access Point", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_cctv", "Is Cctv", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_firewall", "Is Firewall", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_other", "Is Other", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_phone", "Is Phone", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_port_patch_panel", "Is Port Patch Panel", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_pos", "Is Pos", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_printer", "Is Printer", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_router", "Is Router", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_server", "Is Server", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_switch", "Is Switch", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_workstation", "Is Workstation", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("license_management", "License Management", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("it_locations", "It Locations", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("location_types", "Location Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("manufacturers", "Manufacturers", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("modules_registry", "Modules Registry", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("monthly_budgets", "Monthly Budgets", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("note_labels", "Note Labels", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("notes", "Notes", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("org_chart", "Org Chart", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ops_report", "Ops Report", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("password_entries", "Password Entries", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("password_folders", "Password Folders", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("passwords", "Passwords", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("patches_updates", "Patches Updates", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("patches_updates_level", "Patches Updates Level", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("patches_updates_status", "Patches Updates Status", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("printer_device_types", "Printer Device Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("private_contacts", "Private Contacts", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("rack_planner", "Rack Planner", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("rack_statuses", "Rack Statuses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("racks", "Racks", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("registration_invitations", "Registration Invitations", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("resignations", "Resignations", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("rj45_speed", "Rj45 Speed", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("reports", "Reports Hub", 0, 1, "📊");
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("role_assignment_rights", "Role Assignment Rights", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("role_hierarchy", "Role Hierarchy", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("role_module_permissions", "Role Module Permissions", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("roles_permissions", "Roles & Permissions", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("settings", "Settings", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("supplier_statuses", "Supplier Statuses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("suppliers", "Suppliers", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_port_numbering_layout", "Switch Port Numbering Layout", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_port_types", "Switch Port Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_ports", "Switch Ports", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_status", "Switch Status", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("system_access", "System Access", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ticket_categories", "Ticket Categories", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ticket_priorities", "Ticket Priorities", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ticket_statuses", "Ticket Statuses", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("tickets", "Tickets", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("todo", "Todo", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("todo_categories", "Todo Categories", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ui_configuration", "Ui Configuration", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_companies", "Employee Companies", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_roles", "Employee Roles", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_sidebar_preferences", "Employee Sidebar Preferences", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("visitors_access_log", "Visitors Access Log", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("vlans", "Vlans", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("warranty_types", "Warranty Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_device_types", "Workstation Device Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_modes", "Workstation Modes", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_office", "Workstation Office", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_os_types", "Workstation Os Types", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_os_versions", "Workstation Os Versions", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_ram", "Workstation Ram", 0, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("import", "Bulk Import", 1, 1, "📥");
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("system_status", "System Status", 1, 1);
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("knowledge_base", "Knowledge Base", 0, 1, "🧩");
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("it_settings", "IT Settings", 0, 1, "⚙️");
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("request_password", "Request Password", 0, 1, "🔑");
-- Data for `company_module_access`
INSERT INTO `company_module_access` (`company_id`, `module_id`, `enabled`)
SELECT c.`id`, mr.`id`, 1
FROM `companies` c
CROSS JOIN `modules_registry` mr
WHERE c.`active` = 1;
-- Table structure for `departments`
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dect` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'IT Operations', 'IT', 'Core IT operations team', 'it-ops@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Food and Drinks', 'FNB', 'Food and Beverages department', 'fnb@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Human Resources', 'HR', 'Human resources department', 'hr@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Housekeeping', 'HK', 'Housekeeping operations', 'housekeeping@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Front Office', 'FO', 'Front Office', 'frontoffice@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '2', 'IT Operations', 'IT', 'Core IT operations team', 'it-ops@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '2', 'Food and Drinks', 'FNB', 'Food and Beverages department', 'fnb@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '2', 'Human Resources', 'HR', 'Human resources department', 'hr@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '2', 'Housekeeping', 'HK', 'Housekeeping operations', 'housekeeping@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '2', 'Front Office', 'FO', 'Front Office', 'frontoffice@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '3', 'IT Operations', 'IT', 'Core IT operations team', 'it-ops@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '3', 'Food and Drinks', 'FNB', 'Food and Beverages department', 'fnb@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '3', 'Human Resources', 'HR', 'Human resources department', 'hr@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '3', 'Housekeeping', 'HK', 'Housekeeping operations', 'housekeeping@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '3', 'Front Office', 'FO', 'Front Office', 'frontoffice@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '4', 'IT Operations', 'IT', 'Core IT operations team', 'it-ops@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '4', 'Food and Drinks', 'FNB', 'Food and Beverages department', 'fnb@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '4', 'Human Resources', 'HR', 'Human resources department', 'hr@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '4', 'Housekeeping', 'HK', 'Housekeeping operations', 'housekeeping@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '4', 'Front Office', 'FO', 'Front Office', 'frontoffice@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '5', 'IT Operations', 'IT', 'Core IT operations team', 'it-ops@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '5', 'Food and Drinks', 'FNB', 'Food and Beverages department', 'fnb@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '5', 'Human Resources', 'HR', 'Human resources department', 'hr@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '5', 'Housekeeping', 'HK', 'Housekeeping operations', 'housekeeping@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '5', 'Front Office', 'FO', 'Front Office', 'frontoffice@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');
-- Table structure for `budget_categories`
DROP TABLE IF EXISTS `budget_categories`;
CREATE TABLE `budget_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_categories_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `budget_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '2', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '3', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '4', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '5', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Operating Expense', 'Operational expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '2', 'Operating Expense', 'Operational expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '3', 'Operating Expense', 'Operational expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '4', 'Operating Expense', 'Operational expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '5', 'Operating Expense', 'Operational expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Capital Expense', 'Capital expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '2', 'Capital Expense', 'Capital expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '3', 'Capital Expense', 'Capital expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '4', 'Capital Expense', 'Capital expense accounts', '1', '2026-01-01 00:00:01');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '5', 'Capital Expense', 'Capital expense accounts', '1', '2026-01-01 00:00:01');
-- Table structure for `cost_centers`
DROP TABLE IF EXISTS `cost_centers`;
CREATE TABLE `cost_centers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `department_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cost_centers_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `cost_centers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cost_centers_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '1', '1', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '2', '6', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '3', '11', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '4', '16', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '5', '21', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '1', '2', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '2', '7', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '3', '12', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '4', '17', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '5', '22', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '1', '4', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '2', '9', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '3', '14', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '4', '19', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '5', '24', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');
-- Table structure for `gl_accounts`
DROP TABLE IF EXISTS `gl_accounts`;
CREATE TABLE `gl_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `account_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gl_accounts_company_code` (`company_id`,`account_code`),
  KEY `company_id` (`company_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `gl_accounts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gl_accounts_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `budget_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, seed.`account_code`, seed.`account_name`, bc.`id`, 1, '2026-01-01 00:00:01'
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, '6100' AS `account_code`, 'IT Maintenance Contracts' AS `account_name`, 'Operating Expense' AS `category_name`
  UNION ALL SELECT 2, 2, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 3, 3, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 4, 4, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 5, 5, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 6, 1, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 7, 2, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 8, 3, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 9, 4, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 10, 5, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 11, 1, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 12, 2, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 13, 3, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 14, 4, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 15, 5, '7100', 'Capital IT Equipment', 'Capital Expense'
) seed
INNER JOIN `budget_categories` bc
  ON bc.`company_id` = seed.`company_id`
 AND bc.`name` = seed.`category_name`
ORDER BY seed.`sort_key`;
-- Table structure for `annual_budgets`
DROP TABLE IF EXISTS `annual_budgets`;
CREATE TABLE `annual_budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cost_center_id` int NOT NULL,
  `gl_account_id` int NOT NULL,
  `year` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_annual_budgets_company_scope` (`company_id`,`cost_center_id`,`gl_account_id`,`year`),
  KEY `company_id` (`company_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `annual_budgets_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `annual_budgets_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `annual_budgets_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, cc.`id`, ga.`id`, seed.`year`, seed.`amount`, NULL, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, 2026 AS `year`, 48000.00 AS `amount`, 'Admin' AS `created_username`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin2', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin3', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin4', '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin5', '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin', '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 2, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin2', '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 3, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin3', '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 4, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin4', '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 5, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin5', '2026-01-01 00:00:01'
  UNION ALL SELECT 11, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2025, 45000.00, 'Admin', '2025-01-01 00:00:01'
  UNION ALL SELECT 12, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2025, 33000.00, 'Admin', '2025-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
ORDER BY seed.`sort_key`;
-- Table structure for `monthly_budgets`
DROP TABLE IF EXISTS `monthly_budgets`;
CREATE TABLE `monthly_budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `annual_budget_id` int NOT NULL,
  `month` tinyint unsigned NOT NULL COMMENT '1=January ... 12=December',
  `amount` decimal(12,2) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_budgets_scope` (`company_id`,`annual_budget_id`,`month`),
  KEY `company_id` (`company_id`),
  KEY `annual_budget_id` (`annual_budget_id`),
  CONSTRAINT `monthly_budgets_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_budgets_ibfk_annual_budget` FOREIGN KEY (`annual_budget_id`) REFERENCES `annual_budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_budgets_chk_month` CHECK ((`month` between 1 and 12))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, ab.`id`, seed.`month`, seed.`amount`, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, 2026 AS `year`, 1 AS `month`, 4000.00 AS `amount`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 3, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 4, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 5, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 11, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 6, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 12, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 7, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 13, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 14, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 3, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 15, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 4, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 16, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 5, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 17, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 6, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 18, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 7, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 19, 2, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 20, 3, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 21, 4, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 22, 5, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
INNER JOIN `annual_budgets` ab
  ON ab.`company_id` = seed.`company_id`
 AND ab.`cost_center_id` = cc.`id`
 AND ab.`gl_account_id` = ga.`id`
 AND ab.`year` = seed.`year`
ORDER BY seed.`sort_key`;
-- Table structure for `expenses`
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `gl_account_id` int NOT NULL,
  `cost_center_id` int NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `invoice_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expenses_company_scope` (`company_id`,`gl_account_id`,`date`,`invoice_number`),
  KEY `company_id` (`company_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `expenses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES
(NULL, 1, 1, 1, '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 1, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-02-12', 2450.00, 'Network switch refresh spares', 'INV-IT-2026-0002', 1, 1, '2026-02-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-03-18', 3125.50, 'Endpoint security subscription', 'INV-IT-2026-0003', 1, 1, '2026-03-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-04-09', 1980.00, 'UPS battery replacement', 'INV-IT-2026-0004', 1, 1, '2026-04-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-05-22', 4275.00, 'Wi-Fi controller licence renewal', 'INV-IT-2026-0005', 1, 1, '2026-05-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-06-11', 2650.00, 'Helpdesk tooling annual fee', 'INV-IT-2026-0006', 1, 1, '2026-06-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-07-08', 3510.00, 'Server rack PDU upgrade', 'INV-IT-2026-0007', 1, 1, '2026-07-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2025-07-14', 2990.00, 'Prior-year July infrastructure spend', 'INV-IT-2025-0007', 1, 1, '2025-07-01 00:00:01', NULL);
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`)
SELECT NULL, seed.`company_id`, cc.`id`, ga.`id`, seed.`expense_date`, seed.`amount`, seed.`description`, seed.`invoice_number`, NULL, 1, seed.`created_at`, NULL
FROM (
  SELECT 1 AS `sort_key`, 2 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, '2026-01-15' AS `expense_date`, 3890.00 AS `amount`, 'Quarterly preventive maintenance contract renewal' AS `description`, 'INV-IT-2026-0001' AS `invoice_number`, 'Admin2' AS `created_username`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 'Admin3', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 'Admin4', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 'Admin5', '2026-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
ORDER BY seed.`sort_key`;
-- Table structure for `floor_plan_folders`
DROP TABLE IF EXISTS `floor_plan_folders`;
CREATE TABLE `floor_plan_folders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `parent_folder_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `parent_folder_id` (`parent_folder_id`),
  UNIQUE KEY `uq_floor_plan_folders_company_parent_name` (`company_id`, (IFNULL(`parent_folder_id`, 0)), `name`),
  CONSTRAINT `floor_plan_folders_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_plan_folders_ibfk_parent` FOREIGN KEY (`parent_folder_id`) REFERENCES `floor_plan_folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', NULL, 'General', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', '1', 'Level 1', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '2', NULL, 'General', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '2', '3', 'Level 1', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '3', NULL, 'General', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '3', '5', 'Level 1', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '4', NULL, 'General', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '4', '7', 'Level 1', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '5', NULL, 'General', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '5', '9', 'Level 1', '1', '2026-01-01 00:00:01');
DROP TABLE IF EXISTS `floor_plan_tags`;
CREATE TABLE `floor_plan_tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_floor_plan_tags_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `floor_plan_tags_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', 'Ground Floor', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', 'Building A', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '2', 'Ground Floor', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '2', 'Building A', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '3', 'Ground Floor', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '3', 'Building A', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '4', 'Ground Floor', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '4', 'Building A', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '5', 'Ground Floor', '1', '2026-01-01 00:00:01');
INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '5', 'Building A', '1', '2026-01-01 00:00:01');
-- Table structure for `floor_plans`
DROP TABLE IF EXISTS `floor_plans`;
CREATE TABLE `floor_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `it_location_id` int DEFAULT NULL COMMENT 'Optional FK: floor plan links to it_locations',
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_ext` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `folder_id` (`folder_id`),
  KEY `it_location_id` (`it_location_id`),
  UNIQUE KEY `uq_floor_plans_company_folder_display_name` (`company_id`, (IFNULL(`folder_id`, 0)), `display_name`),
  CONSTRAINT `floor_plans_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_plans_ibfk_folder` FOREIGN KEY (`folder_id`) REFERENCES `floor_plan_folders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `floor_plans_ibfk_it_location` FOREIGN KEY (`it_location_id`) REFERENCES `it_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `floor_plan_item_tags`
DROP TABLE IF EXISTS `floor_plan_item_tags`;
CREATE TABLE `floor_plan_item_tags` (
  `company_id` int NOT NULL,
  `floor_plan_id` int NOT NULL,
  `tag_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`floor_plan_id`,`tag_id`),
  KEY `company_id` (`company_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `floor_plan_item_tags_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_plan_item_tags_ibfk_plan` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_plan_item_tags_ibfk_tag` FOREIGN KEY (`tag_id`) REFERENCES `floor_plan_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `forecast_revisions_status`
DROP TABLE IF EXISTS `forecast_revisions_status`;
CREATE TABLE `forecast_revisions_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forecast_revisions_status_company_scope` (`company_id`,`status`),
  KEY `forecast_revisions_status_company_id` (`company_id`),
  CONSTRAINT `forecast_revisions_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `forecast_revisions_status`
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES (NULL, 1, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL);
-- Table structure for `forecast_revisions`
DROP TABLE IF EXISTS `forecast_revisions`;
CREATE TABLE `forecast_revisions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cost_center_id` int NOT NULL,
  `gl_account_id` int NOT NULL,
  `year` int NOT NULL,
  `month` tinyint unsigned NOT NULL COMMENT '1=January ... 12=December',
  `forecast_amount` decimal(12,2) NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `locked` tinyint DEFAULT '0',
  `submitted_by` int DEFAULT NULL,
  `finance_reviewed_by` int DEFAULT NULL,
  `gm_approved_by` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forecast_revisions_scope` (`company_id`,`cost_center_id`,`gl_account_id`,`year`,`month`),
  KEY `company_id` (`company_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  KEY `status` (`status`),
  KEY `submitted_by` (`submitted_by`),
  KEY `finance_reviewed_by` (`finance_reviewed_by`),
  KEY `gm_approved_by` (`gm_approved_by`),
  CONSTRAINT `forecast_revisions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forecast_revisions_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `forecast_revisions_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `forecast_revisions_ibfk_status` FOREIGN KEY (`status`) REFERENCES `forecast_revisions_status` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `forecast_revisions_ibfk_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_ibfk_finance_reviewed_by` FOREIGN KEY (`finance_reviewed_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_ibfk_gm_approved_by` FOREIGN KEY (`gm_approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_chk_month` CHECK ((`month` between 1 and 12))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, cc.`id`, ga.`id`, seed.`year`, seed.`month`, seed.`forecast_amount`, frs.`id`, 0, NULL, NULL, NULL, seed.`notes`, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, 2026 AS `year`, 2 AS `month`, 4200.00 AS `forecast_amount`, 'Draft' AS `status_name`, 'Admin' AS `submitted_username`, 'Draft projection before finance review' AS `notes`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin2', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin3', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin4', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin5', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 2, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin2', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 3, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin3', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 4, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin4', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 5, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin5', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
INNER JOIN `forecast_revisions_status` frs
  ON frs.`company_id` = seed.`company_id`
 AND frs.`status` = seed.`status_name`
ORDER BY seed.`sort_key`;
-- Table structure for `approvals_stage`
DROP TABLE IF EXISTS `approvals_stage`;
CREATE TABLE `approvals_stage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `stage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approvals_stage_company_stage` (`company_id`,`stage`),
  KEY `approvals_stage_company_id` (`company_id`),
  CONSTRAINT `approvals_stage_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '2', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '3', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '4', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '5', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'GM Review', 'General manager review stage before final approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '2', 'GM Review', 'General manager review stage before final approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '3', 'GM Review', 'General manager review stage before final approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '4', 'GM Review', 'General manager review stage before final approval.', '1', '2026-01-01 00:00:01');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '5', 'GM Review', 'General manager review stage before final approval.', '1', '2026-01-01 00:00:01');
-- Table structure for `approvals`
DROP TABLE IF EXISTS `approvals`;
CREATE TABLE `approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `forecast_revision_id` int NOT NULL,
  `stage` int NOT NULL DEFAULT '1',
  `status` int NOT NULL DEFAULT '1',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approvals_company_scope` (`company_id`,`forecast_revision_id`),
  KEY `company_id` (`company_id`),
  KEY `forecast_revision_id` (`forecast_revision_id`),
  KEY `stage` (`stage`),
  KEY `status` (`status`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `approvals_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvals_ibfk_forecast_revision` FOREIGN KEY (`forecast_revision_id`) REFERENCES `forecast_revisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvals_ibfk_stage` FOREIGN KEY (`stage`) REFERENCES `approvals_stage` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvals_ibfk_status` FOREIGN KEY (`status`) REFERENCES `forecast_revisions_status` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvals_ibfk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, fr.`id`, aps.`id`, frs.`id`, NULL, NULL, seed.`comments`, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Submitted to finance for February forecast' AS `forecast_note`, 'Finance Review' AS `stage_name`, 'Finance Review' AS `status_name`, 'Awaiting finance validation for submission batch.' AS `comments`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 2, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 3, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 4, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 5, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
) seed
INNER JOIN `forecast_revisions` fr
  ON fr.`company_id` = seed.`company_id`
 AND fr.`notes` = seed.`forecast_note`
INNER JOIN `approvals_stage` aps
  ON aps.`company_id` = seed.`company_id`
 AND aps.`stage` = seed.`stage_name`
INNER JOIN `forecast_revisions_status` frs
  ON frs.`company_id` = seed.`company_id`
 AND frs.`status` = seed.`status_name`
ORDER BY seed.`sort_key`;
-- Table structure for `approver_type`
DROP TABLE IF EXISTS `approver_type`;
CREATE TABLE `approver_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `approver_type_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approver_type_company_scope` (`company_id`,`approver_type_description`),
  KEY `approver_type_company_id` (`company_id`),
  CONSTRAINT `approver_type_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'GM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'HOD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'ISM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'GM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'HOD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'ISM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'GM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'HOD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'ISM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'GM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'HOD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'ISM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'GM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'HOD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'ISM Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'HRD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'HRD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'HRD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'HRD Approval', '1', '2026-01-01 00:00:01');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'HRD Approval', '1', '2026-01-01 00:00:01');
-- Table structure for `approvers`
DROP TABLE IF EXISTS `approvers`;
CREATE TABLE `approvers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `employee_position_id` int NOT NULL,
  `department_id` int NOT NULL,
  `approver_type_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approvers_company_scope` (`company_id`,`employee_id`),
  KEY `approvers_company_id` (`company_id`),
  KEY `approvers_employee_id` (`employee_id`),
  KEY `approvers_employee_position_id` (`employee_position_id`),
  KEY `approvers_department_id` (`department_id`),
  KEY `approvers_approver_type_id` (`approver_type_id`),
  CONSTRAINT `approvers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvers_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvers_ibfk_employee_position` FOREIGN KEY (`employee_position_id`) REFERENCES `employee_positions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvers_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvers_ibfk_approver_type` FOREIGN KEY (`approver_type_id`) REFERENCES `approver_type` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `org_chart`
-- Table structure for `employee_assignment_history`
DROP TABLE IF EXISTS `employee_assignment_history`;
CREATE TABLE `employee_assignment_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `inventory_item_id` int DEFAULT NULL,
  `asset_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sim_imei` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `returned_date` date DEFAULT NULL,
  `condition_on_return` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_handover` tinyint(1) NOT NULL DEFAULT '0',
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assigned_by_employee_id` int DEFAULT NULL,
  `received_by_employee_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_assignment_history_company_scope` (`company_id`,`employee_id`),
  KEY `idx_employee_assignment_history_company` (`company_id`),
  KEY `idx_employee_assignment_history_employee` (`employee_id`),
  KEY `idx_employee_assignment_history_equipment` (`equipment_id`),
  KEY `idx_employee_assignment_history_inventory_item` (`inventory_item_id`),
  KEY `idx_employee_assignment_history_assigned_by` (`assigned_by_employee_id`),
  KEY `idx_employee_assignment_history_received_by` (`received_by_employee_id`),
  CONSTRAINT `employee_assignment_history_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_assignment_history_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `employee_assignment_history_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignment_history_ibfk_inventory_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignment_history_ibfk_assigned_by_employee` FOREIGN KEY (`assigned_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignment_history_ibfk_received_by_employee` FOREIGN KEY (`received_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `employee_onboarding_requests`
DROP TABLE IF EXISTS `employee_onboarding_requests`;
CREATE TABLE `employee_onboarding_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `employee_position_id` int DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `office_key_card_dep` int DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `starting_date` date DEFAULT NULL,
  `requested_by` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by_date` date DEFAULT NULL,
  `requested_on` date DEFAULT NULL,
  `hod_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hod_approval_date` date DEFAULT NULL,
  `hrd_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hrd_approval_date` date DEFAULT NULL,
  `ism_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ism_approval_date` date DEFAULT NULL,
  `gm_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gm_approval_date` date DEFAULT NULL,
  `fin_approval` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fin_approval_date` date DEFAULT NULL,
  `status_hod` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `status_hrd` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `status_ism` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `status_gm` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `status_fin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `email_sent_hod` tinyint NOT NULL DEFAULT '0',
  `email_sent_hod_at` datetime DEFAULT NULL,
  `email_sent_hrd` tinyint NOT NULL DEFAULT '0',
  `email_sent_hrd_at` datetime DEFAULT NULL,
  `email_sent_ism` tinyint NOT NULL DEFAULT '0',
  `email_sent_ism_at` datetime DEFAULT NULL,
  `email_sent_gm` tinyint NOT NULL DEFAULT '0',
  `email_sent_gm_at` datetime DEFAULT NULL,
  `email_sent_fin` tinyint NOT NULL DEFAULT '0',
  `email_sent_fin_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_onboarding_requests_company_scope` (`company_id`,`employee_id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `employee_position_id` (`employee_position_id`),
  KEY `office_key_card_dep` (`office_key_card_dep`),
  CONSTRAINT `employee_onboarding_requests_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_onboarding_requests_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_onboarding_requests_ibfk_4` FOREIGN KEY (`employee_position_id`) REFERENCES `employee_positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_onboarding_requests_ibfk_3` FOREIGN KEY (`office_key_card_dep`) REFERENCES `departments` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `employee_onboarding_requests`
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES 
(NULL, 1, NULL, 3, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, NULL, 15, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, NULL, 6, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, NULL, 9, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, NULL, 12, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL);
-- Table structure for `employee_statuses`
DROP TABLE IF EXISTS `employee_statuses`;
CREATE TABLE `employee_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Active', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Active', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Active', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Active', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Active', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Contractor', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Contractor', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Contractor', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Contractor', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Contractor', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'On Leave', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'On Leave', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'On Leave', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'On Leave', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'On Leave', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Terminated', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', NULL, 'Terminated', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', NULL, 'Terminated', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', NULL, 'Terminated', '2026-01-01 00:00:01');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', NULL, 'Terminated', '2026-01-01 00:00:01');
-- Table structure for `employee_type`
DROP TABLE IF EXISTS `employee_type`;
CREATE TABLE `employee_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_type_company_scope` (`company_id`,`name_type`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_type_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('1', '1', 'Team member', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('1', '2', 'Internship', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('2', '3', 'Team member', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('2', '4', 'Internship', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('3', '5', 'Team member', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('3', '6', 'Internship', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('4', '7', 'Team member', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('4', '8', 'Internship', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('5', '9', 'Team member', '2026-01-01 00:00:01');
INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('5', '10', 'Internship', '2026-01-01 00:00:01');
-- Table structure for `email_smtp_configurations`
DROP TABLE IF EXISTS `emails`;
DROP TABLE IF EXISTS `email_alert_rules`;
DROP TABLE IF EXISTS `email_smtp_configurations`;
CREATE TABLE `email_smtp_configurations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `config_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `smtp_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `smtp_port` int NOT NULL DEFAULT '587',
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `from_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_port` int NOT NULL DEFAULT '143',
  `pop3_port` int NOT NULL DEFAULT '110',
  `pop3_tls_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'None',
  `pop3_require_secure_connection` tinyint NOT NULL DEFAULT '0',
  `is_default` tinyint NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_smtp_configurations_company_scope` (`company_id`, `config_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `email_smtp_configurations_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');
INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');
INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');
INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');
INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');
-- Table structure for `emails`
CREATE TABLE `emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `smtp_config_id` int DEFAULT NULL,
  `to_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emails_company_scope` (`company_id`, `smtp_config_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `smtp_config_id` (`smtp_config_id`),
  CONSTRAINT `emails_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `emails_ibfk_smtp_config` FOREIGN KEY (`smtp_config_id`) REFERENCES `email_smtp_configurations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `emails` (`id`, `company_id`, `smtp_config_id`, `to_email`, `subject`, `status`, `details`, `sent_at`, `active`, `created_at`) VALUES ('1', '1', '1', 'nelson.salvador@gmail.com', 'Test Email from IT Manager Pro', 'sent', NULL, '2026-06-18 02:06:00', '1', '2026-06-18 02:06:00');
-- Table structure for `email_alert_rules`
CREATE TABLE `email_alert_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `rule_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT '0',
  `days_before` int DEFAULT '30',
  `notify_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_alert_rules_company_slug` (`company_id`,`rule_slug`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `email_alert_rules_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'warranty_expiry', '1', '30', 'admin@company.com, it@company.com', '1', '2026-06-18 02:00:00');
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'license_expiry', '1', '30', 'admin@company.com', '1', '2026-06-18 02:00:00');
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'certificate_expiry', '0', '30', NULL, '1', '2026-06-18 02:00:00');
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'alerts_expiry', '0', '30', NULL, '1', '2026-06-18 02:00:00');
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'notes_reminder', '0', '0', NULL, '1', '2026-06-18 02:00:00');
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'todo_deadline', '0', '0', NULL, '1', '2026-06-18 02:00:00');
INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'events_datetime', '0', '0', NULL, '1', '2026-06-18 02:00:00');
-- Table structure for `employee_positions`
DROP TABLE IF EXISTS `employee_positions`;
CREATE TABLE `employee_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `department_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_positions_company_scope` (`company_id`,`name`),
  KEY `idx_employee_positions_company` (`company_id`),
  KEY `idx_employee_positions_department` (`department_id`),
  CONSTRAINT `employee_positions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_positions_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`) VALUES (1, 1, 1, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(2, 1, 1, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(3, 1, 2, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(4, 2, 6, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(5, 2, 6, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(6, 2, 7, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(7, 3, 11, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(8, 3, 11, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(9, 3, 12, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(10, 4, 16, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(11, 4, 16, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(12, 4, 17, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(13, 5, 21, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(14, 5, 21, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(15, 5, 22, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01');
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
  `email_account` tinyint(1) NOT NULL DEFAULT '0',
  `landline_phone` tinyint(1) NOT NULL DEFAULT '0',
  `hu_the_lobby` tinyint(1) NOT NULL DEFAULT '0',
  `mobile_phone` tinyint(1) NOT NULL DEFAULT '0',
  `navision` tinyint(1) NOT NULL DEFAULT '0',
  `mobile_email` tinyint(1) NOT NULL DEFAULT '0',
  `onq_ri` tinyint(1) NOT NULL DEFAULT '0',
  `birchstreet` tinyint(1) NOT NULL DEFAULT '0',
  `delphi` tinyint(1) NOT NULL DEFAULT '0',
  `omina` tinyint(1) NOT NULL DEFAULT '0',
  `vingcard_system` tinyint(1) NOT NULL DEFAULT '0',
  `digital_rev` tinyint(1) NOT NULL DEFAULT '0',
  `office_key_card` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_system_access_company_employee` (`company_id`,`employee_id`),
  KEY `idx_employee_system_access_company` (`company_id`),
  KEY `fk_employee_system_access_employee` (`employee_id`),
  CONSTRAINT `fk_employee_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_system_access_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `employees`
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `duplicate` tinyint(1) NOT NULL DEFAULT '0',
  `company_id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personal_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,

  `theme` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'light',
  `emergency_contact_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  
  
  `mobile_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dect` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_n` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,

  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vault_key_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `access_level_id` int DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `job_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `request_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
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
  `workstation_mode_id` int DEFAULT NULL,
  `assignment_type_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `employment_status_id` int NOT NULL,
  `employee_position_id` int DEFAULT NULL,
  `reports_to` int DEFAULT NULL,
  `on_contacts` tinyint(1) DEFAULT '0',
  `on_orgchart` tinyint(1) DEFAULT '0',
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_type_id` int DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `hide_year` tinyint(1) NOT NULL DEFAULT '0',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `raw_status_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_company_username` (`company_id`,`username`),
  UNIQUE KEY `uq_employees_company_work_email` (`company_id`,`work_email`),
  KEY `department_id` (`department_id`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `idx_employees_external_id` (`external_id`),
  KEY `idx_employees_username` (`username`),
  KEY `employment_status_id` (`employment_status_id`),
  KEY `idx_employees_office_key_department` (`office_key_card_department_id`),
  KEY `idx_employees_workstation_mode` (`workstation_mode_id`),
  KEY `idx_employees_assignment_type` (`assignment_type_id`),
  KEY `idx_employees_position` (`employee_position_id`),
  KEY `idx_employees_reports_to` (`reports_to`),
  KEY `idx_employees_employee_type` (`employee_type_id`),
  KEY `role_id` (`role_id`),
  KEY `access_level_id` (`access_level_id`),
  KEY `idx_employees_reset_token` (`reset_token`),
  KEY `idx_employees_reset_token_hash` (`reset_token_hash`),
  KEY `idx_employees_reset_token_expires_at` (`reset_token_expires_at`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `employees_ibfk_5` FOREIGN KEY (`office_key_card_department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_6` FOREIGN KEY (`employment_status_id`) REFERENCES `employee_statuses` (`id`),
  CONSTRAINT `employees_ibfk_7` FOREIGN KEY (`assignment_type_id`) REFERENCES `assignment_types` (`id`),
  CONSTRAINT `employees_ibfk_8` FOREIGN KEY (`employee_position_id`) REFERENCES `employee_positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_9` FOREIGN KEY (`reports_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_10` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_type` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_role` FOREIGN KEY (`role_id`) REFERENCES `employee_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_access_level` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_workstation_mode` FOREIGN KEY (`workstation_mode_id`) REFERENCES `workstation_modes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employees` (`id`, `duplicate`, `company_id`, `first_name`, `last_name`, `display_name`, `work_email`, `personal_email`, `theme`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `mobile_phone`, `external_number`, `dect`, `extension`, `employee_code`, `external_id`, `password`, `vault_key_hash`, `reset_token`, `reset_token_hash`, `reset_token_expires_at`, `role_id`, `access_level_id`, `username`, `department_id`, `job_code`, `comments`, `request_date`, `start_date`, `requested_by`, `termination_requested_by`, `termination_date`, `network_access`, `micros_emc`, `opera_username`, `micros_card`, `pms_id`, `synergy_mms`, `hu_the_lobby`, `navision`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_department_id`, `workstation_mode_id`, `assignment_type_id`, `location_id`, `employment_status_id`, `employee_position_id`, `reports_to`, `on_contacts`, `on_orgchart`, `photo`, `employee_type_id`, `birthday`, `hide_year`, `is_hidden`, `raw_status_code`, `created_at`, `updated_at`) VALUES
(NULL, 0, 1, 'System', 'Admin', 'System Admin1', 'admin@techcorp.example1.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 1 AND `name` = 'Full' LIMIT 1), 'Admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 1 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 2, 'System', 'Admin', 'System Admin2', 'admin@techcorp.example2.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 2 AND `name` = 'Full' LIMIT 1), 'Admin2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 2 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 3, 'System', 'Admin', 'System Admin3', 'admin@techcorp.example3.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 3 AND `name` = 'Full' LIMIT 1), 'Admin3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 3 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 4, 'System', 'Admin', 'System Admin4', 'admin@techcorp.example4.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 4 AND `name` = 'Full' LIMIT 1), 'Admin4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 4 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 5, 'System', 'Admin', 'System Admin5', 'admin@techcorp.example5.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 5 AND `name` = 'Full' LIMIT 1), 'Admin5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 5 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL);


-- Table structure for `equipment`
DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_type_id` int NOT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `rack_id` int DEFAULT NULL,
  `idf_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hostname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `patch_port` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `status_id` int NOT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `certificate_expiry` date DEFAULT NULL,
  `warranty_type_id` int DEFAULT NULL,
  `printer_device_type_id` int DEFAULT NULL,
  `printer_color_capable` tinyint DEFAULT '0',
  `printer_scan` tinyint DEFAULT NULL,
  `workstation_device_type_id` int DEFAULT NULL,
  `workstation_os_type_id` int DEFAULT NULL,
  `workstation_office_id` int DEFAULT NULL,
  `workstation_processor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_storage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workstation_os_installed_on` date DEFAULT NULL,
  `workstation_ram_id` int DEFAULT NULL,
  `workstation_os_version_id` int DEFAULT NULL,
  `rj45_speed_id` int DEFAULT NULL,
  `switch_rj45_id` int DEFAULT NULL,
  `switch_port_numbering_layout_id` int DEFAULT '1',
  `switch_fiber_id` int DEFAULT NULL,
  `switch_fiber_patch_id` int DEFAULT NULL,
  `switch_fiber_rack_id` int DEFAULT NULL,
  `switch_fiber_ports_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `switch_fiber_port_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `switch_poe_id` int DEFAULT NULL,
  `switch_environment_id` int DEFAULT NULL,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `photo_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, 
  PRIMARY KEY (`id`),
  KEY `equipment_type_id` (`equipment_type_id`),
  KEY `manufacturer_id` (`manufacturer_id`),
  KEY `location_id` (`location_id`),
  KEY `rack_id` (`rack_id`),
  KEY `idf_id` (`idf_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  KEY `warranty_type_id` (`warranty_type_id`),
  KEY `printer_device_type_id` (`printer_device_type_id`),
  KEY `workstation_device_type_id` (`workstation_device_type_id`),
  KEY `workstation_os_type_id` (`workstation_os_type_id`),
  KEY `workstation_office_id` (`workstation_office_id`),
  KEY `workstation_ram_id` (`workstation_ram_id`),
  KEY `workstation_os_version_id` (`workstation_os_version_id`),
  KEY `rj45_speed_id` (`rj45_speed_id`),
  KEY `switch_rj45_id` (`switch_rj45_id`),
  KEY `switch_port_numbering_layout_id` (`switch_port_numbering_layout_id`),
  KEY `switch_fiber_id` (`switch_fiber_id`),
  KEY `switch_fiber_patch_id` (`switch_fiber_patch_id`),
  KEY `switch_fiber_rack_id` (`switch_fiber_rack_id`),
  KEY `switch_poe_id` (`switch_poe_id`),
  KEY `switch_environment_id` (`switch_environment_id`),
  KEY `department_id` (`department_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  UNIQUE KEY `uq_equipment_company_name` (`company_id`,`name`),
  CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipment_ibfk_10` FOREIGN KEY (`workstation_os_type_id`) REFERENCES `workstation_os_types` (`id`),
  CONSTRAINT `equipment_ibfk_16` FOREIGN KEY (`workstation_office_id`) REFERENCES `workstation_office` (`id`),
  CONSTRAINT `equipment_ibfk_17` FOREIGN KEY (`workstation_ram_id`) REFERENCES `workstation_ram` (`id`),
  CONSTRAINT `equipment_ibfk_19` FOREIGN KEY (`workstation_os_version_id`) REFERENCES `workstation_os_versions` (`id`),
  CONSTRAINT `equipment_ibfk_18` FOREIGN KEY (`rj45_speed_id`) REFERENCES `rj45_speed` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_11` FOREIGN KEY (`switch_rj45_id`) REFERENCES `equipment_rj45` (`id`),
  CONSTRAINT `equipment_ibfk_12` FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`),
  CONSTRAINT `equipment_ibfk_13` FOREIGN KEY (`switch_fiber_id`) REFERENCES `equipment_fiber` (`id`),
  CONSTRAINT `equipment_ibfk_20` FOREIGN KEY (`switch_fiber_patch_id`) REFERENCES `equipment_fiber_patch` (`id`),
  CONSTRAINT `equipment_ibfk_21` FOREIGN KEY (`switch_fiber_rack_id`) REFERENCES `equipment_fiber_rack` (`id`),
  CONSTRAINT `equipment_ibfk_14` FOREIGN KEY (`switch_poe_id`) REFERENCES `equipment_poe` (`id`),
  CONSTRAINT `equipment_ibfk_15` FOREIGN KEY (`switch_environment_id`) REFERENCES `equipment_environment` (`id`),
  CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`),
  CONSTRAINT `equipment_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `equipment_ibfk_23` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_24` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_25` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_5` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_22` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_6` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`),
  CONSTRAINT `equipment_ibfk_7` FOREIGN KEY (`warranty_type_id`) REFERENCES `warranty_types` (`id`),
  CONSTRAINT `equipment_ibfk_8` FOREIGN KEY (`printer_device_type_id`) REFERENCES `printer_device_types` (`id`),
  CONSTRAINT `equipment_ibfk_9` FOREIGN KEY (`workstation_device_type_id`) REFERENCES `workstation_device_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `equipment`
-- Why: Relative warranty keeps company-1 email alert runner / verify_emails_module in the default 30-day window after import.
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`) VALUES (1, 1, 1, NULL, 1, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 1, NULL, NULL, 1, '2026-06-05', 8500.00, DATE_ADD(CURDATE(), INTERVAL 14 DAY), NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-01 00:00:01', '2026-04-26 22:07:32');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`) VALUES (2, 2, 13, NULL, 2, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 6, NULL, NULL, 9, '2026-06-05', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-01 00:00:01', '2026-04-26 22:06:38');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`) VALUES (3, 3, 25, NULL, 3, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 11, NULL, NULL, 17, '2026-06-05', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-01 00:00:01', '2026-04-26 22:07:18');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`) VALUES (4, 4, 37, NULL, 4, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 16, NULL, NULL, 25, '2026-06-05', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-01 00:00:01', '2026-04-26 22:04:17');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`) VALUES (5, 5, 49, NULL, 5, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 21, NULL, NULL, 33, '2026-06-05', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-01 00:00:01', '2026-04-26 22:06:55');
-- Table structure for `equipment_environment`
DROP TABLE IF EXISTS `equipment_environment`;
CREATE TABLE `equipment_environment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_environment_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Managed', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '3', 'Managed', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '5', 'Managed', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '7', 'Managed', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '9', 'Managed', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Unmanaged', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '4', 'Unmanaged', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '6', 'Unmanaged', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '8', 'Unmanaged', '2026-01-01 00:00:01');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '10', 'Unmanaged', '2026-01-01 00:00:01');
-- Table structure for `equipment_fiber`
DROP TABLE IF EXISTS `equipment_fiber`;
CREATE TABLE `equipment_fiber` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'QSFP 40 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '4', 'QSFP 40 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '7', 'QSFP 40 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '10', 'QSFP 40 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '13', 'QSFP 40 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'SFP 1 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '5', 'SFP 1 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '8', 'SFP 1 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '11', 'SFP 1 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '14', 'SFP 1 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'SFP+ 10 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', 'SFP+ 10 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '9', 'SFP+ 10 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '12', 'SFP+ 10 Gbps', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '15', 'SFP+ 10 Gbps', '2026-01-01 00:00:01');
-- Table structure for `equipment_fiber_patch`
DROP TABLE IF EXISTS `equipment_fiber_patch`;
CREATE TABLE `equipment_fiber_patch` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_patch_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Patch Panel A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '3', 'Patch Panel A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '5', 'Patch Panel A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '7', 'Patch Panel A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '9', 'Patch Panel A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Patch Panel B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '4', 'Patch Panel B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '6', 'Patch Panel B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '8', 'Patch Panel B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '10', 'Patch Panel B', '2026-01-01 00:00:01');
-- Table structure for `equipment_fiber_rack`
DROP TABLE IF EXISTS `equipment_fiber_rack`;
CREATE TABLE `equipment_fiber_rack` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_rack_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Rack A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '3', 'Rack A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '5', 'Rack A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '7', 'Rack A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '9', 'Rack A', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Rack B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '4', 'Rack B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '6', 'Rack B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '8', 'Rack B', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '10', 'Rack B', '2026-01-01 00:00:01');
-- Table structure for `equipment_fiber_count`
DROP TABLE IF EXISTS `equipment_fiber_count`;
CREATE TABLE `equipment_fiber_count` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_count_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '5', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '9', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '13', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '17', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '2', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', '2', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '10', '2', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '14', '2', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '18', '2', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '3', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', '3', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '11', '3', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '15', '3', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '19', '3', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '4', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', '4', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '12', '4', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '16', '4', '2026-01-01 00:00:01');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '20', '4', '2026-01-01 00:00:01');
-- Table structure for `idf_ports`
DROP TABLE IF EXISTS `idf_ports`;
CREATE TABLE `idf_ports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `position_id` int NOT NULL,
  `port_no` smallint NOT NULL,
  `port_type` int NOT NULL,
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `connected_to` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vlan_id` int DEFAULT NULL,
  `speed_id` int DEFAULT NULL,
  `fiber_patch_id` int DEFAULT NULL,
  `fiber_rack_id` int DEFAULT NULL,
  `to_idf_id` int DEFAULT NULL,
  `to_rack_id` int DEFAULT NULL,
  `to_location_id` int DEFAULT NULL,
  `rj45_speed_id` int DEFAULT NULL,
  `fiber_ports_number` int DEFAULT NULL,
  `switch_port_numbering_layout_id` int DEFAULT NULL,
  `management_id` int DEFAULT NULL,
  `poe_id` int DEFAULT NULL,
  `cable_color` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hex_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_port_unique` (`company_id`,`position_id`,`port_no`,`port_type`),
  KEY `company_id` (`company_id`),
  KEY `position_id` (`position_id`),
  KEY `idf_ports_port_type_idx` (`port_type`),
  KEY `idf_ports_status_idx` (`status_id`),
  KEY `idf_ports_vlan_idx` (`vlan_id`),
  KEY `idf_ports_speed_idx` (`speed_id`),
  KEY `idf_ports_fiber_patch_idx` (`fiber_patch_id`),
  KEY `idf_ports_fiber_rack_idx` (`fiber_rack_id`),
  KEY `idf_ports_to_idf_idx` (`to_idf_id`),
  KEY `idf_ports_to_rack_idx` (`to_rack_id`),
  KEY `idf_ports_to_location_idx` (`to_location_id`),
  KEY `idf_ports_rj45_speed_idx` (`rj45_speed_id`),
  KEY `idf_ports_fiber_ports_number_idx` (`fiber_ports_number`),
  KEY `idf_ports_layout_idx` (`switch_port_numbering_layout_id`),
  KEY `idf_ports_management_idx` (`management_id`),
  KEY `idf_ports_poe_idx` (`poe_id`),
  CONSTRAINT `idf_ports_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_ports_ibfk_position` FOREIGN KEY (`position_id`) REFERENCES `idf_positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_ports_ibfk_speed` FOREIGN KEY (`speed_id`) REFERENCES `equipment_fiber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_fiber_ports_number` FOREIGN KEY (`fiber_ports_number`) REFERENCES `equipment_fiber_count` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_layout` FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_management` FOREIGN KEY (`management_id`) REFERENCES `equipment_environment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_poe` FOREIGN KEY (`poe_id`) REFERENCES `equipment_poe` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_rj45_speed` FOREIGN KEY (`rj45_speed_id`) REFERENCES `rj45_speed` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_port_type` FOREIGN KEY (`port_type`) REFERENCES `switch_port_types` (`id`),
  CONSTRAINT `idf_ports_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`),
  CONSTRAINT `idf_ports_ibfk_vlan` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `equipment_poe`
DROP TABLE IF EXISTS `equipment_poe`;
CREATE TABLE `equipment_poe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `watts` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equipment_poe_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_poe_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('1', '1', 'PoE (802.3af)', 'Up to 15.4W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('2', '4', 'PoE (802.3af)', 'Up to 15.4W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('3', '7', 'PoE (802.3af)', 'Up to 15.4W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('4', '10', 'PoE (802.3af)', 'Up to 15.4W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('5', '13', 'PoE (802.3af)', 'Up to 15.4W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('1', '2', 'PoE+ (802.3at)', 'Up to 30W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('2', '5', 'PoE+ (802.3at)', 'Up to 30W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('3', '8', 'PoE+ (802.3at)', 'Up to 30W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('4', '11', 'PoE+ (802.3at)', 'Up to 30W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('5', '14', 'PoE+ (802.3at)', 'Up to 30W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('1', '3', 'PoE++ (802.3bt)', 'Up to 60-90W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('2', '6', 'PoE++ (802.3bt)', 'Up to 60-90W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('3', '9', 'PoE++ (802.3bt)', 'Up to 60-90W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('4', '12', 'PoE++ (802.3bt)', 'Up to 60-90W', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('5', '15', 'PoE++ (802.3bt)', 'Up to 60-90W', '1', '2026-01-01 00:00:01');
-- Table structure for `equipment_rj45`
DROP TABLE IF EXISTS `equipment_rj45`;
CREATE TABLE `equipment_rj45` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_rj45_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '16 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '5', '16 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '9', '16 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '13', '16 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '17', '16 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '24 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', '24 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '10', '24 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '14', '24 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '18', '24 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '48 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', '48 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '11', '48 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '15', '48 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '19', '48 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '8 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', '8 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '12', '8 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '16', '8 ports', '2026-01-01 00:00:01');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '20', '8 ports', '2026-01-01 00:00:01');
-- Table structure for `rj45_speed`
DROP TABLE IF EXISTS `rj45_speed`;
CREATE TABLE `rj45_speed` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cable_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_speed` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bandwidth` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_distance_full_speed` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rj45_speed_company_type` (`company_id`,`cable_type`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `rj45_speed_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('1', '1', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('2', '1', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('3', '1', 'Cat6', '10 Gbps (up to 55 m), 1 Gbps (100 m)', '250 MHz', '55 m @ 10G', 'Good for most offices/homes.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('4', '1', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('5', '1', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('6', '1', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('7', '2', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('8', '2', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('9', '2', 'Cat6', '10 Gbps (up to 55 m), 1 Gbps (100 m)', '250 MHz', '55 m @ 10G', 'Good for most offices/homes.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('10', '2', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('11', '2', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('12', '2', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('13', '3', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('14', '3', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('15', '3', 'Cat6', '10 Gbps (up to 55 m), 1 Gbps (100 m)', '250 MHz', '55 m @ 10G', 'Good for most offices/homes.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('16', '3', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('17', '3', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('18', '3', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('19', '4', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('20', '4', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('21', '4', 'Cat6', '10 Gbps (up to 55 m), 1 Gbps (100 m)', '250 MHz', '55 m @ 10G', 'Good for most offices/homes.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('22', '4', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('23', '4', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('24', '4', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('25', '5', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('26', '5', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('27', '5', 'Cat6', '10 Gbps (up to 55 m), 1 Gbps (100 m)', '250 MHz', '55 m @ 10G', 'Good for most offices/homes.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('28', '5', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('29', '5', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');
INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('30', '5', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');
-- Table structure for `equipment_statuses`
DROP TABLE IF EXISTS `equipment_statuses`;
CREATE TABLE `equipment_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Active', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '9', 'Active', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '17', 'Active', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '25', 'Active', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '33', 'Active', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '18', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '26', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '34', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Faulty', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '11', 'Faulty', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '19', 'Faulty', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '27', 'Faulty', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '35', 'Faulty', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '12', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '20', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '28', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '36', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '13', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '21', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '29', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '37', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'On-Order', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '14', 'On-Order', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '22', 'On-Order', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '30', 'On-Order', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '38', 'On-Order', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Other', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '15', 'Other', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '23', 'Other', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '31', 'Other', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '39', 'Other', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Reserved', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '16', 'Reserved', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '24', 'Reserved', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '32', 'Reserved', '2026-01-01 00:00:01');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '40', 'Reserved', '2026-01-01 00:00:01');
-- Table structure for `equipment_types`
DROP TABLE IF EXISTS `equipment_types`;
CREATE TABLE `equipment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_edit_emoji` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '1', 'Switch', 'SWITCH', '🔀', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '2', 'Server', 'SRV', '🖥️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '3', 'Router', 'RTR', '✳️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '4', 'Firewall', 'FW', '🔥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '5', 'Port Patch Panel', 'PORT', '➿', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '6', 'Access Point', 'AP', '🛜', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '7', 'Workstation', 'WS', '💻', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '8', 'POS', 'POS', '🏧', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '9', 'Printer', 'PRN', '🖨️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '10', 'Phone', 'PHONE', '📞', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '11', 'CCTV', 'CCCTV', '🎥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '12', 'Other', 'OTHER', NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '13', 'Switch', 'SWITCH', '🔀', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '14', 'Server', 'SRV', '🖥️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '15', 'Router', 'RTR', '✳️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '16', 'Firewall', 'FW', '🔥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '17', 'Port Patch Panel', 'PORT', '➿', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '18', 'Access Point', 'AP', '🛜', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '19', 'Workstation', 'WS', '💻', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '20', 'POS', 'POS', '🏧', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '21', 'Printer', 'PRN', '🖨️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '22', 'Phone', 'PHONE', '📞', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '23', 'CCTV', 'CCCTV', '🎥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('2', '24', 'Other', 'OTHER', NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '25', 'Switch', 'SWITCH', '🔀', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '26', 'Server', 'SRV', '🖥️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '27', 'Router', 'RTR', '✳️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '28', 'Firewall', 'FW', '🔥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '29', 'Port Patch Panel', 'PORT', '➿', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '30', 'Access Point', 'AP', '🛜', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '31', 'Workstation', 'WS', '💻', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '32', 'POS', 'POS', '🏧', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '33', 'Printer', 'PRN', '🖨️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '34', 'Phone', 'PHONE', '📞', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '35', 'CCTV', 'CCCTV', '🎥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('3', '36', 'Other', 'OTHER', NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '37', 'Switch', 'SWITCH', '🔀', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '38', 'Server', 'SRV', '🖥️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '39', 'Router', 'RTR', '✳️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '40', 'Firewall', 'FW', '🔥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '41', 'Port Patch Panel', 'PORT', '➿', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '42', 'Access Point', 'AP', '🛜', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '43', 'Workstation', 'WS', '💻', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '44', 'POS', 'POS', '🏧', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '45', 'Printer', 'PRN', '🖨️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '46', 'Phone', 'PHONE', '📞', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '47', 'CCTV', 'CCCTV', '🎥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('4', '48', 'Other', 'OTHER', NULL, '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '49', 'Switch', 'SWITCH', '🔀', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '50', 'Server', 'SRV', '🖥️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '51', 'Router', 'RTR', '✳️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '52', 'Firewall', 'FW', '🔥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '53', 'Port Patch Panel', 'PORT', '➿', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '54', 'Access Point', 'AP', '🛜', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '55', 'Workstation', 'WS', '💻', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '56', 'POS', 'POS', '🏧', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '57', 'Printer', 'PRN', '🖨️', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '58', 'Phone', 'PHONE', '📞', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '59', 'CCTV', 'CCCTV', '🎥', '1', '2026-01-01 00:00:01');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('5', '60', 'Other', 'OTHER', NULL, '1', '2026-01-01 00:00:01');
-- Table structure for `idf_links`
DROP TABLE IF EXISTS `idf_links`;
CREATE TABLE `idf_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `port_id_a` int NOT NULL,
  `port_id_b` int NOT NULL,
  `equipment_id` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_hostname` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_port_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_port` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_vlan_id` int DEFAULT NULL,
  `equipment_rj45_speed_id` int DEFAULT NULL,
  `equipment_fiber_port_id` int DEFAULT NULL,
  `equipment_fiber_patch_id` int DEFAULT NULL,
  `equipment_fiber_rack_id` int DEFAULT NULL,
  `equipment_to_idf_id` int DEFAULT NULL,
  `equipment_to_rack_id` int DEFAULT NULL,
  `equipment_to_location_id` int DEFAULT NULL,
  `equipment_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `equipment_status_id` int DEFAULT NULL,
  `equipment_color_id` int DEFAULT NULL,
  `cable_color_id` int DEFAULT NULL,
  `cable_color_hex` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cable_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`company_id`,`port_id_a`,`port_id_b`),
  KEY `port_id_a` (`port_id_a`),
  KEY `port_id_b` (`port_id_b`),
  KEY `equipment_rj45_speed_id` (`equipment_rj45_speed_id`),
  KEY `equipment_fiber_port_id` (`equipment_fiber_port_id`),
  KEY `equipment_fiber_patch_id` (`equipment_fiber_patch_id`),
  KEY `equipment_fiber_rack_id` (`equipment_fiber_rack_id`),
  KEY `equipment_to_idf_id` (`equipment_to_idf_id`),
  KEY `equipment_to_rack_id` (`equipment_to_rack_id`),
  KEY `equipment_to_location_id` (`equipment_to_location_id`),
  KEY `cable_color_id` (`cable_color_id`),
  CONSTRAINT `idf_links_ibfk_a` FOREIGN KEY (`port_id_a`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_b` FOREIGN KEY (`port_id_b`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_rj45_speed` FOREIGN KEY (`equipment_rj45_speed_id`) REFERENCES `rj45_speed` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_fiber_port` FOREIGN KEY (`equipment_fiber_port_id`) REFERENCES `equipment_fiber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_fiber_patch` FOREIGN KEY (`equipment_fiber_patch_id`) REFERENCES `equipment_fiber_patch` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_fiber_rack` FOREIGN KEY (`equipment_fiber_rack_id`) REFERENCES `equipment_fiber_rack` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_to_idf` FOREIGN KEY (`equipment_to_idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_to_rack` FOREIGN KEY (`equipment_to_rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_to_location` FOREIGN KEY (`equipment_to_location_id`) REFERENCES `it_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_cable_color` FOREIGN KEY (`cable_color_id`) REFERENCES `cable_colors` (`id`) ON DELETE SET NULL,
  KEY `company_id` (`company_id`),
  CONSTRAINT `idf_links_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `idf_device_type`
DROP TABLE IF EXISTS `idf_device_type`;
CREATE TABLE `idf_device_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `idfdevicetype_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_edit_emoji` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_device_type_unique` (`company_id`,`idfdevicetype_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `idf_device_type_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `idf_device_type`
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'switch', '🔀', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'switch', '🔀', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'switch', '🔀', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'switch', '🔀', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'switch', '🔀', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'patch_panel', '➿', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'patch_panel', '➿', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'patch_panel', '➿', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'patch_panel', '➿', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'patch_panel', '➿', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'ups', '🔋', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'ups', '🔋', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'ups', '🔋', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'ups', '🔋', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'ups', '🔋', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'server', '🖥️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'server', '🖥️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'server', '🖥️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'server', '🖥️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'server', '🖥️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'other', '📦', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'other', '📦', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'other', '📦', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'other', '📦', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'other', '📦', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('26', '1', 'firewall', '🛡️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('29', '2', 'firewall', '🛡️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('32', '3', 'firewall', '🛡️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('35', '4', 'firewall', '🛡️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('38', '5', 'firewall', '🛡️', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('27', '1', 'router', '📡', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('30', '2', 'router', '📡', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('33', '3', 'router', '📡', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('36', '4', 'router', '📡', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('39', '5', 'router', '📡', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('28', '1', 'pdu', '🔌', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('31', '2', 'pdu', '🔌', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('34', '3', 'pdu', '🔌', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('37', '4', 'pdu', '🔌', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('40', '5', 'pdu', '🔌', '1', '2026-01-01 00:00:01', NULL);
-- Table structure for `idf_positions`
DROP TABLE IF EXISTS `idf_positions`;
CREATE TABLE `idf_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `idf_id` int NOT NULL,
  `position_no` smallint NOT NULL,
  `device_type` int NOT NULL,
  `device_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_id` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rj45_count` smallint NOT NULL DEFAULT '0',
  `sfp_count` smallint NOT NULL DEFAULT '0',
  `price` decimal(10,2) DEFAULT NULL,
  `switch_port_numbering_layout_id` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_pos_unique` (`company_id`,`idf_id`,`position_no`),
  KEY `company_id` (`company_id`),
  KEY `idf_id` (`idf_id`),
  KEY `device_type` (`device_type`),
  KEY `equipment_id` (`equipment_id`),
  KEY `switch_port_numbering_layout_id` (`switch_port_numbering_layout_id`),
  CONSTRAINT `idf_positions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_positions_ibfk_device_type` FOREIGN KEY (`device_type`) REFERENCES `idf_device_type` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `idf_positions_ibfk_idf` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_positions_ibfk_layout` FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `idfs`
DROP TABLE IF EXISTS `idfs`;
CREATE TABLE `idfs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `rack_id` int DEFAULT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `idf_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idfs_company_scope` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `location_id` (`location_id`),
  KEY `rack_id` (`rack_id`),
  CONSTRAINT `idfs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idfs_ibfk_location` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idfs_ibfk_rack` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `idfs`
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('1', '1', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-01-01 00:00:01');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('2', '2', '2', '2', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-01-01 00:00:01');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('3', '3', '3', '3', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-01-01 00:00:01');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('4', '4', '4', '4', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-01-01 00:00:01');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('5', '5', '5', '5', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-01-01 00:00:01');
-- Table structure for `inventory_categories`
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_categories_company_scope` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `inventory_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '1', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '7', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '13', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '19', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '25', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '2', 'Cables - USB', 'CBL-USB', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '8', 'Cables - USB', 'CBL-USB', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '14', 'Cables - USB', 'CBL-USB', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '20', 'Cables - USB', 'CBL-USB', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '26', 'Cables - USB', 'CBL-USB', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '3', 'Adapters', 'ADP', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '9', 'Adapters', 'ADP', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '15', 'Adapters', 'ADP', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '21', 'Adapters', 'ADP', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '27', 'Adapters', 'ADP', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '4', 'Batteries', 'BAT', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '10', 'Batteries', 'BAT', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '16', 'Batteries', 'BAT', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '22', 'Batteries', 'BAT', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '28', 'Batteries', 'BAT', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '5', 'Consumables', 'CONS', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '11', 'Consumables', 'CONS', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '17', 'Consumables', 'CONS', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '23', 'Consumables', 'CONS', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '29', 'Consumables', 'CONS', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '6', 'Other', 'OTH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '12', 'Other', 'OTH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '18', 'Other', 'OTH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '24', 'Other', 'OTH', '1', '2026-01-01 00:00:01');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '30', 'Other', 'OTH', '1', '2026-01-01 00:00:01');
-- Table structure for `inventory_items`
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_date` date DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `quantity_minimum` int DEFAULT '5',
  `price_eur` decimal(10,2) DEFAULT NULL,
  `last_employee_id` int DEFAULT NULL,
  `last_employee_manual` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_items_company_scope` (`company_id`,`name`),
  KEY `category_id` (`category_id`),
  KEY `manufacturer_id` (`manufacturer_id`),
  KEY `location_id` (`location_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `last_employee_id` (`last_employee_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`),
  CONSTRAINT `inventory_items_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `inventory_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `inventory_items_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `inventory_items_ibfk_6` FOREIGN KEY (`last_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `inventory_items`
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_employee_id`, `last_employee_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES (1, 1, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 1, 1, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 1, 1, 1, '2026-01-01 00:00:01', '2026-05-17 05:08:05'),
(2, 2, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 7, 9, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 2, 2, 1, '2026-01-01 00:00:01', '2026-05-17 05:08:05'),
(3, 3, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 13, 17, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 3, 3, 1, '2026-01-01 00:00:01', '2026-05-17 05:07:05'),
(4, 4, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 19, 25, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 4, 4, 1, '2026-01-01 00:00:01', '2026-05-17 05:05:19'),
(5, 5, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 25, 33, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 5, 5, 1, '2026-01-01 00:00:01', '2026-05-17 05:07:27');
-- Table structure for `license_types`
DROP TABLE IF EXISTS `license_types`;
CREATE TABLE `license_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `license_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '1', 'Per User', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '2', 'Per Device', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '3', 'Enterprise', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '4', 'Subscription', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '5', 'Other', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('2', '6', 'Per User', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('2', '7', 'Per Device', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('2', '8', 'Enterprise', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('2', '9', 'Subscription', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('2', '10', 'Other', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('3', '11', 'Per User', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('3', '12', 'Per Device', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('3', '13', 'Enterprise', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('3', '14', 'Subscription', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('3', '15', 'Other', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('4', '16', 'Per User', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('4', '17', 'Per Device', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('4', '18', 'Enterprise', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('4', '19', 'Subscription', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('4', '20', 'Other', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('5', '21', 'Per User', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('5', '22', 'Per Device', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('5', '23', 'Enterprise', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('5', '24', 'Subscription', '1', '2026-01-01 00:00:01');
INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('5', '25', 'Other', '1', '2026-01-01 00:00:01');
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
  `type_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_it_locations_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `type_id` (`type_id`),
  CONSTRAINT `it_locations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_locations_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `location_types` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `it_locations`
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '10', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '17', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '24', '1', '2026-01-01 00:00:01', NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '31', '1', '2026-01-01 00:00:01', NULL);
-- Table structure for `location_types`
DROP TABLE IF EXISTS `location_types`;
CREATE TABLE `location_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location_types_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `location_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `location_types`
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Branch', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', 'Branch', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '15', 'Branch', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '22', 'Branch', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '29', 'Branch', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'DataCenter', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '9', 'DataCenter', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '16', 'DataCenter', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '23', 'DataCenter', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '30', 'DataCenter', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Headquarters', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', 'Headquarters', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '17', 'Headquarters', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '24', 'Headquarters', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '31', 'Headquarters', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Office', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '11', 'Office', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '18', 'Office', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '25', 'Office', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '32', 'Office', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'Other', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '12', 'Other', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '19', 'Other', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '26', 'Other', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '33', 'Other', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Remote', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '13', 'Remote', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '20', 'Remote', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '27', 'Remote', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '34', 'Remote', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Warehouse', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '14', 'Warehouse', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '21', 'Warehouse', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '28', 'Warehouse', '2026-01-01 00:00:01');
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '35', 'Warehouse', '2026-01-01 00:00:01');
-- Table structure for `manufacturers`
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE `manufacturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `manufacturers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '1', 'Cisco Systems', 'CSCO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '9', 'Cisco Systems', 'CSCO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '17', 'Cisco Systems', 'CSCO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '25', 'Cisco Systems', 'CSCO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '33', 'Cisco Systems', 'CSCO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '2', 'Dell Technologies', 'DELL', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '10', 'Dell Technologies', 'DELL', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '18', 'Dell Technologies', 'DELL', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '26', 'Dell Technologies', 'DELL', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '34', 'Dell Technologies', 'DELL', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '3', 'HP Inc', 'HPE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '11', 'HP Inc', 'HPE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '19', 'HP Inc', 'HPE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '27', 'HP Inc', 'HPE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '35', 'HP Inc', 'HPE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '4', 'Juniper Networks', 'JNPR', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '12', 'Juniper Networks', 'JNPR', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '20', 'Juniper Networks', 'JNPR', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '28', 'Juniper Networks', 'JNPR', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '36', 'Juniper Networks', 'JNPR', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '5', 'Ubiquiti Networks', 'UBNT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '13', 'Ubiquiti Networks', 'UBNT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '21', 'Ubiquiti Networks', 'UBNT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '29', 'Ubiquiti Networks', 'UBNT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '37', 'Ubiquiti Networks', 'UBNT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '6', 'Apple', 'APPLE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '14', 'Apple', 'APPLE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '22', 'Apple', 'APPLE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '30', 'Apple', 'APPLE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '38', 'Apple', 'APPLE', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '7', 'Lenovo', 'LENOVO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '15', 'Lenovo', 'LENOVO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '23', 'Lenovo', 'LENOVO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '31', 'Lenovo', 'LENOVO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '39', 'Lenovo', 'LENOVO', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '8', 'Microsoft', 'MSFT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '16', 'Microsoft', 'MSFT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '24', 'Microsoft', 'MSFT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '32', 'Microsoft', 'MSFT', '1', '2026-01-01 00:00:01');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '40', 'Microsoft', 'MSFT', '1', '2026-01-01 00:00:01');
-- Table structure for `catalogs`
DROP TABLE IF EXISTS `catalogs`;
CREATE TABLE `catalogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_type_id` int DEFAULT NULL,
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `product_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalogs_company_model_supplier` (`company_id`,`model`,`supplier_id`),
  KEY `company_id` (`company_id`),
  KEY `equipment_type_id` (`equipment_type_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `manufacturer_id` (`manufacturer_id`),
  CONSTRAINT `catalogs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `catalogs_ibfk_equipment_type` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `catalogs_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `catalogs_ibfk_manufacturer` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Dumping data for table `catalogs`
--

INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES (1, 1, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, 'https://fls-na.amaz', 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:57'),
(2, 1, 'Cisco Catalyst C9200L-24P-4G-A', 1, 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:38'),
(3, 1, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:33'),
(4, 1, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, 'https://www.bhphotovideo.com/', 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:20'),
(5, 1, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, 'https://www.bestbuy.com/', 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:29'),
(7, 1, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', '2026-04-13 01:20:28'),
(8, 1, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', '2026-04-13 01:20:22'),
(9, 1, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:20:12'),
(10, 1, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', 1599.00, 1, 1, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', '2026-04-12 16:51:50'),
(11, 5, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 49, NULL, 500.00, NULL, 35, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(13, 3, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 25, NULL, 500.00, NULL, 19, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(14, 2, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 13, NULL, 500.00, NULL, 11, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(15, 5, 'Cisco Catalyst C9200L-24P-4G-A', 49, NULL, 3899.00, NULL, 33, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(17, 3, 'Cisco Catalyst C9200L-24P-4G-A', 25, NULL, 3899.00, NULL, 17, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(18, 2, 'Cisco Catalyst C9200L-24P-4G-A', 13, NULL, 3899.00, NULL, 9, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(19, 5, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 49, NULL, 699.00, NULL, 37, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(21, 3, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 25, NULL, 699.00, NULL, 21, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(22, 2, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 13, NULL, 699.00, NULL, 13, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(23, 5, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 49, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(25, 3, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 25, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(26, 2, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 13, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(27, 5, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 49, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(29, 3, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 25, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(30, 2, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 13, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(31, 5, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 49, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(33, 3, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 25, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(34, 2, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 13, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(35, 5, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 49, NULL, 698.99, NULL, 37, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(37, 3, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 25, NULL, 698.99, NULL, 21, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(38, 2, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 13, NULL, 698.99, NULL, 13, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(39, 5, 'Ubiquiti Networks UniFi Switch 24 PoE', 49, NULL, 379.00, NULL, 37, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(41, 3, 'Ubiquiti Networks UniFi Switch 24 PoE', 25, NULL, 379.00, NULL, 21, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(42, 2, 'Ubiquiti Networks UniFi Switch 24 PoE', 13, NULL, 379.00, NULL, 13, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(43, 5, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 49, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(45, 3, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 25, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(46, 2, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 13, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(47, 5, 'Cisco Meraki MS120-24P Cloud Managed Switch', 49, NULL, 1599.00, NULL, 33, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL),
(49, 3, 'Cisco Meraki MS120-24P Cloud Managed Switch', 25, NULL, 1599.00, NULL, 17, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL),
(50, 2, 'Cisco Meraki MS120-24P Cloud Managed Switch', 13, NULL, 1599.00, NULL, 9, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL),
(84, 4, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 37, NULL, 500.00, NULL, 27, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(85, 4, 'Cisco Catalyst C9200L-24P-4G-A', 37, NULL, 3899.00, NULL, 25, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(86, 4, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 37, NULL, 699.00, NULL, 29, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(87, 4, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 37, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(88, 4, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 37, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(89, 4, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 37, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(90, 4, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 37, NULL, 698.99, NULL, 29, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(91, 4, 'Ubiquiti Networks UniFi Switch 24 PoE', 37, NULL, 379.00, NULL, 29, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(92, 4, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 37, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(93, 4, 'Cisco Meraki MS120-24P Cloud Managed Switch', 37, NULL, 1599.00, NULL, 25, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL);
-- Table structure for `patches_updates_status`
DROP TABLE IF EXISTS `patches_updates_status`;
CREATE TABLE `patches_updates_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_closed` tinyint DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patches_updates_status_company_name_unique` (`company_id`,`name`),
  KEY `patches_updates_status_company_idx` (`company_id`),
  CONSTRAINT `patches_updates_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '2', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '3', 'Resolved', '#00FF00', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '4', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '5', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '6', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '7', 'Resolved', '#00FF00', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '8', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '9', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '10', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '11', 'Resolved', '#00FF00', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '12', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '13', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '14', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '15', 'Resolved', '#00FF00', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '16', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '17', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '18', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '19', 'Resolved', '#00FF00', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '20', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
-- Table structure for `patches_updates_level`
DROP TABLE IF EXISTS `patches_updates_level`;
CREATE TABLE `patches_updates_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patches_updates_level_company_level_unique` (`company_id`,`level`),
  KEY `patches_updates_level_company_idx` (`company_id`),
  CONSTRAINT `patches_updates_level_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '1', 'Critical', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '2', 'High', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '3', 'Medium', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '4', 'Low', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '5', 'Other', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('2', '6', 'Critical', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('2', '7', 'High', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('2', '8', 'Medium', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('2', '9', 'Low', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('2', '10', 'Other', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('3', '11', 'Critical', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('3', '12', 'High', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('3', '13', 'Medium', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('3', '14', 'Low', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('3', '15', 'Other', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('4', '16', 'Critical', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('4', '17', 'High', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('4', '18', 'Medium', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('4', '19', 'Low', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('4', '20', 'Other', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('5', '21', 'Critical', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('5', '22', 'High', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('5', '23', 'Medium', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('5', '24', 'Low', '2026-01-01 00:00:01');
INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('5', '25', 'Other', '2026-01-01 00:00:01');
-- Table structure for `patches_updates`
DROP TABLE IF EXISTS `patches_updates`;
CREATE TABLE `patches_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_external` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inncode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dest` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dest_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vuln_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `base_score` decimal(5,2) DEFAULT NULL,
  `remediation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cve` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_mac_manufacturer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `days_since_last_seen` int DEFAULT NULL,
  `host_health_score` decimal(10,2) DEFAULT NULL,
  `host_health_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `host_resolution_priority` int DEFAULT NULL,
  `host_workload_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operating_system` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_function` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `last_user_department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `problem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `troubleshooting` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `patches_updates_photos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status_id` int DEFAULT NULL,
  `level_id` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patches_updates_company_scope` (`company_id`,`equipment_id`),
  KEY `patches_updates_company_idx` (`company_id`),
  KEY `patches_updates_equipment_idx` (`equipment_id`),
  KEY `patches_updates_status_idx` (`status_id`),
  KEY `patches_updates_level_idx` (`level_id`),
  KEY `patches_updates_created_by_idx` (`created_by`),
  CONSTRAINT `patches_updates_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patches_updates_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `patches_updates_status` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_level` FOREIGN KEY (`level_id`) REFERENCES `patches_updates_level` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_created_by` FOREIGN KEY (`created_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `printer_device_types`
DROP TABLE IF EXISTS `printer_device_types`;
CREATE TABLE `printer_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `printer_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '19', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '28', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '37', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Dotmatrix', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '11', 'Dotmatrix', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '20', 'Dotmatrix', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '29', 'Dotmatrix', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '38', 'Dotmatrix', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Inkjet', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '12', 'Inkjet', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '21', 'Inkjet', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '30', 'Inkjet', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '39', 'Inkjet', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'Label', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '13', 'Label', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '22', 'Label', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '31', 'Label', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '40', 'Label', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Laser', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '14', 'Laser', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '23', 'Laser', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '32', 'Laser', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '41', 'Laser', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '9', 'Other', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '15', 'Other', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '24', 'Other', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '33', 'Other', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '42', 'Other', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Photo', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '16', 'Photo', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '25', 'Photo', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '34', 'Photo', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '43', 'Photo', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Thermal', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '17', 'Thermal', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '26', 'Thermal', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '35', 'Thermal', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '44', 'Thermal', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Wide-Format', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '18', 'Wide-Format', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '27', 'Wide-Format', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '36', 'Wide-Format', '2026-01-01 00:00:01');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '45', 'Wide-Format', '2026-01-01 00:00:01');
-- Table structure for `rack_statuses`
DROP TABLE IF EXISTS `rack_statuses`;
CREATE TABLE `rack_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `rack_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Active', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '5', 'Active', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '9', 'Active', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '13', 'Active', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '17', 'Active', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '10', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '14', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '18', 'Decommissioned', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Full', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', 'Full', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '11', 'Full', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '15', 'Full', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '19', 'Full', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '12', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '16', 'Maintenance', '2026-01-01 00:00:01');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '20', 'Maintenance', '2026-01-01 00:00:01');
-- Table structure for `racks`
DROP TABLE IF EXISTS `racks`;
CREATE TABLE `racks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rack_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_racks_company_scope` (`company_id`,`name`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `racks_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `rack_statuses` (`id`)) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) VALUES ('1', '1', '1', 'Main Rack A', 'RACK-A', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) VALUES ('2', '2', '2', 'Main Rack A', 'RACK-A', '5', '1', '2026-01-01 00:00:01');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) VALUES ('3', '3', '3', 'Main Rack A', 'RACK-A', '9', '1', '2026-01-01 00:00:01');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) VALUES ('4', '4', '4', 'Main Rack A', 'RACK-A', '13', '1', '2026-01-01 00:00:01');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) VALUES ('5', '5', '5', 'Main Rack A', 'RACK-A', '17', '1', '2026-01-01 00:00:01');
-- Table structure for `employee_sidebar_preferences`
DROP TABLE IF EXISTS `employee_sidebar_preferences`;
CREATE TABLE `employee_sidebar_preferences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `entry_type` enum('section','item') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_sidebar_pref_entry` (`company_id`,`employee_id`,`entry_type`,`entry_id`),
  KEY `idx_employee_sidebar_pref_company_user_type_order` (`company_id`,`employee_id`,`entry_type`,`display_order`),
  CONSTRAINT `fk_employee_sidebar_pref_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_sidebar_pref_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `employee_sidebar_preferences`
-- Why: seed default sidebar layout for all 5 base companies and rely on table defaults for timestamps.
INSERT INTO `employee_sidebar_preferences` (`company_id`, `employee_id`, `entry_type`, `entry_id`, `section_id`, `display_order`, `is_visible`, `active`)
SELECT c.company_id, 1 AS employee_id, t.entry_type, t.entry_id, t.section_id, t.display_order, 1 AS is_visible, 1 AS active
FROM (
    SELECT 1 AS company_id
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
) AS c
CROSS JOIN (
      SELECT 'section' AS entry_type, 'dashboard' AS entry_id, NULL AS section_id, 0 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'management' AS entry_id, NULL AS section_id, 1 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'employee' AS entry_id, NULL AS section_id, 2 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'budgeting' AS entry_id, NULL AS section_id, 3 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'admin' AS entry_id, NULL AS section_id, 4 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'reference_data' AS entry_id, NULL AS section_id, 5 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'dashboard_link' AS entry_id, 'dashboard' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'settings' AS entry_id, 'dashboard' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment' AS entry_id, 'management' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_workstation' AS entry_id, 'management' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_server' AS entry_id, 'management' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_switch' AS entry_id, 'management' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_printer' AS entry_id, 'management' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_pos' AS entry_id, 'management' AS section_id, 5 AS display_order
            UNION ALL SELECT 'item' AS entry_type, 'tickets' AS entry_id, 'management' AS section_id, 6 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_other' AS entry_id, 'management' AS section_id, 7 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_router' AS entry_id, 'management' AS section_id, 8 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_port_patch_panel' AS entry_id, 'management' AS section_id, 9 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_cctv' AS entry_id, 'management' AS section_id, 10 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_phone' AS entry_id, 'management' AS section_id, 11 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_firewall' AS entry_id, 'management' AS section_id, 12 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_access_point' AS entry_id, 'management' AS section_id, 13 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employees' AS entry_id, 'employee' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_system_access' AS entry_id, 'employee' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'system_access' AS entry_id, 'employee' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'departments' AS entry_id, 'employee' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_assignment_history' AS entry_id, 'employee' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'budget_categories' AS entry_id, 'budgeting' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'cost_centers' AS entry_id, 'budgeting' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'gl_accounts' AS entry_id, 'budgeting' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'annual_budgets' AS entry_id, 'budgeting' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'monthly_budgets' AS entry_id, 'budgeting' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'forecast_revisions' AS entry_id, 'budgeting' AS section_id, 5 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'forecast_revisions_status' AS entry_id, 'budgeting' AS section_id, 6 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approvals' AS entry_id, 'budgeting' AS section_id, 7 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approvals_stage' AS entry_id, 'budgeting' AS section_id, 8 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'expenses' AS entry_id, 'budgeting' AS section_id, 9 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'budget_report' AS entry_id, 'budgeting' AS section_id, 10 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'inventory_items' AS entry_id, 'admin' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'companies' AS entry_id, 'admin' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'it_locations' AS entry_id, 'reference_data' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'location_types' AS entry_id, 'reference_data' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_types' AS entry_id, 'reference_data' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_statuses' AS entry_id, 'reference_data' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'manufacturers' AS entry_id, 'reference_data' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'catalogs' AS entry_id, 'reference_data' AS section_id, 5 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'suppliers' AS entry_id, 'reference_data' AS section_id, 6 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'supplier_statuses' AS entry_id, 'reference_data' AS section_id, 7 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'racks' AS entry_id, 'reference_data' AS section_id, 8 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idfs' AS entry_id, 'reference_data' AS section_id, 9 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'rack_statuses' AS entry_id, 'reference_data' AS section_id, 10 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_status' AS entry_id, 'reference_data' AS section_id, 11 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'cable_colors' AS entry_id, 'reference_data' AS section_id, 12 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ticket_categories' AS entry_id, 'reference_data' AS section_id, 13 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ticket_statuses' AS entry_id, 'reference_data' AS section_id, 14 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ticket_priorities' AS entry_id, 'reference_data' AS section_id, 15 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_statuses' AS entry_id, 'reference_data' AS section_id, 16 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_positions' AS entry_id, 'reference_data' AS section_id, 17 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approver_type' AS entry_id, 'reference_data' AS section_id, 18 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approvers' AS entry_id, 'reference_data' AS section_id, 19 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'audit_logs' AS entry_id, 'reference_data' AS section_id, 20 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'access_levels' AS entry_id, 'reference_data' AS section_id, 21 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'assignment_types' AS entry_id, 'reference_data' AS section_id, 22 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'attempts' AS entry_id, 'reference_data' AS section_id, 23 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_onboarding_requests' AS entry_id, 'reference_data' AS section_id, 24 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_environment' AS entry_id, 'reference_data' AS section_id, 25 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber' AS entry_id, 'reference_data' AS section_id, 26 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber_count' AS entry_id, 'reference_data' AS section_id, 27 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber_patch' AS entry_id, 'reference_data' AS section_id, 28 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber_rack' AS entry_id, 'reference_data' AS section_id, 29 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_poe' AS entry_id, 'reference_data' AS section_id, 30 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_rj45' AS entry_id, 'reference_data' AS section_id, 31 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'expiring' AS entry_id, 'reference_data' AS section_id, 32 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_device_type' AS entry_id, 'reference_data' AS section_id, 33 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_links' AS entry_id, 'reference_data' AS section_id, 34 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_ports' AS entry_id, 'reference_data' AS section_id, 35 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_positions' AS entry_id, 'reference_data' AS section_id, 36 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'inventory_categories' AS entry_id, 'reference_data' AS section_id, 37 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates' AS entry_id, 'reference_data' AS section_id, 38 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates_level' AS entry_id, 'reference_data' AS section_id, 40 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates_status' AS entry_id, 'reference_data' AS section_id, 41 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'printer_device_types' AS entry_id, 'reference_data' AS section_id, 42 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'registration_invitations' AS entry_id, 'reference_data' AS section_id, 43 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'role_assignment_rights' AS entry_id, 'reference_data' AS section_id, 44 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'role_hierarchy' AS entry_id, 'reference_data' AS section_id, 45 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'role_module_permissions' AS entry_id, 'reference_data' AS section_id, 46 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_ports' AS entry_id, 'reference_data' AS section_id, 47 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_port_numbering_layout' AS entry_id, 'reference_data' AS section_id, 48 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_port_types' AS entry_id, 'reference_data' AS section_id, 49 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ui_configuration' AS entry_id, 'reference_data' AS section_id, 50 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_companies' AS entry_id, 'reference_data' AS section_id, 51 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_roles' AS entry_id, 'reference_data' AS section_id, 52 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_sidebar_preferences' AS entry_id, 'reference_data' AS section_id, 53 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'vlans' AS entry_id, 'reference_data' AS section_id, 54 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'warranty_types' AS entry_id, 'reference_data' AS section_id, 55 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_device_types' AS entry_id, 'reference_data' AS section_id, 56 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_modes' AS entry_id, 'reference_data' AS section_id, 57 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_office' AS entry_id, 'reference_data' AS section_id, 58 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_os_types' AS entry_id, 'reference_data' AS section_id, 59 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_os_versions' AS entry_id, 'reference_data' AS section_id, 60 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_ram' AS entry_id, 'reference_data' AS section_id, 61 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'floor_plans' AS entry_id, 'reference_data' AS section_id, 62 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'rj45_speed' AS entry_id, 'reference_data' AS section_id, 63 AS display_order
) AS t
ORDER BY c.company_id, FIELD(t.entry_type, 'section', 'item'), t.display_order, t.entry_id;
-- Table structure for `supplier_statuses`
DROP TABLE IF EXISTS `supplier_statuses`;
CREATE TABLE `supplier_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `supplier_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Active', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', 'Active', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '11', 'Active', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '16', 'Active', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '21', 'Active', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Backup', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', 'Backup', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '12', 'Backup', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '17', 'Backup', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '22', 'Backup', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '13', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '18', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '23', 'Inactive', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Other', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '9', 'Other', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '14', 'Other', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '19', 'Other', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '24', 'Other', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Preferred', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', 'Preferred', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '15', 'Preferred', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '20', 'Preferred', '2026-01-01 00:00:01');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '25', 'Preferred', '2026-01-01 00:00:01');
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
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_suppliers_company_scope` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `supplier_statuses` (`id`)) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) VALUES ('1', '1', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) VALUES ('2', '2', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '6', '1', '2026-01-01 00:00:01');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) VALUES ('3', '3', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '11', '1', '2026-01-01 00:00:01');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) VALUES ('4', '4', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '16', '1', '2026-01-01 00:00:01');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) VALUES ('5', '5', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '21', '1', '2026-01-01 00:00:01');
-- Table structure for `license_management`
DROP TABLE IF EXISTS `license_management`;
CREATE TABLE `license_management` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_type_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `supplier_id` int DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_license_management_company_scope` (`company_id`, `name`),
  KEY `company_id` (`company_id`),
  KEY `license_type_id` (`license_type_id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `license_management_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `license_management_ibfk_license_type` FOREIGN KEY (`license_type_id`) REFERENCES `license_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `license_management_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Why: Relative expiry dates keep license alert seeds inside the default 30-day runner window after import.
INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('1', '1', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '1', '1', '1', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');
INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('2', '2', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '6', '1', '2', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');
INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('3', '3', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '11', '1', '3', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');
INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('4', '4', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '16', '1', '4', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');
INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('5', '5', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '21', '1', '5', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');
-- Table structure for `cable_colors`
DROP TABLE IF EXISTS `cable_colors`;
CREATE TABLE `cable_colors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `color_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grey',
  `hex_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `color_name` (`company_id`,`color_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `cable_colors_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '1', 'Gray', '#808080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '11', 'Gray', '#808080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '21', 'Gray', '#808080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '31', 'Gray', '#808080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '41', 'Gray', '#808080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '2', 'Green', '#03b003', 'Printers', '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '12', 'Green', '#03b003', 'Printers', '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '22', 'Green', '#03b003', 'Printers', '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '32', 'Green', '#03b003', 'Printers', '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '42', 'Green', '#03b003', 'Printers', '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '3', 'Red', '#ff0000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '13', 'Red', '#ff0000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '23', 'Red', '#ff0000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '33', 'Red', '#ff0000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '43', 'Red', '#ff0000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '4', 'Yellow', '#ffff00', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '14', 'Yellow', '#ffff00', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '24', 'Yellow', '#ffff00', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '34', 'Yellow', '#ffff00', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '44', 'Yellow', '#ffff00', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '5', 'Black', '#000000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '15', 'Black', '#000000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '25', 'Black', '#000000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '35', 'Black', '#000000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '45', 'Black', '#000000', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '6', 'Blue', '#0000ff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '16', 'Blue', '#0000ff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '26', 'Blue', '#0000ff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '36', 'Blue', '#0000ff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '46', 'Blue', '#0000ff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '7', 'White', '#ffffff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '17', 'White', '#ffffff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '27', 'White', '#ffffff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '37', 'White', '#ffffff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '47', 'White', '#ffffff', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '8', 'Orange', '#ffa500', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '18', 'Orange', '#ffa500', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '28', 'Orange', '#ffa500', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '38', 'Orange', '#ffa500', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '48', 'Orange', '#ffa500', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '9', 'Dark Pink', '#800080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '19', 'Dark Pink', '#800080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '29', 'Dark Pink', '#800080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '39', 'Dark Pink', '#800080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '49', 'Dark Pink', '#800080', NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '10', 'Other', NULL, NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('2', '20', 'Other', NULL, NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('3', '30', 'Other', NULL, NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('4', '40', 'Other', NULL, NULL, '2026-01-01 00:00:01');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('5', '50', 'Other', NULL, NULL, '2026-01-01 00:00:01');
-- Table structure for `switch_port_numbering_layout`
DROP TABLE IF EXISTS `switch_port_numbering_layout`;
CREATE TABLE `switch_port_numbering_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_port_numbering_layout_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Horizontal', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '3', 'Horizontal', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '5', 'Horizontal', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '7', 'Horizontal', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '9', 'Horizontal', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Vertical', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '4', 'Vertical', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '6', 'Vertical', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '8', 'Vertical', '2026-01-01 00:00:01');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '10', 'Vertical', '2026-01-01 00:00:01');
-- Table structure for `switch_port_types`
DROP TABLE IF EXISTS `switch_port_types`;
CREATE TABLE `switch_port_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`company_id`,`type`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_port_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '1', 'RJ45', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('2', '4', 'RJ45', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('3', '7', 'RJ45', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('4', '10', 'RJ45', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('5', '13', 'RJ45', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '2', 'SFP', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('2', '5', 'SFP', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('3', '8', 'SFP', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('4', '11', 'SFP', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('5', '14', 'SFP', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '3', 'Door', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('2', '6', 'Door', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('3', '9', 'Door', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('4', '12', 'Door', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('5', '15', 'Door', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '16', 'Access Point', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('2', '17', 'Access Point', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('3', '18', 'Access Point', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('4', '19', 'Access Point', '2026-01-01 00:00:01');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('5', '20', 'Access Point', '2026-01-01 00:00:01');

-- Table structure for `switch_ports`
DROP TABLE IF EXISTS `switch_ports`;
CREATE TABLE `switch_ports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RJ45',
  `port_number` int NOT NULL,
  `to_patch_port` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `color_id` int NOT NULL,
  `rj45_speed_id` int DEFAULT NULL,
  `vlan_id` int DEFAULT NULL,
  `fiber_port_id` int DEFAULT NULL,
  `fiber_patch_id` int DEFAULT NULL,
  `fiber_rack_id` int DEFAULT NULL,
  `idf_id` int DEFAULT NULL,
  `to_idf_id` int DEFAULT NULL,
  `to_rack_id` int DEFAULT NULL,
  `rack_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `to_location_id` int DEFAULT NULL,
  `management_id` int DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_switch_port` (`company_id`,`equipment_id`,`port_type`,`port_number`),
  KEY `company_id` (`company_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `idx_switch_ports_company_port_type` (`company_id`,`port_type`),
  KEY `status_id` (`status_id`),
  KEY `color_id` (`color_id`),
  KEY `idx_switch_ports_rj45_speed` (`rj45_speed_id`),
  KEY `vlan_id` (`vlan_id`),
  KEY `idx_switch_ports_fiber_port` (`fiber_port_id`),
  KEY `idx_switch_ports_fiber_patch` (`fiber_patch_id`),
  KEY `idx_switch_ports_fiber_rack` (`fiber_rack_id`),
  KEY `idx_switch_ports_idf` (`idf_id`),
  KEY `idx_switch_ports_to_idf` (`to_idf_id`),
  KEY `idx_switch_ports_to_rack` (`to_rack_id`),
  KEY `idx_switch_ports_rack` (`rack_id`),
  KEY `idx_switch_ports_location` (`location_id`),
  KEY `idx_switch_ports_to_location` (`to_location_id`),
  KEY `idx_switch_ports_management` (`management_id`),
  CONSTRAINT `switch_ports_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`),
  CONSTRAINT `switch_ports_ibfk_4` FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`),
  CONSTRAINT `switch_ports_ibfk_rj45_speed` FOREIGN KEY (`rj45_speed_id`) REFERENCES `rj45_speed` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_5` FOREIGN KEY (`company_id`,`port_type`) REFERENCES `switch_port_types` (`company_id`,`type`),
  CONSTRAINT `switch_ports_ibfk_6` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_7` FOREIGN KEY (`fiber_port_id`) REFERENCES `equipment_fiber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_8` FOREIGN KEY (`fiber_patch_id`) REFERENCES `equipment_fiber_patch` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_to_location` FOREIGN KEY (`to_location_id`) REFERENCES `it_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_9` FOREIGN KEY (`fiber_rack_id`) REFERENCES `equipment_fiber_rack` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_10` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_13` FOREIGN KEY (`to_idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_14` FOREIGN KEY (`to_rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_11` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_12` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_management` FOREIGN KEY (`management_id`) REFERENCES `equipment_environment` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `switch_status`
DROP TABLE IF EXISTS `switch_status`;
CREATE TABLE `switch_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  `color_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `status` (`company_id`,`status`),
  KEY `company_id` (`company_id`),
  KEY `color_id` (`color_id`),
  CONSTRAINT `switch_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_status_ibfk_color` FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '4', 'Disabled', '31', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '10', 'Disabled', '1', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '19', 'Disabled', '11', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '28', 'Disabled', '21', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '37', 'Disabled', '41', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '2', 'Down', '33', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '11', 'Down', '3', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '20', 'Down', '13', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '29', 'Down', '23', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '38', 'Down', '43', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '6', 'Err-Disabled', '39', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '12', 'Err-Disabled', '9', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '21', 'Err-Disabled', '19', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '30', 'Err-Disabled', '29', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '39', 'Err-Disabled', '49', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '8', 'Faulty', '38', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '13', 'Faulty', '8', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '22', 'Faulty', '18', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '31', 'Faulty', '28', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '40', 'Faulty', '48', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '3', 'Free', '32', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '14', 'Free', '2', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '23', 'Free', '12', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '32', 'Free', '22', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '41', 'Free', '42', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '9', 'Reserved', '34', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '15', 'Reserved', '4', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '24', 'Reserved', '14', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '33', 'Reserved', '24', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '42', 'Reserved', '44', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '7', 'Testing', '36', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '16', 'Testing', '6', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '25', 'Testing', '16', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '34', 'Testing', '26', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '43', 'Testing', '46', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '5', 'Unknown', '31', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '17', 'Unknown', '1', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '26', 'Unknown', '11', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '35', 'Unknown', '21', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '44', 'Unknown', '41', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('4', '1', 'Up', '36', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '18', 'Up', '6', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('2', '27', 'Up', '16', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('3', '36', 'Up', '26', '2026-01-01 00:00:01');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('5', '45', 'Up', '46', '2026-01-01 00:00:01');
-- Table structure for `system_access`
DROP TABLE IF EXISTS `system_access`;
CREATE TABLE `system_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_access_company_name` (`company_id`,`name`),
  KEY `idx_system_access_company` (`company_id`),
  CONSTRAINT `fk_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('1', '1', 'network_access', 'Network Access', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('16', '2', 'network_access', 'Network Access', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('31', '3', 'network_access', 'Network Access', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('46', '4', 'network_access', 'Network Access', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('61', '5', 'network_access', 'Network Access', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('2', '1', 'micros_emc', 'Micros Emc', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('17', '2', 'micros_emc', 'Micros Emc', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('32', '3', 'micros_emc', 'Micros Emc', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('47', '4', 'micros_emc', 'Micros Emc', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('62', '5', 'micros_emc', 'Micros Emc', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('3', '1', 'opera_username', 'Opera Username', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('18', '2', 'opera_username', 'Opera Username', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('33', '3', 'opera_username', 'Opera Username', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('48', '4', 'opera_username', 'Opera Username', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('63', '5', 'opera_username', 'Opera Username', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('4', '1', 'micros_card', 'Micros Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('19', '2', 'micros_card', 'Micros Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('34', '3', 'micros_card', 'Micros Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('49', '4', 'micros_card', 'Micros Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('64', '5', 'micros_card', 'Micros Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('5', '1', 'pms_id', 'PMS Id', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('20', '2', 'pms_id', 'PMS Id', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('35', '3', 'pms_id', 'PMS Id', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('50', '4', 'pms_id', 'PMS Id', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('65', '5', 'pms_id', 'PMS Id', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('6', '1', 'synergy_mms', 'Synergy Mms', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('21', '2', 'synergy_mms', 'Synergy Mms', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('36', '3', 'synergy_mms', 'Synergy Mms', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('51', '4', 'synergy_mms', 'Synergy Mms', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('66', '5', 'synergy_mms', 'Synergy Mms', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('7', '1', 'hu_the_lobby', 'HU The Lobby', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('22', '2', 'hu_the_lobby', 'HU The Lobby', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('37', '3', 'hu_the_lobby', 'HU The Lobby', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('52', '4', 'hu_the_lobby', 'HU The Lobby', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('67', '5', 'hu_the_lobby', 'HU The Lobby', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('8', '1', 'navision', 'Navision', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('23', '2', 'navision', 'Navision', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('38', '3', 'navision', 'Navision', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('53', '4', 'navision', 'Navision', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('68', '5', 'navision', 'Navision', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('9', '1', 'onq_ri', 'Onq Ri', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('24', '2', 'onq_ri', 'Onq Ri', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('39', '3', 'onq_ri', 'Onq Ri', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('54', '4', 'onq_ri', 'Onq Ri', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('69', '5', 'onq_ri', 'Onq Ri', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('10', '1', 'birchstreet', 'Birchstreet', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('25', '2', 'birchstreet', 'Birchstreet', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('40', '3', 'birchstreet', 'Birchstreet', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('55', '4', 'birchstreet', 'Birchstreet', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('70', '5', 'birchstreet', 'Birchstreet', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('11', '1', 'delphi', 'Delphi', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('26', '2', 'delphi', 'Delphi', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('41', '3', 'delphi', 'Delphi', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('56', '4', 'delphi', 'Delphi', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('71', '5', 'delphi', 'Delphi', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('12', '1', 'omina', 'Omina', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('27', '2', 'omina', 'Omina', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('42', '3', 'omina', 'Omina', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('57', '4', 'omina', 'Omina', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('72', '5', 'omina', 'Omina', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('13', '1', 'vingcard_system', 'Vingcard System', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('28', '2', 'vingcard_system', 'Vingcard System', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('43', '3', 'vingcard_system', 'Vingcard System', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('58', '4', 'vingcard_system', 'Vingcard System', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('73', '5', 'vingcard_system', 'Vingcard System', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('14', '1', 'digital_rev', 'Digital Rev', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('29', '2', 'digital_rev', 'Digital Rev', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('44', '3', 'digital_rev', 'Digital Rev', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('59', '4', 'digital_rev', 'Digital Rev', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('74', '5', 'digital_rev', 'Digital Rev', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('15', '1', 'office_key_card', 'Office Key Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('30', '2', 'office_key_card', 'Office Key Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('45', '3', 'office_key_card', 'Office Key Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('60', '4', 'office_key_card', 'Office Key Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('75', '5', 'office_key_card', 'Office Key Card', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('76', '1', 'email_account', 'Email Account', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('77', '2', 'email_account', 'Email Account', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('78', '3', 'email_account', 'Email Account', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('79', '4', 'email_account', 'Email Account', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('80', '5', 'email_account', 'Email Account', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('81', '1', 'landline_phone', 'Landline Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('82', '2', 'landline_phone', 'Landline Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('83', '3', 'landline_phone', 'Landline Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('84', '4', 'landline_phone', 'Landline Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('85', '5', 'landline_phone', 'Landline Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('86', '1', 'mobile_phone', 'Mobile Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('87', '2', 'mobile_phone', 'Mobile Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('88', '3', 'mobile_phone', 'Mobile Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('89', '4', 'mobile_phone', 'Mobile Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('90', '5', 'mobile_phone', 'Mobile Phone', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('91', '1', 'mobile_email', 'Mobile Email', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('92', '2', 'mobile_email', 'Mobile Email', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('93', '3', 'mobile_email', 'Mobile Email', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('94', '4', 'mobile_email', 'Mobile Email', '1', '2026-01-01 00:00:01');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('95', '5', 'mobile_email', 'Mobile Email', '1', '2026-01-01 00:00:01');
-- Table structure for `ticket_categories`
DROP TABLE IF EXISTS `ticket_categories`;
CREATE TABLE `ticket_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_categories_company_scope` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '1', 'Hardware Issue', 'HW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '6', 'Hardware Issue', 'HW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '11', 'Hardware Issue', 'HW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '16', 'Hardware Issue', 'HW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '21', 'Hardware Issue', 'HW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '2', 'Network Problem', 'NET', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '7', 'Network Problem', 'NET', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '12', 'Network Problem', 'NET', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '17', 'Network Problem', 'NET', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '22', 'Network Problem', 'NET', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '3', 'Software Issue', 'SW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '8', 'Software Issue', 'SW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '13', 'Software Issue', 'SW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '18', 'Software Issue', 'SW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '23', 'Software Issue', 'SW', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '4', 'Maintenance', 'MAINT', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '9', 'Maintenance', 'MAINT', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '14', 'Maintenance', 'MAINT', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '19', 'Maintenance', 'MAINT', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '24', 'Maintenance', 'MAINT', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '5', 'Other', 'OTHER', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('2', '10', 'Other', 'OTHER', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('3', '15', 'Other', 'OTHER', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('4', '20', 'Other', 'OTHER', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('5', '25', 'Other', 'OTHER', '1', '2026-01-01 00:00:01');
-- Table structure for `ticket_priorities`
DROP TABLE IF EXISTS `ticket_priorities`;
CREATE TABLE `ticket_priorities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_priorities_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '1', 'Low', '1', '#0000FF', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('2', '6', 'Low', '1', '#0000FF', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('3', '11', 'Low', '1', '#0000FF', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('4', '16', 'Low', '1', '#0000FF', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('5', '21', 'Low', '1', '#0000FF', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '2', 'Normal', '2', '#00FF00', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('2', '7', 'Normal', '2', '#00FF00', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('3', '12', 'Normal', '2', '#00FF00', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('4', '17', 'Normal', '2', '#00FF00', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('5', '22', 'Normal', '2', '#00FF00', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '3', 'High', '3', '#FFA500', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('2', '8', 'High', '3', '#FFA500', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('3', '13', 'High', '3', '#FFA500', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('4', '18', 'High', '3', '#FFA500', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('5', '23', 'High', '3', '#FFA500', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '4', 'Urgent', '4', '#FF0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('2', '9', 'Urgent', '4', '#FF0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('3', '14', 'Urgent', '4', '#FF0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('4', '19', 'Urgent', '4', '#FF0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('5', '24', 'Urgent', '4', '#FF0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '5', 'Critical', '5', '#8B0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('2', '10', 'Critical', '5', '#8B0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('3', '15', 'Critical', '5', '#8B0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('4', '20', 'Critical', '5', '#8B0000', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('5', '25', 'Critical', '5', '#8B0000', '1', '2026-01-01 00:00:01');
-- Table structure for `ticket_statuses`
DROP TABLE IF EXISTS `ticket_statuses`;
CREATE TABLE `ticket_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_closed` tinyint DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '5', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '9', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '13', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '17', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '2', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '6', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '10', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '14', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '18', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '4', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('2', '8', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('3', '12', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('4', '16', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('5', '20', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');
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
  `created_by_employee_id` int NOT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `tickets_photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_id_company` (`id`,`company_id`),
  KEY `category_id` (`category_id`),
  KEY `status_id` (`status_id`),
  KEY `priority_id` (`priority_id`),
  KEY `created_by_employee_id` (`created_by_employee_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses` (`id`),
  CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`),
  CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`created_by_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `tickets_ibfk_6` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `tickets_ibfk_7` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Historical note for existing DBs (do not run on fresh import — columns are in CREATE TABLE above):
-- ALTER TABLE `employees` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `raw_status_code`;
-- ALTER TABLE `equipment` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `photo_filename`;
-- ALTER TABLE `patches_updates` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `due_date`;
-- ALTER TABLE `tickets` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `tickets_photos`;
-- Data for `tickets`
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('1', '1', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '4', '1', '2', '1', '1', '1', NULL, '2026-01-01 00:00:01');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('2', '2', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '9', '5', '7', '1', '1', '2', NULL, '2026-01-01 00:00:01');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('3', '3', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '14', '9', '12', '1', '1', '3', NULL, '2026-01-01 00:00:01');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('4', '4', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '19', '13', '17', '1', '1', '4', NULL, '2026-01-01 00:00:01');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('5', '5', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '24', '17', '22', '1', '1', '5', NULL, '2026-01-01 00:00:01');
-- Table structure for `ui_configuration`
DROP TABLE IF EXISTS `ui_configuration`;
CREATE TABLE `ui_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `table_actions_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `new_button_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `export_buttons_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `back_save_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `enable_all_error_reporting` tinyint(1) NOT NULL DEFAULT '1',
  `enable_audit_logs` tinyint(1) NOT NULL DEFAULT '1',
  `enable_chatbot` tinyint(1) NOT NULL DEFAULT '1',
  `enable_auto_scaffolding` tinyint(1) NOT NULL DEFAULT '0',
  `records_per_page` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '25',
  `app_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '⚙️ IT Controls',
  `favicon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `equipment_type_sidebar_visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `module_icon_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `api_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `api_key_is_active` tinyint(1) NOT NULL DEFAULT '1',
  `api_key_last_used_at` timestamp NULL DEFAULT NULL,
  `rate_limit_window_start` int NOT NULL DEFAULT '0',
  `rate_limit_request_count` int NOT NULL DEFAULT '0',
  `rate_limit_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `tier` enum('Free','Basic','Pro','Enterprise') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Free',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ui_configuration_company_employee` (`company_id`,`employee_id`),
  CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ui_configuration_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `ui_configuration`
-- Why: Per-company UI defaults belong to that tenant's seed Admin employee (not employee_id=1 for every company).
INSERT INTO `ui_configuration` (`company_id`, `employee_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `enable_chatbot`, `enable_auto_scaffolding`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`)
SELECT e.`company_id`, e.`id`, 'left', 'left', 'left', 'left', 1, 1, 1, 0, '25', '⚙️ IT Controls', CONCAT('images/favicons/company_', e.`company_id`, '.ico'), '{"is_access_point":1, "is_cctv":1, "is_firewall":1, "is_other":1, "is_phone":1, "is_port_patch_panel":1, "is_printer":1, "is_router":1, "is_server":1, "is_switch":1, "is_workstation":1}', '2026-01-01 00:00:01', NULL
FROM `employees` e
WHERE e.`work_email` LIKE 'admin@techcorp.example%.com';
-- Table structure for `employee_roles`
DROP TABLE IF EXISTS `employee_roles`;
CREATE TABLE `employee_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_roles_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'Admin', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'IT Manager', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'IT Assistant', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'Helpdesk', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'User', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (2, 'Admin', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (2, 'IT Manager', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (2, 'IT Assistant', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (2, 'Helpdesk', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (2, 'User', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (3, 'Admin', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (3, 'IT Manager', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (3, 'IT Assistant', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (3, 'Helpdesk', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (3, 'User', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (4, 'Admin', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (4, 'IT Manager', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (4, 'IT Assistant', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (4, 'Helpdesk', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (4, 'User', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (5, 'Admin', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (5, 'IT Manager', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (5, 'IT Assistant', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (5, 'Helpdesk', '2026-01-01 00:00:01');
INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (5, 'User', '2026-01-01 00:00:01');

-- Why: Seed admins insert before employee_roles; bind tenant-correct Admin role_id by name.
UPDATE `employees` e
INNER JOIN `employee_roles` er ON er.`company_id` = e.`company_id` AND er.`name` = 'Admin'
SET e.`role_id` = er.`id`
WHERE e.`work_email` LIKE 'admin@techcorp.example%.com';

-- Table structure for `registration_invitations`
DROP TABLE IF EXISTS `registration_invitations`;
CREATE TABLE `registration_invitations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_code` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invited_by_employee_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `access_level_id` int DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registration_invitations_company_scope` (`company_id`,`email`),
  KEY `idx_registration_invitations_code` (`invitation_code`),
  KEY `idx_registration_invitations_active_expires_at` (`active`,`expires_at`),
  KEY `idx_registration_invitations_invited_by` (`invited_by_employee_id`),
  KEY `idx_registration_invitations_role` (`role_id`),
  KEY `idx_registration_invitations_access_level` (`access_level_id`),
  CONSTRAINT `fk_registration_invitations_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registration_invitations_invited_by` FOREIGN KEY (`invited_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_invitations_role` FOREIGN KEY (`role_id`) REFERENCES `employee_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_invitations_access_level` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Data for `registration_invitations`
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_employee_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`)
SELECT seed.`id`, seed.`company_id`, seed.`email`, seed.`invitation_code`, inviter.`id`, er.`id`, al.`id`, NULL, NULL, 1, '2026-01-01 00:00:01'
FROM (
  SELECT 1 AS `id`, 1 AS `company_id`, 'new.user@techcorp.example' AS `email`, 'INVITE-TECHCORP-001' AS `invitation_code`, 'Admin' AS `inviter_username`, 'Admin' AS `role_name`, 'Full' AS `access_name`
  UNION ALL SELECT 2, 2, 'new.user@datacenterplus.example', 'INVITE-DATACENTERPLUS-001', 'Admin2', 'Admin', 'Full'
  UNION ALL SELECT 3, 3, 'new.user@networksolutions.example', 'INVITE-NETWORKSOLUTIONS-001', 'Admin3', 'Admin', 'Full'
  UNION ALL SELECT 4, 4, 'new.user@cloudtech.example', 'INVITE-CLOUDTECH-001', 'Admin4', 'Admin', 'Full'
  UNION ALL SELECT 5, 5, 'new.user@enterpriseit.example', 'INVITE-ENTERPRISEIT-001', 'Admin5', 'Admin', 'Full'
) seed
LEFT JOIN `employees` inviter
  ON inviter.`company_id` = seed.`company_id`
 AND inviter.`username` = seed.`inviter_username`
LEFT JOIN `employee_roles` er
  ON er.`company_id` = seed.`company_id`
 AND er.`name` = seed.`role_name`
LEFT JOIN `access_levels` al
  ON al.`company_id` = seed.`company_id`
 AND al.`name` = seed.`access_name`
ORDER BY seed.`id`;
-- Table structure for `attempts`
-- Why: Unified security telemetry table for login and password reset events (legacy module folders were merged into modules/attempts).
DROP TABLE IF EXISTS `attempts`;
CREATE TABLE `attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dect` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `attempt_source` enum('login','password_reset') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_type` enum('success','failure','request','reset') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `idx_attempts_source_type_ip_time` (`attempt_source`,`attempt_type`,`ip_address`,`created_at`),
  KEY `idx_attempts_source_type_email_time` (`attempt_source`,`attempt_type`,`email`,`created_at`),
  KEY `idx_attempts_source_type_user_time` (`attempt_source`,`attempt_type`,`employee_id`,`created_at`),
  CONSTRAINT `fk_attempts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attempts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'login', 'success', '192.168.1.10', '2026-01-01 08:00:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'unknown@example.com', '0', 'login', 'failure', '10.0.0.55', '2026-01-01 08:05:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '0', 'login', 'failure', '192.168.1.10', '2026-01-01 08:06:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'admin@techcorp.example', '1', 'password_reset', 'request', '192.168.1.20', '2026-01-02 09:00:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'password_reset', 'reset', '192.168.1.20', '2026-01-02 09:15:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'password_reset', 'success', '192.168.1.20', '2026-01-02 09:16:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'wrong@example.com', '0', 'password_reset', 'failure', '203.0.113.8', '2026-01-03 10:00:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'login', 'success', '127.0.0.1', '2026-01-03 11:00:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'guest@example.com', '0', 'login', 'failure', '172.16.0.4', '2026-01-04 14:30:01');
INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'login', 'success', '192.168.1.50', '2026-01-05 07:45:01');
UPDATE `attempts` SET `company_id` = COALESCE(
  (SELECT `company_id` FROM `employees` WHERE `id` = `employee_id` LIMIT 1),
  (SELECT `company_id` FROM `employees` WHERE `work_email` = `email` LIMIT 1),
  (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)
) WHERE `company_id` IS NULL;
-- Table structure for `employee_companies`
DROP TABLE IF EXISTS `employee_companies`;
CREATE TABLE `employee_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `company_id` int NOT NULL,
  `granted_by_employee_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_companies_company_scope` (`company_id`,`employee_id`),
  KEY `idx_employee_companies_company` (`company_id`),
  KEY `idx_employee_companies_granted_by` (`granted_by_employee_id`),
  CONSTRAINT `fk_employee_companies_user` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_companies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_companies_granted_by` FOREIGN KEY (`granted_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Why: Each seed admin gets home-company access; TechCorp Admin (company 1) also gets companies 2–5 for tenant switcher / MBQA.
INSERT INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `active`, `created_at`)
SELECT e.`id`, e.`company_id`, NULL, 1, '2026-01-01 00:00:01'
FROM `employees` e
WHERE e.`work_email` LIKE 'admin@techcorp.example%.com';
INSERT INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `active`, `created_at`)
SELECT e.`id`, c.`id`, NULL, 1, '2026-01-01 00:00:01'
FROM `employees` e
CROSS JOIN `companies` c
WHERE e.`company_id` = 1
  AND e.`username` = 'Admin'
  AND c.`id` BETWEEN 2 AND 5;

-- Table structure for `role_hierarchy`
DROP TABLE IF EXISTS `role_hierarchy`;
CREATE TABLE `role_hierarchy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `hierarchy_order` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_hierarchy_company_scope` (`company_id`,`role_id`),
  CONSTRAINT `fk_role_hierarchy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_hierarchy_role` FOREIGN KEY (`role_id`) REFERENCES `employee_roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Why: Hierarchy uses role name lookups (no hardcoded role ids).
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`, `created_at`)
SELECT er.`company_id`, er.`id`, ord.`hierarchy_order`, '2026-01-01 00:00:01'
FROM (
  SELECT 'Admin' AS `name`, 1 AS `hierarchy_order`
  UNION ALL SELECT 'IT Manager', 2
  UNION ALL SELECT 'IT Assistant', 3
  UNION ALL SELECT 'Helpdesk', 4
  UNION ALL SELECT 'User', 5
) ord
INNER JOIN `employee_roles` er ON er.`name` = ord.`name`;

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
  `can_import` tinyint(1) NOT NULL DEFAULT '0',
  `can_export` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module_permissions` (`company_id`,`role_id`,`module_name`),
  CONSTRAINT `fk_role_module_permissions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_module_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `employee_roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Why: Permission seeds resolve role_id by tenant + role name (no hardcoded ids).
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`)
SELECT er.`company_id`, er.`id`, 'ALL', 1, 1, 1, 1, 1, 1, '2026-01-01 00:00:01'
FROM `employee_roles` er
WHERE er.`name` = 'Admin';
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`)
SELECT er.`company_id`, er.`id`, 'Tickets', 1, 1, 1, 1, 1, 1, '2026-01-01 00:00:01'
FROM `employee_roles` er
WHERE er.`name` IN ('Helpdesk', 'User');

-- Table structure for `role_assignment_rights`
DROP TABLE IF EXISTS `role_assignment_rights`;
CREATE TABLE `role_assignment_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `can_assign_role_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_assignment_rights` (`company_id`,`role_id`,`can_assign_role_id`),
  CONSTRAINT `fk_role_assignment_rights_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_role` FOREIGN KEY (`role_id`) REFERENCES `employee_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_target_role` FOREIGN KEY (`can_assign_role_id`) REFERENCES `employee_roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Why: Assignment rights resolve both sides by role name within the same company_id.
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`, `created_at`)
SELECT src.`company_id`, src.`id`, tgt.`id`, '2026-01-01 00:00:01'
FROM `employee_roles` src
INNER JOIN `employee_roles` tgt ON tgt.`company_id` = src.`company_id`
INNER JOIN (
  SELECT 'Admin' AS `role_name`, 'IT Manager' AS `target_name`
  UNION ALL SELECT 'Admin', 'IT Assistant'
  UNION ALL SELECT 'Admin', 'Helpdesk'
  UNION ALL SELECT 'Admin', 'User'
  UNION ALL SELECT 'IT Manager', 'IT Assistant'
  UNION ALL SELECT 'IT Manager', 'Helpdesk'
  UNION ALL SELECT 'IT Manager', 'User'
  UNION ALL SELECT 'IT Assistant', 'Helpdesk'
  UNION ALL SELECT 'IT Assistant', 'User'
) map ON map.`role_name` = src.`name` AND map.`target_name` = tgt.`name`;

-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `actor_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_company` (`company_id`),
  KEY `idx_audit_logs_user` (`employee_id`),
  KEY `idx_audit_logs_actor_username` (`actor_username`),
  KEY `idx_audit_logs_actor_email` (`actor_email`),
  KEY `idx_audit_logs_module_name` (`module_name`),
  KEY `idx_audit_logs_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_updated_at` (`updated_at`),
  KEY `idx_audit_logs_company_updated` (`company_id`,`updated_at`),
  CONSTRAINT `audit_logs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_logs_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vlans_company_scope` (`company_id`,`vlan_number`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `vlans_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) VALUES ('1', '1', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-01-01 00:00:01');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) VALUES ('2', '2', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-01-01 00:00:01');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) VALUES ('3', '3', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-01-01 00:00:01');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) VALUES ('4', '4', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-01-01 00:00:01');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) VALUES ('5', '5', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-01-01 00:00:01');
-- Table structure for `ip_subnets`
DROP TABLE IF EXISTS `ip_addresses`;
DROP TABLE IF EXISTS `ip_subnets`;
CREATE TABLE `ip_subnets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `vlan_id` int DEFAULT NULL,
  `cidr` varchar(43) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `network_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix_length` tinyint unsigned NOT NULL,
  `gateway_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dns1_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dns2_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dhcp_enabled` tinyint DEFAULT '0',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_subnets_company_scope` (`company_id`,`vlan_id`,`cidr`),
  KEY `vlan_id` (`vlan_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ip_subnets_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ip_subnets_ibfk_vlan` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `ip_addresses`
CREATE TABLE `ip_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `subnet_id` int NOT NULL,
  `ip_text` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('free','used','reserved','gateway','dns','dhcp','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `equipment_id` int DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_gateway` tinyint DEFAULT '0',
  `is_dns` tinyint DEFAULT '0',
  `dhcp_managed` tinyint DEFAULT '0',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_addresses_company_scope` (`company_id`,`subnet_id`,`ip_text`),
  KEY `equipment_id` (`equipment_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ip_addresses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ip_addresses_ibfk_subnet` FOREIGN KEY (`subnet_id`) REFERENCES `ip_subnets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ip_addresses_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `ip_subnets` (`company_id`, `vlan_id`, `cidr`, `network_ip`, `prefix_length`, `gateway_ip`, `dns1_ip`, `dns2_ip`, `dhcp_enabled`, `description`, `active`, `created_at`) VALUES ('1', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('2', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('3', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('4', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('5', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01');
-- Table structure for `warranty_types`
DROP TABLE IF EXISTS `warranty_types`;
CREATE TABLE `warranty_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `warranty_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Enterprise', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', 'Enterprise', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '13', 'Enterprise', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '19', 'Enterprise', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '25', 'Enterprise', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Extended', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', 'Extended', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '14', 'Extended', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '20', 'Extended', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '26', 'Extended', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'None', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '9', 'None', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '15', 'None', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '21', 'None', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '27', 'None', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Other', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', 'Other', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '16', 'Other', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '22', 'Other', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '28', 'Other', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Premium', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '11', 'Premium', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '17', 'Premium', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '23', 'Premium', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '29', 'Premium', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Standard', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '12', 'Standard', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '18', 'Standard', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '24', 'Standard', '2026-01-01 00:00:01');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '30', 'Standard', '2026-01-01 00:00:01');
-- Table structure for `workstation_device_types`
DROP TABLE IF EXISTS `workstation_device_types`;
CREATE TABLE `workstation_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '9', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '17', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '25', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '33', 'All-in-One', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Desktop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', 'Desktop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '18', 'Desktop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '26', 'Desktop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '34', 'Desktop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Laptop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '11', 'Laptop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '19', 'Laptop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '27', 'Laptop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '35', 'Laptop', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Mobile', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '12', 'Mobile', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '20', 'Mobile', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '28', 'Mobile', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '36', 'Mobile', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '13', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '21', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '29', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '37', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'POS', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '14', 'POS', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '22', 'POS', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '30', 'POS', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '38', 'POS', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Tablet', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '15', 'Tablet', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '23', 'Tablet', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '31', 'Tablet', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '39', 'Tablet', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Thin-Client', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '16', 'Thin-Client', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '24', 'Thin-Client', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '32', 'Thin-Client', '2026-01-01 00:00:01');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '40', 'Thin-Client', '2026-01-01 00:00:01');
-- Table structure for `workstation_modes`
DROP TABLE IF EXISTS `workstation_modes`;
CREATE TABLE `workstation_modes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `mode_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `monitor_count` int DEFAULT '0',
  `has_keyboard_mouse` tinyint(1) DEFAULT '1',
  `pos` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mode_name` (`company_id`,`mode_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_modes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '1', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '12', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '23', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '34', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '45', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '2', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '13', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '24', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '35', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '46', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '3', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '14', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '25', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '36', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '47', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '4', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '15', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '26', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '37', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '48', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '5', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '16', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '27', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '38', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '49', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '6', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '17', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '28', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '39', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '50', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '7', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '18', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '29', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '40', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '51', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '8', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '19', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '30', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '41', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '52', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '9', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '20', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '31', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '42', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '53', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '10', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '21', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '32', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '43', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '54', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '11', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('2', '22', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('3', '33', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('4', '44', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-01-01 00:00:01');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('5', '55', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-01-01 00:00:01');
-- Table structure for `workstation_office`
DROP TABLE IF EXISTS `workstation_office`;
CREATE TABLE `workstation_office` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_office_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'None', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '5', 'None', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '9', 'None', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '13', 'None', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '17', 'None', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Office 2024 Pro', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', 'Office 2024 Pro', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '10', 'Office 2024 Pro', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '14', 'Office 2024 Pro', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '18', 'Office 2024 Pro', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Office 2024 STD', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', 'Office 2024 STD', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '11', 'Office 2024 STD', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '15', 'Office 2024 STD', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '19', 'Office 2024 STD', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Office 365', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', 'Office 365', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '12', 'Office 365', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '16', 'Office 365', '2026-01-01 00:00:01');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '20', 'Office 365', '2026-01-01 00:00:01');
-- Table structure for `workstation_os_types`
DROP TABLE IF EXISTS `workstation_os_types`;
CREATE TABLE `workstation_os_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_os_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Windows', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '16', 'Windows', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '31', 'Windows', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '46', 'Windows', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '61', 'Windows', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Windows 11', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '17', 'Windows 11', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '32', 'Windows 11', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '47', 'Windows 11', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '62', 'Windows 11', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Windows 10', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '18', 'Windows 10', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '33', 'Windows 10', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '48', 'Windows 10', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '63', 'Windows 10', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Windows Server', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '19', 'Windows Server', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '34', 'Windows Server', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '49', 'Windows Server', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '64', 'Windows Server', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Windows Server 2012', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '20', 'Windows Server 2012', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '35', 'Windows Server 2012', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '50', 'Windows Server 2012', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '65', 'Windows Server 2012', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Windows Server 2016', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '21', 'Windows Server 2016', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '36', 'Windows Server 2016', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '51', 'Windows Server 2016', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '66', 'Windows Server 2016', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'Windows Server 2019', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '22', 'Windows Server 2019', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '37', 'Windows Server 2019', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '52', 'Windows Server 2019', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '67', 'Windows Server 2019', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Windows Server 2022', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '23', 'Windows Server 2022', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '38', 'Windows Server 2022', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '53', 'Windows Server 2022', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '68', 'Windows Server 2022', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '9', 'Windows Server 2025', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '24', 'Windows Server 2025', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '39', 'Windows Server 2025', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '54', 'Windows Server 2025', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '69', 'Windows Server 2025', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '10', 'Android', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '25', 'Android', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '40', 'Android', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '55', 'Android', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '70', 'Android', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '11', 'iOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '26', 'iOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '41', 'iOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '56', 'iOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '71', 'iOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '12', 'ChromeOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '27', 'ChromeOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '42', 'ChromeOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '57', 'ChromeOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '72', 'ChromeOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '13', 'Linux', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '28', 'Linux', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '43', 'Linux', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '58', 'Linux', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '73', 'Linux', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '14', 'macOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '29', 'macOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '44', 'macOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '59', 'macOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '74', 'macOS', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '15', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '30', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '45', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '60', 'Other', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '75', 'Other', '2026-01-01 00:00:01');
-- Table structure for `workstation_os_versions`
DROP TABLE IF EXISTS `workstation_os_versions`;
CREATE TABLE `workstation_os_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_os_versions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '24H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '5', '24H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '9', '24H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '13', '24H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '17', '24H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '25H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '6', '25H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '10', '25H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '14', '25H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '18', '25H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '26H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', '26H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '11', '26H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '15', '26H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '19', '26H2', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '10 LTSC', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', '10 LTSC', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '12', '10 LTSC', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '16', '10 LTSC', '2026-01-01 00:00:01');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '20', '10 LTSC', '2026-01-01 00:00:01');
-- Table structure for `workstation_ram`
DROP TABLE IF EXISTS `workstation_ram`;
CREATE TABLE `workstation_ram` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_ram_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '4 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '7', '4 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '13', '4 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '19', '4 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '25', '4 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '8 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '8', '8 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '14', '8 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '20', '8 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '26', '8 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '16 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '9', '16 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '15', '16 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '21', '16 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '27', '16 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '32 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '10', '32 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '16', '32 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '22', '32 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '28', '32 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', '64 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '11', '64 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '17', '64 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '23', '64 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '29', '64 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', '128 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('2', '12', '128 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('3', '18', '128 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('4', '24', '128 GB', '2026-01-01 00:00:01');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('5', '30', '128 GB', '2026-01-01 00:00:01');
-- Replicate shared table data to all companies
SET @replicate_source_company_id := COALESCE(@replicate_source_company_id, 1);
INSERT IGNORE INTO `access_levels` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `access_levels` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `assignment_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `assignment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `budget_categories` (`company_id`, `name`, `description`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`description`, t.`active`, '2026-01-01 00:00:01' FROM `budget_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `floor_plan_folders` (`company_id`, `parent_folder_id`, `name`, `active`, `created_at`)
SELECT c.`id`, NULL, t.`name`, t.`active`, '2026-01-01 00:00:01'
FROM `floor_plan_folders` t
JOIN `companies` c ON c.`id` <> t.`company_id`
WHERE t.`company_id` = @replicate_source_company_id AND t.`parent_folder_id` IS NULL;
INSERT IGNORE INTO `floor_plan_tags` (`company_id`, `name`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`active`, '2026-01-01 00:00:01' FROM `floor_plan_tags` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `gl_accounts` (`company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`)
SELECT
    c.`id`,
    ga.`account_code`,
    ga.`account_name`,
    target_bc.`id`,
    ga.`active`,
    '2026-01-01 00:00:01'
FROM `gl_accounts` ga
JOIN `companies` c ON c.`id` <> ga.`company_id`
LEFT JOIN `budget_categories` source_bc ON source_bc.`id` = ga.`category_id`
LEFT JOIN `budget_categories` target_bc ON target_bc.`company_id` = c.`id` AND target_bc.`name` = source_bc.`name`
WHERE ga.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `employee_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `employee_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `employee_positions` (`company_id`, `department_id`, `name`, `description`, `active`, `created_at`)
SELECT
    c.`id`,
    d_target.`id`,
    t.`name`,
    t.`description`,
    t.`active`,
    '2026-01-01 00:00:01'
FROM `employee_positions` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `departments` d_source ON d_source.`id` = t.`department_id`
LEFT JOIN `departments` d_target ON d_target.`company_id` = c.`id` AND d_target.`name` = d_source.`name`
WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_environment` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_environment` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber_patch` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber_patch` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber_rack` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber_rack` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber_count` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber_count` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_poe` (`company_id`, `name`, `watts`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`watts`, t.`active`, '2026-01-01 00:00:01' FROM `equipment_poe` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_rj45` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_rj45` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `rj45_speed` (`company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) SELECT c.`id`, t.`cable_type`, t.`max_speed`, t.`bandwidth`, t.`max_distance_full_speed`, t.`notes`, t.`active`, '2026-01-01 00:00:01' FROM `rj45_speed` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_types` (`company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`field_edit_emoji`, t.`active`, '2026-01-01 00:00:01' FROM `equipment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `inventory_categories` (`company_id`, `name`, `code`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`active`, '2026-01-01 00:00:01' FROM `inventory_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `location_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `location_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `manufacturers` (`company_id`, `name`, `code`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`active`, '2026-01-01 00:00:01' FROM `manufacturers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `forecast_revisions_status` (`company_id`, `status`, `active`, `created_at`) SELECT c.`id`, t.`status`, t.`active`, '2026-01-01 00:00:01' FROM `forecast_revisions_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `approvals_stage` (`company_id`, `stage`, `active`, `created_at`) SELECT c.`id`, t.`stage`, t.`active`, '2026-01-01 00:00:01' FROM `approvals_stage` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
-- Why: catalogs are seeded per tenant in the INSERT block above (with tenant FK ids). Replicating company-1 rows here duplicated models and kept company-1 equipment_type_id/manufacturer_id/supplier_id values.
INSERT IGNORE INTO `printer_device_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `printer_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `rack_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `rack_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `supplier_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `supplier_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `cable_colors` (`company_id`, `color_name`, `hex_color`, `comments`, `created_at`) SELECT c.`id`, t.`color_name`, t.`hex_color`, t.`comments`, '2026-01-01 00:00:01' FROM `cable_colors` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_port_numbering_layout` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `switch_port_numbering_layout` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_port_types` (`company_id`, `type`, `created_at`) SELECT c.`id`, t.`type`, '2026-01-01 00:00:01' FROM `switch_port_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_status` (`company_id`, `status`, `created_at`) SELECT c.`id`, t.`status`, '2026-01-01 00:00:01' FROM `switch_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `ticket_categories` (`company_id`, `name`, `code`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`active`, '2026-01-01 00:00:01' FROM `ticket_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `ticket_priorities` (`company_id`, `name`, `level`, `color`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`level`, t.`color`, t.`active`, '2026-01-01 00:00:01' FROM `ticket_priorities` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `ticket_statuses` (`company_id`, `name`, `color`, `is_closed`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`color`, t.`is_closed`, t.`active`, '2026-01-01 00:00:01' FROM `ticket_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `employee_roles` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `employee_roles` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `warranty_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `warranty_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `license_types` (`company_id`, `name`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`active`, '2026-01-01 00:00:01' FROM `license_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `idf_device_type` (`company_id`, `idfdevicetype_name`, `created_at`) SELECT c.`id`, t.`idfdevicetype_name`, '2026-01-01 00:00:01' FROM `idf_device_type` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `patches_updates_status` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `patches_updates_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `patches_updates_level` (`company_id`, `level`, `created_at`) SELECT c.`id`, t.`level`, '2026-01-01 00:00:01' FROM `patches_updates_level` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_device_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_modes` (`company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) SELECT c.`id`, t.`mode_name`, t.`mode_code`, t.`description`, t.`monitor_count`, t.`has_keyboard_mouse`, t.`pos`, t.`active`, '2026-01-01 00:00:01' FROM `workstation_modes` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_office` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_office` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_os_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_os_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_os_versions` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_os_versions` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_ram` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_ram` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `departments` (`company_id`, `name`, `code`, `description`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`description`, t.`active`, '2026-01-01 00:00:01' FROM `departments` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT INTO `employee_onboarding_requests` (`company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `created_at`)
SELECT c.`id`, t.`employee_id`, ep_target.`id`, t.`first_name`, t.`last_name`, t.`department_name`, t.`request_date`, t.`termination_date`, t.`network_access`, t.`micros_emc`, t.`opera`, t.`micros_card`, t.`pms_id`, t.`synergy_mms`, t.`email_account`, t.`landline_phone`, t.`hu_the_lobby`, t.`mobile_phone`, t.`navision`, t.`mobile_email`, t.`onq_ri`, t.`birchstreet`, t.`delphi`, t.`omina`, t.`vingcard_system`, t.`digital_rev`, t.`office_key_card`, t.`office_key_card_dep`, t.`comments`, t.`starting_date`, t.`requested_by`, t.`requested_by_date`, t.`requested_on`, t.`hod_approval`, t.`hod_approval_date`, t.`hrd_approval`, t.`hrd_approval_date`, t.`ism_approval`, t.`ism_approval_date`, t.`gm_approval`, t.`gm_approval_date`, t.`fin_approval`, t.`fin_approval_date`, COALESCE(NULLIF(t.`status_hod`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_hrd`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_ism`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_gm`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_fin`, ''), 'Waiting'), COALESCE(t.`email_sent_hod`, 0), t.`email_sent_hod_at`, COALESCE(t.`email_sent_hrd`, 0), t.`email_sent_hrd_at`, COALESCE(t.`email_sent_ism`, 0), t.`email_sent_ism_at`, COALESCE(t.`email_sent_gm`, 0), t.`email_sent_gm_at`, COALESCE(t.`email_sent_fin`, 0), t.`email_sent_fin_at`, '2026-01-01 00:00:01'
FROM `employee_onboarding_requests` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `employee_positions` ep_source ON ep_source.`id` = t.`employee_position_id`
LEFT JOIN `employee_positions` ep_target ON ep_target.`company_id` = c.`id` AND ep_target.`name` = ep_source.`name`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
      SELECT 1
      FROM `employee_onboarding_requests` e
      WHERE e.`company_id` = c.`id`
        AND COALESCE(e.`employee_id`, 0) = COALESCE(t.`employee_id`, 0)
        AND COALESCE(e.`first_name`, '') = COALESCE(t.`first_name`, '')
        AND COALESCE(e.`last_name`, '') = COALESCE(t.`last_name`, '')
        AND COALESCE(e.`starting_date`, '1000-01-01') = COALESCE(t.`starting_date`, '1000-01-01')
        AND COALESCE(e.`request_date`, '1000-01-01') = COALESCE(t.`request_date`, '1000-01-01')
  );
-- Why: department_id and supplier_id resolve by name on the target company; unmatched or NULL source rows stay NULL (same FK remap pattern as location/rack). assigned_to_employee_id stays NULL (no employee remap).
INSERT IGNORE INTO `equipment` (`company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_office_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `rj45_speed_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`)
SELECT
    c.`id`,
    COALESCE(et_target.`id`, et_fallback.`id`),
    m_target.`id`,
    l_target.`id`,
    r_target.`id`,
    t.`name`, t.`serial_number`, t.`model`, t.`hostname`, t.`ip_address`, t.`patch_port`, t.`mac_address`,
    dept_target.`id`,
    supp_target.`id`,
    NULL,
    COALESCE(es_target.`id`, es_fallback.`id`),
    t.`purchase_date`, t.`purchase_cost`, t.`warranty_expiry`, t.`certificate_expiry`,
    wt_target.`id`,
    pdt_target.`id`,
    t.`printer_color_capable`,
    t.`printer_scan`,
    wdt_target.`id`,
    wot_target.`id`,
    wo_target.`id`,
    t.`workstation_processor`, t.`workstation_storage`, t.`workstation_os_installed_on`,
    wr_target.`id`,
    wov_target.`id`,
    rj45_speed_target.`id`,
    rj45_target.`id`,
    spnl_target.`id`,
    fiber_target.`id`,
    fiber_patch_target.`id`,
    fiber_rack_target.`id`,
    t.`switch_fiber_ports_number`,
    t.`switch_fiber_port_label`,
    poe_target.`id`,
    env_target.`id`,
    t.`notes`, t.`photo_filename`, '2026-01-01 00:00:01', t.`updated_at`
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
LEFT JOIN `departments` dept_source ON dept_source.`id` = t.`department_id`
LEFT JOIN `departments` dept_target ON dept_target.`company_id` = c.`id` AND dept_target.`name` = dept_source.`name`
LEFT JOIN `suppliers` supp_source ON supp_source.`id` = t.`supplier_id`
LEFT JOIN `suppliers` supp_target ON supp_target.`company_id` = c.`id` AND supp_target.`name` = supp_source.`name`
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
LEFT JOIN `workstation_office` wo_source ON wo_source.`id` = t.`workstation_office_id`
LEFT JOIN `workstation_office` wo_target ON wo_target.`company_id` = c.`id` AND wo_target.`name` = wo_source.`name`
LEFT JOIN `workstation_ram` wr_source ON wr_source.`id` = t.`workstation_ram_id`
LEFT JOIN `workstation_ram` wr_target ON wr_target.`company_id` = c.`id` AND wr_target.`name` = wr_source.`name`
LEFT JOIN `workstation_os_versions` wov_source ON wov_source.`id` = t.`workstation_os_version_id`
LEFT JOIN `workstation_os_versions` wov_target ON wov_target.`company_id` = c.`id` AND wov_target.`name` = wov_source.`name`
LEFT JOIN `rj45_speed` rj45_speed_source ON rj45_speed_source.`id` = t.`rj45_speed_id`
LEFT JOIN `rj45_speed` rj45_speed_target ON rj45_speed_target.`company_id` = c.`id` AND rj45_speed_target.`cable_type` = rj45_speed_source.`cable_type`
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
LEFT JOIN `equipment_poe` poe_source ON poe_source.`id` = t.`switch_poe_id`
LEFT JOIN `equipment_poe` poe_target ON poe_target.`company_id` = c.`id` AND poe_target.`name` = poe_source.`name`
LEFT JOIN `equipment_environment` env_source ON env_source.`id` = t.`switch_environment_id`
LEFT JOIN `equipment_environment` env_target ON env_target.`company_id` = c.`id` AND env_target.`name` = env_source.`name`
WHERE t.`company_id` = @replicate_source_company_id
  AND COALESCE(et_target.`id`, et_fallback.`id`) IS NOT NULL
  AND COALESCE(es_target.`id`, es_fallback.`id`) IS NOT NULL;
INSERT IGNORE INTO `idf_ports` (`company_id`, `position_id`, `port_no`, `port_type`, `label`, `status_id`, `connected_to`, `vlan_id`, `speed_id`, `rj45_speed_id`, `poe_id`, `cable_color`, `hex_color`, `notes`, `created_at`, `updated_at`) SELECT c.`id`, t.`position_id`, t.`port_no`, t.`port_type`, t.`label`, t.`status_id`, t.`connected_to`, t.`vlan_id`, t.`speed_id`, t.`rj45_speed_id`, t.`poe_id`, t.`cable_color`, t.`hex_color`, t.`notes`, '2026-01-01 00:00:01', t.`updated_at` FROM `idf_ports` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT INTO `idf_device_type` (`company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, t.`idfdevicetype_name`, t.`field_edit_emoji`, t.`active`, '2026-01-01 00:00:01', t.`updated_at`
FROM `idf_device_type` t
JOIN `companies` c ON c.`id` <> t.`company_id`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
    SELECT 1
    FROM `idf_device_type` t_existing
    WHERE t_existing.`company_id` = c.`id`
      AND t_existing.`idfdevicetype_name` = t.`idfdevicetype_name`
  );
INSERT INTO `idf_positions` (`company_id`, `idf_id`, `position_no`, `device_type`, `device_name`, `equipment_id`, `rj45_count`, `sfp_count`, `price`, `notes`, `created_at`, `updated_at`)
SELECT c.`id`, t.`idf_id`, t.`position_no`, dt_target.`id`, t.`device_name`, t.`equipment_id`, t.`rj45_count`, t.`sfp_count`, t.`price`, t.`notes`, '2026-01-01 00:00:01', t.`updated_at`
FROM `idf_positions` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `idf_device_type` dt_source ON dt_source.`id` = t.`device_type`
LEFT JOIN `idf_device_type` dt_target ON dt_target.`company_id` = c.`id` AND dt_target.`idfdevicetype_name` = dt_source.`idfdevicetype_name`
WHERE t.`company_id` = @replicate_source_company_id
  AND dt_target.`id` IS NOT NULL;
INSERT IGNORE INTO `idfs` (`company_id`, `location_id`, `name`, `idf_code`, `notes`, `created_at`) SELECT c.`id`, t.`location_id`, t.`name`, t.`idf_code`, t.`notes`, '2026-01-01 00:00:01' FROM `idfs` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `inventory_items` (`company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_employee_id`, `last_employee_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`)
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
    NULL,
    t.`last_employee_manual`,
    t.`comments`,
    l_target.`id`,
    s_target.`id`,
    t.`active`,
    '2026-01-01 00:00:01'
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
WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `it_locations` (`company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`location_code`, t.`address`, t.`city`, t.`state`, t.`country`, t.`postal_code`, t.`phone`, t.`type_id`, t.`active`, '2026-01-01 00:00:01' FROM `it_locations` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `racks` (`company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) SELECT c.`id`, t.`location_id`, t.`name`, t.`rack_code`, t.`status_id`, t.`active`, '2026-01-01 00:00:01' FROM `racks` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `suppliers` (`company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`supplier_code`, t.`contact_person`, t.`email`, t.`phone`, t.`status_id`, t.`active`, '2026-01-01 00:00:01' FROM `suppliers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_ports` (`company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `created_at`, `updated_at`)
SELECT
    c.`id`,
    e_target.`id`,
    t.`hostname`,
    t.`port_type`,
    t.`port_number`,
    t.`to_patch_port`,
    COALESCE(ss_target.`id`, ss_fallback.`id`),
    COALESCE(sc_target.`id`, sc_fallback.`id`),
    v_target.`id`,
    t.`fiber_port_id`,
    t.`fiber_patch_id`,
    t.`fiber_rack_id`,
    t.`idf_id`,
    t.`comments`,
    '2026-01-01 00:00:01',
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
LEFT JOIN `cable_colors` sc_source ON sc_source.`id` = t.`color_id`
LEFT JOIN `cable_colors` sc_target ON sc_target.`company_id` = c.`id` AND sc_target.`color_name` = sc_source.`color_name`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `cable_colors`
    GROUP BY `company_id`
) sc_fallback ON sc_fallback.`company_id` = c.`id`
LEFT JOIN `vlans` v_source ON v_source.`id` = t.`vlan_id`
LEFT JOIN `vlans` v_target ON v_target.`company_id` = c.`id` AND v_target.`vlan_number` = v_source.`vlan_number`
WHERE t.`company_id` = @replicate_source_company_id
  AND COALESCE(ss_target.`id`, ss_fallback.`id`) IS NOT NULL
  AND COALESCE(sc_target.`id`, sc_fallback.`id`) IS NOT NULL;
INSERT IGNORE INTO `system_access` (`company_id`, `code`, `name`, `active`, `created_at`) SELECT c.`id`, t.`code`, t.`name`, t.`active`, '2026-01-01 00:00:01' FROM `system_access` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`, `created_at`) SELECT c.`id`, ur_target.`id`, rh.`hierarchy_order`, '2026-01-01 00:00:01' FROM `role_hierarchy` rh JOIN `companies` c ON c.`id` <> rh.`company_id` JOIN `employee_roles` ur_source ON ur_source.`id` = rh.`role_id` JOIN `employee_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rh.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`) SELECT c.`id`, ur_target.`id`, rmp.`module_name`, rmp.`can_view`, rmp.`can_create`, rmp.`can_edit`, rmp.`can_delete`, rmp.`can_import`, rmp.`can_export`, '2026-01-01 00:00:01' FROM `role_module_permissions` rmp JOIN `companies` c ON c.`id` <> rmp.`company_id` JOIN `employee_roles` ur_source ON ur_source.`id` = rmp.`role_id` JOIN `employee_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rmp.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`, `created_at`) SELECT c.`id`, ur_granter_target.`id`, ur_target_target.`id`, '2026-01-01 00:00:01' FROM `role_assignment_rights` rar JOIN `companies` c ON c.`id` <> rar.`company_id` JOIN `employee_roles` ur_granter_source ON ur_granter_source.`id` = rar.`role_id` JOIN `employee_roles` ur_target_source ON ur_target_source.`id` = rar.`can_assign_role_id` JOIN `employee_roles` ur_granter_target ON ur_granter_target.`company_id` = c.`id` AND ur_granter_target.`name` = ur_granter_source.`name` JOIN `employee_roles` ur_target_target ON ur_target_target.`company_id` = c.`id` AND ur_target_target.`name` = ur_target_source.`name` WHERE rar.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `created_at`)
SELECT e.`id`, e.`company_id`, NULL, '2026-01-01 00:00:01'
FROM `employees` e
WHERE e.`password` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `employee_companies` uc
    WHERE uc.`employee_id` = e.`id` AND uc.`company_id` = e.`company_id`
  );
INSERT IGNORE INTO `ui_configuration` (
    `company_id`,
    `employee_id`,
    `table_actions_position`,
    `new_button_position`,
    `export_buttons_position`,
    `back_save_position`,
    `enable_all_error_reporting`,
    `enable_audit_logs`,
    `records_per_page`,
    `app_name`,
    `favicon_path`,
    `equipment_type_sidebar_visibility`,
    `created_at`,
    `updated_at`
)
SELECT
    c.`id`,
    e_target.`id`,
    t.`table_actions_position`,
    t.`new_button_position`,
    t.`export_buttons_position`,
    t.`back_save_position`,
    t.`enable_all_error_reporting`,
    t.`enable_audit_logs`,
    t.`records_per_page`,
    t.`app_name`,
    CONCAT('images/favicons/company_', c.`id`, '.ico'),
    t.`equipment_type_sidebar_visibility`,
    '2026-01-01 00:00:01',
    t.`updated_at`
FROM `ui_configuration` t
JOIN `companies` c
    ON c.`id` <> t.`company_id`
JOIN `employees` e_source
    ON e_source.`id` = t.`employee_id`
JOIN `employees` e_target
    ON e_target.`company_id` = c.`id`
   AND e_target.`username` = e_source.`username`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
      SELECT 1
      FROM `ui_configuration` u
      WHERE u.`company_id` = c.`id`
        AND u.`employee_id` = e_target.`id`
  );
INSERT IGNORE INTO `vlans` (`company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) SELECT c.`id`, t.`vlan_number`, t.`vlan_name`, t.`vlan_color`, t.`subnet`, t.`ip`, t.`comments`, t.`gateway_ip`, t.`active`, '2026-01-01 00:00:01' FROM `vlans` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
-- Why: Remove catalog rows whose FK parents belong to another company (legacy replicate row or partial import).
DELETE c FROM `catalogs` c
INNER JOIN `equipment_types` et ON et.id = c.equipment_type_id
WHERE c.company_id > 0 AND et.company_id > 0 AND c.company_id <> et.company_id;
DELETE c FROM `catalogs` c
INNER JOIN `manufacturers` m ON m.id = c.manufacturer_id
WHERE c.manufacturer_id IS NOT NULL AND c.company_id > 0 AND m.company_id > 0 AND c.company_id <> m.company_id;
DELETE c FROM `catalogs` c
INNER JOIN `suppliers` s ON s.id = c.supplier_id
WHERE c.supplier_id IS NOT NULL AND c.company_id > 0 AND s.company_id > 0 AND c.company_id <> s.company_id;
-- Workstations are tenant-specific and reference tenant-bound records.
-- Keep this table empty on bootstrap to avoid cross-company foreign key mismatches.

-- Build database-level audit triggers for every application table.
DROP TRIGGER IF EXISTS `trg_access_levels_audit_insert`;
DROP TRIGGER IF EXISTS `trg_access_levels_audit_update`;
DROP TRIGGER IF EXISTS `trg_access_levels_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_access_levels_audit_insert` AFTER INSERT ON `access_levels` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'access_levels', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_access_levels_audit_update` AFTER UPDATE ON `access_levels` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'access_levels', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_access_levels_audit_delete` AFTER DELETE ON `access_levels` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'access_levels', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_annual_budgets_audit_insert`;
DROP TRIGGER IF EXISTS `trg_annual_budgets_audit_update`;
DROP TRIGGER IF EXISTS `trg_annual_budgets_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_annual_budgets_audit_insert` AFTER INSERT ON `annual_budgets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'annual_budgets', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cost_center_id', NEW.`cost_center_id`, 'gl_account_id', NEW.`gl_account_id`, 'year', NEW.`year`, 'amount', NEW.`amount`, 'created_by', NEW.`created_by`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_annual_budgets_audit_update` AFTER UPDATE ON `annual_budgets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'annual_budgets', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cost_center_id', OLD.`cost_center_id`, 'gl_account_id', OLD.`gl_account_id`, 'year', OLD.`year`, 'amount', OLD.`amount`, 'created_by', OLD.`created_by`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cost_center_id', NEW.`cost_center_id`, 'gl_account_id', NEW.`gl_account_id`, 'year', NEW.`year`, 'amount', NEW.`amount`, 'created_by', NEW.`created_by`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_annual_budgets_audit_delete` AFTER DELETE ON `annual_budgets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'annual_budgets', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cost_center_id', OLD.`cost_center_id`, 'gl_account_id', OLD.`gl_account_id`, 'year', OLD.`year`, 'amount', OLD.`amount`, 'created_by', OLD.`created_by`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_approvals_audit_insert`;
DROP TRIGGER IF EXISTS `trg_approvals_audit_update`;
DROP TRIGGER IF EXISTS `trg_approvals_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_approvals_audit_insert` AFTER INSERT ON `approvals` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvals', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'forecast_revision_id', NEW.`forecast_revision_id`, 'stage', NEW.`stage`, 'status', NEW.`status`, 'approved_by', NEW.`approved_by`, 'approved_at', NEW.`approved_at`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approvals_audit_update` AFTER UPDATE ON `approvals` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvals', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'forecast_revision_id', OLD.`forecast_revision_id`, 'stage', OLD.`stage`, 'status', OLD.`status`, 'approved_by', OLD.`approved_by`, 'approved_at', OLD.`approved_at`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'forecast_revision_id', NEW.`forecast_revision_id`, 'stage', NEW.`stage`, 'status', NEW.`status`, 'approved_by', NEW.`approved_by`, 'approved_at', NEW.`approved_at`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approvals_audit_delete` AFTER DELETE ON `approvals` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvals', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'forecast_revision_id', OLD.`forecast_revision_id`, 'stage', OLD.`stage`, 'status', OLD.`status`, 'approved_by', OLD.`approved_by`, 'approved_at', OLD.`approved_at`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_approvals_stage_audit_insert`;
DROP TRIGGER IF EXISTS `trg_approvals_stage_audit_update`;
DROP TRIGGER IF EXISTS `trg_approvals_stage_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_approvals_stage_audit_insert` AFTER INSERT ON `approvals_stage` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvals_stage', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'stage', NEW.`stage`, 'description', NEW.`description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approvals_stage_audit_update` AFTER UPDATE ON `approvals_stage` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvals_stage', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'stage', OLD.`stage`, 'description', OLD.`description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'stage', NEW.`stage`, 'description', NEW.`description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approvals_stage_audit_delete` AFTER DELETE ON `approvals_stage` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvals_stage', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'stage', OLD.`stage`, 'description', OLD.`description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_approvers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_approvers_audit_update`;
DROP TRIGGER IF EXISTS `trg_approvers_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_approvers_audit_insert` AFTER INSERT ON `approvers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvers', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'employee_position_id', NEW.`employee_position_id`, 'approver_type_id', NEW.`approver_type_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approvers_audit_update` AFTER UPDATE ON `approvers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvers', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'employee_position_id', OLD.`employee_position_id`, 'approver_type_id', OLD.`approver_type_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'employee_position_id', NEW.`employee_position_id`, 'approver_type_id', NEW.`approver_type_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approvers_audit_delete` AFTER DELETE ON `approvers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approvers', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'employee_position_id', OLD.`employee_position_id`, 'approver_type_id', OLD.`approver_type_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_approver_type_audit_insert`;
DROP TRIGGER IF EXISTS `trg_approver_type_audit_update`;
DROP TRIGGER IF EXISTS `trg_approver_type_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_approver_type_audit_insert` AFTER INSERT ON `approver_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approver_type', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'approver_type_description', NEW.`approver_type_description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approver_type_audit_update` AFTER UPDATE ON `approver_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approver_type', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'approver_type_description', OLD.`approver_type_description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'approver_type_description', NEW.`approver_type_description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_approver_type_audit_delete` AFTER DELETE ON `approver_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'approver_type', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'approver_type_description', OLD.`approver_type_description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_budget_categories_audit_insert`;
DROP TRIGGER IF EXISTS `trg_budget_categories_audit_update`;
DROP TRIGGER IF EXISTS `trg_budget_categories_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_budget_categories_audit_insert` AFTER INSERT ON `budget_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'budget_categories', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'description', NEW.`description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_budget_categories_audit_update` AFTER UPDATE ON `budget_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'budget_categories', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'description', OLD.`description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'description', NEW.`description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_budget_categories_audit_delete` AFTER DELETE ON `budget_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'budget_categories', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'description', OLD.`description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_cost_centers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_cost_centers_audit_update`;
DROP TRIGGER IF EXISTS `trg_cost_centers_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_cost_centers_audit_insert` AFTER INSERT ON `cost_centers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'cost_centers', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_cost_centers_audit_update` AFTER UPDATE ON `cost_centers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'cost_centers', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_cost_centers_audit_delete` AFTER DELETE ON `cost_centers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'cost_centers', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_assignment_history_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_assignment_history_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_assignment_history_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_assignment_history_audit_insert` AFTER INSERT ON `employee_assignment_history` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_assignment_history', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'equipment_id', NEW.`equipment_id`, 'inventory_item_id', NEW.`inventory_item_id`, 'asset_description', NEW.`asset_description`, 'sim_imei', NEW.`sim_imei`, 'assigned_date', NEW.`assigned_date`, 'returned_date', NEW.`returned_date`, 'condition_on_return', NEW.`condition_on_return`, 'signed_handover', NEW.`signed_handover`, 'comments', NEW.`comments`, 'assigned_by_employee_id', NEW.`assigned_by_employee_id`, 'received_by_employee_id', NEW.`received_by_employee_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_assignment_history_audit_update` AFTER UPDATE ON `employee_assignment_history` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_assignment_history', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'equipment_id', OLD.`equipment_id`, 'inventory_item_id', OLD.`inventory_item_id`, 'asset_description', OLD.`asset_description`, 'sim_imei', OLD.`sim_imei`, 'assigned_date', OLD.`assigned_date`, 'returned_date', OLD.`returned_date`, 'condition_on_return', OLD.`condition_on_return`, 'signed_handover', OLD.`signed_handover`, 'comments', OLD.`comments`, 'assigned_by_employee_id', OLD.`assigned_by_employee_id`, 'received_by_employee_id', OLD.`received_by_employee_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'equipment_id', NEW.`equipment_id`, 'inventory_item_id', NEW.`inventory_item_id`, 'asset_description', NEW.`asset_description`, 'sim_imei', NEW.`sim_imei`, 'assigned_date', NEW.`assigned_date`, 'returned_date', NEW.`returned_date`, 'condition_on_return', NEW.`condition_on_return`, 'signed_handover', NEW.`signed_handover`, 'comments', NEW.`comments`, 'assigned_by_employee_id', NEW.`assigned_by_employee_id`, 'received_by_employee_id', NEW.`received_by_employee_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_assignment_history_audit_delete` AFTER DELETE ON `employee_assignment_history` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_assignment_history', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'equipment_id', OLD.`equipment_id`, 'inventory_item_id', OLD.`inventory_item_id`, 'asset_description', OLD.`asset_description`, 'sim_imei', OLD.`sim_imei`, 'assigned_date', OLD.`assigned_date`, 'returned_date', OLD.`returned_date`, 'condition_on_return', OLD.`condition_on_return`, 'signed_handover', OLD.`signed_handover`, 'comments', OLD.`comments`, 'assigned_by_employee_id', OLD.`assigned_by_employee_id`, 'received_by_employee_id', OLD.`received_by_employee_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_expenses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_expenses_audit_update`;
DROP TRIGGER IF EXISTS `trg_expenses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_expenses_audit_insert` AFTER INSERT ON `expenses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'expenses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cost_center_id', NEW.`cost_center_id`, 'gl_account_id', NEW.`gl_account_id`, 'date', NEW.`date`, 'amount', NEW.`amount`, 'description', NEW.`description`, 'invoice_number', NEW.`invoice_number`, 'created_by', NEW.`created_by`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_expenses_audit_update` AFTER UPDATE ON `expenses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'expenses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cost_center_id', OLD.`cost_center_id`, 'gl_account_id', OLD.`gl_account_id`, 'date', OLD.`date`, 'amount', OLD.`amount`, 'description', OLD.`description`, 'invoice_number', OLD.`invoice_number`, 'created_by', OLD.`created_by`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cost_center_id', NEW.`cost_center_id`, 'gl_account_id', NEW.`gl_account_id`, 'date', NEW.`date`, 'amount', NEW.`amount`, 'description', NEW.`description`, 'invoice_number', NEW.`invoice_number`, 'created_by', NEW.`created_by`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_expenses_audit_delete` AFTER DELETE ON `expenses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'expenses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cost_center_id', OLD.`cost_center_id`, 'gl_account_id', OLD.`gl_account_id`, 'date', OLD.`date`, 'amount', OLD.`amount`, 'description', OLD.`description`, 'invoice_number', OLD.`invoice_number`, 'created_by', OLD.`created_by`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_assignment_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_assignment_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_assignment_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_assignment_types_audit_insert` AFTER INSERT ON `assignment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'assignment_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_assignment_types_audit_update` AFTER UPDATE ON `assignment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'assignment_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_assignment_types_audit_delete` AFTER DELETE ON `assignment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'assignment_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_companies_audit_insert`;
DROP TRIGGER IF EXISTS `trg_companies_audit_update`;
DROP TRIGGER IF EXISTS `trg_companies_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_companies_audit_insert` AFTER INSERT ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_employee_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'unit_no', NEW.`unit_no`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_companies_audit_update` AFTER UPDATE ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_employee_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company', OLD.`company`, 'incode', OLD.`incode`, 'city', OLD.`city`, 'country', OLD.`country`, 'phone', OLD.`phone`, 'email', OLD.`email`, 'website', OLD.`website`, 'vat', OLD.`vat`, 'unit_no', OLD.`unit_no`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'unit_no', NEW.`unit_no`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_companies_audit_delete` AFTER DELETE ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, 0), @app_employee_id, @app_username, @app_email, 'companies', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company', OLD.`company`, 'incode', OLD.`incode`, 'city', OLD.`city`, 'country', OLD.`country`, 'phone', OLD.`phone`, 'email', OLD.`email`, 'website', OLD.`website`, 'vat', OLD.`vat`, 'unit_no', OLD.`unit_no`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_departments_audit_insert`;
DROP TRIGGER IF EXISTS `trg_departments_audit_update`;
DROP TRIGGER IF EXISTS `trg_departments_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_departments_audit_insert` AFTER INSERT ON `departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'departments', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'description', NEW.`description`, 'email', NEW.`email`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_departments_audit_update` AFTER UPDATE ON `departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'departments', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'description', OLD.`description`, 'email', OLD.`email`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'description', NEW.`description`, 'email', NEW.`email`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_departments_audit_delete` AFTER DELETE ON `departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'departments', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'description', OLD.`description`, 'email', OLD.`email`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_email_smtp_configurations_audit_insert`;
DROP TRIGGER IF EXISTS `trg_email_smtp_configurations_audit_update`;
DROP TRIGGER IF EXISTS `trg_email_smtp_configurations_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_email_smtp_configurations_audit_insert` AFTER INSERT ON `email_smtp_configurations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'email_smtp_configurations', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'config_name', NEW.`config_name`, 'smtp_host', NEW.`smtp_host`, 'smtp_port', NEW.`smtp_port`, 'username', NEW.`username`, 'from_email', NEW.`from_email`, 'from_name', NEW.`from_name`, 'imap_port', NEW.`imap_port`, 'pop3_port', NEW.`pop3_port`, 'pop3_tls_mode', NEW.`pop3_tls_mode`, 'pop3_require_secure_connection', NEW.`pop3_require_secure_connection`, 'is_default', NEW.`is_default`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_email_smtp_configurations_audit_update` AFTER UPDATE ON `email_smtp_configurations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'email_smtp_configurations', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'config_name', OLD.`config_name`, 'smtp_host', OLD.`smtp_host`, 'smtp_port', OLD.`smtp_port`, 'username', OLD.`username`, 'from_email', OLD.`from_email`, 'from_name', OLD.`from_name`, 'imap_port', OLD.`imap_port`, 'pop3_port', OLD.`pop3_port`, 'pop3_tls_mode', OLD.`pop3_tls_mode`, 'pop3_require_secure_connection', OLD.`pop3_require_secure_connection`, 'is_default', OLD.`is_default`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'config_name', NEW.`config_name`, 'smtp_host', NEW.`smtp_host`, 'smtp_port', NEW.`smtp_port`, 'username', NEW.`username`, 'from_email', NEW.`from_email`, 'from_name', NEW.`from_name`, 'imap_port', NEW.`imap_port`, 'pop3_port', NEW.`pop3_port`, 'pop3_tls_mode', NEW.`pop3_tls_mode`, 'pop3_require_secure_connection', NEW.`pop3_require_secure_connection`, 'is_default', NEW.`is_default`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_email_smtp_configurations_audit_delete` AFTER DELETE ON `email_smtp_configurations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'email_smtp_configurations', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'config_name', OLD.`config_name`, 'smtp_host', OLD.`smtp_host`, 'smtp_port', OLD.`smtp_port`, 'username', OLD.`username`, 'from_email', OLD.`from_email`, 'from_name', OLD.`from_name`, 'imap_port', OLD.`imap_port`, 'pop3_port', OLD.`pop3_port`, 'pop3_tls_mode', OLD.`pop3_tls_mode`, 'pop3_require_secure_connection', OLD.`pop3_require_secure_connection`, 'is_default', OLD.`is_default`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_email_alert_rules_audit_insert`;
DROP TRIGGER IF EXISTS `trg_email_alert_rules_audit_update`;
DROP TRIGGER IF EXISTS `trg_email_alert_rules_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_email_alert_rules_audit_insert` AFTER INSERT ON `email_alert_rules` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'email_alert_rules', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'rule_slug', NEW.`rule_slug`, 'enabled', NEW.`enabled`, 'days_before', NEW.`days_before`, 'notify_emails', NEW.`notify_emails`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_email_alert_rules_audit_update` AFTER UPDATE ON `email_alert_rules` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'email_alert_rules', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'rule_slug', OLD.`rule_slug`, 'enabled', OLD.`enabled`, 'days_before', OLD.`days_before`, 'notify_emails', OLD.`notify_emails`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'rule_slug', NEW.`rule_slug`, 'enabled', NEW.`enabled`, 'days_before', NEW.`days_before`, 'notify_emails', NEW.`notify_emails`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_email_alert_rules_audit_delete` AFTER DELETE ON `email_alert_rules` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'email_alert_rules', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'rule_slug', OLD.`rule_slug`, 'enabled', OLD.`enabled`, 'days_before', OLD.`days_before`, 'notify_emails', OLD.`notify_emails`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_onboarding_requests_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_onboarding_requests_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_onboarding_requests_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_insert` AFTER INSERT ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'employee_position_id', NEW.`employee_position_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'department_name', NEW.`department_name`, 'request_date', NEW.`request_date`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera', NEW.`opera`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'email_account', NEW.`email_account`, 'landline_phone', NEW.`landline_phone`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'mobile_phone', NEW.`mobile_phone`, 'navision', NEW.`navision`, 'mobile_email', NEW.`mobile_email`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_dep', NEW.`office_key_card_dep`, 'comments', NEW.`comments`, 'starting_date', NEW.`starting_date`, 'requested_by', NEW.`requested_by`, 'requested_by_date', NEW.`requested_by_date`, 'requested_on', NEW.`requested_on`, 'hod_approval', NEW.`hod_approval`, 'hod_approval_date', NEW.`hod_approval_date`, 'hrd_approval', NEW.`hrd_approval`, 'hrd_approval_date', NEW.`hrd_approval_date`, 'ism_approval', NEW.`ism_approval`, 'ism_approval_date', NEW.`ism_approval_date`, 'gm_approval', NEW.`gm_approval`, 'gm_approval_date', NEW.`gm_approval_date`, 'fin_approval', NEW.`fin_approval`, 'fin_approval_date', NEW.`fin_approval_date`, 'status_hod', NEW.`status_hod`, 'status_hrd', NEW.`status_hrd`, 'status_ism', NEW.`status_ism`, 'status_gm', NEW.`status_gm`, 'status_fin', NEW.`status_fin`, 'email_sent_hod', NEW.`email_sent_hod`, 'email_sent_hod_at', NEW.`email_sent_hod_at`, 'email_sent_hrd', NEW.`email_sent_hrd`, 'email_sent_hrd_at', NEW.`email_sent_hrd_at`, 'email_sent_ism', NEW.`email_sent_ism`, 'email_sent_ism_at', NEW.`email_sent_ism_at`, 'email_sent_gm', NEW.`email_sent_gm`, 'email_sent_gm_at', NEW.`email_sent_gm_at`, 'email_sent_fin', NEW.`email_sent_fin`, 'email_sent_fin_at', NEW.`email_sent_fin_at`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_update` AFTER UPDATE ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'employee_position_id', OLD.`employee_position_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'department_name', OLD.`department_name`, 'request_date', OLD.`request_date`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera', OLD.`opera`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'email_account', OLD.`email_account`, 'landline_phone', OLD.`landline_phone`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'mobile_phone', OLD.`mobile_phone`, 'navision', OLD.`navision`, 'mobile_email', OLD.`mobile_email`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_dep', OLD.`office_key_card_dep`, 'comments', OLD.`comments`, 'starting_date', OLD.`starting_date`, 'requested_by', OLD.`requested_by`, 'requested_by_date', OLD.`requested_by_date`, 'requested_on', OLD.`requested_on`, 'hod_approval', OLD.`hod_approval`, 'hod_approval_date', OLD.`hod_approval_date`, 'hrd_approval', OLD.`hrd_approval`, 'hrd_approval_date', OLD.`hrd_approval_date`, 'ism_approval', OLD.`ism_approval`, 'ism_approval_date', OLD.`ism_approval_date`, 'gm_approval', OLD.`gm_approval`, 'gm_approval_date', OLD.`gm_approval_date`, 'fin_approval', OLD.`fin_approval`, 'fin_approval_date', OLD.`fin_approval_date`, 'status_hod', OLD.`status_hod`, 'status_hrd', OLD.`status_hrd`, 'status_ism', OLD.`status_ism`, 'status_gm', OLD.`status_gm`, 'status_fin', OLD.`status_fin`, 'email_sent_hod', OLD.`email_sent_hod`, 'email_sent_hod_at', OLD.`email_sent_hod_at`, 'email_sent_hrd', OLD.`email_sent_hrd`, 'email_sent_hrd_at', OLD.`email_sent_hrd_at`, 'email_sent_ism', OLD.`email_sent_ism`, 'email_sent_ism_at', OLD.`email_sent_ism_at`, 'email_sent_gm', OLD.`email_sent_gm`, 'email_sent_gm_at', OLD.`email_sent_gm_at`, 'email_sent_fin', OLD.`email_sent_fin`, 'email_sent_fin_at', OLD.`email_sent_fin_at`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'employee_position_id', NEW.`employee_position_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'department_name', NEW.`department_name`, 'request_date', NEW.`request_date`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera', NEW.`opera`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'email_account', NEW.`email_account`, 'landline_phone', NEW.`landline_phone`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'mobile_phone', NEW.`mobile_phone`, 'navision', NEW.`navision`, 'mobile_email', NEW.`mobile_email`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_dep', NEW.`office_key_card_dep`, 'comments', NEW.`comments`, 'starting_date', NEW.`starting_date`, 'requested_by', NEW.`requested_by`, 'requested_by_date', NEW.`requested_by_date`, 'requested_on', NEW.`requested_on`, 'hod_approval', NEW.`hod_approval`, 'hod_approval_date', NEW.`hod_approval_date`, 'hrd_approval', NEW.`hrd_approval`, 'hrd_approval_date', NEW.`hrd_approval_date`, 'ism_approval', NEW.`ism_approval`, 'ism_approval_date', NEW.`ism_approval_date`, 'gm_approval', NEW.`gm_approval`, 'gm_approval_date', NEW.`gm_approval_date`, 'fin_approval', NEW.`fin_approval`, 'fin_approval_date', NEW.`fin_approval_date`, 'status_hod', NEW.`status_hod`, 'status_hrd', NEW.`status_hrd`, 'status_ism', NEW.`status_ism`, 'status_gm', NEW.`status_gm`, 'status_fin', NEW.`status_fin`, 'email_sent_hod', NEW.`email_sent_hod`, 'email_sent_hod_at', NEW.`email_sent_hod_at`, 'email_sent_hrd', NEW.`email_sent_hrd`, 'email_sent_hrd_at', NEW.`email_sent_hrd_at`, 'email_sent_ism', NEW.`email_sent_ism`, 'email_sent_ism_at', NEW.`email_sent_ism_at`, 'email_sent_gm', NEW.`email_sent_gm`, 'email_sent_gm_at', NEW.`email_sent_gm_at`, 'email_sent_fin', NEW.`email_sent_fin`, 'email_sent_fin_at', NEW.`email_sent_fin_at`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_delete` AFTER DELETE ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'employee_position_id', OLD.`employee_position_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'department_name', OLD.`department_name`, 'request_date', OLD.`request_date`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera', OLD.`opera`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'email_account', OLD.`email_account`, 'landline_phone', OLD.`landline_phone`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'mobile_phone', OLD.`mobile_phone`, 'navision', OLD.`navision`, 'mobile_email', OLD.`mobile_email`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_dep', OLD.`office_key_card_dep`, 'comments', OLD.`comments`, 'starting_date', OLD.`starting_date`, 'requested_by', OLD.`requested_by`, 'requested_by_date', OLD.`requested_by_date`, 'requested_on', OLD.`requested_on`, 'hod_approval', OLD.`hod_approval`, 'hod_approval_date', OLD.`hod_approval_date`, 'hrd_approval', OLD.`hrd_approval`, 'hrd_approval_date', OLD.`hrd_approval_date`, 'ism_approval', OLD.`ism_approval`, 'ism_approval_date', OLD.`ism_approval_date`, 'gm_approval', OLD.`gm_approval`, 'gm_approval_date', OLD.`gm_approval_date`, 'fin_approval', OLD.`fin_approval`, 'fin_approval_date', OLD.`fin_approval_date`, 'status_hod', OLD.`status_hod`, 'status_hrd', OLD.`status_hrd`, 'status_ism', OLD.`status_ism`, 'status_gm', OLD.`status_gm`, 'status_fin', OLD.`status_fin`, 'email_sent_hod', OLD.`email_sent_hod`, 'email_sent_hod_at', OLD.`email_sent_hod_at`, 'email_sent_hrd', OLD.`email_sent_hrd`, 'email_sent_hrd_at', OLD.`email_sent_hrd_at`, 'email_sent_ism', OLD.`email_sent_ism`, 'email_sent_ism_at', OLD.`email_sent_ism_at`, 'email_sent_gm', OLD.`email_sent_gm`, 'email_sent_gm_at', OLD.`email_sent_gm_at`, 'email_sent_fin', OLD.`email_sent_fin`, 'email_sent_fin_at', OLD.`email_sent_fin_at`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_type_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_type_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_type_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_type_audit_insert` AFTER INSERT ON `employee_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_type', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name_type', NEW.`name_type`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_type_audit_update` AFTER UPDATE ON `employee_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_type', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name_type', OLD.`name_type`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name_type', NEW.`name_type`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_type_audit_delete` AFTER DELETE ON `employee_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_type', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name_type', OLD.`name_type`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_statuses_audit_insert` AFTER INSERT ON `employee_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_statuses_audit_update` AFTER UPDATE ON `employee_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_statuses_audit_delete` AFTER DELETE ON `employee_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_positions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_positions_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_positions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_positions_audit_insert` AFTER INSERT ON `employee_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_positions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'description', NEW.`description`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_positions_audit_update` AFTER UPDATE ON `employee_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_positions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'description', OLD.`description`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'description', NEW.`description`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_positions_audit_delete` AFTER DELETE ON `employee_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_positions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'description', OLD.`description`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_system_access_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_system_access_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_system_access_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_system_access_audit_insert` AFTER INSERT ON `employee_system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_system_access', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_system_access_audit_update` AFTER UPDATE ON `employee_system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_system_access', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_system_access_audit_delete` AFTER DELETE ON `employee_system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_system_access', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employees_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employees_audit_update`;
DROP TRIGGER IF EXISTS `trg_employees_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employees_audit_insert` AFTER INSERT ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employees', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'duplicate', NEW.`duplicate`, 'company_id', NEW.`company_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'display_name', NEW.`display_name`, 'work_email', NEW.`work_email`, 'personal_email', NEW.`personal_email`, 'mobile_phone', NEW.`mobile_phone`, 'external_number', NEW.`external_number`, 'dect', NEW.`dect`, 'extension', NEW.`extension`, 'employee_code', NEW.`employee_code`, 'external_id', NEW.`external_id`, 'username', NEW.`username`, 'job_code', NEW.`job_code`, 'comments', NEW.`comments`, 'request_date', NEW.`request_date`, 'start_date', NEW.`start_date`, 'requested_by', NEW.`requested_by`, 'termination_requested_by', NEW.`termination_requested_by`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_department_id', NEW.`office_key_card_department_id`, 'location_id', NEW.`location_id`, 'employment_status_id', NEW.`employment_status_id`, 'employee_type_id', NEW.`employee_type_id`, 'workstation_mode_id', NEW.`workstation_mode_id`, 'assignment_type_id', NEW.`assignment_type_id`, 'raw_status_code', NEW.`raw_status_code`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employees_audit_update` AFTER UPDATE ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employees', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'duplicate', OLD.`duplicate`, 'company_id', OLD.`company_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'display_name', OLD.`display_name`, 'work_email', OLD.`work_email`, 'personal_email', OLD.`personal_email`, 'mobile_phone', OLD.`mobile_phone`, 'external_number', OLD.`external_number`, 'dect', OLD.`dect`, 'extension', OLD.`extension`, 'employee_code', OLD.`employee_code`, 'external_id', OLD.`external_id`, 'username', OLD.`username`, 'job_code', OLD.`job_code`, 'comments', OLD.`comments`, 'request_date', OLD.`request_date`, 'start_date', OLD.`start_date`, 'requested_by', OLD.`requested_by`, 'termination_requested_by', OLD.`termination_requested_by`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_department_id', OLD.`office_key_card_department_id`, 'location_id', OLD.`location_id`, 'employment_status_id', OLD.`employment_status_id`, 'employee_type_id', OLD.`employee_type_id`, 'workstation_mode_id', OLD.`workstation_mode_id`, 'assignment_type_id', OLD.`assignment_type_id`, 'raw_status_code', OLD.`raw_status_code`), JSON_OBJECT('id', NEW.`id`, 'duplicate', NEW.`duplicate`, 'company_id', NEW.`company_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'display_name', NEW.`display_name`, 'work_email', NEW.`work_email`, 'personal_email', NEW.`personal_email`, 'mobile_phone', NEW.`mobile_phone`, 'external_number', NEW.`external_number`, 'dect', NEW.`dect`, 'extension', NEW.`extension`, 'employee_code', NEW.`employee_code`, 'external_id', NEW.`external_id`, 'username', NEW.`username`, 'job_code', NEW.`job_code`, 'comments', NEW.`comments`, 'request_date', NEW.`request_date`, 'start_date', NEW.`start_date`, 'requested_by', NEW.`requested_by`, 'termination_requested_by', NEW.`termination_requested_by`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_department_id', NEW.`office_key_card_department_id`, 'location_id', NEW.`location_id`, 'employment_status_id', NEW.`employment_status_id`, 'employee_type_id', NEW.`employee_type_id`, 'workstation_mode_id', NEW.`workstation_mode_id`, 'assignment_type_id', NEW.`assignment_type_id`, 'raw_status_code', NEW.`raw_status_code`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employees_audit_delete` AFTER DELETE ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employees', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'duplicate', OLD.`duplicate`, 'company_id', OLD.`company_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'display_name', OLD.`display_name`, 'work_email', OLD.`work_email`, 'personal_email', OLD.`personal_email`, 'mobile_phone', OLD.`mobile_phone`, 'external_number', OLD.`external_number`, 'dect', OLD.`dect`, 'extension', OLD.`extension`, 'employee_code', OLD.`employee_code`, 'external_id', OLD.`external_id`, 'username', OLD.`username`, 'job_code', OLD.`job_code`, 'comments', OLD.`comments`, 'request_date', OLD.`request_date`, 'start_date', OLD.`start_date`, 'requested_by', OLD.`requested_by`, 'termination_requested_by', OLD.`termination_requested_by`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_department_id', OLD.`office_key_card_department_id`, 'location_id', OLD.`location_id`, 'employment_status_id', OLD.`employment_status_id`, 'employee_type_id', OLD.`employee_type_id`, 'workstation_mode_id', OLD.`workstation_mode_id`, 'assignment_type_id', OLD.`assignment_type_id`, 'raw_status_code', OLD.`raw_status_code`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_audit_insert` AFTER INSERT ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_type_id', NEW.`equipment_type_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'location_id', NEW.`location_id`, 'rack_id', NEW.`rack_id`, 'idf_id', NEW.`idf_id`, 'name', NEW.`name`, 'serial_number', NEW.`serial_number`, 'model', NEW.`model`, 'hostname', NEW.`hostname`, 'ip_address', NEW.`ip_address`, 'patch_port', NEW.`patch_port`, 'mac_address', NEW.`mac_address`, 'department_id', NEW.`department_id`, 'supplier_id', NEW.`supplier_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'status_id', NEW.`status_id`, 'purchase_date', NEW.`purchase_date`, 'purchase_cost', NEW.`purchase_cost`, 'warranty_expiry', NEW.`warranty_expiry`, 'certificate_expiry', NEW.`certificate_expiry`, 'warranty_type_id', NEW.`warranty_type_id`, 'printer_device_type_id', NEW.`printer_device_type_id`, 'printer_color_capable', NEW.`printer_color_capable`, 'printer_scan', NEW.`printer_scan`, 'workstation_device_type_id', NEW.`workstation_device_type_id`, 'workstation_os_type_id', NEW.`workstation_os_type_id`, 'workstation_office_id', NEW.`workstation_office_id`, 'workstation_processor', NEW.`workstation_processor`, 'workstation_storage', NEW.`workstation_storage`, 'workstation_os_installed_on', NEW.`workstation_os_installed_on`, 'workstation_ram_id', NEW.`workstation_ram_id`, 'workstation_os_version_id', NEW.`workstation_os_version_id`, 'rj45_speed_id', NEW.`rj45_speed_id`, 'switch_rj45_id', NEW.`switch_rj45_id`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'switch_fiber_id', NEW.`switch_fiber_id`, 'switch_fiber_patch_id', NEW.`switch_fiber_patch_id`, 'switch_fiber_rack_id', NEW.`switch_fiber_rack_id`, 'switch_fiber_ports_number', NEW.`switch_fiber_ports_number`, 'switch_fiber_port_label', NEW.`switch_fiber_port_label`, 'switch_poe_id', NEW.`switch_poe_id`, 'switch_environment_id', NEW.`switch_environment_id`, 'notes', NEW.`notes`, 'photo_filename', NEW.`photo_filename`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_audit_update` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_type_id', OLD.`equipment_type_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'location_id', OLD.`location_id`, 'rack_id', OLD.`rack_id`, 'idf_id', OLD.`idf_id`, 'name', OLD.`name`, 'serial_number', OLD.`serial_number`, 'model', OLD.`model`, 'hostname', OLD.`hostname`, 'ip_address', OLD.`ip_address`, 'patch_port', OLD.`patch_port`, 'mac_address', OLD.`mac_address`, 'department_id', OLD.`department_id`, 'supplier_id', OLD.`supplier_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'status_id', OLD.`status_id`, 'purchase_date', OLD.`purchase_date`, 'purchase_cost', OLD.`purchase_cost`, 'warranty_expiry', OLD.`warranty_expiry`, 'certificate_expiry', OLD.`certificate_expiry`, 'warranty_type_id', OLD.`warranty_type_id`, 'printer_device_type_id', OLD.`printer_device_type_id`, 'printer_color_capable', OLD.`printer_color_capable`, 'printer_scan', OLD.`printer_scan`, 'workstation_device_type_id', OLD.`workstation_device_type_id`, 'workstation_os_type_id', OLD.`workstation_os_type_id`, 'workstation_office_id', OLD.`workstation_office_id`, 'workstation_processor', OLD.`workstation_processor`, 'workstation_storage', OLD.`workstation_storage`, 'workstation_os_installed_on', OLD.`workstation_os_installed_on`, 'workstation_ram_id', OLD.`workstation_ram_id`, 'workstation_os_version_id', OLD.`workstation_os_version_id`, 'rj45_speed_id', OLD.`rj45_speed_id`, 'switch_rj45_id', OLD.`switch_rj45_id`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'switch_fiber_id', OLD.`switch_fiber_id`, 'switch_fiber_patch_id', OLD.`switch_fiber_patch_id`, 'switch_fiber_rack_id', OLD.`switch_fiber_rack_id`, 'switch_fiber_ports_number', OLD.`switch_fiber_ports_number`, 'switch_fiber_port_label', OLD.`switch_fiber_port_label`, 'switch_poe_id', OLD.`switch_poe_id`, 'switch_environment_id', OLD.`switch_environment_id`, 'notes', OLD.`notes`, 'photo_filename', OLD.`photo_filename`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_type_id', NEW.`equipment_type_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'location_id', NEW.`location_id`, 'rack_id', NEW.`rack_id`, 'idf_id', NEW.`idf_id`, 'name', NEW.`name`, 'serial_number', NEW.`serial_number`, 'model', NEW.`model`, 'hostname', NEW.`hostname`, 'ip_address', NEW.`ip_address`, 'patch_port', NEW.`patch_port`, 'mac_address', NEW.`mac_address`, 'department_id', NEW.`department_id`, 'supplier_id', NEW.`supplier_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'status_id', NEW.`status_id`, 'purchase_date', NEW.`purchase_date`, 'purchase_cost', NEW.`purchase_cost`, 'warranty_expiry', NEW.`warranty_expiry`, 'certificate_expiry', NEW.`certificate_expiry`, 'warranty_type_id', NEW.`warranty_type_id`, 'printer_device_type_id', NEW.`printer_device_type_id`, 'printer_color_capable', NEW.`printer_color_capable`, 'printer_scan', NEW.`printer_scan`, 'workstation_device_type_id', NEW.`workstation_device_type_id`, 'workstation_os_type_id', NEW.`workstation_os_type_id`, 'workstation_office_id', NEW.`workstation_office_id`, 'workstation_processor', NEW.`workstation_processor`, 'workstation_storage', NEW.`workstation_storage`, 'workstation_os_installed_on', NEW.`workstation_os_installed_on`, 'workstation_ram_id', NEW.`workstation_ram_id`, 'workstation_os_version_id', NEW.`workstation_os_version_id`, 'rj45_speed_id', NEW.`rj45_speed_id`, 'switch_rj45_id', NEW.`switch_rj45_id`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'switch_fiber_id', NEW.`switch_fiber_id`, 'switch_fiber_patch_id', NEW.`switch_fiber_patch_id`, 'switch_fiber_rack_id', NEW.`switch_fiber_rack_id`, 'switch_fiber_ports_number', NEW.`switch_fiber_ports_number`, 'switch_fiber_port_label', NEW.`switch_fiber_port_label`, 'switch_poe_id', NEW.`switch_poe_id`, 'switch_environment_id', NEW.`switch_environment_id`, 'notes', NEW.`notes`, 'photo_filename', NEW.`photo_filename`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_audit_delete` AFTER DELETE ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_type_id', OLD.`equipment_type_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'location_id', OLD.`location_id`, 'rack_id', OLD.`rack_id`, 'idf_id', OLD.`idf_id`, 'name', OLD.`name`, 'serial_number', OLD.`serial_number`, 'model', OLD.`model`, 'hostname', OLD.`hostname`, 'ip_address', OLD.`ip_address`, 'patch_port', OLD.`patch_port`, 'mac_address', OLD.`mac_address`, 'department_id', OLD.`department_id`, 'supplier_id', OLD.`supplier_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'status_id', OLD.`status_id`, 'purchase_date', OLD.`purchase_date`, 'purchase_cost', OLD.`purchase_cost`, 'warranty_expiry', OLD.`warranty_expiry`, 'certificate_expiry', OLD.`certificate_expiry`, 'warranty_type_id', OLD.`warranty_type_id`, 'printer_device_type_id', OLD.`printer_device_type_id`, 'printer_color_capable', OLD.`printer_color_capable`, 'printer_scan', OLD.`printer_scan`, 'workstation_device_type_id', OLD.`workstation_device_type_id`, 'workstation_os_type_id', OLD.`workstation_os_type_id`, 'workstation_office_id', OLD.`workstation_office_id`, 'workstation_processor', OLD.`workstation_processor`, 'workstation_storage', OLD.`workstation_storage`, 'workstation_os_installed_on', OLD.`workstation_os_installed_on`, 'workstation_ram_id', OLD.`workstation_ram_id`, 'workstation_os_version_id', OLD.`workstation_os_version_id`, 'rj45_speed_id', OLD.`rj45_speed_id`, 'switch_rj45_id', OLD.`switch_rj45_id`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'switch_fiber_id', OLD.`switch_fiber_id`, 'switch_fiber_patch_id', OLD.`switch_fiber_patch_id`, 'switch_fiber_rack_id', OLD.`switch_fiber_rack_id`, 'switch_fiber_ports_number', OLD.`switch_fiber_ports_number`, 'switch_fiber_port_label', OLD.`switch_fiber_port_label`, 'switch_poe_id', OLD.`switch_poe_id`, 'switch_environment_id', OLD.`switch_environment_id`, 'notes', OLD.`notes`, 'photo_filename', OLD.`photo_filename`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_environment_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_environment_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_environment_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_environment_audit_insert` AFTER INSERT ON `equipment_environment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_environment', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_environment_audit_update` AFTER UPDATE ON `equipment_environment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_environment', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_environment_audit_delete` AFTER DELETE ON `equipment_environment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_environment', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_audit_insert` AFTER INSERT ON `equipment_fiber` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_audit_update` AFTER UPDATE ON `equipment_fiber` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_audit_delete` AFTER DELETE ON `equipment_fiber` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_patch_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_patch_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_patch_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_patch_audit_insert` AFTER INSERT ON `equipment_fiber_patch` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_patch', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_patch_audit_update` AFTER UPDATE ON `equipment_fiber_patch` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_patch', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_patch_audit_delete` AFTER DELETE ON `equipment_fiber_patch` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_patch', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_rack_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_rack_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_rack_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_rack_audit_insert` AFTER INSERT ON `equipment_fiber_rack` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_rack', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_rack_audit_update` AFTER UPDATE ON `equipment_fiber_rack` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_rack', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_rack_audit_delete` AFTER DELETE ON `equipment_fiber_rack` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_rack', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_count_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_count_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_fiber_count_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_fiber_count_audit_insert` AFTER INSERT ON `equipment_fiber_count` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_count', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_count_audit_update` AFTER UPDATE ON `equipment_fiber_count` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_count', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_fiber_count_audit_delete` AFTER DELETE ON `equipment_fiber_count` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_fiber_count', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_poe_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_poe_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_poe_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_poe_audit_insert` AFTER INSERT ON `equipment_poe` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_poe', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'watts', NEW.`watts`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_poe_audit_update` AFTER UPDATE ON `equipment_poe` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_poe', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'watts', OLD.`watts`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'watts', NEW.`watts`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_poe_audit_delete` AFTER DELETE ON `equipment_poe` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_poe', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'watts', OLD.`watts`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_rj45_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_rj45_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_rj45_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_rj45_audit_insert` AFTER INSERT ON `equipment_rj45` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_rj45', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_rj45_audit_update` AFTER UPDATE ON `equipment_rj45` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_rj45', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_rj45_audit_delete` AFTER DELETE ON `equipment_rj45` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_rj45', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_rj45_speed_audit_insert`;
DROP TRIGGER IF EXISTS `trg_rj45_speed_audit_update`;
DROP TRIGGER IF EXISTS `trg_rj45_speed_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_rj45_speed_audit_insert` AFTER INSERT ON `rj45_speed` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rj45_speed', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cable_type', NEW.`cable_type`, 'max_speed', NEW.`max_speed`, 'bandwidth', NEW.`bandwidth`, 'max_distance_full_speed', NEW.`max_distance_full_speed`, 'notes', NEW.`notes`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rj45_speed_audit_update` AFTER UPDATE ON `rj45_speed` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rj45_speed', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cable_type', OLD.`cable_type`, 'max_speed', OLD.`max_speed`, 'bandwidth', OLD.`bandwidth`, 'max_distance_full_speed', OLD.`max_distance_full_speed`, 'notes', OLD.`notes`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cable_type', NEW.`cable_type`, 'max_speed', NEW.`max_speed`, 'bandwidth', NEW.`bandwidth`, 'max_distance_full_speed', NEW.`max_distance_full_speed`, 'notes', NEW.`notes`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rj45_speed_audit_delete` AFTER DELETE ON `rj45_speed` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rj45_speed', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cable_type', OLD.`cable_type`, 'max_speed', OLD.`max_speed`, 'bandwidth', OLD.`bandwidth`, 'max_distance_full_speed', OLD.`max_distance_full_speed`, 'notes', OLD.`notes`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_statuses_audit_insert` AFTER INSERT ON `equipment_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_statuses_audit_update` AFTER UPDATE ON `equipment_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_statuses_audit_delete` AFTER DELETE ON `equipment_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_equipment_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_types_audit_insert` AFTER INSERT ON `equipment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_types_audit_update` AFTER UPDATE ON `equipment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_types_audit_delete` AFTER DELETE ON `equipment_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'equipment_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_floor_plan_folders_audit_insert`;
DROP TRIGGER IF EXISTS `trg_floor_plan_folders_audit_update`;
DROP TRIGGER IF EXISTS `trg_floor_plan_folders_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_floor_plan_folders_audit_insert` AFTER INSERT ON `floor_plan_folders` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plan_folders', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'parent_folder_id', NEW.`parent_folder_id`, 'name', NEW.`name`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plan_folders_audit_update` AFTER UPDATE ON `floor_plan_folders` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plan_folders', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'parent_folder_id', OLD.`parent_folder_id`, 'name', OLD.`name`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'parent_folder_id', NEW.`parent_folder_id`, 'name', NEW.`name`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plan_folders_audit_delete` AFTER DELETE ON `floor_plan_folders` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plan_folders', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'parent_folder_id', OLD.`parent_folder_id`, 'name', OLD.`name`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_floor_plan_item_tags_audit_insert`;
DROP TRIGGER IF EXISTS `trg_floor_plan_item_tags_audit_update`;
DROP TRIGGER IF EXISTS `trg_floor_plan_item_tags_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_floor_plan_item_tags_audit_insert` AFTER INSERT ON `floor_plan_item_tags` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, (SELECT `company_id` FROM `floor_plans` WHERE `id` = NEW.`floor_plan_id` LIMIT 1), (SELECT `company_id` FROM `floor_plan_tags` WHERE `id` = NEW.`tag_id` LIMIT 1)), @app_employee_id, @app_username, @app_email, 'floor_plan_item_tags', COALESCE(NEW.`floor_plan_id`, 0), 'INSERT', NULL, JSON_OBJECT('floor_plan_id', NEW.`floor_plan_id`, 'tag_id', NEW.`tag_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plan_item_tags_audit_update` AFTER UPDATE ON `floor_plan_item_tags` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, (SELECT `company_id` FROM `floor_plans` WHERE `id` = COALESCE(NEW.`floor_plan_id`, OLD.`floor_plan_id`) LIMIT 1), (SELECT `company_id` FROM `floor_plan_tags` WHERE `id` = COALESCE(NEW.`tag_id`, OLD.`tag_id`) LIMIT 1)), @app_employee_id, @app_username, @app_email, 'floor_plan_item_tags', COALESCE(NEW.`floor_plan_id`, OLD.`floor_plan_id`, 0), 'UPDATE', JSON_OBJECT('floor_plan_id', OLD.`floor_plan_id`, 'tag_id', OLD.`tag_id`), JSON_OBJECT('floor_plan_id', NEW.`floor_plan_id`, 'tag_id', NEW.`tag_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plan_item_tags_audit_delete` AFTER DELETE ON `floor_plan_item_tags` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, (SELECT `company_id` FROM `floor_plans` WHERE `id` = OLD.`floor_plan_id` LIMIT 1), (SELECT `company_id` FROM `floor_plan_tags` WHERE `id` = OLD.`tag_id` LIMIT 1)), @app_employee_id, @app_username, @app_email, 'floor_plan_item_tags', COALESCE(OLD.`floor_plan_id`, 0), 'DELETE', JSON_OBJECT('floor_plan_id', OLD.`floor_plan_id`, 'tag_id', OLD.`tag_id`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_floor_plan_tags_audit_insert`;
DROP TRIGGER IF EXISTS `trg_floor_plan_tags_audit_update`;
DROP TRIGGER IF EXISTS `trg_floor_plan_tags_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_floor_plan_tags_audit_insert` AFTER INSERT ON `floor_plan_tags` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plan_tags', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plan_tags_audit_update` AFTER UPDATE ON `floor_plan_tags` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plan_tags', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plan_tags_audit_delete` AFTER DELETE ON `floor_plan_tags` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plan_tags', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_floor_plans_audit_insert`;
DROP TRIGGER IF EXISTS `trg_floor_plans_audit_update`;
DROP TRIGGER IF EXISTS `trg_floor_plans_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_floor_plans_audit_insert` AFTER INSERT ON `floor_plans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plans', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'folder_id', NEW.`folder_id`, 'it_location_id', NEW.`it_location_id`, 'display_name', NEW.`display_name`, 'stored_filename', NEW.`stored_filename`, 'mime_type', NEW.`mime_type`, 'file_ext', NEW.`file_ext`, 'file_size', NEW.`file_size`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plans_audit_update` AFTER UPDATE ON `floor_plans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plans', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'folder_id', OLD.`folder_id`, 'it_location_id', OLD.`it_location_id`, 'display_name', OLD.`display_name`, 'stored_filename', OLD.`stored_filename`, 'mime_type', OLD.`mime_type`, 'file_ext', OLD.`file_ext`, 'file_size', OLD.`file_size`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'folder_id', NEW.`folder_id`, 'it_location_id', NEW.`it_location_id`, 'display_name', NEW.`display_name`, 'stored_filename', NEW.`stored_filename`, 'mime_type', NEW.`mime_type`, 'file_ext', NEW.`file_ext`, 'file_size', NEW.`file_size`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_floor_plans_audit_delete` AFTER DELETE ON `floor_plans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_plans', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'folder_id', OLD.`folder_id`, 'it_location_id', OLD.`it_location_id`, 'display_name', OLD.`display_name`, 'stored_filename', OLD.`stored_filename`, 'mime_type', OLD.`mime_type`, 'file_ext', OLD.`file_ext`, 'file_size', OLD.`file_size`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_forecast_revisions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_forecast_revisions_audit_update`;
DROP TRIGGER IF EXISTS `trg_forecast_revisions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_forecast_revisions_audit_insert` AFTER INSERT ON `forecast_revisions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'forecast_revisions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cost_center_id', NEW.`cost_center_id`, 'gl_account_id', NEW.`gl_account_id`, 'year', NEW.`year`, 'month', NEW.`month`, 'forecast_amount', NEW.`forecast_amount`, 'status', NEW.`status`, 'locked', NEW.`locked`, 'submitted_by', NEW.`submitted_by`, 'finance_reviewed_by', NEW.`finance_reviewed_by`, 'gm_approved_by', NEW.`gm_approved_by`, 'notes', NEW.`notes`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_forecast_revisions_audit_update` AFTER UPDATE ON `forecast_revisions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'forecast_revisions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cost_center_id', OLD.`cost_center_id`, 'gl_account_id', OLD.`gl_account_id`, 'year', OLD.`year`, 'month', OLD.`month`, 'forecast_amount', OLD.`forecast_amount`, 'status', OLD.`status`, 'locked', OLD.`locked`, 'submitted_by', OLD.`submitted_by`, 'finance_reviewed_by', OLD.`finance_reviewed_by`, 'gm_approved_by', OLD.`gm_approved_by`, 'notes', OLD.`notes`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'cost_center_id', NEW.`cost_center_id`, 'gl_account_id', NEW.`gl_account_id`, 'year', NEW.`year`, 'month', NEW.`month`, 'forecast_amount', NEW.`forecast_amount`, 'status', NEW.`status`, 'locked', NEW.`locked`, 'submitted_by', NEW.`submitted_by`, 'finance_reviewed_by', NEW.`finance_reviewed_by`, 'gm_approved_by', NEW.`gm_approved_by`, 'notes', NEW.`notes`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_forecast_revisions_audit_delete` AFTER DELETE ON `forecast_revisions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'forecast_revisions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'cost_center_id', OLD.`cost_center_id`, 'gl_account_id', OLD.`gl_account_id`, 'year', OLD.`year`, 'month', OLD.`month`, 'forecast_amount', OLD.`forecast_amount`, 'status', OLD.`status`, 'locked', OLD.`locked`, 'submitted_by', OLD.`submitted_by`, 'finance_reviewed_by', OLD.`finance_reviewed_by`, 'gm_approved_by', OLD.`gm_approved_by`, 'notes', OLD.`notes`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_forecast_revisions_status_audit_insert`;
DROP TRIGGER IF EXISTS `trg_forecast_revisions_status_audit_update`;
DROP TRIGGER IF EXISTS `trg_forecast_revisions_status_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_forecast_revisions_status_audit_insert` AFTER INSERT ON `forecast_revisions_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'forecast_revisions_status', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'status', NEW.`status`, 'notes', NEW.`notes`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_forecast_revisions_status_audit_update` AFTER UPDATE ON `forecast_revisions_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'forecast_revisions_status', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'status', OLD.`status`, 'notes', OLD.`notes`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'status', NEW.`status`, 'notes', NEW.`notes`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_forecast_revisions_status_audit_delete` AFTER DELETE ON `forecast_revisions_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'forecast_revisions_status', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'status', OLD.`status`, 'notes', OLD.`notes`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_gl_accounts_audit_insert`;
DROP TRIGGER IF EXISTS `trg_gl_accounts_audit_update`;
DROP TRIGGER IF EXISTS `trg_gl_accounts_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_gl_accounts_audit_insert` AFTER INSERT ON `gl_accounts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'gl_accounts', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'account_code', NEW.`account_code`, 'account_name', NEW.`account_name`, 'category_id', NEW.`category_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_gl_accounts_audit_update` AFTER UPDATE ON `gl_accounts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'gl_accounts', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'account_code', OLD.`account_code`, 'account_name', OLD.`account_name`, 'category_id', OLD.`category_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'account_code', NEW.`account_code`, 'account_name', NEW.`account_name`, 'category_id', NEW.`category_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_gl_accounts_audit_delete` AFTER DELETE ON `gl_accounts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'gl_accounts', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'account_code', OLD.`account_code`, 'account_name', OLD.`account_name`, 'category_id', OLD.`category_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_idf_links_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_links_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_links_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_links_audit_insert` AFTER INSERT ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_links', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'port_id_a', NEW.`port_id_a`, 'port_id_b', NEW.`port_id_b`, 'equipment_id', NEW.`equipment_id`, 'equipment_hostname', NEW.`equipment_hostname`, 'equipment_port_type', NEW.`equipment_port_type`, 'equipment_port', NEW.`equipment_port`, 'equipment_vlan_id', NEW.`equipment_vlan_id`, 'equipment_label', NEW.`equipment_label`, 'equipment_comments', NEW.`equipment_comments`, 'equipment_status_id', NEW.`equipment_status_id`, 'equipment_color_id', NEW.`equipment_color_id`, 'cable_color_id', NEW.`cable_color_id`, 'cable_color_hex', NEW.`cable_color_hex`, 'cable_label', NEW.`cable_label`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_links_audit_update` AFTER UPDATE ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_links', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'port_id_a', OLD.`port_id_a`, 'port_id_b', OLD.`port_id_b`, 'equipment_id', OLD.`equipment_id`, 'equipment_hostname', OLD.`equipment_hostname`, 'equipment_port_type', OLD.`equipment_port_type`, 'equipment_port', OLD.`equipment_port`, 'equipment_vlan_id', OLD.`equipment_vlan_id`, 'equipment_label', OLD.`equipment_label`, 'equipment_comments', OLD.`equipment_comments`, 'equipment_status_id', OLD.`equipment_status_id`, 'equipment_color_id', OLD.`equipment_color_id`, 'cable_color_id', OLD.`cable_color_id`, 'cable_color_hex', OLD.`cable_color_hex`, 'cable_label', OLD.`cable_label`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'port_id_a', NEW.`port_id_a`, 'port_id_b', NEW.`port_id_b`, 'equipment_id', NEW.`equipment_id`, 'equipment_hostname', NEW.`equipment_hostname`, 'equipment_port_type', NEW.`equipment_port_type`, 'equipment_port', NEW.`equipment_port`, 'equipment_vlan_id', NEW.`equipment_vlan_id`, 'equipment_label', NEW.`equipment_label`, 'equipment_comments', NEW.`equipment_comments`, 'equipment_status_id', NEW.`equipment_status_id`, 'equipment_color_id', NEW.`equipment_color_id`, 'cable_color_id', NEW.`cable_color_id`, 'cable_color_hex', NEW.`cable_color_hex`, 'cable_label', NEW.`cable_label`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_links_audit_delete` AFTER DELETE ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_links', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'port_id_a', OLD.`port_id_a`, 'port_id_b', OLD.`port_id_b`, 'equipment_id', OLD.`equipment_id`, 'equipment_hostname', OLD.`equipment_hostname`, 'equipment_port_type', OLD.`equipment_port_type`, 'equipment_port', OLD.`equipment_port`, 'equipment_vlan_id', OLD.`equipment_vlan_id`, 'equipment_label', OLD.`equipment_label`, 'equipment_comments', OLD.`equipment_comments`, 'equipment_status_id', OLD.`equipment_status_id`, 'equipment_color_id', OLD.`equipment_color_id`, 'cable_color_id', OLD.`cable_color_id`, 'cable_color_hex', OLD.`cable_color_hex`, 'cable_label', OLD.`cable_label`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_idf_device_type_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_device_type_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_device_type_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_device_type_audit_insert` AFTER INSERT ON `idf_device_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_device_type', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idfdevicetype_name', NEW.`idfdevicetype_name`, 'field_edit_emoji', NEW.`field_edit_emoji`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_device_type_audit_update` AFTER UPDATE ON `idf_device_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_device_type', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idfdevicetype_name', OLD.`idfdevicetype_name`, 'field_edit_emoji', OLD.`field_edit_emoji`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idfdevicetype_name', NEW.`idfdevicetype_name`, 'field_edit_emoji', NEW.`field_edit_emoji`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_device_type_audit_delete` AFTER DELETE ON `idf_device_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_device_type', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idfdevicetype_name', OLD.`idfdevicetype_name`, 'field_edit_emoji', OLD.`field_edit_emoji`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_ports_audit_insert` AFTER INSERT ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_ports', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'position_id', NEW.`position_id`, 'port_no', NEW.`port_no`, 'port_type', NEW.`port_type`, 'label', NEW.`label`, 'status_id', NEW.`status_id`, 'connected_to', NEW.`connected_to`, 'vlan_id', NEW.`vlan_id`, 'speed_id', NEW.`speed_id`, 'rj45_speed_id', NEW.`rj45_speed_id`, 'poe_id', NEW.`poe_id`, 'cable_color', NEW.`cable_color`, 'hex_color', NEW.`hex_color`, 'notes', NEW.`notes`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_ports_audit_update` AFTER UPDATE ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_ports', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'position_id', OLD.`position_id`, 'port_no', OLD.`port_no`, 'port_type', OLD.`port_type`, 'label', OLD.`label`, 'status_id', OLD.`status_id`, 'connected_to', OLD.`connected_to`, 'vlan_id', OLD.`vlan_id`, 'speed_id', OLD.`speed_id`, 'rj45_speed_id', OLD.`rj45_speed_id`, 'poe_id', OLD.`poe_id`, 'cable_color', OLD.`cable_color`, 'hex_color', OLD.`hex_color`, 'notes', OLD.`notes`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'position_id', NEW.`position_id`, 'port_no', NEW.`port_no`, 'port_type', NEW.`port_type`, 'label', NEW.`label`, 'status_id', NEW.`status_id`, 'connected_to', NEW.`connected_to`, 'vlan_id', NEW.`vlan_id`, 'speed_id', NEW.`speed_id`, 'rj45_speed_id', NEW.`rj45_speed_id`, 'poe_id', NEW.`poe_id`, 'cable_color', NEW.`cable_color`, 'hex_color', NEW.`hex_color`, 'notes', NEW.`notes`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_ports_audit_delete` AFTER DELETE ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_ports', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'position_id', OLD.`position_id`, 'port_no', OLD.`port_no`, 'port_type', OLD.`port_type`, 'label', OLD.`label`, 'status_id', OLD.`status_id`, 'connected_to', OLD.`connected_to`, 'vlan_id', OLD.`vlan_id`, 'speed_id', OLD.`speed_id`, 'rj45_speed_id', OLD.`rj45_speed_id`, 'poe_id', OLD.`poe_id`, 'cable_color', OLD.`cable_color`, 'hex_color', OLD.`hex_color`, 'notes', OLD.`notes`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_positions_audit_insert` AFTER INSERT ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_positions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idf_id', NEW.`idf_id`, 'position_no', NEW.`position_no`, 'device_type', NEW.`device_type`, 'device_name', NEW.`device_name`, 'equipment_id', NEW.`equipment_id`, 'rj45_count', NEW.`rj45_count`, 'sfp_count', NEW.`sfp_count`, 'price', NEW.`price`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_positions_audit_update` AFTER UPDATE ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_positions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idf_id', OLD.`idf_id`, 'position_no', OLD.`position_no`, 'device_type', OLD.`device_type`, 'device_name', OLD.`device_name`, 'equipment_id', OLD.`equipment_id`, 'rj45_count', OLD.`rj45_count`, 'sfp_count', OLD.`sfp_count`, 'price', OLD.`price`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idf_id', NEW.`idf_id`, 'position_no', NEW.`position_no`, 'device_type', NEW.`device_type`, 'device_name', NEW.`device_name`, 'equipment_id', NEW.`equipment_id`, 'rj45_count', NEW.`rj45_count`, 'sfp_count', NEW.`sfp_count`, 'price', NEW.`price`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_positions_audit_delete` AFTER DELETE ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idf_positions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idf_id', OLD.`idf_id`, 'position_no', OLD.`position_no`, 'device_type', OLD.`device_type`, 'device_name', OLD.`device_name`, 'equipment_id', OLD.`equipment_id`, 'rj45_count', OLD.`rj45_count`, 'sfp_count', OLD.`sfp_count`, 'price', OLD.`price`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_idfs_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idfs_audit_update`;
DROP TRIGGER IF EXISTS `trg_idfs_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idfs_audit_insert` AFTER INSERT ON `idfs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idfs', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'idf_code', NEW.`idf_code`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idfs_audit_update` AFTER UPDATE ON `idfs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idfs', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'idf_code', OLD.`idf_code`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'idf_code', NEW.`idf_code`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idfs_audit_delete` AFTER DELETE ON `idfs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'idfs', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'idf_code', OLD.`idf_code`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_inventory_categories_audit_insert`;
DROP TRIGGER IF EXISTS `trg_inventory_categories_audit_update`;
DROP TRIGGER IF EXISTS `trg_inventory_categories_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_inventory_categories_audit_insert` AFTER INSERT ON `inventory_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'inventory_categories', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_categories_audit_update` AFTER UPDATE ON `inventory_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'inventory_categories', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_categories_audit_delete` AFTER DELETE ON `inventory_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'inventory_categories', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_inventory_items_audit_insert`;
DROP TRIGGER IF EXISTS `trg_inventory_items_audit_update`;
DROP TRIGGER IF EXISTS `trg_inventory_items_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_inventory_items_audit_insert` AFTER INSERT ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'inventory_items', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'item_code', NEW.`item_code`, 'serial', NEW.`serial`, 'category_id', NEW.`category_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'quantity_on_hand', NEW.`quantity_on_hand`, 'quantity_minimum', NEW.`quantity_minimum`, 'price_eur', NEW.`price_eur`, 'last_employee_id', NEW.`last_employee_id`, 'last_employee_manual', NEW.`last_employee_manual`, 'comments', NEW.`comments`, 'location_id', NEW.`location_id`, 'supplier_id', NEW.`supplier_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_items_audit_update` AFTER UPDATE ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'inventory_items', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'item_code', OLD.`item_code`, 'serial', OLD.`serial`, 'category_id', OLD.`category_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'quantity_on_hand', OLD.`quantity_on_hand`, 'quantity_minimum', OLD.`quantity_minimum`, 'price_eur', OLD.`price_eur`, 'last_employee_id', OLD.`last_employee_id`, 'last_employee_manual', OLD.`last_employee_manual`, 'comments', OLD.`comments`, 'location_id', OLD.`location_id`, 'supplier_id', OLD.`supplier_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'item_code', NEW.`item_code`, 'serial', NEW.`serial`, 'category_id', NEW.`category_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'quantity_on_hand', NEW.`quantity_on_hand`, 'quantity_minimum', NEW.`quantity_minimum`, 'price_eur', NEW.`price_eur`, 'last_employee_id', NEW.`last_employee_id`, 'last_employee_manual', NEW.`last_employee_manual`, 'comments', NEW.`comments`, 'location_id', NEW.`location_id`, 'supplier_id', NEW.`supplier_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_items_audit_delete` AFTER DELETE ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'inventory_items', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'item_code', OLD.`item_code`, 'serial', OLD.`serial`, 'category_id', OLD.`category_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'quantity_on_hand', OLD.`quantity_on_hand`, 'quantity_minimum', OLD.`quantity_minimum`, 'price_eur', OLD.`price_eur`, 'last_employee_id', OLD.`last_employee_id`, 'last_employee_manual', OLD.`last_employee_manual`, 'comments', OLD.`comments`, 'location_id', OLD.`location_id`, 'supplier_id', OLD.`supplier_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_it_locations_audit_insert`;
DROP TRIGGER IF EXISTS `trg_it_locations_audit_update`;
DROP TRIGGER IF EXISTS `trg_it_locations_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_it_locations_audit_insert` AFTER INSERT ON `it_locations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_locations', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'location_code', NEW.`location_code`, 'address', NEW.`address`, 'city', NEW.`city`, 'state', NEW.`state`, 'country', NEW.`country`, 'postal_code', NEW.`postal_code`, 'phone', NEW.`phone`, 'type_id', NEW.`type_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_it_locations_audit_update` AFTER UPDATE ON `it_locations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_locations', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'location_code', OLD.`location_code`, 'address', OLD.`address`, 'city', OLD.`city`, 'state', OLD.`state`, 'country', OLD.`country`, 'postal_code', OLD.`postal_code`, 'phone', OLD.`phone`, 'type_id', OLD.`type_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'location_code', NEW.`location_code`, 'address', NEW.`address`, 'city', NEW.`city`, 'state', NEW.`state`, 'country', NEW.`country`, 'postal_code', NEW.`postal_code`, 'phone', NEW.`phone`, 'type_id', NEW.`type_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_it_locations_audit_delete` AFTER DELETE ON `it_locations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_locations', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'location_code', OLD.`location_code`, 'address', OLD.`address`, 'city', OLD.`city`, 'state', OLD.`state`, 'country', OLD.`country`, 'postal_code', OLD.`postal_code`, 'phone', OLD.`phone`, 'type_id', OLD.`type_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_license_management_audit_insert`;
DROP TRIGGER IF EXISTS `trg_license_management_audit_update`;
DROP TRIGGER IF EXISTS `trg_license_management_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_license_management_audit_insert` AFTER INSERT ON `license_management` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'license_management', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'license_key', NEW.`license_key`, 'license_type_id', NEW.`license_type_id`, 'quantity', NEW.`quantity`, 'supplier_id', NEW.`supplier_id`, 'purchase_date', NEW.`purchase_date`, 'expiry_date', NEW.`expiry_date`, 'price', NEW.`price`, 'active', NEW.`active`, 'notes', NEW.`notes`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_license_management_audit_update` AFTER UPDATE ON `license_management` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'license_management', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'license_key', OLD.`license_key`, 'license_type_id', OLD.`license_type_id`, 'quantity', OLD.`quantity`, 'supplier_id', OLD.`supplier_id`, 'purchase_date', OLD.`purchase_date`, 'expiry_date', OLD.`expiry_date`, 'price', OLD.`price`, 'active', OLD.`active`, 'notes', OLD.`notes`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'license_key', NEW.`license_key`, 'license_type_id', NEW.`license_type_id`, 'quantity', NEW.`quantity`, 'supplier_id', NEW.`supplier_id`, 'purchase_date', NEW.`purchase_date`, 'expiry_date', NEW.`expiry_date`, 'price', NEW.`price`, 'active', NEW.`active`, 'notes', NEW.`notes`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_license_management_audit_delete` AFTER DELETE ON `license_management` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'license_management', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'license_key', OLD.`license_key`, 'license_type_id', OLD.`license_type_id`, 'quantity', OLD.`quantity`, 'supplier_id', OLD.`supplier_id`, 'purchase_date', OLD.`purchase_date`, 'expiry_date', OLD.`expiry_date`, 'price', OLD.`price`, 'active', OLD.`active`, 'notes', OLD.`notes`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_license_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_license_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_license_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_license_types_audit_insert` AFTER INSERT ON `license_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'license_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_license_types_audit_update` AFTER UPDATE ON `license_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'license_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_license_types_audit_delete` AFTER DELETE ON `license_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'license_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_ip_addresses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ip_addresses_audit_update`;
DROP TRIGGER IF EXISTS `trg_ip_addresses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ip_addresses_audit_insert` AFTER INSERT ON `ip_addresses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ip_addresses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'subnet_id', NEW.`subnet_id`, 'ip_text', NEW.`ip_text`, 'status', NEW.`status`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'is_gateway', NEW.`is_gateway`, 'is_dns', NEW.`is_dns`, 'dhcp_managed', NEW.`dhcp_managed`, 'notes', NEW.`notes`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ip_addresses_audit_update` AFTER UPDATE ON `ip_addresses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ip_addresses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'subnet_id', OLD.`subnet_id`, 'ip_text', OLD.`ip_text`, 'status', OLD.`status`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'is_gateway', OLD.`is_gateway`, 'is_dns', OLD.`is_dns`, 'dhcp_managed', OLD.`dhcp_managed`, 'notes', OLD.`notes`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'subnet_id', NEW.`subnet_id`, 'ip_text', NEW.`ip_text`, 'status', NEW.`status`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'is_gateway', NEW.`is_gateway`, 'is_dns', NEW.`is_dns`, 'dhcp_managed', NEW.`dhcp_managed`, 'notes', NEW.`notes`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ip_addresses_audit_delete` AFTER DELETE ON `ip_addresses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ip_addresses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'subnet_id', OLD.`subnet_id`, 'ip_text', OLD.`ip_text`, 'status', OLD.`status`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'is_gateway', OLD.`is_gateway`, 'is_dns', OLD.`is_dns`, 'dhcp_managed', OLD.`dhcp_managed`, 'notes', OLD.`notes`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_ip_subnets_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ip_subnets_audit_update`;
DROP TRIGGER IF EXISTS `trg_ip_subnets_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ip_subnets_audit_insert` AFTER INSERT ON `ip_subnets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ip_subnets', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'vlan_id', NEW.`vlan_id`, 'cidr', NEW.`cidr`, 'network_ip', NEW.`network_ip`, 'prefix_length', NEW.`prefix_length`, 'gateway_ip', NEW.`gateway_ip`, 'dns1_ip', NEW.`dns1_ip`, 'dns2_ip', NEW.`dns2_ip`, 'dhcp_enabled', NEW.`dhcp_enabled`, 'description', NEW.`description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ip_subnets_audit_update` AFTER UPDATE ON `ip_subnets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ip_subnets', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'vlan_id', OLD.`vlan_id`, 'cidr', OLD.`cidr`, 'network_ip', OLD.`network_ip`, 'prefix_length', OLD.`prefix_length`, 'gateway_ip', OLD.`gateway_ip`, 'dns1_ip', OLD.`dns1_ip`, 'dns2_ip', OLD.`dns2_ip`, 'dhcp_enabled', OLD.`dhcp_enabled`, 'description', OLD.`description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'vlan_id', NEW.`vlan_id`, 'cidr', NEW.`cidr`, 'network_ip', NEW.`network_ip`, 'prefix_length', NEW.`prefix_length`, 'gateway_ip', NEW.`gateway_ip`, 'dns1_ip', NEW.`dns1_ip`, 'dns2_ip', NEW.`dns2_ip`, 'dhcp_enabled', NEW.`dhcp_enabled`, 'description', NEW.`description`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ip_subnets_audit_delete` AFTER DELETE ON `ip_subnets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ip_subnets', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'vlan_id', OLD.`vlan_id`, 'cidr', OLD.`cidr`, 'network_ip', OLD.`network_ip`, 'prefix_length', OLD.`prefix_length`, 'gateway_ip', OLD.`gateway_ip`, 'dns1_ip', OLD.`dns1_ip`, 'dns2_ip', OLD.`dns2_ip`, 'dhcp_enabled', OLD.`dhcp_enabled`, 'description', OLD.`description`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_location_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_location_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_location_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_location_types_audit_insert` AFTER INSERT ON `location_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'location_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_location_types_audit_update` AFTER UPDATE ON `location_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'location_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_location_types_audit_delete` AFTER DELETE ON `location_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'location_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_catalogs_audit_insert`;
DROP TRIGGER IF EXISTS `trg_catalogs_audit_update`;
DROP TRIGGER IF EXISTS `trg_catalogs_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_catalogs_audit_insert` AFTER INSERT ON `catalogs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'catalogs', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'model', NEW.`model`, 'equipment_type_id', NEW.`equipment_type_id`, 'image_url', NEW.`image_url`, 'price', NEW.`price`, 'supplier_id', NEW.`supplier_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'product_url', NEW.`product_url`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_catalogs_audit_update` AFTER UPDATE ON `catalogs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'catalogs', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'model', OLD.`model`, 'equipment_type_id', OLD.`equipment_type_id`, 'image_url', OLD.`image_url`, 'price', OLD.`price`, 'supplier_id', OLD.`supplier_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'product_url', OLD.`product_url`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'model', NEW.`model`, 'equipment_type_id', NEW.`equipment_type_id`, 'image_url', NEW.`image_url`, 'price', NEW.`price`, 'supplier_id', NEW.`supplier_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'product_url', NEW.`product_url`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_catalogs_audit_delete` AFTER DELETE ON `catalogs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'catalogs', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'model', OLD.`model`, 'equipment_type_id', OLD.`equipment_type_id`, 'image_url', OLD.`image_url`, 'price', OLD.`price`, 'supplier_id', OLD.`supplier_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'product_url', OLD.`product_url`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_manufacturers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_manufacturers_audit_update`;
DROP TRIGGER IF EXISTS `trg_manufacturers_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_manufacturers_audit_insert` AFTER INSERT ON `manufacturers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'manufacturers', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_manufacturers_audit_update` AFTER UPDATE ON `manufacturers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'manufacturers', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_manufacturers_audit_delete` AFTER DELETE ON `manufacturers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'manufacturers', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_monthly_budgets_audit_insert`;
DROP TRIGGER IF EXISTS `trg_monthly_budgets_audit_update`;
DROP TRIGGER IF EXISTS `trg_monthly_budgets_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_monthly_budgets_audit_insert` AFTER INSERT ON `monthly_budgets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'monthly_budgets', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'annual_budget_id', NEW.`annual_budget_id`, 'month', NEW.`month`, 'amount', NEW.`amount`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_monthly_budgets_audit_update` AFTER UPDATE ON `monthly_budgets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'monthly_budgets', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'annual_budget_id', OLD.`annual_budget_id`, 'month', OLD.`month`, 'amount', OLD.`amount`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'annual_budget_id', NEW.`annual_budget_id`, 'month', NEW.`month`, 'amount', NEW.`amount`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_monthly_budgets_audit_delete` AFTER DELETE ON `monthly_budgets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'monthly_budgets', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'annual_budget_id', OLD.`annual_budget_id`, 'month', OLD.`month`, 'amount', OLD.`amount`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_patches_updates_status_audit_insert`;
DROP TRIGGER IF EXISTS `trg_patches_updates_status_audit_update`;
DROP TRIGGER IF EXISTS `trg_patches_updates_status_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_patches_updates_status_audit_insert` AFTER INSERT ON `patches_updates_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates_status', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_status_audit_update` AFTER UPDATE ON `patches_updates_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates_status', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_status_audit_delete` AFTER DELETE ON `patches_updates_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates_status', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_patches_updates_level_audit_insert`;
DROP TRIGGER IF EXISTS `trg_patches_updates_level_audit_update`;
DROP TRIGGER IF EXISTS `trg_patches_updates_level_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_patches_updates_level_audit_insert` AFTER INSERT ON `patches_updates_level` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates_level', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'level', NEW.`level`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_level_audit_update` AFTER UPDATE ON `patches_updates_level` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates_level', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'level', OLD.`level`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'level', NEW.`level`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_level_audit_delete` AFTER DELETE ON `patches_updates_level` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates_level', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'level', OLD.`level`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_patches_updates_audit_insert`;
DROP TRIGGER IF EXISTS `trg_patches_updates_audit_update`;
DROP TRIGGER IF EXISTS `trg_patches_updates_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_patches_updates_audit_insert` AFTER INSERT ON `patches_updates` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'ip', NEW.`ip`, 'id_external', NEW.`id_external`, 'inncode', NEW.`inncode`, 'dest', NEW.`dest`, 'dest_ip', NEW.`dest_ip`, 'severity', NEW.`severity`, 'vuln_description', NEW.`vuln_description`, 'base_score', NEW.`base_score`, 'remediation', NEW.`remediation`, 'cve', NEW.`cve`, 'host_ip', NEW.`host_ip`, 'host_mac_manufacturer', NEW.`host_mac_manufacturer`, 'days_since_last_seen', NEW.`days_since_last_seen`, 'host_health_score', NEW.`host_health_score`, 'host_health_reason', NEW.`host_health_reason`, 'host_resolution_priority', NEW.`host_resolution_priority`, 'host_workload_type', NEW.`host_workload_type`, 'operating_system', NEW.`operating_system`, 'business_function', NEW.`business_function`, 'data_source', NEW.`data_source`, 'date', NEW.`date`, 'last_user_department', NEW.`last_user_department`, 'problem', NEW.`problem`, 'troubleshooting', NEW.`troubleshooting`, 'patches_updates_photos', NEW.`patches_updates_photos`, 'status_id', NEW.`status_id`, 'level_id', NEW.`level_id`, 'created_by', NEW.`created_by`, 'due_date', NEW.`due_date`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_audit_update` AFTER UPDATE ON `patches_updates` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'ip', OLD.`ip`, 'id_external', OLD.`id_external`, 'inncode', OLD.`inncode`, 'dest', OLD.`dest`, 'dest_ip', OLD.`dest_ip`, 'severity', OLD.`severity`, 'vuln_description', OLD.`vuln_description`, 'base_score', OLD.`base_score`, 'remediation', OLD.`remediation`, 'cve', OLD.`cve`, 'host_ip', OLD.`host_ip`, 'host_mac_manufacturer', OLD.`host_mac_manufacturer`, 'days_since_last_seen', OLD.`days_since_last_seen`, 'host_health_score', OLD.`host_health_score`, 'host_health_reason', OLD.`host_health_reason`, 'host_resolution_priority', OLD.`host_resolution_priority`, 'host_workload_type', OLD.`host_workload_type`, 'operating_system', OLD.`operating_system`, 'business_function', OLD.`business_function`, 'data_source', OLD.`data_source`, 'date', OLD.`date`, 'last_user_department', OLD.`last_user_department`, 'problem', OLD.`problem`, 'troubleshooting', OLD.`troubleshooting`, 'patches_updates_photos', OLD.`patches_updates_photos`, 'status_id', OLD.`status_id`, 'level_id', OLD.`level_id`, 'created_by', OLD.`created_by`, 'due_date', OLD.`due_date`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'ip', NEW.`ip`, 'id_external', NEW.`id_external`, 'inncode', NEW.`inncode`, 'dest', NEW.`dest`, 'dest_ip', NEW.`dest_ip`, 'severity', NEW.`severity`, 'vuln_description', NEW.`vuln_description`, 'base_score', NEW.`base_score`, 'remediation', NEW.`remediation`, 'cve', NEW.`cve`, 'host_ip', NEW.`host_ip`, 'host_mac_manufacturer', NEW.`host_mac_manufacturer`, 'days_since_last_seen', NEW.`days_since_last_seen`, 'host_health_score', NEW.`host_health_score`, 'host_health_reason', NEW.`host_health_reason`, 'host_resolution_priority', NEW.`host_resolution_priority`, 'host_workload_type', NEW.`host_workload_type`, 'operating_system', NEW.`operating_system`, 'business_function', NEW.`business_function`, 'data_source', NEW.`data_source`, 'date', NEW.`date`, 'last_user_department', NEW.`last_user_department`, 'problem', NEW.`problem`, 'troubleshooting', NEW.`troubleshooting`, 'patches_updates_photos', NEW.`patches_updates_photos`, 'status_id', NEW.`status_id`, 'level_id', NEW.`level_id`, 'created_by', NEW.`created_by`, 'due_date', NEW.`due_date`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_audit_delete` AFTER DELETE ON `patches_updates` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'patches_updates', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'ip', OLD.`ip`, 'id_external', OLD.`id_external`, 'inncode', OLD.`inncode`, 'dest', OLD.`dest`, 'dest_ip', OLD.`dest_ip`, 'severity', OLD.`severity`, 'vuln_description', OLD.`vuln_description`, 'base_score', OLD.`base_score`, 'remediation', OLD.`remediation`, 'cve', OLD.`cve`, 'host_ip', OLD.`host_ip`, 'host_mac_manufacturer', OLD.`host_mac_manufacturer`, 'days_since_last_seen', OLD.`days_since_last_seen`, 'host_health_score', OLD.`host_health_score`, 'host_health_reason', OLD.`host_health_reason`, 'host_resolution_priority', OLD.`host_resolution_priority`, 'host_workload_type', OLD.`host_workload_type`, 'operating_system', OLD.`operating_system`, 'business_function', OLD.`business_function`, 'data_source', OLD.`data_source`, 'date', OLD.`date`, 'last_user_department', OLD.`last_user_department`, 'problem', OLD.`problem`, 'troubleshooting', OLD.`troubleshooting`, 'patches_updates_photos', OLD.`patches_updates_photos`, 'status_id', OLD.`status_id`, 'level_id', OLD.`level_id`, 'created_by', OLD.`created_by`, 'due_date', OLD.`due_date`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_printer_device_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_printer_device_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_printer_device_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_printer_device_types_audit_insert` AFTER INSERT ON `printer_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'printer_device_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_printer_device_types_audit_update` AFTER UPDATE ON `printer_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'printer_device_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_printer_device_types_audit_delete` AFTER DELETE ON `printer_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'printer_device_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_rack_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_rack_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_rack_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_rack_statuses_audit_insert` AFTER INSERT ON `rack_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rack_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rack_statuses_audit_update` AFTER UPDATE ON `rack_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rack_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rack_statuses_audit_delete` AFTER DELETE ON `rack_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rack_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_racks_audit_insert`;
DROP TRIGGER IF EXISTS `trg_racks_audit_update`;
DROP TRIGGER IF EXISTS `trg_racks_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_racks_audit_insert` AFTER INSERT ON `racks` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'racks', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'rack_code', NEW.`rack_code`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_racks_audit_update` AFTER UPDATE ON `racks` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'racks', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'rack_code', OLD.`rack_code`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'location_id', NEW.`location_id`, 'name', NEW.`name`, 'rack_code', NEW.`rack_code`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_racks_audit_delete` AFTER DELETE ON `racks` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'racks', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'location_id', OLD.`location_id`, 'name', OLD.`name`, 'rack_code', OLD.`rack_code`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_registration_invitations_audit_insert`;
DROP TRIGGER IF EXISTS `trg_registration_invitations_audit_update`;
DROP TRIGGER IF EXISTS `trg_registration_invitations_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_registration_invitations_audit_insert` AFTER INSERT ON `registration_invitations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'registration_invitations', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'email', NEW.`email`, 'invitation_code', NEW.`invitation_code`, 'invited_by_employee_id', NEW.`invited_by_employee_id`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'expires_at', NEW.`expires_at`, 'accepted_at', NEW.`accepted_at`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_registration_invitations_audit_update` AFTER UPDATE ON `registration_invitations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'registration_invitations', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'email', OLD.`email`, 'invitation_code', OLD.`invitation_code`, 'invited_by_employee_id', OLD.`invited_by_employee_id`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'expires_at', OLD.`expires_at`, 'accepted_at', OLD.`accepted_at`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'email', NEW.`email`, 'invitation_code', NEW.`invitation_code`, 'invited_by_employee_id', NEW.`invited_by_employee_id`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'expires_at', NEW.`expires_at`, 'accepted_at', NEW.`accepted_at`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_registration_invitations_audit_delete` AFTER DELETE ON `registration_invitations` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'registration_invitations', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'email', OLD.`email`, 'invitation_code', OLD.`invitation_code`, 'invited_by_employee_id', OLD.`invited_by_employee_id`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'expires_at', OLD.`expires_at`, 'accepted_at', OLD.`accepted_at`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_supplier_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_supplier_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_supplier_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_supplier_statuses_audit_insert` AFTER INSERT ON `supplier_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'supplier_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_supplier_statuses_audit_update` AFTER UPDATE ON `supplier_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'supplier_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_supplier_statuses_audit_delete` AFTER DELETE ON `supplier_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'supplier_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_suppliers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_suppliers_audit_update`;
DROP TRIGGER IF EXISTS `trg_suppliers_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_suppliers_audit_insert` AFTER INSERT ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'suppliers', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'supplier_code', NEW.`supplier_code`, 'contact_person', NEW.`contact_person`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_suppliers_audit_update` AFTER UPDATE ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'suppliers', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'supplier_code', OLD.`supplier_code`, 'contact_person', OLD.`contact_person`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'supplier_code', NEW.`supplier_code`, 'contact_person', NEW.`contact_person`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'status_id', NEW.`status_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_suppliers_audit_delete` AFTER DELETE ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'suppliers', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'supplier_code', OLD.`supplier_code`, 'contact_person', OLD.`contact_person`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'status_id', OLD.`status_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_cable_colors_audit_insert`;
DROP TRIGGER IF EXISTS `trg_cable_colors_audit_update`;
DROP TRIGGER IF EXISTS `trg_cable_colors_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_cable_colors_audit_insert` AFTER INSERT ON `cable_colors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'cable_colors', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'color_name', NEW.`color_name`, 'hex_color', NEW.`hex_color`, 'comments', NEW.`comments`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_cable_colors_audit_update` AFTER UPDATE ON `cable_colors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'cable_colors', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'color_name', OLD.`color_name`, 'hex_color', OLD.`hex_color`, 'comments', OLD.`comments`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'color_name', NEW.`color_name`, 'hex_color', NEW.`hex_color`, 'comments', NEW.`comments`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_cable_colors_audit_delete` AFTER DELETE ON `cable_colors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'cable_colors', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'color_name', OLD.`color_name`, 'hex_color', OLD.`hex_color`, 'comments', OLD.`comments`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_switch_port_numbering_layout_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_port_numbering_layout_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_port_numbering_layout_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_port_numbering_layout_audit_insert` AFTER INSERT ON `switch_port_numbering_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_port_numbering_layout', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_numbering_layout_audit_update` AFTER UPDATE ON `switch_port_numbering_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_port_numbering_layout', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_numbering_layout_audit_delete` AFTER DELETE ON `switch_port_numbering_layout` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_port_numbering_layout', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_switch_port_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_port_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_port_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_port_types_audit_insert` AFTER INSERT ON `switch_port_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_port_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'type', NEW.`type`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_types_audit_update` AFTER UPDATE ON `switch_port_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_port_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'type', OLD.`type`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'type', NEW.`type`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_port_types_audit_delete` AFTER DELETE ON `switch_port_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_port_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'type', OLD.`type`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_switch_ports_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_ports_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_ports_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_ports_audit_insert` AFTER INSERT ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_ports', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'port_type', NEW.`port_type`, 'port_number', NEW.`port_number`, 'to_patch_port', NEW.`to_patch_port`, 'status_id', NEW.`status_id`, 'color_id', NEW.`color_id`, 'rj45_speed_id', NEW.`rj45_speed_id`, 'vlan_id', NEW.`vlan_id`, 'comments', NEW.`comments`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_ports_audit_update` AFTER UPDATE ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_ports', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'port_type', OLD.`port_type`, 'port_number', OLD.`port_number`, 'to_patch_port', OLD.`to_patch_port`, 'status_id', OLD.`status_id`, 'color_id', OLD.`color_id`, 'rj45_speed_id', OLD.`rj45_speed_id`, 'vlan_id', OLD.`vlan_id`, 'comments', OLD.`comments`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'port_type', NEW.`port_type`, 'port_number', NEW.`port_number`, 'to_patch_port', NEW.`to_patch_port`, 'status_id', NEW.`status_id`, 'color_id', NEW.`color_id`, 'rj45_speed_id', NEW.`rj45_speed_id`, 'vlan_id', NEW.`vlan_id`, 'comments', NEW.`comments`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_ports_audit_delete` AFTER DELETE ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_ports', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'port_type', OLD.`port_type`, 'port_number', OLD.`port_number`, 'to_patch_port', OLD.`to_patch_port`, 'status_id', OLD.`status_id`, 'color_id', OLD.`color_id`, 'rj45_speed_id', OLD.`rj45_speed_id`, 'vlan_id', OLD.`vlan_id`, 'comments', OLD.`comments`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_switch_status_audit_insert`;
DROP TRIGGER IF EXISTS `trg_switch_status_audit_update`;
DROP TRIGGER IF EXISTS `trg_switch_status_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_switch_status_audit_insert` AFTER INSERT ON `switch_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_status', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'status', NEW.`status`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_status_audit_update` AFTER UPDATE ON `switch_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_status', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'status', OLD.`status`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'status', NEW.`status`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_status_audit_delete` AFTER DELETE ON `switch_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'switch_status', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'status', OLD.`status`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_system_access_audit_insert`;
DROP TRIGGER IF EXISTS `trg_system_access_audit_update`;
DROP TRIGGER IF EXISTS `trg_system_access_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_system_access_audit_insert` AFTER INSERT ON `system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'system_access', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'code', NEW.`code`, 'name', NEW.`name`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_system_access_audit_update` AFTER UPDATE ON `system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'system_access', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'code', OLD.`code`, 'name', OLD.`name`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'code', NEW.`code`, 'name', NEW.`name`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_system_access_audit_delete` AFTER DELETE ON `system_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'system_access', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'code', OLD.`code`, 'name', OLD.`name`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_ticket_categories_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ticket_categories_audit_update`;
DROP TRIGGER IF EXISTS `trg_ticket_categories_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ticket_categories_audit_insert` AFTER INSERT ON `ticket_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_categories', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_categories_audit_update` AFTER UPDATE ON `ticket_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_categories', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'code', NEW.`code`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_categories_audit_delete` AFTER DELETE ON `ticket_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_categories', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'code', OLD.`code`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_ticket_priorities_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ticket_priorities_audit_update`;
DROP TRIGGER IF EXISTS `trg_ticket_priorities_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ticket_priorities_audit_insert` AFTER INSERT ON `ticket_priorities` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_priorities', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'level', NEW.`level`, 'color', NEW.`color`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_priorities_audit_update` AFTER UPDATE ON `ticket_priorities` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_priorities', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'level', OLD.`level`, 'color', OLD.`color`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'level', NEW.`level`, 'color', NEW.`color`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_priorities_audit_delete` AFTER DELETE ON `ticket_priorities` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_priorities', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'level', OLD.`level`, 'color', OLD.`color`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_ticket_statuses_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ticket_statuses_audit_update`;
DROP TRIGGER IF EXISTS `trg_ticket_statuses_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ticket_statuses_audit_insert` AFTER INSERT ON `ticket_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_statuses', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_statuses_audit_update` AFTER UPDATE ON `ticket_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_statuses', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ticket_statuses_audit_delete` AFTER DELETE ON `ticket_statuses` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ticket_statuses', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_tickets_audit_insert`;
DROP TRIGGER IF EXISTS `trg_tickets_audit_update`;
DROP TRIGGER IF EXISTS `trg_tickets_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_tickets_audit_insert` AFTER INSERT ON `tickets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'tickets', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'ticket_external_code', NEW.`ticket_external_code`, 'title', NEW.`title`, 'description', NEW.`description`, 'category_id', NEW.`category_id`, 'status_id', NEW.`status_id`, 'priority_id', NEW.`priority_id`, 'created_by_employee_id', NEW.`created_by_employee_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'equipment_id', NEW.`equipment_id`, 'due_date', NEW.`due_date`, 'is_archived', NEW.`is_archived`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_tickets_audit_update` AFTER UPDATE ON `tickets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'tickets', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'ticket_external_code', OLD.`ticket_external_code`, 'title', OLD.`title`, 'description', OLD.`description`, 'category_id', OLD.`category_id`, 'status_id', OLD.`status_id`, 'priority_id', OLD.`priority_id`, 'created_by_employee_id', OLD.`created_by_employee_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'equipment_id', OLD.`equipment_id`, 'due_date', OLD.`due_date`, 'is_archived', OLD.`is_archived`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'ticket_external_code', NEW.`ticket_external_code`, 'title', NEW.`title`, 'description', NEW.`description`, 'category_id', NEW.`category_id`, 'status_id', NEW.`status_id`, 'priority_id', NEW.`priority_id`, 'created_by_employee_id', NEW.`created_by_employee_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'equipment_id', NEW.`equipment_id`, 'due_date', NEW.`due_date`, 'is_archived', NEW.`is_archived`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_tickets_audit_delete` AFTER DELETE ON `tickets` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'tickets', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'ticket_external_code', OLD.`ticket_external_code`, 'title', OLD.`title`, 'description', OLD.`description`, 'category_id', OLD.`category_id`, 'status_id', OLD.`status_id`, 'priority_id', OLD.`priority_id`, 'created_by_employee_id', OLD.`created_by_employee_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'equipment_id', OLD.`equipment_id`, 'due_date', OLD.`due_date`, 'is_archived', OLD.`is_archived`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DELIMITER $$
DROP TRIGGER IF EXISTS `trg_employee_sidebar_preferences_audit_insert`$$
DROP TRIGGER IF EXISTS `trg_employee_sidebar_preferences_audit_update`$$
DROP TRIGGER IF EXISTS `trg_employee_sidebar_preferences_audit_delete`$$

CREATE TRIGGER `trg_employee_sidebar_preferences_audit_insert` AFTER INSERT ON `employee_sidebar_preferences` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_sidebar_preferences', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_employee_sidebar_preferences_audit_update` AFTER UPDATE ON `employee_sidebar_preferences` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_sidebar_preferences', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_employee_sidebar_preferences_audit_delete` AFTER DELETE ON `employee_sidebar_preferences` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_sidebar_preferences', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_update`;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ui_configuration_audit_insert` AFTER INSERT ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ui_configuration', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'table_actions_position', NEW.`table_actions_position`, 'new_button_position', NEW.`new_button_position`, 'export_buttons_position', NEW.`export_buttons_position`, 'back_save_position', NEW.`back_save_position`, 'enable_all_error_reporting', NEW.`enable_all_error_reporting`, 'enable_audit_logs', NEW.`enable_audit_logs`, 'enable_chatbot', NEW.`enable_chatbot`, 'records_per_page', NEW.`records_per_page`, 'app_name', NEW.`app_name`, 'favicon_path', NEW.`favicon_path`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ui_configuration_audit_update` AFTER UPDATE ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ui_configuration', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'table_actions_position', OLD.`table_actions_position`, 'new_button_position', OLD.`new_button_position`, 'export_buttons_position', OLD.`export_buttons_position`, 'back_save_position', OLD.`back_save_position`, 'enable_all_error_reporting', OLD.`enable_all_error_reporting`, 'enable_audit_logs', OLD.`enable_audit_logs`, 'enable_chatbot', OLD.`enable_chatbot`, 'records_per_page', OLD.`records_per_page`, 'app_name', OLD.`app_name`, 'favicon_path', OLD.`favicon_path`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'table_actions_position', NEW.`table_actions_position`, 'new_button_position', NEW.`new_button_position`, 'export_buttons_position', NEW.`export_buttons_position`, 'back_save_position', NEW.`back_save_position`, 'enable_all_error_reporting', NEW.`enable_all_error_reporting`, 'enable_audit_logs', NEW.`enable_audit_logs`, 'enable_chatbot', NEW.`enable_chatbot`, 'records_per_page', NEW.`records_per_page`, 'app_name', NEW.`app_name`, 'favicon_path', NEW.`favicon_path`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ui_configuration_audit_delete` AFTER DELETE ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ui_configuration', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'table_actions_position', OLD.`table_actions_position`, 'new_button_position', OLD.`new_button_position`, 'export_buttons_position', OLD.`export_buttons_position`, 'back_save_position', OLD.`back_save_position`, 'enable_all_error_reporting', OLD.`enable_all_error_reporting`, 'enable_audit_logs', OLD.`enable_audit_logs`, 'records_per_page', OLD.`records_per_page`, 'app_name', OLD.`app_name`, 'favicon_path', OLD.`favicon_path`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_roles_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_roles_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_roles_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_roles_audit_insert` AFTER INSERT ON `employee_roles` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_roles', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_roles_audit_update` AFTER UPDATE ON `employee_roles` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_roles', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_roles_audit_delete` AFTER DELETE ON `employee_roles` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_roles', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_attempts_before_insert`;
DROP TRIGGER IF EXISTS `trg_attempts_audit_insert`;
DROP TRIGGER IF EXISTS `trg_attempts_audit_update`;
DROP TRIGGER IF EXISTS `trg_attempts_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_attempts_before_insert` BEFORE INSERT ON `attempts` FOR EACH ROW
BEGIN
  IF NEW.`company_id` IS NULL THEN
    SET NEW.`company_id` = COALESCE(
      @app_company_id,
      (SELECT `company_id` FROM `employees` WHERE `id` = NEW.`employee_id` LIMIT 1),
      (SELECT `company_id` FROM `employees` WHERE `work_email` = NEW.`email` LIMIT 1),
      (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)
    );
  END IF;
END$$
CREATE TRIGGER `trg_attempts_audit_insert` AFTER INSERT ON `attempts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, (SELECT `company_id` FROM `employees` WHERE `id` = NEW.`employee_id` LIMIT 1), (SELECT `company_id` FROM `employees` WHERE `work_email` = NEW.`email` LIMIT 1), (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)), @app_employee_id, @app_username, @app_email, 'attempts', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'email', NEW.`email`, 'attempt_source', NEW.`attempt_source`, 'attempt_type', NEW.`attempt_type`, 'ip_address', NEW.`ip_address`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_attempts_audit_update` AFTER UPDATE ON `attempts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, (SELECT `company_id` FROM `employees` WHERE `id` = NEW.`employee_id` LIMIT 1), (SELECT `company_id` FROM `employees` WHERE `id` = OLD.`employee_id` LIMIT 1), (SELECT `company_id` FROM `employees` WHERE `work_email` = NEW.`email` LIMIT 1), (SELECT `company_id` FROM `employees` WHERE `work_email` = OLD.`email` LIMIT 1), (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)), @app_employee_id, @app_username, @app_email, 'attempts', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'email', OLD.`email`, 'attempt_source', OLD.`attempt_source`, 'attempt_type', OLD.`attempt_type`, 'ip_address', OLD.`ip_address`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'email', NEW.`email`, 'attempt_source', NEW.`attempt_source`, 'attempt_type', NEW.`attempt_type`, 'ip_address', NEW.`ip_address`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_attempts_audit_delete` AFTER DELETE ON `attempts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, (SELECT `company_id` FROM `employees` WHERE `id` = OLD.`employee_id` LIMIT 1), (SELECT `company_id` FROM `employees` WHERE `work_email` = OLD.`email` LIMIT 1), (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)), @app_employee_id, @app_username, @app_email, 'attempts', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'email', OLD.`email`, 'attempt_source', OLD.`attempt_source`, 'attempt_type', OLD.`attempt_type`, 'ip_address', OLD.`ip_address`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_employee_companies_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_companies_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_companies_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_companies_audit_insert` AFTER INSERT ON `employee_companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_companies', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'granted_by_employee_id', NEW.`granted_by_employee_id`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_companies_audit_update` AFTER UPDATE ON `employee_companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_companies', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'granted_by_employee_id', OLD.`granted_by_employee_id`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'granted_by_employee_id', NEW.`granted_by_employee_id`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_companies_audit_delete` AFTER DELETE ON `employee_companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_companies', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'granted_by_employee_id', OLD.`granted_by_employee_id`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_role_hierarchy_audit_insert`;
DROP TRIGGER IF EXISTS `trg_role_hierarchy_audit_update`;
DROP TRIGGER IF EXISTS `trg_role_hierarchy_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_role_hierarchy_audit_insert` AFTER INSERT ON `role_hierarchy` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_hierarchy', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'hierarchy_order', NEW.`hierarchy_order`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_hierarchy_audit_update` AFTER UPDATE ON `role_hierarchy` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_hierarchy', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'hierarchy_order', OLD.`hierarchy_order`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'hierarchy_order', NEW.`hierarchy_order`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_hierarchy_audit_delete` AFTER DELETE ON `role_hierarchy` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_hierarchy', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'hierarchy_order', OLD.`hierarchy_order`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_role_module_permissions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_role_module_permissions_audit_update`;
DROP TRIGGER IF EXISTS `trg_role_module_permissions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_role_module_permissions_audit_insert` AFTER INSERT ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_module_permissions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'module_name', NEW.`module_name`, 'can_view', NEW.`can_view`, 'can_create', NEW.`can_create`, 'can_edit', NEW.`can_edit`, 'can_delete', NEW.`can_delete`, 'can_import', NEW.`can_import`, 'can_export', NEW.`can_export`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_module_permissions_audit_update` AFTER UPDATE ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_module_permissions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'module_name', OLD.`module_name`, 'can_view', OLD.`can_view`, 'can_create', OLD.`can_create`, 'can_edit', OLD.`can_edit`, 'can_delete', OLD.`can_delete`, 'can_import', OLD.`can_import`, 'can_export', OLD.`can_export`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'module_name', NEW.`module_name`, 'can_view', NEW.`can_view`, 'can_create', NEW.`can_create`, 'can_edit', NEW.`can_edit`, 'can_delete', NEW.`can_delete`, 'can_import', NEW.`can_import`, 'can_export', NEW.`can_export`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_module_permissions_audit_delete` AFTER DELETE ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_module_permissions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'module_name', OLD.`module_name`, 'can_view', OLD.`can_view`, 'can_create', OLD.`can_create`, 'can_edit', OLD.`can_edit`, 'can_delete', OLD.`can_delete`, 'can_import', OLD.`can_import`, 'can_export', OLD.`can_export`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_modules_registry_audit_insert`;
DROP TRIGGER IF EXISTS `trg_modules_registry_audit_update`;
DROP TRIGGER IF EXISTS `trg_modules_registry_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_modules_registry_audit_insert` AFTER INSERT ON `modules_registry` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(NULLIF(@app_company_id, 0), 1), @app_employee_id, @app_username, @app_email, 'modules_registry', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'module_name', NEW.`module_name`, 'module_slug', NEW.`module_slug`, 'icon', NEW.`icon`, 'is_system_module', NEW.`is_system_module`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_modules_registry_audit_update` AFTER UPDATE ON `modules_registry` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(NULLIF(@app_company_id, 0), 1), @app_employee_id, @app_username, @app_email, 'modules_registry', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'module_name', OLD.`module_name`, 'module_slug', OLD.`module_slug`, 'icon', OLD.`icon`, 'is_system_module', OLD.`is_system_module`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'module_name', NEW.`module_name`, 'module_slug', NEW.`module_slug`, 'icon', NEW.`icon`, 'is_system_module', NEW.`is_system_module`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_modules_registry_audit_delete` AFTER DELETE ON `modules_registry` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(NULLIF(@app_company_id, 0), 1), @app_employee_id, @app_username, @app_email, 'modules_registry', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'module_name', OLD.`module_name`, 'module_slug', OLD.`module_slug`, 'icon', OLD.`icon`, 'is_system_module', OLD.`is_system_module`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_company_module_access_audit_insert`;
DROP TRIGGER IF EXISTS `trg_company_module_access_audit_update`;
DROP TRIGGER IF EXISTS `trg_company_module_access_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_company_module_access_audit_insert` AFTER INSERT ON `company_module_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'company_module_access', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'module_id', NEW.`module_id`, 'enabled', NEW.`enabled`, 'icon', NEW.`icon`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_company_module_access_audit_update` AFTER UPDATE ON `company_module_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'company_module_access', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'module_id', OLD.`module_id`, 'enabled', OLD.`enabled`, 'icon', OLD.`icon`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'module_id', NEW.`module_id`, 'enabled', NEW.`enabled`, 'icon', NEW.`icon`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_company_module_access_audit_delete` AFTER DELETE ON `company_module_access` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'company_module_access', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'module_id', OLD.`module_id`, 'enabled', OLD.`enabled`, 'icon', OLD.`icon`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_role_assignment_rights_audit_insert`;
DROP TRIGGER IF EXISTS `trg_role_assignment_rights_audit_update`;
DROP TRIGGER IF EXISTS `trg_role_assignment_rights_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_role_assignment_rights_audit_insert` AFTER INSERT ON `role_assignment_rights` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_assignment_rights', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'can_assign_role_id', NEW.`can_assign_role_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_assignment_rights_audit_update` AFTER UPDATE ON `role_assignment_rights` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_assignment_rights', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'can_assign_role_id', OLD.`can_assign_role_id`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'can_assign_role_id', NEW.`can_assign_role_id`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_assignment_rights_audit_delete` AFTER DELETE ON `role_assignment_rights` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'role_assignment_rights', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'can_assign_role_id', OLD.`can_assign_role_id`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_vlans_audit_insert`;
DROP TRIGGER IF EXISTS `trg_vlans_audit_update`;
DROP TRIGGER IF EXISTS `trg_vlans_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_vlans_audit_insert` AFTER INSERT ON `vlans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'vlans', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'vlan_number', NEW.`vlan_number`, 'vlan_name', NEW.`vlan_name`, 'vlan_color', NEW.`vlan_color`, 'subnet', NEW.`subnet`, 'ip', NEW.`ip`, 'comments', NEW.`comments`, 'gateway_ip', NEW.`gateway_ip`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_vlans_audit_update` AFTER UPDATE ON `vlans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'vlans', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'vlan_number', OLD.`vlan_number`, 'vlan_name', OLD.`vlan_name`, 'vlan_color', OLD.`vlan_color`, 'subnet', OLD.`subnet`, 'ip', OLD.`ip`, 'comments', OLD.`comments`, 'gateway_ip', OLD.`gateway_ip`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'vlan_number', NEW.`vlan_number`, 'vlan_name', NEW.`vlan_name`, 'vlan_color', NEW.`vlan_color`, 'subnet', NEW.`subnet`, 'ip', NEW.`ip`, 'comments', NEW.`comments`, 'gateway_ip', NEW.`gateway_ip`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_vlans_audit_delete` AFTER DELETE ON `vlans` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'vlans', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'vlan_number', OLD.`vlan_number`, 'vlan_name', OLD.`vlan_name`, 'vlan_color', OLD.`vlan_color`, 'subnet', OLD.`subnet`, 'ip', OLD.`ip`, 'comments', OLD.`comments`, 'gateway_ip', OLD.`gateway_ip`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_warranty_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_warranty_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_warranty_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_warranty_types_audit_insert` AFTER INSERT ON `warranty_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'warranty_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_warranty_types_audit_update` AFTER UPDATE ON `warranty_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'warranty_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_warranty_types_audit_delete` AFTER DELETE ON `warranty_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'warranty_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_workstation_device_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_device_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_device_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_device_types_audit_insert` AFTER INSERT ON `workstation_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_device_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_device_types_audit_update` AFTER UPDATE ON `workstation_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_device_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_device_types_audit_delete` AFTER DELETE ON `workstation_device_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_device_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_workstation_modes_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_modes_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_modes_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_modes_audit_insert` AFTER INSERT ON `workstation_modes` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_modes', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'mode_name', NEW.`mode_name`, 'mode_code', NEW.`mode_code`, 'description', NEW.`description`, 'monitor_count', NEW.`monitor_count`, 'has_keyboard_mouse', NEW.`has_keyboard_mouse`, 'pos', NEW.`pos`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_modes_audit_update` AFTER UPDATE ON `workstation_modes` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_modes', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'mode_name', OLD.`mode_name`, 'mode_code', OLD.`mode_code`, 'description', OLD.`description`, 'monitor_count', OLD.`monitor_count`, 'has_keyboard_mouse', OLD.`has_keyboard_mouse`, 'pos', OLD.`pos`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'mode_name', NEW.`mode_name`, 'mode_code', NEW.`mode_code`, 'description', NEW.`description`, 'monitor_count', NEW.`monitor_count`, 'has_keyboard_mouse', NEW.`has_keyboard_mouse`, 'pos', NEW.`pos`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_modes_audit_delete` AFTER DELETE ON `workstation_modes` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_modes', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'mode_name', OLD.`mode_name`, 'mode_code', OLD.`mode_code`, 'description', OLD.`description`, 'monitor_count', OLD.`monitor_count`, 'has_keyboard_mouse', OLD.`has_keyboard_mouse`, 'pos', OLD.`pos`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_workstation_office_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_office_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_office_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_office_audit_insert` AFTER INSERT ON `workstation_office` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_office', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_office_audit_update` AFTER UPDATE ON `workstation_office` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_office', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_office_audit_delete` AFTER DELETE ON `workstation_office` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_office', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_workstation_os_types_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_os_types_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_os_types_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_os_types_audit_insert` AFTER INSERT ON `workstation_os_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_os_types', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_types_audit_update` AFTER UPDATE ON `workstation_os_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_os_types', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_types_audit_delete` AFTER DELETE ON `workstation_os_types` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_os_types', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_workstation_os_versions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_os_versions_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_os_versions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_os_versions_audit_insert` AFTER INSERT ON `workstation_os_versions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_os_versions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_versions_audit_update` AFTER UPDATE ON `workstation_os_versions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_os_versions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_os_versions_audit_delete` AFTER DELETE ON `workstation_os_versions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_os_versions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_workstation_ram_audit_insert`;
DROP TRIGGER IF EXISTS `trg_workstation_ram_audit_update`;
DROP TRIGGER IF EXISTS `trg_workstation_ram_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_workstation_ram_audit_insert` AFTER INSERT ON `workstation_ram` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_ram', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_ram_audit_update` AFTER UPDATE ON `workstation_ram` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_ram', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_workstation_ram_audit_delete` AFTER DELETE ON `workstation_ram` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'workstation_ram', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
-- Table structure for `rack_planner`
DROP TABLE IF EXISTS `rack_planner`;
CREATE TABLE `rack_planner` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rack_units` int NOT NULL DEFAULT '42',
  `layout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rack_planner_name_company` (`company_id`,`name`),
  KEY `rack_planner_company_id` (`company_id`),
  KEY `rack_planner_employee_id` (`employee_id`),
  KEY `rack_planner_status_id` (`status_id`),
  CONSTRAINT `rack_planner_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rack_planner_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rack_planner_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `rack_statuses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rack_planner` (`company_id`, `employee_id`, `id`, `name`, `rack_units`, `layout_json`, `notes`, `status_id`, `active`, `created_at`) VALUES (1, 1, 1, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 1.', 1, 1, '2026-01-01 00:00:01'),
(2, 2, 2, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 2.', 5, 1, '2026-01-01 00:00:01'),
(3, 3, 3, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 3.', 9, 1, '2026-01-01 00:00:01'),
(4, 4, 4, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 4.', 13, 1, '2026-01-01 00:00:01'),
(5, 5, 5, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 5.', 17, 1, '2026-01-01 00:00:01');
DROP TRIGGER IF EXISTS `trg_rack_planner_audit_insert`;
DROP TRIGGER IF EXISTS `trg_rack_planner_audit_update`;
DROP TRIGGER IF EXISTS `trg_rack_planner_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_rack_planner_audit_insert` AFTER INSERT ON `rack_planner` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rack_planner', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'name', NEW.`name`, 'rack_units', NEW.`rack_units`, 'layout_json', NEW.`layout_json`, 'notes', NEW.`notes`, 'status_id', NEW.`status_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rack_planner_audit_update` AFTER UPDATE ON `rack_planner` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rack_planner', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'name', OLD.`name`, 'rack_units', OLD.`rack_units`, 'layout_json', OLD.`layout_json`, 'notes', OLD.`notes`, 'status_id', OLD.`status_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'name', NEW.`name`, 'rack_units', NEW.`rack_units`, 'layout_json', NEW.`layout_json`, 'notes', NEW.`notes`, 'status_id', NEW.`status_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_rack_planner_audit_delete` AFTER DELETE ON `rack_planner` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'rack_planner', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'name', OLD.`name`, 'rack_units', OLD.`rack_units`, 'layout_json', OLD.`layout_json`, 'notes', OLD.`notes`, 'status_id', OLD.`status_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;
-- Table structure for `explorer`
DROP TABLE IF EXISTS `explorer`;
CREATE TABLE `explorer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `folder_path` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_favorite` tinyint(1) DEFAULT '0',
  `is_private` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_explorer_company_path_name` (`company_id`,`folder_path`(191),`file_name`(191)),
  UNIQUE KEY `uq_explorer_user_path_name` (`company_id`,`employee_id`,`folder_path`(191),`file_name`(191)),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `explorer_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `explorer_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `explorer_ibfk_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Triggers for `explorer`
DROP TRIGGER IF EXISTS `trg_explorer_audit_insert`;
DROP TRIGGER IF EXISTS `trg_explorer_audit_update`;
DROP TRIGGER IF EXISTS `trg_explorer_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_explorer_audit_insert` AFTER INSERT ON `explorer` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'explorer', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'folder_path', NEW.`folder_path`, 'file_name', NEW.`file_name`, 'file_type', NEW.`file_type`, 'is_favorite', NEW.`is_favorite`, 'is_private', NEW.`is_private`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_explorer_audit_update` AFTER UPDATE ON `explorer` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'explorer', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'folder_path', OLD.`folder_path`, 'file_name', OLD.`file_name`, 'file_type', OLD.`file_type`, 'is_favorite', OLD.`is_favorite`, 'is_private', OLD.`is_private`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'folder_path', NEW.`folder_path`, 'file_name', NEW.`file_name`, 'file_type', NEW.`file_type`, 'is_favorite', NEW.`is_favorite`, 'is_private', NEW.`is_private`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_explorer_audit_delete` AFTER DELETE ON `explorer` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'explorer', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'folder_path', OLD.`folder_path`, 'file_name', OLD.`file_name`, 'file_type', OLD.`file_type`, 'is_favorite', OLD.`is_favorite`, 'is_private', OLD.`is_private`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

-- Table structure for `event_categories`
DROP TABLE IF EXISTS `event_categories`;
CREATE TABLE `event_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_categories_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `event_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `events`
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id_2` (`company_id`,`title`),
  KEY `company_id` (`company_id`),
  KEY `category_id` (`category_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  CONSTRAINT `events_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_ibfk_assigned_to` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers for `event_categories`
DELIMITER $$
CREATE TRIGGER `trg_event_categories_audit_insert` AFTER INSERT ON `event_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'event_categories', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_event_categories_audit_update` AFTER UPDATE ON `event_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'event_categories', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_event_categories_audit_delete` AFTER DELETE ON `event_categories` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'event_categories', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

-- Triggers for `events`
DELIMITER $$
CREATE TRIGGER `trg_events_audit_insert` AFTER INSERT ON `events` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'events', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'title', NEW.`title`, 'description', NEW.`description`, 'start_datetime', NEW.`start_datetime`, 'end_datetime', NEW.`end_datetime`, 'location', NEW.`location`, 'category_id', NEW.`category_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_events_audit_update` AFTER UPDATE ON `events` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'events', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'title', OLD.`title`, 'description', OLD.`description`, 'start_datetime', OLD.`start_datetime`, 'end_datetime', OLD.`end_datetime`, 'location', OLD.`location`, 'category_id', OLD.`category_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'title', NEW.`title`, 'description', NEW.`description`, 'start_datetime', NEW.`start_datetime`, 'end_datetime', NEW.`end_datetime`, 'location', NEW.`location`, 'category_id', NEW.`category_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_events_audit_delete` AFTER DELETE ON `events` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'events', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'title', OLD.`title`, 'description', OLD.`description`, 'start_datetime', OLD.`start_datetime`, 'end_datetime', OLD.`end_datetime`, 'location', OLD.`location`, 'category_id', OLD.`category_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

-- Data for `event_categories`
INSERT INTO `event_categories` (`company_id`, `name`, `color`) VALUES
(1, 'Meeting', '#3b82f6'),
(1, 'Maintenance', '#ef4444'),
(1, 'Holiday', '#10b981'),
(1, 'Other', '#6b7280'),
(2, 'Meeting', '#3b82f6'),
(2, 'Maintenance', '#ef4444'),
(2, 'Holiday', '#10b981'),
(2, 'Other', '#6b7280'),
(3, 'Meeting', '#3b82f6'),
(3, 'Maintenance', '#ef4444'),
(3, 'Holiday', '#10b981'),
(3, 'Other', '#6b7280'),
(4, 'Meeting', '#3b82f6'),
(4, 'Maintenance', '#ef4444'),
(4, 'Holiday', '#10b981'),
(4, 'Other', '#6b7280'),
(5, 'Meeting', '#3b82f6'),
(5, 'Maintenance', '#ef4444'),
(5, 'Holiday', '#10b981'),
(5, 'Other', '#6b7280');

-- Data for `events`
INSERT INTO `events` (`company_id`, `assigned_to_employee_id`, `created_by`, `title`, `description`, `start_datetime`, `end_datetime`, `location`, `category_id`, `active`) VALUES
(1,1,1, 'Project Kickoff', 'Initial meeting for the new project', '2026-05-01 09:00:00', '2026-05-01 11:00:00', 'Meeting Room A', 1, 1),
(1, NULL, NULL, 'Server Maintenance', 'Monthly server updates and backup verification', '2026-05-15 22:00:00', '2026-05-16 02:00:00', 'Data Center', 2, 1),
(1, NULL, NULL, 'Team Lunch', 'Monthly team building lunch', '2026-05-20 12:00:00', '2026-05-20 13:30:00', 'Local Restaurant', 4, 1);

-- Table structure for `visitors_access_log`
DROP TABLE IF EXISTS `visitors_access_log`;
CREATE TABLE `visitors_access_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `visitor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason_for_visit` text COLLATE utf8mb4_unicode_ci,
  `pre_approved_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_opened_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_time_in` datetime DEFAULT NULL,
  `date_time_out` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_visitors_access_log_company_scope` (`company_id`, `visitor_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `visitors_access_log_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Triggers for `visitors_access_log`
DELIMITER //
CREATE TRIGGER `trg_visitors_access_log_audit_insert` AFTER INSERT ON `visitors_access_log` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'visitors_access_log', NEW.`id`, 'INSERT', NULL, JSON_OBJECT('visitor_name', NEW.`visitor_name`, 'company_department', NEW.`company_department`, 'reason_for_visit', NEW.`reason_for_visit`, 'pre_approved_by', NEW.`pre_approved_by`, 'room_opened_by', NEW.`room_opened_by`, 'date_time_in', NEW.`date_time_in`, 'date_time_out', NEW.`date_time_out`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_visitors_access_log_audit_update` AFTER UPDATE ON `visitors_access_log` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'visitors_access_log', NEW.`id`, 'UPDATE', JSON_OBJECT('visitor_name', OLD.`visitor_name`, 'company_department', OLD.`company_department`, 'reason_for_visit', OLD.`reason_for_visit`, 'pre_approved_by', OLD.`pre_approved_by`, 'room_opened_by', OLD.`room_opened_by`, 'date_time_in', OLD.`date_time_in`, 'date_time_out', OLD.`date_time_out`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('visitor_name', NEW.`visitor_name`, 'company_department', NEW.`company_department`, 'reason_for_visit', NEW.`reason_for_visit`, 'pre_approved_by', NEW.`pre_approved_by`, 'room_opened_by', NEW.`room_opened_by`, 'date_time_in', NEW.`date_time_in`, 'date_time_out', NEW.`date_time_out`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_visitors_access_log_audit_delete` AFTER DELETE ON `visitors_access_log` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'visitors_access_log', OLD.`id`, 'DELETE', JSON_OBJECT('visitor_name', OLD.`visitor_name`, 'company_department', OLD.`company_department`, 'reason_for_visit', OLD.`reason_for_visit`, 'pre_approved_by', OLD.`pre_approved_by`, 'room_opened_by', OLD.`room_opened_by`, 'date_time_in', OLD.`date_time_in`, 'date_time_out', OLD.`date_time_out`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END//
DELIMITER ;

-- Table structure for `backup_tape_log`
DROP TABLE IF EXISTS `backup_tape_log`;
CREATE TABLE `backup_tape_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `server_id` int NOT NULL,
  `log_date` date NOT NULL,
  `tape_to_be_used` varchar(50) NOT NULL,
  `time_tape_inserted` datetime DEFAULT NULL,
  `time_returned_to_safe` datetime DEFAULT NULL,
  `print_name` varchar(255) NOT NULL DEFAULT '',
  `backup_status` ENUM('Full', 'Part', 'Fail') NOT NULL DEFAULT 'Full',
  `problem_details` text,
  `tape_used_for_restore` tinyint(1) DEFAULT '0',
  `ism_review` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup_tape_log_scope` (`company_id`,`server_id`,`log_date`),
  KEY `company_id` (`company_id`),
  KEY `server_id` (`server_id`),
  CONSTRAINT `backup_tape_log_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `backup_tape_log_ibfk_server` FOREIGN KEY (`server_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Triggers for `backup_tape_log`
DELIMITER //
CREATE TRIGGER `trg_backup_tape_log_audit_insert` AFTER INSERT ON `backup_tape_log` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'backup_tape_log', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('server_id', NEW.`server_id`, 'log_date', NEW.`log_date`, 'tape_to_be_used', NEW.`tape_to_be_used`, 'time_tape_inserted', NEW.`time_tape_inserted`, 'time_returned_to_safe', NEW.`time_returned_to_safe`, 'print_name', NEW.`print_name`, 'backup_status', NEW.`backup_status`, 'problem_details', NEW.`problem_details`, 'tape_used_for_restore', NEW.`tape_used_for_restore`, 'ism_review', NEW.`ism_review`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_backup_tape_log_audit_update` AFTER UPDATE ON `backup_tape_log` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'backup_tape_log', NEW.`id`, 'UPDATE',
  JSON_OBJECT('server_id', OLD.`server_id`, 'log_date', OLD.`log_date`, 'tape_to_be_used', OLD.`tape_to_be_used`, 'time_tape_inserted', OLD.`time_tape_inserted`, 'time_returned_to_safe', OLD.`time_returned_to_safe`, 'print_name', OLD.`print_name`, 'backup_status', OLD.`backup_status`, 'problem_details', OLD.`problem_details`, 'tape_used_for_restore', OLD.`tape_used_for_restore`, 'ism_review', OLD.`ism_review`, 'active', OLD.`active`),
  JSON_OBJECT('server_id', NEW.`server_id`, 'log_date', NEW.`log_date`, 'tape_to_be_used', NEW.`tape_to_be_used`, 'time_tape_inserted', NEW.`time_tape_inserted`, 'time_returned_to_safe', NEW.`time_returned_to_safe`, 'print_name', NEW.`print_name`, 'backup_status', NEW.`backup_status`, 'problem_details', NEW.`problem_details`, 'tape_used_for_restore', NEW.`tape_used_for_restore`, 'ism_review', NEW.`ism_review`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_backup_tape_log_audit_delete` AFTER DELETE ON `backup_tape_log` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'backup_tape_log', OLD.`id`, 'DELETE',
  JSON_OBJECT('server_id', OLD.`server_id`, 'log_date', OLD.`log_date`, 'tape_to_be_used', OLD.`tape_to_be_used`, 'time_tape_inserted', OLD.`time_tape_inserted`, 'time_returned_to_safe', OLD.`time_returned_to_safe`, 'print_name', OLD.`print_name`, 'backup_status', OLD.`backup_status`, 'problem_details', OLD.`problem_details`, 'tape_used_for_restore', OLD.`tape_used_for_restore`, 'ism_review', OLD.`ism_review`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
DELIMITER ;

-- Table structure for `ops_report`
DROP TABLE IF EXISTS `ops_report_night_shift`;
DROP TABLE IF EXISTS `ops_report_hotel_figure`;
DROP TABLE IF EXISTS `ops_report_butler`;
DROP TABLE IF EXISTS `ops_report_guest_experience`;
DROP TABLE IF EXISTS `ops_report_courtesy_call`;
DROP TABLE IF EXISTS `ops_report_walk_round`;
DROP TABLE IF EXISTS `ops_report_fb_outlet`;
DROP TABLE IF EXISTS `ops_report`;
CREATE TABLE `ops_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `report_date` date NOT NULL,
  `today_shift` text,
  `tomorrow_shift` text,
  `occupancy_pct` varchar(20) DEFAULT NULL,
  `occupied_rooms` varchar(20) DEFAULT NULL,
  `total_pax` varchar(20) DEFAULT NULL,
  `average_daily_rate` decimal(12,2) DEFAULT NULL,
  `revpar` decimal(12,2) DEFAULT NULL,
  `room_revenue` decimal(14,2) DEFAULT NULL,
  `fb_revenue` decimal(14,2) DEFAULT NULL,
  `spa_revenue` decimal(14,2) DEFAULT NULL,
  `kids_club_revenue` decimal(14,2) DEFAULT NULL,
  `fo_upgrade_rooms` decimal(14,2) DEFAULT NULL,
  `total_revenue` decimal(14,2) DEFAULT NULL,
  `stay_score_target` varchar(20) DEFAULT NULL,
  `stay_score_ytd` varchar(50) DEFAULT NULL,
  `stay_experience_comment` text,
  `hsk_revenue` decimal(14,2) DEFAULT NULL,
  `welcomes_notes` text,
  `report_ui_json` longtext,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_scope` (`company_id`,`report_date`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ops_report_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_fb_outlet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `outlet_name` varchar(255) NOT NULL,
  `covers_breakfast` varchar(20) DEFAULT NULL,
  `covers_lunch` varchar(20) DEFAULT NULL,
  `covers_dinner` varchar(20) DEFAULT NULL,
  `covers_dado` varchar(20) DEFAULT NULL,
  `covers_pool` varchar(20) DEFAULT NULL,
  `covers_brunch` varchar(20) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_fb_outlet_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_fb_outlet_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_fb_outlet_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_walk_round` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `area_name` varchar(255) NOT NULL,
  `early_shift` varchar(255) DEFAULT NULL,
  `late_shift` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_walk_round_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_walk_round_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_walk_round_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_courtesy_call` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `time_reported` varchar(50) DEFAULT NULL,
  `checkout_date` varchar(50) DEFAULT NULL,
  `notes` text,
  `action_taken` text,
  `case_closed` varchar(10) DEFAULT NULL,
  `monitor` varchar(50) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_courtesy_call_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_courtesy_call_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_courtesy_call_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_guest_experience` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `ref_id` varchar(50) DEFAULT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `time_reported` varchar(50) DEFAULT NULL,
  `checkout_date` varchar(50) DEFAULT NULL,
  `feedback` text,
  `action_taken` text,
  `case_closed` varchar(10) DEFAULT NULL,
  `monitor` varchar(50) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_guest_experience_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_guest_experience_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_guest_experience_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_butler` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `notes` text,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_butler_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_butler_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_butler_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_night_shift` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `notes` text,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_night_shift_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_night_shift_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_night_shift_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ops_report_hotel_figure` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ops_report_id` int NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_value` varchar(255) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ops_report_hotel_figure_company_scope` (`company_id`, `ops_report_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `ops_report_id` (`ops_report_id`),
  CONSTRAINT `ops_report_hotel_figure_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_report_hotel_figure_ibfk_report` FOREIGN KEY (`ops_report_id`) REFERENCES `ops_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports Hub sample data: ops_report daily trend + YoY anchors (company 1)
INSERT INTO `ops_report` (`company_id`, `report_date`, `occupancy_pct`, `average_daily_rate`, `revpar`, `room_revenue`, `fb_revenue`, `spa_revenue`, `kids_club_revenue`, `hsk_revenue`, `fo_upgrade_rooms`, `total_revenue`, `active`, `created_at`) VALUES
(1, '2025-01-15', '70.0', 165.00, 115.50, 32798.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 52900.00, 1, '2025-01-15 08:00:00'),
(1, '2025-02-15', '70.0', 165.00, 115.50, 33356.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 53800.00, 1, '2025-02-15 08:00:00'),
(1, '2025-03-15', '70.0', 165.00, 115.50, 33914.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 54700.00, 1, '2025-03-15 08:00:00'),
(1, '2025-04-15', '70.0', 165.00, 115.50, 34472.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 55600.00, 1, '2025-04-15 08:00:00'),
(1, '2025-05-15', '70.0', 165.00, 115.50, 35030.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 56500.00, 1, '2025-05-15 08:00:00'),
(1, '2025-06-15', '70.0', 165.00, 115.50, 35588.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 57400.00, 1, '2025-06-15 08:00:00'),
(1, '2025-07-15', '70.0', 165.00, 115.50, 36146.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 58300.00, 1, '2025-07-15 08:00:00'),
(1, '2025-08-15', '70.0', 165.00, 115.50, 36704.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 59200.00, 1, '2025-08-15 08:00:00'),
(1, '2025-09-15', '70.0', 165.00, 115.50, 37262.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 60100.00, 1, '2025-09-15 08:00:00'),
(1, '2025-10-15', '70.0', 165.00, 115.50, 37820.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 61000.00, 1, '2025-10-15 08:00:00'),
(1, '2025-11-15', '70.0', 165.00, 115.50, 38378.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 61900.00, 1, '2025-11-15 08:00:00'),
(1, '2025-12-15', '70.0', 165.00, 115.50, 38936.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 62800.00, 1, '2025-12-15 08:00:00'),
(1, '2026-01-15', '74.0', 172.00, 127.28, 35168.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 54950.00, 1, '2026-01-15 08:00:00'),
(1, '2026-02-15', '74.0', 172.00, 127.28, 35776.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 55900.00, 1, '2026-02-15 08:00:00'),
(1, '2026-03-15', '74.0', 172.00, 127.28, 36384.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 56850.00, 1, '2026-03-15 08:00:00'),
(1, '2026-04-15', '74.0', 172.00, 127.28, 36992.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 57800.00, 1, '2026-04-15 08:00:00'),
(1, '2026-05-15', '74.0', 172.00, 127.28, 37600.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 58750.00, 1, '2026-05-15 08:00:00'),
(1, '2026-06-17', '72.0', 175.00, 126.00, 38000.00, 11000.00, 2800.00, 650.00, 1200.00, 900.00, 54550.00, 1, '2026-06-17 08:00:00'),
(1, '2026-06-18', '73.0', 178.00, 129.94, 38150.00, 11040.00, 2815.00, 655.00, 1210.00, 908.00, 54778.00, 1, '2026-06-18 08:00:00'),
(1, '2026-06-19', '74.0', 181.00, 133.94, 38300.00, 11080.00, 2830.00, 660.00, 1220.00, 916.00, 55006.00, 1, '2026-06-19 08:00:00'),
(1, '2026-06-20', '75.0', 184.00, 138.00, 38450.00, 11120.00, 2845.00, 665.00, 1230.00, 924.00, 55234.00, 1, '2026-06-20 08:00:00'),
(1, '2026-06-21', '76.0', 187.00, 142.12, 38600.00, 11160.00, 2860.00, 670.00, 1240.00, 932.00, 55462.00, 1, '2026-06-21 08:00:00'),
(1, '2026-06-22', '77.0', 175.00, 134.75, 38750.00, 11200.00, 2875.00, 675.00, 1250.00, 940.00, 55690.00, 1, '2026-06-22 08:00:00'),
(1, '2026-06-23', '78.0', 178.00, 138.84, 38900.00, 11240.00, 2890.00, 680.00, 1260.00, 948.00, 55918.00, 1, '2026-06-23 08:00:00'),
(1, '2026-06-24', '79.0', 181.00, 142.99, 39050.00, 11280.00, 2905.00, 685.00, 1270.00, 956.00, 56146.00, 1, '2026-06-24 08:00:00'),
(1, '2026-06-25', '80.0', 184.00, 147.20, 39200.00, 11320.00, 2920.00, 690.00, 1280.00, 964.00, 56374.00, 1, '2026-06-25 08:00:00'),
(1, '2026-06-26', '81.0', 187.00, 151.47, 39350.00, 11360.00, 2935.00, 695.00, 1290.00, 972.00, 56602.00, 1, '2026-06-26 08:00:00'),
(1, '2026-06-27', '82.0', 175.00, 143.50, 39500.00, 11400.00, 2950.00, 700.00, 1300.00, 980.00, 56830.00, 1, '2026-06-27 08:00:00'),
(1, '2026-06-28', '83.0', 178.00, 147.74, 39650.00, 11440.00, 2965.00, 705.00, 1310.00, 988.00, 57058.00, 1, '2026-06-28 08:00:00'),
(1, '2026-06-29', '72.0', 181.00, 130.32, 39800.00, 11480.00, 2980.00, 710.00, 1320.00, 996.00, 57286.00, 1, '2026-06-29 08:00:00'),
(1, '2026-06-30', '73.0', 184.00, 134.32, 39950.00, 11520.00, 2995.00, 715.00, 1330.00, 1004.00, 57514.00, 1, '2026-06-30 08:00:00'),
(1, '2026-07-01', '74.0', 187.00, 138.38, 40100.00, 11560.00, 3010.00, 720.00, 1340.00, 1012.00, 57742.00, 1, '2026-07-01 08:00:00'),
(1, '2026-07-02', '75.0', 175.00, 131.25, 40250.00, 11600.00, 3025.00, 725.00, 1350.00, 1020.00, 57970.00, 1, '2026-07-02 08:00:00'),
(1, '2026-07-03', '76.0', 178.00, 135.28, 40400.00, 11640.00, 3040.00, 730.00, 1360.00, 1028.00, 58198.00, 1, '2026-07-03 08:00:00'),
(1, '2026-07-04', '77.0', 181.00, 139.37, 40550.00, 11680.00, 3055.00, 735.00, 1370.00, 1036.00, 58426.00, 1, '2026-07-04 08:00:00'),
(1, '2026-07-05', '78.0', 184.00, 143.52, 40700.00, 11720.00, 3070.00, 740.00, 1380.00, 1044.00, 58654.00, 1, '2026-07-05 08:00:00'),
(1, '2026-07-06', '79.0', 187.00, 147.73, 40850.00, 11760.00, 3085.00, 745.00, 1390.00, 1052.00, 58882.00, 1, '2026-07-06 08:00:00'),
(1, '2026-07-07', '80.0', 175.00, 140.00, 41000.00, 11800.00, 3100.00, 750.00, 1400.00, 1060.00, 59110.00, 1, '2026-07-07 08:00:00'),
(1, '2026-07-08', '81.0', 178.00, 144.18, 41150.00, 11840.00, 3115.00, 755.00, 1410.00, 1068.00, 59338.00, 1, '2026-07-08 08:00:00'),
(1, '2026-07-09', '82.0', 181.00, 148.42, 41300.00, 11880.00, 3130.00, 760.00, 1420.00, 1076.00, 59566.00, 1, '2026-07-09 08:00:00'),
(1, '2026-07-10', '83.0', 184.00, 152.72, 41450.00, 11920.00, 3145.00, 765.00, 1430.00, 1084.00, 59794.00, 1, '2026-07-10 08:00:00'),
(1, '2026-07-11', '72.0', 187.00, 134.64, 41600.00, 11960.00, 3160.00, 770.00, 1440.00, 1092.00, 60022.00, 1, '2026-07-11 08:00:00'),
(1, '2026-07-12', '73.0', 175.00, 127.75, 41750.00, 12000.00, 3175.00, 775.00, 1450.00, 1100.00, 60250.00, 1, '2026-07-12 08:00:00'),
(1, '2026-07-13', '74.0', 178.00, 131.72, 41900.00, 12040.00, 3190.00, 780.00, 1460.00, 1108.00, 60478.00, 1, '2026-07-13 08:00:00'),
(1, '2026-07-14', '75.0', 181.00, 135.75, 42050.00, 12080.00, 3205.00, 785.00, 1470.00, 1116.00, 60706.00, 1, '2026-07-14 08:00:00'),
(1, '2026-07-15', '76.0', 184.00, 139.84, 42200.00, 12120.00, 3220.00, 790.00, 1480.00, 1124.00, 60934.00, 1, '2026-07-15 08:00:00'),
(1, '2026-07-16', '77.0', 187.00, 143.99, 42350.00, 12160.00, 3235.00, 795.00, 1490.00, 1132.00, 61162.00, 1, '2026-07-16 08:00:00');
INSERT INTO `ops_report_fb_outlet` (`company_id`, `ops_report_id`, `outlet_name`, `covers_breakfast`, `covers_lunch`, `covers_dinner`, `sort_order`, `active`)
SELECT 1, r.id, 'OLIVEIRA BRASSERIE', '40', '55', '70', 0, 1
FROM `ops_report` r
WHERE r.company_id = 1 AND r.report_date BETWEEN '2026-07-01' AND '2026-07-16' AND r.active = 1;
INSERT INTO `ops_report_fb_outlet` (`company_id`, `ops_report_id`, `outlet_name`, `covers_breakfast`, `covers_lunch`, `covers_dinner`, `sort_order`, `active`)
SELECT 1, r.id, 'IN-ROOM DINING', '45', '62', '79', 1, 1
FROM `ops_report` r
WHERE r.company_id = 1 AND r.report_date BETWEEN '2026-07-01' AND '2026-07-16' AND r.active = 1;
INSERT INTO `ops_report_fb_outlet` (`company_id`, `ops_report_id`, `outlet_name`, `covers_breakfast`, `covers_lunch`, `covers_dinner`, `sort_order`, `active`)
SELECT 1, r.id, 'THE NEST COCKTAILS & BAR', '50', '69', '88', 2, 1
FROM `ops_report` r
WHERE r.company_id = 1 AND r.report_date BETWEEN '2026-07-01' AND '2026-07-16' AND r.active = 1;

-- Audit Triggers for `ops_report` and child tables
DELIMITER //
CREATE TRIGGER `trg_ops_report_audit_insert` AFTER INSERT ON `ops_report` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('report_date', NEW.`report_date`, 'today_shift', NEW.`today_shift`, 'tomorrow_shift', NEW.`tomorrow_shift`, 'occupancy_pct', NEW.`occupancy_pct`, 'occupied_rooms', NEW.`occupied_rooms`, 'total_pax', NEW.`total_pax`, 'average_daily_rate', NEW.`average_daily_rate`, 'revpar', NEW.`revpar`, 'room_revenue', NEW.`room_revenue`, 'fb_revenue', NEW.`fb_revenue`, 'spa_revenue', NEW.`spa_revenue`, 'kids_club_revenue', NEW.`kids_club_revenue`, 'fo_upgrade_rooms', NEW.`fo_upgrade_rooms`, 'total_revenue', NEW.`total_revenue`, 'stay_score_target', NEW.`stay_score_target`, 'stay_score_ytd', NEW.`stay_score_ytd`, 'stay_experience_comment', NEW.`stay_experience_comment`, 'hsk_revenue', NEW.`hsk_revenue`, 'welcomes_notes', NEW.`welcomes_notes`, 'report_ui_json', NEW.`report_ui_json`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_audit_update` AFTER UPDATE ON `ops_report` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report', NEW.`id`, 'UPDATE',
  JSON_OBJECT('report_date', OLD.`report_date`, 'today_shift', OLD.`today_shift`, 'tomorrow_shift', OLD.`tomorrow_shift`, 'occupancy_pct', OLD.`occupancy_pct`, 'occupied_rooms', OLD.`occupied_rooms`, 'total_pax', OLD.`total_pax`, 'average_daily_rate', OLD.`average_daily_rate`, 'revpar', OLD.`revpar`, 'room_revenue', OLD.`room_revenue`, 'fb_revenue', OLD.`fb_revenue`, 'spa_revenue', OLD.`spa_revenue`, 'kids_club_revenue', OLD.`kids_club_revenue`, 'fo_upgrade_rooms', OLD.`fo_upgrade_rooms`, 'total_revenue', OLD.`total_revenue`, 'stay_score_target', OLD.`stay_score_target`, 'stay_score_ytd', OLD.`stay_score_ytd`, 'stay_experience_comment', OLD.`stay_experience_comment`, 'hsk_revenue', OLD.`hsk_revenue`, 'welcomes_notes', OLD.`welcomes_notes`, 'report_ui_json', OLD.`report_ui_json`, 'active', OLD.`active`),
  JSON_OBJECT('report_date', NEW.`report_date`, 'today_shift', NEW.`today_shift`, 'tomorrow_shift', NEW.`tomorrow_shift`, 'occupancy_pct', NEW.`occupancy_pct`, 'occupied_rooms', NEW.`occupied_rooms`, 'total_pax', NEW.`total_pax`, 'average_daily_rate', NEW.`average_daily_rate`, 'revpar', NEW.`revpar`, 'room_revenue', NEW.`room_revenue`, 'fb_revenue', NEW.`fb_revenue`, 'spa_revenue', NEW.`spa_revenue`, 'kids_club_revenue', NEW.`kids_club_revenue`, 'fo_upgrade_rooms', NEW.`fo_upgrade_rooms`, 'total_revenue', NEW.`total_revenue`, 'stay_score_target', NEW.`stay_score_target`, 'stay_score_ytd', NEW.`stay_score_ytd`, 'stay_experience_comment', NEW.`stay_experience_comment`, 'hsk_revenue', NEW.`hsk_revenue`, 'welcomes_notes', NEW.`welcomes_notes`, 'report_ui_json', NEW.`report_ui_json`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_audit_delete` AFTER DELETE ON `ops_report` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report', OLD.`id`, 'DELETE',
  JSON_OBJECT('report_date', OLD.`report_date`, 'today_shift', OLD.`today_shift`, 'tomorrow_shift', OLD.`tomorrow_shift`, 'occupancy_pct', OLD.`occupancy_pct`, 'occupied_rooms', OLD.`occupied_rooms`, 'total_pax', OLD.`total_pax`, 'average_daily_rate', OLD.`average_daily_rate`, 'revpar', OLD.`revpar`, 'room_revenue', OLD.`room_revenue`, 'fb_revenue', OLD.`fb_revenue`, 'spa_revenue', OLD.`spa_revenue`, 'kids_club_revenue', OLD.`kids_club_revenue`, 'fo_upgrade_rooms', OLD.`fo_upgrade_rooms`, 'total_revenue', OLD.`total_revenue`, 'stay_score_target', OLD.`stay_score_target`, 'stay_score_ytd', OLD.`stay_score_ytd`, 'stay_experience_comment', OLD.`stay_experience_comment`, 'hsk_revenue', OLD.`hsk_revenue`, 'welcomes_notes', OLD.`welcomes_notes`, 'report_ui_json', OLD.`report_ui_json`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_fb_outlet_audit_insert` AFTER INSERT ON `ops_report_fb_outlet` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_fb_outlet', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'outlet_name', NEW.`outlet_name`, 'covers_breakfast', NEW.`covers_breakfast`, 'covers_lunch', NEW.`covers_lunch`, 'covers_dinner', NEW.`covers_dinner`, 'covers_dado', NEW.`covers_dado`, 'covers_pool', NEW.`covers_pool`, 'covers_brunch', NEW.`covers_brunch`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_fb_outlet_audit_update` AFTER UPDATE ON `ops_report_fb_outlet` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_fb_outlet', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'outlet_name', OLD.`outlet_name`, 'covers_breakfast', OLD.`covers_breakfast`, 'covers_lunch', OLD.`covers_lunch`, 'covers_dinner', OLD.`covers_dinner`, 'covers_dado', OLD.`covers_dado`, 'covers_pool', OLD.`covers_pool`, 'covers_brunch', OLD.`covers_brunch`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'outlet_name', NEW.`outlet_name`, 'covers_breakfast', NEW.`covers_breakfast`, 'covers_lunch', NEW.`covers_lunch`, 'covers_dinner', NEW.`covers_dinner`, 'covers_dado', NEW.`covers_dado`, 'covers_pool', NEW.`covers_pool`, 'covers_brunch', NEW.`covers_brunch`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_fb_outlet_audit_delete` AFTER DELETE ON `ops_report_fb_outlet` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_fb_outlet', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'outlet_name', OLD.`outlet_name`, 'covers_breakfast', OLD.`covers_breakfast`, 'covers_lunch', OLD.`covers_lunch`, 'covers_dinner', OLD.`covers_dinner`, 'covers_dado', OLD.`covers_dado`, 'covers_pool', OLD.`covers_pool`, 'covers_brunch', OLD.`covers_brunch`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_walk_round_audit_insert` AFTER INSERT ON `ops_report_walk_round` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_walk_round', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'area_name', NEW.`area_name`, 'early_shift', NEW.`early_shift`, 'late_shift', NEW.`late_shift`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_walk_round_audit_update` AFTER UPDATE ON `ops_report_walk_round` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_walk_round', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'area_name', OLD.`area_name`, 'early_shift', OLD.`early_shift`, 'late_shift', OLD.`late_shift`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'area_name', NEW.`area_name`, 'early_shift', NEW.`early_shift`, 'late_shift', NEW.`late_shift`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_walk_round_audit_delete` AFTER DELETE ON `ops_report_walk_round` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_walk_round', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'area_name', OLD.`area_name`, 'early_shift', OLD.`early_shift`, 'late_shift', OLD.`late_shift`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_courtesy_call_audit_insert` AFTER INSERT ON `ops_report_courtesy_call` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_courtesy_call', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'guest_name', NEW.`guest_name`, 'room_number', NEW.`room_number`, 'time_reported', NEW.`time_reported`, 'checkout_date', NEW.`checkout_date`, 'notes', NEW.`notes`, 'action_taken', NEW.`action_taken`, 'case_closed', NEW.`case_closed`, 'monitor', NEW.`monitor`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_courtesy_call_audit_update` AFTER UPDATE ON `ops_report_courtesy_call` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_courtesy_call', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'guest_name', OLD.`guest_name`, 'room_number', OLD.`room_number`, 'time_reported', OLD.`time_reported`, 'checkout_date', OLD.`checkout_date`, 'notes', OLD.`notes`, 'action_taken', OLD.`action_taken`, 'case_closed', OLD.`case_closed`, 'monitor', OLD.`monitor`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'guest_name', NEW.`guest_name`, 'room_number', NEW.`room_number`, 'time_reported', NEW.`time_reported`, 'checkout_date', NEW.`checkout_date`, 'notes', NEW.`notes`, 'action_taken', NEW.`action_taken`, 'case_closed', NEW.`case_closed`, 'monitor', NEW.`monitor`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_courtesy_call_audit_delete` AFTER DELETE ON `ops_report_courtesy_call` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_courtesy_call', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'guest_name', OLD.`guest_name`, 'room_number', OLD.`room_number`, 'time_reported', OLD.`time_reported`, 'checkout_date', OLD.`checkout_date`, 'notes', OLD.`notes`, 'action_taken', OLD.`action_taken`, 'case_closed', OLD.`case_closed`, 'monitor', OLD.`monitor`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_guest_experience_audit_insert` AFTER INSERT ON `ops_report_guest_experience` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_guest_experience', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'ref_id', NEW.`ref_id`, 'guest_name', NEW.`guest_name`, 'room_number', NEW.`room_number`, 'time_reported', NEW.`time_reported`, 'checkout_date', NEW.`checkout_date`, 'feedback', NEW.`feedback`, 'action_taken', NEW.`action_taken`, 'case_closed', NEW.`case_closed`, 'monitor', NEW.`monitor`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_guest_experience_audit_update` AFTER UPDATE ON `ops_report_guest_experience` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_guest_experience', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'ref_id', OLD.`ref_id`, 'guest_name', OLD.`guest_name`, 'room_number', OLD.`room_number`, 'time_reported', OLD.`time_reported`, 'checkout_date', OLD.`checkout_date`, 'feedback', OLD.`feedback`, 'action_taken', OLD.`action_taken`, 'case_closed', OLD.`case_closed`, 'monitor', OLD.`monitor`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'ref_id', NEW.`ref_id`, 'guest_name', NEW.`guest_name`, 'room_number', NEW.`room_number`, 'time_reported', NEW.`time_reported`, 'checkout_date', NEW.`checkout_date`, 'feedback', NEW.`feedback`, 'action_taken', NEW.`action_taken`, 'case_closed', NEW.`case_closed`, 'monitor', NEW.`monitor`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_guest_experience_audit_delete` AFTER DELETE ON `ops_report_guest_experience` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_guest_experience', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'ref_id', OLD.`ref_id`, 'guest_name', OLD.`guest_name`, 'room_number', OLD.`room_number`, 'time_reported', OLD.`time_reported`, 'checkout_date', OLD.`checkout_date`, 'feedback', OLD.`feedback`, 'action_taken', OLD.`action_taken`, 'case_closed', OLD.`case_closed`, 'monitor', OLD.`monitor`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_butler_audit_insert` AFTER INSERT ON `ops_report_butler` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_butler', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'room_number', NEW.`room_number`, 'notes', NEW.`notes`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_butler_audit_update` AFTER UPDATE ON `ops_report_butler` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_butler', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'room_number', OLD.`room_number`, 'notes', OLD.`notes`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'room_number', NEW.`room_number`, 'notes', NEW.`notes`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_butler_audit_delete` AFTER DELETE ON `ops_report_butler` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_butler', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'room_number', OLD.`room_number`, 'notes', OLD.`notes`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_night_shift_audit_insert` AFTER INSERT ON `ops_report_night_shift` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_night_shift', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'guest_name', NEW.`guest_name`, 'notes', NEW.`notes`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_night_shift_audit_update` AFTER UPDATE ON `ops_report_night_shift` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_night_shift', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'guest_name', OLD.`guest_name`, 'notes', OLD.`notes`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'guest_name', NEW.`guest_name`, 'notes', NEW.`notes`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_night_shift_audit_delete` AFTER DELETE ON `ops_report_night_shift` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_night_shift', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'guest_name', OLD.`guest_name`, 'notes', OLD.`notes`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_hotel_figure_audit_insert` AFTER INSERT ON `ops_report_hotel_figure` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_hotel_figure', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'field_label', NEW.`field_label`, 'field_value', NEW.`field_value`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_hotel_figure_audit_update` AFTER UPDATE ON `ops_report_hotel_figure` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_hotel_figure', NEW.`id`, 'UPDATE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'field_label', OLD.`field_label`, 'field_value', OLD.`field_value`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  JSON_OBJECT('ops_report_id', NEW.`ops_report_id`, 'field_label', NEW.`field_label`, 'field_value', NEW.`field_value`, 'sort_order', NEW.`sort_order`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_ops_report_hotel_figure_audit_delete` AFTER DELETE ON `ops_report_hotel_figure` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'ops_report_hotel_figure', OLD.`id`, 'DELETE',
  JSON_OBJECT('ops_report_id', OLD.`ops_report_id`, 'field_label', OLD.`field_label`, 'field_value', OLD.`field_value`, 'sort_order', OLD.`sort_order`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
DELIMITER ;
-- Table structure for `floor_designer`
DROP TABLE IF EXISTS `floor_designer`;
CREATE TABLE `floor_designer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `it_location_id` int DEFAULT NULL,
  `sq_meters` decimal(10,2) DEFAULT NULL,
  `shape_type` enum('Square', 'Rectangular', 'Irregular east-west walls', 'Irregular east wall', 'Irregular west wall', 'Irregular north-south walls', 'Irregular north wall', 'Irregular south wall') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Square',
  `floor_plan_id` int DEFAULT NULL COMMENT 'Background image from floor_plans',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_floor_designer_company_scope` (`company_id`, `name`),
  KEY `company_id` (`company_id`),
  KEY `it_location_id` (`it_location_id`),
  KEY `floor_plan_id` (`floor_plan_id`),
  CONSTRAINT `floor_designer_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_designer_ibfk_location` FOREIGN KEY (`it_location_id`) REFERENCES `it_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `floor_designer_ibfk_plan` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for `floor_designer_points`
DROP TABLE IF EXISTS `floor_designer_points`;
CREATE TABLE `floor_designer_points` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `floor_designer_id` int NOT NULL,
  `point_type_id` int DEFAULT NULL COMMENT 'FK to switch_port_types',
  `x` decimal(10,2) NOT NULL DEFAULT '0.00',
  `y` decimal(10,2) NOT NULL DEFAULT '0.00',
  `comment_x` decimal(10,2) NOT NULL DEFAULT '0.00',
  `comment_y` decimal(10,2) NOT NULL DEFAULT '20.00',
  `wlan_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `patch_port` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `switch_id` int DEFAULT NULL COMMENT 'FK to equipment (Switch)',
  `switch_port_id` int DEFAULT NULL COMMENT 'FK to switch_ports',
  `cable_color_id` int DEFAULT NULL COMMENT 'FK to cable_colors',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rotation` decimal(5,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_floor_designer_points_company_scope` (`company_id`, `floor_designer_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `floor_designer_id` (`floor_designer_id`),
  KEY `point_type_id` (`point_type_id`),
  KEY `switch_id` (`switch_id`),
  KEY `switch_port_id` (`switch_port_id`),
  KEY `cable_color_id` (`cable_color_id`),
  CONSTRAINT `floor_designer_points_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_designer_points_ibfk_designer` FOREIGN KEY (`floor_designer_id`) REFERENCES `floor_designer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `floor_designer_points_ibfk_type` FOREIGN KEY (`point_type_id`) REFERENCES `switch_port_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `floor_designer_points_ibfk_switch` FOREIGN KEY (`switch_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `floor_designer_points_ibfk_port` FOREIGN KEY (`switch_port_id`) REFERENCES `switch_ports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `floor_designer_points_ibfk_color` FOREIGN KEY (`cable_color_id`) REFERENCES `cable_colors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Triggers for `floor_designer`
DELIMITER //
CREATE TRIGGER `trg_floor_designer_audit_insert` AFTER INSERT ON `floor_designer` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_designer', NEW.`id`, 'INSERT', NULL, 
  JSON_OBJECT('name', NEW.`name`, 'it_location_id', NEW.`it_location_id`, 'sq_meters', NEW.`sq_meters`, 'shape_type', NEW.`shape_type`, 'floor_plan_id', NEW.`floor_plan_id`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_floor_designer_audit_update` AFTER UPDATE ON `floor_designer` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_designer', NEW.`id`, 'UPDATE',
  JSON_OBJECT('name', OLD.`name`, 'it_location_id', OLD.`it_location_id`, 'sq_meters', OLD.`sq_meters`, 'shape_type', OLD.`shape_type`, 'floor_plan_id', OLD.`floor_plan_id`, 'active', OLD.`active`),
  JSON_OBJECT('name', NEW.`name`, 'it_location_id', NEW.`it_location_id`, 'sq_meters', NEW.`sq_meters`, 'shape_type', NEW.`shape_type`, 'floor_plan_id', NEW.`floor_plan_id`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_floor_designer_audit_delete` AFTER DELETE ON `floor_designer` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_designer', OLD.`id`, 'DELETE',
  JSON_OBJECT('name', OLD.`name`, 'it_location_id', OLD.`it_location_id`, 'sq_meters', OLD.`sq_meters`, 'shape_type', OLD.`shape_type`, 'floor_plan_id', OLD.`floor_plan_id`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
DELIMITER ;

-- Audit Triggers for `floor_designer_points`
DELIMITER //
CREATE TRIGGER `trg_floor_designer_points_audit_insert` AFTER INSERT ON `floor_designer_points` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_designer_points', NEW.`id`, 'INSERT', NULL,
  JSON_OBJECT('floor_designer_id', NEW.`floor_designer_id`, 'point_type_id', NEW.`point_type_id`, 'x', NEW.`x`, 'y', NEW.`y`, 'label', NEW.`label`, 'rotation', NEW.`rotation`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_floor_designer_points_audit_update` AFTER UPDATE ON `floor_designer_points` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_designer_points', NEW.`id`, 'UPDATE',
  JSON_OBJECT('floor_designer_id', OLD.`floor_designer_id`, 'point_type_id', OLD.`point_type_id`, 'x', OLD.`x`, 'y', OLD.`y`, 'label', OLD.`label`, 'rotation', OLD.`rotation`, 'active', OLD.`active`),
  JSON_OBJECT('floor_designer_id', NEW.`floor_designer_id`, 'point_type_id', NEW.`point_type_id`, 'x', NEW.`x`, 'y', NEW.`y`, 'label', NEW.`label`, 'rotation', NEW.`rotation`, 'active', NEW.`active`),
  @app_ip_address, @app_user_agent);
END//
CREATE TRIGGER `trg_floor_designer_points_audit_delete` AFTER DELETE ON `floor_designer_points` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'floor_designer_points', OLD.`id`, 'DELETE',
  JSON_OBJECT('floor_designer_id', OLD.`floor_designer_id`, 'point_type_id', OLD.`point_type_id`, 'x', OLD.`x`, 'y', OLD.`y`, 'label', OLD.`label`, 'rotation', OLD.`rotation`, 'active', OLD.`active`),
  NULL, @app_ip_address, @app_user_agent);
END//
DELIMITER ;
-- Table structure for `alerts`
DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id_2` (`company_id`,`title`),
  KEY `company_id` (`company_id`),
  KEY `category_id` (`category_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `alerts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alerts_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alerts_ibfk_assigned_to` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `alerts_ibfk_created_by` FOREIGN KEY (`created_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers for `alerts`
DELIMITER $$
CREATE TRIGGER `trg_alerts_audit_insert` AFTER INSERT ON `alerts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'alerts', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'title', NEW.`title`, 'description', NEW.`description`, 'start_datetime', NEW.`start_datetime`, 'end_datetime', NEW.`end_datetime`, 'location', NEW.`location`, 'category_id', NEW.`category_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_alerts_audit_update` AFTER UPDATE ON `alerts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'alerts', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'title', OLD.`title`, 'description', OLD.`description`, 'start_datetime', OLD.`start_datetime`, 'end_datetime', OLD.`end_datetime`, 'location', OLD.`location`, 'category_id', OLD.`category_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'title', NEW.`title`, 'description', NEW.`description`, 'start_datetime', NEW.`start_datetime`, 'end_datetime', NEW.`end_datetime`, 'location', NEW.`location`, 'category_id', NEW.`category_id`, 'assigned_to_employee_id', NEW.`assigned_to_employee_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_alerts_audit_delete` AFTER DELETE ON `alerts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'alerts', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'title', OLD.`title`, 'description', OLD.`description`, 'start_datetime', OLD.`start_datetime`, 'end_datetime', OLD.`end_datetime`, 'location', OLD.`location`, 'category_id', OLD.`category_id`, 'assigned_to_employee_id', OLD.`assigned_to_employee_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

-- Table structure for `password_folders`
DROP TABLE IF EXISTS `password_folders`;
CREATE TABLE `password_folders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_folders_company_scope` (`company_id`, `name`, `employee_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `password_folders_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `password_folders_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `password_folders_ibfk_parent` FOREIGN KEY (`parent_id`) REFERENCES `password_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `password_entries`
DROP TABLE IF EXISTS `password_entries`;
CREATE TABLE `password_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `account` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_entries_company_scope` (`company_id`, `employee_id`, `account`, `id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `folder_id` (`folder_id`),
  CONSTRAINT `password_entries_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `password_entries_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `password_entries_ibfk_folder` FOREIGN KEY (`folder_id`) REFERENCES `password_folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `bookmark_folders`
DROP TABLE IF EXISTS `bookmark_folders`;
CREATE TABLE `bookmark_folders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `parent_folder_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int DEFAULT '0',
  `shared` tinyint DEFAULT '0',
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
  KEY `parent_folder_id` (`parent_folder_id`),
  CONSTRAINT `bookmark_folders_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmark_folders_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmark_folders_ibfk_parent` FOREIGN KEY (`parent_folder_id`) REFERENCES `bookmark_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Existing databases that still have uq_bookmark_folders_company_scope: DROP INDEX uq_bookmark_folders_company_scope ON bookmark_folders;

-- Table structure for `bookmarks`
DROP TABLE IF EXISTS `bookmarks`;
CREATE TABLE `bookmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position` int DEFAULT '0',
  `shared` tinyint DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookmarks_company_scope` (`company_id`, `employee_id`, `id`),
  UNIQUE KEY `uq_bookmarks_employee_url` (`company_id`, `employee_id`, `url`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `folder_id` (`folder_id`),
  CONSTRAINT `bookmarks_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmarks_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmarks_ibfk_folder` FOREIGN KEY (`folder_id`) REFERENCES `bookmark_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Existing databases: DELETE inactive/duplicate bookmark rows per (company_id, employee_id, url) before adding uq_bookmarks_employee_url.
-- Seed default shared bookmarks
-- Retroactive default bookmarks for existing Admin users
INSERT INTO bookmarks (company_id, employee_id, title, url, shared, active)
SELECT 
    e.company_id,
    e.id,
    b.title,
    b.url,
    1,
    1
FROM employees e
LEFT JOIN employee_roles ur ON ur.id = e.role_id
CROSS JOIN (
    SELECT 'ServiceNow' AS title, 'https://www.servicenow.com/' AS url UNION ALL
    SELECT 'Splunk', 'https://www.splunk.com/' UNION ALL
    SELECT 'M365', 'https://m365.cloud.microsoft/'
) b
WHERE 
    (
        LOWER(e.username) = 'admin'
        OR LOWER(ur.name) = 'admin'
    )
    AND NOT EXISTS (
        SELECT 1 
        FROM bookmarks bk
        WHERE bk.company_id = e.company_id
          AND bk.employee_id = e.id
          AND bk.url = b.url
    );



-- Table structure for private_contacts
DROP TABLE IF EXISTS `private_contacts`;
CREATE TABLE `private_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `name_prefix` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `name_suffix` varchar(50) DEFAULT NULL,
  `phonetic_first_name` varchar(100) DEFAULT NULL,
  `phonetic_middle_name` varchar(100) DEFAULT NULL,
  `phonetic_last_name` varchar(100) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `file_as` varchar(255) DEFAULT NULL,
  `email1_label` varchar(50) DEFAULT NULL,
  `email1_value` varchar(255) DEFAULT NULL,
  `phone1_label` varchar(50) DEFAULT NULL,
  `phone1_value` varchar(50) DEFAULT NULL,
  `address1_label` varchar(50) DEFAULT NULL,
  `address1_country` varchar(100) DEFAULT NULL,
  `address1_street` text DEFAULT NULL,
  `address1_extended` text DEFAULT NULL,
  `address1_city` varchar(100) DEFAULT NULL,
  `address1_region` varchar(100) DEFAULT NULL,
  `address1_postcode` varchar(20) DEFAULT NULL,
  `address1_po_box` varchar(50) DEFAULT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `organization_title` varchar(255) DEFAULT NULL,
  `organization_department` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `event1_label` varchar(50) DEFAULT NULL,
  `event1_value` date DEFAULT NULL,
  `relation1_label` varchar(50) DEFAULT NULL,
  `relation1_value` varchar(255) DEFAULT NULL,
  `website1_label` varchar(50) DEFAULT NULL,
  `website1_value` varchar(255) DEFAULT NULL,
  `custom_field1_label` varchar(50) DEFAULT NULL,
  `custom_field1_value` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `labels` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_private_contacts_company_scope` (`company_id`, `employee_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `private_contacts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `private_contacts_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `todo_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cat_from_employee_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_user_name` (`company_id`,`cat_from_employee_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `cat_from_employee_id` (`cat_from_employee_id`),
  CONSTRAINT `todo_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `todo_categories_ibfk_employee` FOREIGN KEY (`cat_from_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `todo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `due_date` datetime DEFAULT NULL,
  `reminder_at` datetime DEFAULT NULL,
  `repeat_pattern` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to_employee_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed` tinyint NOT NULL DEFAULT '0',
  `importance` tinyint NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_todo_company_scope` (`company_id`, `title`, `id`),
  KEY `company_id` (`company_id`),
  KEY `category_id` (`category_id`),
  KEY `department_id` (`department_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `todo_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






-- Table structure for `notes`
DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `title` VARCHAR(255),
  `content` LONGTEXT,
  `is_checklist` TINYINT DEFAULT 0,
  `checklist_json` JSON DEFAULT NULL,
  `images_json` JSON DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT NULL,
  `is_pinned` TINYINT DEFAULT 0,
  `is_important` TINYINT DEFAULT 0,
  `is_archived` TINYINT DEFAULT 0,
  `reminder_at` DATETIME DEFAULT NULL,
  `shared_with_json` JSON DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notes_company_scope` (`company_id`, `employee_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `notes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notes_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `note_labels`
DROP TABLE IF EXISTS `note_labels`;
CREATE TABLE `note_labels` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `note_id` INT DEFAULT NULL,
  `label` VARCHAR(100) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_labels_company_scope` (`company_id`, `employee_id`, `id`),
  KEY `note_id` (`note_id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `note_labels_ibfk_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `note_labels_ibfk_note` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `note_labels_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- Table structure for `system_status` (admin diagnostics cache — one row per tab)
DROP TABLE IF EXISTS `system_status`;
CREATE TABLE `system_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL DEFAULT 1,
  `tab_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_status_company_tab` (`company_id`, `tab_key`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `system_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers for `system_status`
DELIMITER $$

CREATE TRIGGER `trg_system_status_audit_insert` 
AFTER INSERT ON `system_status` 
FOR EACH ROW 
BEGIN
  INSERT INTO `audit_logs` 
    (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES 
    (
      COALESCE(@app_company_id, NEW.`company_id`, 0),
      @app_employee_id,
      @app_username,
      @app_email,
      'system_status',
      NEW.`id`,
      'INSERT',
      NULL,
      JSON_OBJECT(
        'id', NEW.`id`,
        'company_id', NEW.`company_id`,
        'tab_key', NEW.`tab_key`,
        'active', NEW.`active`,
        'deleted_by', NEW.`deleted_by`,
        'deleted_at', NEW.`deleted_at`,
        'created_by', NEW.`created_by`,
        'created_at', NEW.`created_at`,
        'updated_by', NEW.`updated_by`,
        'updated_at', NEW.`updated_at`
      ),
      @app_ip_address,
      @app_user_agent
    );
END$$


CREATE TRIGGER `trg_system_status_audit_update` 
AFTER UPDATE ON `system_status` 
FOR EACH ROW 
BEGIN
  INSERT INTO `audit_logs` 
    (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES 
    (
      COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0),
      @app_employee_id,
      @app_username,
      @app_email,
      'system_status',
      COALESCE(NEW.`id`, OLD.`id`, 0),
      'UPDATE',
      JSON_OBJECT(
        'id', OLD.`id`,
        'company_id', OLD.`company_id`,
        'tab_key', OLD.`tab_key`,
        'active', OLD.`active`,
        'deleted_by', OLD.`deleted_by`,
        'deleted_at', OLD.`deleted_at`,
        'created_by', OLD.`created_by`,
        'created_at', OLD.`created_at`,
        'updated_by', OLD.`updated_by`,
        'updated_at', OLD.`updated_at`
      ),
      JSON_OBJECT(
        'id', NEW.`id`,
        'company_id', NEW.`company_id`,
        'tab_key', NEW.`tab_key`,
        'active', NEW.`active`,
        'deleted_by', NEW.`deleted_by`,
        'deleted_at', NEW.`deleted_at`,
        'created_by', NEW.`created_by`,
        'created_at', NEW.`created_at`,
        'updated_by', NEW.`updated_by`,
        'updated_at', NEW.`updated_at`
      ),
      @app_ip_address,
      @app_user_agent
    );
END$$


CREATE TRIGGER `trg_system_status_audit_delete` 
AFTER DELETE ON `system_status` 
FOR EACH ROW 
BEGIN
  INSERT INTO `audit_logs` 
    (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES 
    (
      COALESCE(@app_company_id, OLD.`company_id`, 0),
      @app_employee_id,
      @app_username,
      @app_email,
      'system_status',
      OLD.`id`,
      'DELETE',
      JSON_OBJECT(
        'id', OLD.`id`,
        'company_id', OLD.`company_id`,
        'tab_key', OLD.`tab_key`,
        'active', OLD.`active`,
        'deleted_by', OLD.`deleted_by`,
        'deleted_at', OLD.`deleted_at`,
        'created_by', OLD.`created_by`,
        'created_at', OLD.`created_at`,
        'updated_by', OLD.`updated_by`,
        'updated_at', OLD.`updated_at`
      ),
      NULL,
      @app_ip_address,
      @app_user_agent
    );
END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER add_default_bookmarks_for_admin
AFTER INSERT ON employees
FOR EACH ROW
BEGIN
    -- Verifica se é admin pelo username
    IF LOWER(NEW.username) = 'admin' THEN

        INSERT INTO bookmarks (company_id, employee_id, title, url, shared, active)
        SELECT 
            NEW.company_id,
            NEW.id,
            b.title,
            b.url,
            1,
            1
        FROM (
            SELECT 'ServiceNow' AS title, 'https://www.servicenow.com/' AS url UNION ALL
            SELECT 'Splunk', 'https://www.splunk.com/' UNION ALL
            SELECT 'M365', 'https://m365.cloud.microsoft/'
        ) b
        WHERE NOT EXISTS (
            SELECT 1 FROM bookmarks bk
            WHERE bk.company_id = NEW.company_id
              AND bk.employee_id = NEW.id
              AND bk.url = b.url
        );

    ELSEIF EXISTS (
        SELECT 1 
        FROM employee_roles ur
        WHERE ur.id = NEW.role_id
          AND ur.company_id = NEW.company_id
          AND LOWER(ur.name) = 'admin'
    ) THEN

        INSERT INTO bookmarks (company_id, employee_id, title, url, shared, active)
        SELECT 
            NEW.company_id,
            NEW.id,
            b.title,
            b.url,
            1,
            1
        FROM (
            SELECT 'ServiceNow' AS title, 'https://www.servicenow.com/' AS url UNION ALL
            SELECT 'Splunk', 'https://www.splunk.com/' UNION ALL
            SELECT 'M365', 'https://m365.cloud.microsoft/'
        ) b
        WHERE NOT EXISTS (
            SELECT 1 FROM bookmarks bk
            WHERE bk.company_id = NEW.company_id
              AND bk.employee_id = NEW.id
              AND bk.url = b.url
        );

    END IF;
END$$

DELIMITER ;

CREATE TABLE `knowledge_base` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL DEFAULT '1',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_knowledge_base_company_scope` (`company_id`, `title`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `knowledge_base_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `knowledge_base_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `it_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours_of_operation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escalation_procedure` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`),
  CONSTRAINT `it_settings_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_knowledge_base_audit_insert`$$
CREATE TRIGGER `trg_knowledge_base_audit_insert` AFTER INSERT ON `knowledge_base` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'knowledge_base', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'category', NEW.`category`, 'title', NEW.`title`, 'content', NEW.`content`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_knowledge_base_audit_update`$$
CREATE TRIGGER `trg_knowledge_base_audit_update` AFTER UPDATE ON `knowledge_base` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'knowledge_base', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'category', OLD.`category`, 'title', OLD.`title`, 'content', OLD.`content`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'category', NEW.`category`, 'title', NEW.`title`, 'content', NEW.`content`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_knowledge_base_audit_delete`$$
CREATE TRIGGER `trg_knowledge_base_audit_delete` AFTER DELETE ON `knowledge_base` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'knowledge_base', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'category', OLD.`category`, 'title', OLD.`title`, 'content', OLD.`content`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_it_settings_audit_insert`$$
CREATE TRIGGER `trg_it_settings_audit_insert` AFTER INSERT ON `it_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_settings', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'contact_email', NEW.`contact_email`, 'contact_phone', NEW.`contact_phone`, 'hours_of_operation', NEW.`hours_of_operation`, 'escalation_procedure', NEW.`escalation_procedure`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_it_settings_audit_update`$$
CREATE TRIGGER `trg_it_settings_audit_update` AFTER UPDATE ON `it_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_settings', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'contact_email', OLD.`contact_email`, 'contact_phone', OLD.`contact_phone`, 'hours_of_operation', OLD.`hours_of_operation`, 'escalation_procedure', OLD.`escalation_procedure`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'contact_email', NEW.`contact_email`, 'contact_phone', NEW.`contact_phone`, 'hours_of_operation', NEW.`hours_of_operation`, 'escalation_procedure', NEW.`escalation_procedure`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_it_settings_audit_delete`$$
CREATE TRIGGER `trg_it_settings_audit_delete` AFTER DELETE ON `it_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_settings', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'contact_email', OLD.`contact_email`, 'contact_phone', OLD.`contact_phone`, 'hours_of_operation', OLD.`hours_of_operation`, 'escalation_procedure', OLD.`escalation_procedure`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
-- Additional Sample Data for Knowledge Base
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`) VALUES
(1, 'Network', 'VPN Setup Guide', 'To set up your VPN:\n1. Open Cisco AnyConnect.\n2. Enter vpn.techcorp.example.\n3. Log in with your windows credentials.\n4. Approve the DUO push notification.', 1),
(1, 'Password Management', 'How to reset your password', 'To reset your domain password:\n1. Press Ctrl+Alt+Del\n2. Select "Change a password"\n3. Follow the on-screen instructions.\nIf you are locked out, please call the IT helpdesk.', 1),
(1, 'Printers', 'Troubleshooting Printer Issues', 'If your printer is not working:\n1. Check if it is turned on and connected to the network.\n2. Ensure there is paper and toner.\n3. Restart the printer spooler on your PC.\n4. If issues persist, contact IT with the printer name/IP.', 1),
(1, 'Network', 'Connecting to Office WiFi', 'To connect to the "TechCorp_Internal" WiFi:\n1. Select the SSID from your device.\n2. Use your windows credentials (domain username and password).\n3. Accept the security certificate if prompted.', 1),
(1, 'Software', 'Installing Authorized Software', 'Software must be requested via the IT Portal. Once approved, it will appear in the "Software Center" on your desktop for one-click installation.', 1),
(1, 'Security', 'Reporting Suspicious Emails', 'If you receive a suspicious email (phishing):\n1. Do not click any links or download attachments.\n2. Click the "Report Phish" button in Outlook.\n3. Delete the email immediately.', 1);

-- Repeat for other companies if they exist in the seed
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 2, category, title, content, active FROM knowledge_base WHERE company_id = 1;
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 3, category, title, content, active FROM knowledge_base WHERE company_id = 1;
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 4, category, title, content, active FROM knowledge_base WHERE company_id = 1;
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 5, category, title, content, active FROM knowledge_base WHERE company_id = 1;

-- Also add some IT Settings
INSERT INTO `it_settings` (`company_id`, `contact_email`, `contact_phone`, `hours_of_operation`, `escalation_procedure`) VALUES
(1, 'it-support@techcorp.example', '+1-212-555-0199', '24/7', 'For critical outages, call the On-Call Manager at +1-212-555-0911.'),
(2, 'support@datacenterplus.example', '+1-972-555-0200', '08:00 - 18:00 CST', 'Issues unresolved after 4 hours should be escalated to the IT Director.'),
(3, 'help@networksolutions.example', '+1-415-555-0300', '09:00 - 17:00 PST', 'Please submit a ticket via the portal for escalation.'),
(4, 'it@cloudtech.example', '+1-206-555-0400', '24/7', 'Contact the Level 2 support team via Slack #it-escalations.'),
(5, 'it-ops@enterpriseit.example', '+1-617-555-0500', '08:00 - 20:00 EST', 'Standard escalation through the ticketing system.');

-- Table structure for `request_password`
DROP TABLE IF EXISTS `request_password`;
CREATE TABLE `request_password` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `requested_by_employee_id` int NOT NULL,
  `application` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('Cannot recall password', 'Password expired', 'Account locked', 'Security reasons (3rd party may know password)') COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicant_signature_date` date DEFAULT NULL,
  `ism_signature_date` date DEFAULT NULL,
  `hr_approval_status` enum('Waiting', 'Approved', 'Declined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `hr_signature_date` date DEFAULT NULL,
  `hod_approval_status` enum('Waiting', 'Approved', 'Declined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `hod_signature_date` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request_password_company_scope` (`company_id`, `employee_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `requested_by_employee_id` (`requested_by_employee_id`),
  CONSTRAINT `request_password_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_password_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_password_ibfk_3` FOREIGN KEY (`requested_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_request_password_audit_insert`$$
CREATE TRIGGER `trg_request_password_audit_insert` AFTER INSERT ON `request_password` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'request_password', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'application', NEW.`application`, 'reason', NEW.`reason`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_request_password_audit_update`$$
CREATE TRIGGER `trg_request_password_audit_update` AFTER UPDATE ON `request_password` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'request_password', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'requested_by_employee_id', OLD.`requested_by_employee_id`, 'application', OLD.`application`, 'reason', OLD.`reason`, 'applicant_signature_date', OLD.`applicant_signature_date`, 'ism_signature_date', OLD.`ism_signature_date`, 'hr_approval_status', OLD.`hr_approval_status`, 'hr_signature_date', OLD.`hr_signature_date`, 'hod_approval_status', OLD.`hod_approval_status`, 'hod_signature_date', OLD.`hod_signature_date`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'requested_by_employee_id', NEW.`requested_by_employee_id`, 'application', NEW.`application`, 'reason', NEW.`reason`, 'applicant_signature_date', NEW.`applicant_signature_date`, 'ism_signature_date', NEW.`ism_signature_date`, 'hr_approval_status', NEW.`hr_approval_status`, 'hr_signature_date', NEW.`hr_signature_date`, 'hod_approval_status', NEW.`hod_approval_status`, 'hod_signature_date', NEW.`hod_signature_date`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_request_password_audit_delete`$$
CREATE TRIGGER `trg_request_password_audit_delete` AFTER DELETE ON `request_password` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'request_password', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'requested_by_employee_id', OLD.`requested_by_employee_id`, 'application', OLD.`application`, 'reason', OLD.`reason`, 'applicant_signature_date', OLD.`applicant_signature_date`, 'ism_signature_date', OLD.`ism_signature_date`, 'hr_approval_status', OLD.`hr_approval_status`, 'hr_signature_date', OLD.`hr_signature_date`, 'hod_approval_status', OLD.`hod_approval_status`, 'hod_signature_date', OLD.`hod_signature_date`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$


DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;
