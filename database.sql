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
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `access_levels_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `access_levels`
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Full');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('2', '4', 'Full');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('3', '7', 'Full');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('4', '10', 'Full');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('5', '13', 'Full');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Limited');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('2', '5', 'Limited');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('3', '8', 'Limited');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('4', '11', 'Limited');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('5', '14', 'Limited');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Read Only');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Read Only');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('3', '9', 'Read Only');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('4', '12', 'Read Only');
INSERT INTO `access_levels` (`company_id`, `id`, `name`) VALUES ('5', '15', 'Read Only');

-- Table structure for `assignment_types`
DROP TABLE IF EXISTS `assignment_types`;
CREATE TABLE `assignment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `assignment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `assignment_types`
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Department');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('2', '4', 'Department');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('3', '7', 'Department');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('4', '10', 'Department');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('5', '13', 'Department');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Individual');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('2', '5', 'Individual');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('3', '8', 'Individual');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('4', '11', 'Individual');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('5', '14', 'Individual');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Shared');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Shared');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('3', '9', 'Shared');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('4', '12', 'Shared');
INSERT INTO `assignment_types` (`company_id`, `id`, `name`) VALUES ('5', '15', 'Shared');

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `departments`
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('1', '1', 'IT Operations', 'IT', 'Core IT operations team', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('2', '1', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('3', '1', 'Human Resources', 'HR', 'Human resources department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('4', '1', 'Housekeeping', 'HK', 'Housekeeping operations', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('5', '1', 'Front Office', 'FO', 'Front Office', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('6', '2', 'IT Operations', 'IT', 'Core IT operations team', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('7', '2', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('8', '2', 'Human Resources', 'HR', 'Human resources department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('9', '2', 'Housekeeping', 'HK', 'Housekeeping operations', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('10', '2', 'Front Office', 'FO', 'Front Office', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('11', '3', 'IT Operations', 'IT', 'Core IT operations team', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('12', '3', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('13', '3', 'Human Resources', 'HR', 'Human resources department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('14', '3', 'Housekeeping', 'HK', 'Housekeeping operations', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('15', '3', 'Front Office', 'FO', 'Front Office', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('16', '4', 'IT Operations', 'IT', 'Core IT operations team', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('17', '4', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('18', '4', 'Human Resources', 'HR', 'Human resources department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('19', '4', 'Housekeeping', 'HK', 'Housekeeping operations', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('20', '4', 'Front Office', 'FO', 'Front Office', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('21', '5', 'IT Operations', 'IT', 'Core IT operations team', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('22', '5', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('23', '5', 'Human Resources', 'HR', 'Human resources department', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('24', '5', 'Housekeeping', 'HK', 'Housekeeping operations', '1');
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`) VALUES ('25', '5', 'Front Office', 'FO', 'Front Office', '1');


-- Table structure for `budget_categories`
DROP TABLE IF EXISTS `budget_categories`;
CREATE TABLE `budget_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_categories_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `budget_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `budget_categories`
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('1', '1', 'Revenue', 'Revenue-related general ledger accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('4', '2', 'Revenue', 'Revenue-related general ledger accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('7', '3', 'Revenue', 'Revenue-related general ledger accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('10', '4', 'Revenue', 'Revenue-related general ledger accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('13', '5', 'Revenue', 'Revenue-related general ledger accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('2', '1', 'Operating Expense', 'Operational expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('5', '2', 'Operating Expense', 'Operational expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('8', '3', 'Operating Expense', 'Operational expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('11', '4', 'Operating Expense', 'Operational expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('14', '5', 'Operating Expense', 'Operational expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('3', '1', 'Capital Expense', 'Capital expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('6', '2', 'Capital Expense', 'Capital expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('9', '3', 'Capital Expense', 'Capital expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('12', '4', 'Capital Expense', 'Capital expense accounts', '1');
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`) VALUES ('15', '5', 'Capital Expense', 'Capital expense accounts', '1');

-- Table structure for `cost_centers`
DROP TABLE IF EXISTS `cost_centers`;
CREATE TABLE `cost_centers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `department_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cost_centers_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `cost_centers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cost_centers_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `cost_centers`
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('1', '1', '1', 'Infrastructure', 'CC-IT-INFRA', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('4', '2', '6', 'Infrastructure', 'CC-IT-INFRA', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('7', '3', '11', 'Infrastructure', 'CC-IT-INFRA', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('10', '4', '16', 'Infrastructure', 'CC-IT-INFRA', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('13', '5', '21', 'Infrastructure', 'CC-IT-INFRA', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('2', '1', '2', 'Restaurant Operations', 'CC-FNB-OPS', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('5', '2', '7', 'Restaurant Operations', 'CC-FNB-OPS', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('8', '3', '12', 'Restaurant Operations', 'CC-FNB-OPS', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('11', '4', '17', 'Restaurant Operations', 'CC-FNB-OPS', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('14', '5', '22', 'Restaurant Operations', 'CC-FNB-OPS', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('3', '1', '4', 'Room Maintenance', 'CC-HK-RM', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('6', '2', '9', 'Room Maintenance', 'CC-HK-RM', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('9', '3', '14', 'Room Maintenance', 'CC-HK-RM', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('12', '4', '19', 'Room Maintenance', 'CC-HK-RM', '1');
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`) VALUES ('15', '5', '24', 'Room Maintenance', 'CC-HK-RM', '1');

-- Table structure for `gl_accounts`
DROP TABLE IF EXISTS `gl_accounts`;
CREATE TABLE `gl_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `account_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gl_accounts_company_code` (`company_id`,`account_code`),
  KEY `company_id` (`company_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `gl_accounts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gl_accounts_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `budget_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `gl_accounts`
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('1', '1', '6100', 'IT Maintenance Contracts', '2', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('4', '2', '6100', 'IT Maintenance Contracts', '5', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('7', '3', '6100', 'IT Maintenance Contracts', '8', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('10', '4', '6100', 'IT Maintenance Contracts', '11', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('13', '5', '6100', 'IT Maintenance Contracts', '14', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('2', '1', '6200', 'Software Licensing', '2', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('5', '2', '6200', 'Software Licensing', '5', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('8', '3', '6200', 'Software Licensing', '8', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('11', '4', '6200', 'Software Licensing', '11', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('14', '5', '6200', 'Software Licensing', '14', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('3', '1', '7100', 'Capital IT Equipment', '3', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('6', '2', '7100', 'Capital IT Equipment', '6', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('9', '3', '7100', 'Capital IT Equipment', '9', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('12', '4', '7100', 'Capital IT Equipment', '12', '1');
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`) VALUES ('15', '5', '7100', 'Capital IT Equipment', '15', '1');

-- Table structure for `annual_budgets`
DROP TABLE IF EXISTS `annual_budgets`;
CREATE TABLE `annual_budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cost_center_id` int NOT NULL,
  `gl_account_id` int NOT NULL,
  `year` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_by` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_annual_budgets_company_scope` (`company_id`,`cost_center_id`,`gl_account_id`,`year`),
  KEY `company_id` (`company_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `annual_budgets_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `annual_budgets_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `annual_budgets_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `annual_budgets`
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('1', '1', '1', '1', '2026', '48000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('3', '2', '4', '4', '2026', '48000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('5', '3', '7', '7', '2026', '48000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('7', '4', '10', '10', '2026', '48000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('9', '5', '13', '13', '2026', '48000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('2', '1', '1', '2', '2026', '36000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('4', '2', '4', '5', '2026', '36000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('6', '3', '7', '8', '2026', '36000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('8', '4', '10', '11', '2026', '36000.00', '1', '1');
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`) VALUES ('10', '5', '13', '14', '2026', '36000.00', '1', '1');

-- Table structure for `monthly_budgets`
DROP TABLE IF EXISTS `monthly_budgets`;
CREATE TABLE `monthly_budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `annual_budget_id` int NOT NULL,
  `month` tinyint unsigned NOT NULL COMMENT '1=January ... 12=December',
  `amount` decimal(12,2) NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_budgets_scope` (`company_id`,`annual_budget_id`,`month`),
  KEY `company_id` (`company_id`),
  KEY `annual_budget_id` (`annual_budget_id`),
  CONSTRAINT `monthly_budgets_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_budgets_ibfk_annual_budget` FOREIGN KEY (`annual_budget_id`) REFERENCES `annual_budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_budgets_chk_month` CHECK ((`month` between 1 and 12))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `monthly_budgets`
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('1', '1', '1', '1', '4000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('3', '2', '3', '1', '4000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('5', '3', '5', '1', '4000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('7', '4', '7', '1', '4000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('9', '5', '9', '1', '4000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('2', '1', '2', '1', '3000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('4', '2', '4', '1', '3000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('6', '3', '6', '1', '3000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('8', '4', '8', '1', '3000.00', '1');
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`) VALUES ('10', '5', '10', '1', '3000.00', '1');

-- Table structure for `expenses`
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `cost_center_id` int NOT NULL,
  `gl_account_id` int NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `invoice_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `expenses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `expenses`
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`) VALUES ('1', '1', '1', '1', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1');
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`) VALUES ('2', '2', '4', '4', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1');
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`) VALUES ('3', '3', '7', '7', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1');
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`) VALUES ('4', '4', '10', '10', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1');
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`) VALUES ('5', '5', '13', '13', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1');

-- Table structure for `forecast_revisions_status`
DROP TABLE IF EXISTS `forecast_revisions_status`;
CREATE TABLE `forecast_revisions_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `forecast_revisions_status_company_id` (`company_id`),
  CONSTRAINT `forecast_revisions_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `forecast_revisions_status`
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Draft', 'Draft projection before finance review', 1, NULL, NULL),
(2, 1, 'Submitted', 'Submitted to finance for February forecast', 1, NULL, NULL),
(3, 1, 'Finance Review', NULL, 1, NULL, NULL),
(4, 1, 'Gm Review', NULL, 1, NULL, NULL),
(5, 1, 'Approved', NULL, 1, NULL, NULL),
(6, 1, 'Rejected', NULL, 1, NULL, NULL),
(7, 2, 'Draft', 'Draft projection before finance review', 1, NULL, NULL),
(8, 2, 'Submitted', 'Submitted to finance for February forecast', 1, NULL, NULL),
(9, 2, 'Finance Review', NULL, 1, NULL, NULL),
(10, 2, 'Gm Review', NULL, 1, NULL, NULL),
(11, 2, 'Approved', NULL, 1, NULL, NULL),
(12, 2, 'Rejected', NULL, 1, NULL, NULL),
(13, 3, 'Draft', 'Draft projection before finance review', 1, NULL, NULL),
(14, 3, 'Submitted', 'Submitted to finance for February forecast', 1, NULL, NULL),
(15, 3, 'Finance Review', NULL, 1, NULL, NULL),
(16, 3, 'Gm Review', NULL, 1, NULL, NULL),
(17, 3, 'Approved', NULL, 1, NULL, NULL),
(18, 3, 'Rejected', NULL, 1, NULL, NULL),
(19, 4, 'Draft', 'Draft projection before finance review', 1, NULL, NULL),
(20, 4, 'Submitted', 'Submitted to finance for February forecast', 1, NULL, NULL),
(21, 4, 'Finance Review', NULL, 1, NULL, NULL),
(22, 4, 'Gm Review', NULL, 1, NULL, NULL),
(23, 4, 'Approved', NULL, 1, NULL, NULL),
(24, 4, 'Rejected', NULL, 1, NULL, NULL),
(25, 5, 'Draft', 'Draft projection before finance review', 1, NULL, NULL),
(26, 5, 'Submitted', 'Submitted to finance for February forecast', 1, NULL, NULL),
(27, 5, 'Finance Review', NULL, 1, NULL, NULL),
(28, 5, 'Gm Review', NULL, 1, NULL, NULL),
(29, 5, 'Approved', NULL, 1, NULL, NULL),
(30, 5, 'Rejected', NULL, 1, NULL, NULL);

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
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
  CONSTRAINT `forecast_revisions_ibfk_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_ibfk_finance_reviewed_by` FOREIGN KEY (`finance_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_ibfk_gm_approved_by` FOREIGN KEY (`gm_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_chk_month` CHECK ((`month` between 1 and 12))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `forecast_revisions`
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('1', '1', '1', '1', '2026', '2', '4200.00', '1', '0', '1', NULL, NULL, 'Draft projection before finance review', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('3', '2', '4', '4', '2026', '2', '4200.00', '7', '0', '1', NULL, NULL, 'Draft projection before finance review', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('5', '3', '7', '7', '2026', '2', '4200.00', '13', '0', '1', NULL, NULL, 'Draft projection before finance review', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('7', '4', '10', '10', '2026', '2', '4200.00', '19', '0', '1', NULL, NULL, 'Draft projection before finance review', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('9', '5', '13', '13', '2026', '2', '4200.00', '25', '0', '1', NULL, NULL, 'Draft projection before finance review', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('2', '1', '1', '2', '2026', '2', '3150.00', '2', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('4', '2', '4', '5', '2026', '2', '3150.00', '8', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('6', '3', '7', '8', '2026', '2', '3150.00', '14', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('8', '4', '10', '11', '2026', '2', '3150.00', '20', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1');
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`) VALUES ('10', '5', '13', '14', '2026', '2', '3150.00', '26', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1');

-- Table structure for `approvals_stage`
DROP TABLE IF EXISTS `approvals_stage`;
CREATE TABLE `approvals_stage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `stage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approvals_stage_company_stage` (`company_id`,`stage`),
  KEY `approvals_stage_company_id` (`company_id`),
  CONSTRAINT `approvals_stage_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approvals_stage`
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('1', '1', 'Finance Review', 'Finance team review stage before general manager approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('3', '2', 'Finance Review', 'Finance team review stage before general manager approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('5', '3', 'Finance Review', 'Finance team review stage before general manager approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('7', '4', 'Finance Review', 'Finance team review stage before general manager approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('9', '5', 'Finance Review', 'Finance team review stage before general manager approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('2', '1', 'GM Review', 'General manager review stage before final approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('4', '2', 'GM Review', 'General manager review stage before final approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('6', '3', 'GM Review', 'General manager review stage before final approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('8', '4', 'GM Review', 'General manager review stage before final approval.', '1');
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`) VALUES ('10', '5', 'GM Review', 'General manager review stage before final approval.', '1');

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
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `forecast_revision_id` (`forecast_revision_id`),
  KEY `stage` (`stage`),
  KEY `status` (`status`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `approvals_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvals_ibfk_forecast_revision` FOREIGN KEY (`forecast_revision_id`) REFERENCES `forecast_revisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvals_ibfk_stage` FOREIGN KEY (`stage`) REFERENCES `approvals_stage` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvals_ibfk_status` FOREIGN KEY (`status`) REFERENCES `forecast_revisions_status` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvals_ibfk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approvals`
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('1', '1', '2', '1', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('3', '2', '4', '3', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('5', '3', '6', '5', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('7', '4', '8', '7', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('9', '5', '10', '9', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('2', '1', '1', '1', '1', NULL, NULL, 'Draft not submitted yet.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('4', '2', '3', '3', '1', NULL, NULL, 'Draft not submitted yet.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('6', '3', '5', '5', '1', NULL, NULL, 'Draft not submitted yet.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('8', '4', '7', '7', '1', NULL, NULL, 'Draft not submitted yet.', '1');
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`) VALUES ('10', '5', '9', '9', '1', NULL, NULL, 'Draft not submitted yet.', '1');

-- Table structure for `approver_type`
DROP TABLE IF EXISTS `approver_type`;
CREATE TABLE `approver_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `approver_type_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `approver_type_company_id` (`company_id`),
  CONSTRAINT `approver_type_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approver_type`
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('1', '1', 'GM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('2', '1', 'HOD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('3', '1', 'ISM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('4', '2', 'GM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('5', '2', 'HOD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('6', '2', 'ISM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('7', '3', 'GM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('8', '3', 'HOD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('9', '3', 'ISM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('10', '4', 'GM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('11', '4', 'HOD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('12', '4', 'ISM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('13', '5', 'GM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('14', '5', 'HOD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('15', '5', 'ISM Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('16', '1', 'HRD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('17', '2', 'HRD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('18', '3', 'HRD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('19', '4', 'HRD Approval', '1');
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`) VALUES ('20', '5', 'HRD Approval', '1');

-- Table structure for `approvers`
DROP TABLE IF EXISTS `approvers`;
CREATE TABLE `approvers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `employee_position_id` int NOT NULL,
  `department_id` int NOT NULL,
  `approver_type_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approvers`
INSERT INTO `approvers` (`id`, `company_id`, `employee_id`, `employee_position_id`, `department_id`, `approver_type_id`, `active`) VALUES ('1', '1', '1', '1', '1', '1', '1');
INSERT INTO `approvers` (`id`, `company_id`, `employee_id`, `employee_position_id`, `department_id`, `approver_type_id`, `active`) VALUES ('2', '1', '2', '2', '1', '2', '1');

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
  `assigned_by_user_id` int DEFAULT NULL,
  `received_by_user_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_assignment_history_company` (`company_id`),
  KEY `idx_employee_assignment_history_employee` (`employee_id`),
  KEY `idx_employee_assignment_history_equipment` (`equipment_id`),
  KEY `idx_employee_assignment_history_inventory_item` (`inventory_item_id`),
  KEY `idx_employee_assignment_history_assigned_by` (`assigned_by_user_id`),
  KEY `idx_employee_assignment_history_received_by` (`received_by_user_id`),
  CONSTRAINT `employee_assignment_history_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_assignment_history_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `employee_assignment_history_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignment_history_ibfk_inventory_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignment_history_ibfk_assigned_by_user` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignment_history_ibfk_received_by_user` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_assignment_history`
INSERT INTO `employee_assignment_history` (`id`, `company_id`, `employee_id`, `asset_description`, `sim_imei`, `assigned_date`, `returned_date`, `condition_on_return`, `signed_handover`, `comments`, `assigned_by_user_id`, `received_by_user_id`, `active`) VALUES
(1, 1, 1, 'Laptop Dell Latitude 7420', NULL, '2026-01-15', NULL, NULL, 1, 'Primary company laptop assigned during onboarding.', 1, 1, 1),
(2, 2, 6, 'iPhone 14 Pro', '356112223334445', '2026-02-01', NULL, NULL, 1, 'Corporate mobile with active SIM card.', 1, 1, 1),
(3, 3, 10, 'Office key card - Main entrance', NULL, '2026-02-10', '2026-04-10', 'Good', 1, 'Returned after department transfer.', 1, 1, 1),
(4, 4, 13, 'HP LaserJet printer', NULL, '2026-03-05', NULL, NULL, 0, 'Shared printer assigned to front desk supervisor.', 1, NULL, 1),
(5, 5, 16, 'SIM card only', '352099001122334', '2026-03-20', NULL, NULL, 1, 'Data-only SIM issued for field device.', 1, 1, 1);

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
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_onboarding_requests_id` (`id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `employee_position_id` (`employee_position_id`),
  KEY `office_key_card_dep` (`office_key_card_dep`),
  CONSTRAINT `employee_onboarding_requests_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_onboarding_requests_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_onboarding_requests_ibfk_4` FOREIGN KEY (`employee_position_id`) REFERENCES `employee_positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_onboarding_requests_ibfk_3` FOREIGN KEY (`office_key_card_dep`) REFERENCES `departments` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_onboarding_requests`
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 3, 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-03-28 19:43:17', NULL),
(5, 5, 4, 15, 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-03-28 19:43:17', NULL),
(6, 2, 4, 6, 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-03-28 19:43:17', NULL),
(7, 3, 4, 9, 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-03-28 19:43:17', NULL),
(8, 4, 4, 12, 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-03-28 19:43:17', NULL);
-- Table structure for `employee_statuses`
DROP TABLE IF EXISTS `employee_statuses`;
CREATE TABLE `employee_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `employee_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_statuses`
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Active');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('3', '11', 'Active');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('4', '16', 'Active');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('5', '21', 'Active');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Contractor');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('2', '7', 'Contractor');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('3', '12', 'Contractor');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('4', '17', 'Contractor');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('5', '22', 'Contractor');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inactive');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('2', '8', 'Inactive');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('3', '13', 'Inactive');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('4', '18', 'Inactive');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('5', '23', 'Inactive');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'On Leave');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('2', '9', 'On Leave');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('3', '14', 'On Leave');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('4', '19', 'On Leave');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('5', '24', 'On Leave');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Terminated');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('2', '10', 'Terminated');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('3', '15', 'Terminated');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('4', '20', 'Terminated');
INSERT INTO `employee_statuses` (`company_id`, `id`, `name`) VALUES ('5', '25', 'Terminated');

-- Table structure for `employee_positions`
DROP TABLE IF EXISTS `employee_positions`;
CREATE TABLE `employee_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `department_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_positions_name` (`company_id`,`department_id`,`name`),
  KEY `idx_employee_positions_company` (`company_id`),
  KEY `idx_employee_positions_department` (`department_id`),
  CONSTRAINT `employee_positions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_positions_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_positions`
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`) VALUES
(1, 1, 1, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1),
(2, 1, 1, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1),
(3, 1, 2, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1),
(4, 2, 6, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1),
(5, 2, 6, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1),
(6, 2, 7, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1),
(7, 3, 11, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1),
(8, 3, 11, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1),
(9, 3, 12, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1),
(10, 4, 16, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1),
(11, 4, 16, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1),
(12, 4, 17, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1),
(13, 5, 21, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1),
(14, 5, 21, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1),
(15, 5, 22, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1);

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
  `changed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
  `external_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `workstation_mode_id` int DEFAULT NULL,
  `assignment_type_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `employment_status_id` int NOT NULL,
  `raw_status_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `idx_employees_external_id` (`external_id`),
  KEY `idx_employees_username` (`username`),
  KEY `employment_status_id` (`employment_status_id`),
  KEY `idx_employees_office_key_department` (`office_key_card_department_id`),
  KEY `idx_employees_workstation_mode` (`workstation_mode_id`),
  KEY `idx_employees_assignment_type` (`assignment_type_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `employees_ibfk_5` FOREIGN KEY (`office_key_card_department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `employees_ibfk_6` FOREIGN KEY (`employment_status_id`) REFERENCES `employee_statuses` (`id`),
  CONSTRAINT `employees_ibfk_7` FOREIGN KEY (`assignment_type_id`) REFERENCES `assignment_types` (`id`)
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
  `idf_id` int DEFAULT NULL,
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
  `printer_device_type_id` int DEFAULT NULL,
  `printer_color_capable` tinyint DEFAULT '0',
  `printer_scan` tinyint DEFAULT NULL,
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
  KEY `idf_id` (`idf_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
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
  KEY `switch_poe_id` (`switch_poe_id`),
  KEY `switch_environment_id` (`switch_environment_id`),
  UNIQUE KEY `uq_equipment_company_name` (`company_id`,`name`),
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
  CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`),
  CONSTRAINT `equipment_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `equipment_ibfk_5` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_22` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_6` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`),
  CONSTRAINT `equipment_ibfk_7` FOREIGN KEY (`warranty_type_id`) REFERENCES `warranty_types` (`id`),
  CONSTRAINT `equipment_ibfk_8` FOREIGN KEY (`printer_device_type_id`) REFERENCES `printer_device_types` (`id`),
  CONSTRAINT `equipment_ibfk_9` FOREIGN KEY (`workstation_device_type_id`) REFERENCES `workstation_device_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment`
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES (1, 1, 1, 2, 1, 1, 1, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 1, '2025-01-10', 8500.00, NULL, NULL, 4, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 3, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-28 19:43:17', '2026-04-26 22:07:32');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES (2, 2, 13, NULL, NULL, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 9, '2025-01-10', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-28 19:43:17', '2026-04-26 22:06:38');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES (3, 3, 25, NULL, NULL, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 17, '2025-01-10', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-28 19:43:17', '2026-04-26 22:07:18');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES (4, 4, 37, NULL, NULL, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 25, '2025-01-10', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 13, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-28 19:43:17', '2026-04-26 22:04:17');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES (5, 5, 49, NULL, NULL, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 33, '2025-01-10', 8500.00, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-28 19:43:17', '2026-04-26 22:06:55');

-- Table structure for `equipment_environment`
DROP TABLE IF EXISTS `equipment_environment`;
CREATE TABLE `equipment_environment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_environment_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_environment`
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Managed');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('2', '3', 'Managed');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('3', '5', 'Managed');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('4', '7', 'Managed');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('5', '9', 'Managed');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Unmanaged');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('2', '4', 'Unmanaged');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('3', '6', 'Unmanaged');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('4', '8', 'Unmanaged');
INSERT INTO `equipment_environment` (`company_id`, `id`, `name`) VALUES ('5', '10', 'Unmanaged');

-- Table structure for `equipment_fiber`
DROP TABLE IF EXISTS `equipment_fiber`;
CREATE TABLE `equipment_fiber` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber`
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('1', '3', 'QSFP 40 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('2', '4', 'QSFP 40 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('3', '7', 'QSFP 40 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('4', '10', 'QSFP 40 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('5', '13', 'QSFP 40 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('1', '1', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('2', '5', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('3', '8', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('4', '11', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('5', '14', 'SFP 1 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('1', '2', 'SFP+ 10 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('2', '6', 'SFP+ 10 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('3', '9', 'SFP+ 10 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('4', '12', 'SFP+ 10 Gbps');
INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`) VALUES ('5', '15', 'SFP+ 10 Gbps');

-- Table structure for `equipment_fiber_patch`
DROP TABLE IF EXISTS `equipment_fiber_patch`;
CREATE TABLE `equipment_fiber_patch` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_patch_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_patch`
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Patch Panel A');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('2', '3', 'Patch Panel A');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('3', '5', 'Patch Panel A');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('4', '7', 'Patch Panel A');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('5', '9', 'Patch Panel A');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Patch Panel B');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('2', '4', 'Patch Panel B');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('3', '6', 'Patch Panel B');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('4', '8', 'Patch Panel B');
INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`) VALUES ('5', '10', 'Patch Panel B');

-- Table structure for `equipment_fiber_rack`
DROP TABLE IF EXISTS `equipment_fiber_rack`;
CREATE TABLE `equipment_fiber_rack` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_rack_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_rack`
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Rack A');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('2', '3', 'Rack A');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('3', '5', 'Rack A');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('4', '7', 'Rack A');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('5', '9', 'Rack A');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Rack B');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('2', '4', 'Rack B');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('3', '6', 'Rack B');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('4', '8', 'Rack B');
INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`) VALUES ('5', '10', 'Rack B');

-- Table structure for `equipment_fiber_count`
DROP TABLE IF EXISTS `equipment_fiber_count`;
CREATE TABLE `equipment_fiber_count` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_fiber_count_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_count`
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '1', '1');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('2', '5', '1');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('3', '9', '1');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('4', '13', '1');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('5', '17', '1');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '2', '2');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('2', '6', '2');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('3', '10', '2');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('4', '14', '2');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('5', '18', '2');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '3', '3');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('2', '7', '3');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('3', '11', '3');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('4', '15', '3');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('5', '19', '3');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('1', '4', '4');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('2', '8', '4');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('3', '12', '4');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('4', '16', '4');
INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`) VALUES ('5', '20', '4');

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
  `poe_id` int DEFAULT NULL,
  `cable_color` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hex_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_port_unique` (`company_id`,`position_id`,`port_no`,`port_type`),
  KEY `company_id` (`company_id`),
  KEY `position_id` (`position_id`),
  KEY `idf_ports_port_type_idx` (`port_type`),
  KEY `idf_ports_status_idx` (`status_id`),
  KEY `idf_ports_vlan_idx` (`vlan_id`),
  KEY `idf_ports_speed_idx` (`speed_id`),
  KEY `idf_ports_poe_idx` (`poe_id`),
  CONSTRAINT `idf_ports_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_ports_ibfk_position` FOREIGN KEY (`position_id`) REFERENCES `idf_positions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Table structure for `equipment_poe`
DROP TABLE IF EXISTS `equipment_poe`;
CREATE TABLE `equipment_poe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_poe_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_poe`
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('1', '1', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('2', '4', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('3', '7', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('4', '10', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('5', '13', 'PoE (802.3af) - up to 15.4W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('1', '2', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('2', '5', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('3', '8', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('4', '11', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('5', '14', 'PoE+ (802.3at) - up to 30W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('1', '3', 'PoE++ (802.3bt) - up to 60-90W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('2', '6', 'PoE++ (802.3bt) - up to 60-90W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('3', '9', 'PoE++ (802.3bt) - up to 60-90W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('4', '12', 'PoE++ (802.3bt) - up to 60-90W');
INSERT INTO `equipment_poe` (`company_id`, `id`, `name`) VALUES ('5', '15', 'PoE++ (802.3bt) - up to 60-90W');
ALTER TABLE `idf_ports`
  ADD CONSTRAINT `idf_ports_ibfk_speed` FOREIGN KEY (`speed_id`) REFERENCES `equipment_fiber` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `idf_ports_ibfk_poe` FOREIGN KEY (`poe_id`) REFERENCES `equipment_poe` (`id`) ON DELETE SET NULL;

-- Table structure for `equipment_rj45`
DROP TABLE IF EXISTS `equipment_rj45`;
CREATE TABLE `equipment_rj45` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_rj45_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_rj45`
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '2', '16 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('2', '5', '16 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('3', '9', '16 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('4', '13', '16 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('5', '17', '16 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '3', '24 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('2', '6', '24 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('3', '10', '24 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('4', '14', '24 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('5', '18', '24 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '4', '48 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('2', '7', '48 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('3', '11', '48 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('4', '15', '48 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('5', '19', '48 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('1', '1', '8 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('2', '8', '8 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('3', '12', '8 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('4', '16', '8 ports');
INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`) VALUES ('5', '20', '8 ports');

-- Table structure for `equipment_statuses`
DROP TABLE IF EXISTS `equipment_statuses`;
CREATE TABLE `equipment_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_statuses`
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '9', 'Active');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '17', 'Active');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '25', 'Active');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '33', 'Active');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Decommissioned');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '10', 'Decommissioned');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '18', 'Decommissioned');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '26', 'Decommissioned');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '34', 'Decommissioned');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Faulty');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '11', 'Faulty');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '19', 'Faulty');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '27', 'Faulty');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '35', 'Faulty');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inactive');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '12', 'Inactive');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '20', 'Inactive');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '28', 'Inactive');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '36', 'Inactive');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Maintenance');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '13', 'Maintenance');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '21', 'Maintenance');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '29', 'Maintenance');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '37', 'Maintenance');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '7', 'On-Order');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '14', 'On-Order');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '22', 'On-Order');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '30', 'On-Order');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '38', 'On-Order');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Other');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '15', 'Other');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '23', 'Other');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '31', 'Other');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '39', 'Other');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Reserved');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('2', '16', 'Reserved');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('3', '24', 'Reserved');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('4', '32', 'Reserved');
INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`) VALUES ('5', '40', 'Reserved');

-- Table structure for `equipment_types`
DROP TABLE IF EXISTS `equipment_types`;
CREATE TABLE `equipment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_edit_emoji` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `equipment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_types`
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '1', 'Switch', 'SWITCH', '🔀', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '2', 'Server', 'SRV', '🖥️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '3', 'Router', 'RTR', '✳️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '4', 'Firewall', 'FW', '🔥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '5', 'Port Patch Panel', 'PORT', '➿', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '6', 'Access Point', 'AP', '🛜', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '7', 'Workstation', 'WS', '💻', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '8', 'POS', 'POS', '🏧', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '9', 'Printer', 'PRN', '🖨️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '10', 'Phone', 'PHONE', '📞', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '11', 'CCTV', 'CCCTV', '🎥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('1', '12', 'Other', 'OTHER', NULL, '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '13', 'Switch', 'SWITCH', '🔀', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '14', 'Server', 'SRV', '🖥️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '15', 'Router', 'RTR', '✳️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '16', 'Firewall', 'FW', '🔥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '17', 'Port Patch Panel', 'PORT', '➿', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '18', 'Access Point', 'AP', '🛜', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '19', 'Workstation', 'WS', '💻', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '20', 'POS', 'POS', '🏧', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '21', 'Printer', 'PRN', '🖨️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '22', 'Phone', 'PHONE', '📞', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '23', 'CCTV', 'CCCTV', '🎥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('2', '24', 'Other', 'OTHER', NULL, '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '25', 'Switch', 'SWITCH', '🔀', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '26', 'Server', 'SRV', '🖥️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '27', 'Router', 'RTR', '✳️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '28', 'Firewall', 'FW', '🔥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '29', 'Port Patch Panel', 'PORT', '➿', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '30', 'Access Point', 'AP', '🛜', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '31', 'Workstation', 'WS', '💻', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '32', 'POS', 'POS', '🏧', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '33', 'Printer', 'PRN', '🖨️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '34', 'Phone', 'PHONE', '📞', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '35', 'CCTV', 'CCCTV', '🎥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('3', '36', 'Other', 'OTHER', NULL, '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '37', 'Switch', 'SWITCH', '🔀', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '38', 'Server', 'SRV', '🖥️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '39', 'Router', 'RTR', '✳️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '40', 'Firewall', 'FW', '🔥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '41', 'Port Patch Panel', 'PORT', '➿', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '42', 'Access Point', 'AP', '🛜', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '43', 'Workstation', 'WS', '💻', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '44', 'POS', 'POS', '🏧', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '45', 'Printer', 'PRN', '🖨️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '46', 'Phone', 'PHONE', '📞', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '47', 'CCTV', 'CCCTV', '🎥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('4', '48', 'Other', 'OTHER', NULL, '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '49', 'Switch', 'SWITCH', '🔀', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '50', 'Server', 'SRV', '🖥️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '51', 'Router', 'RTR', '✳️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '52', 'Firewall', 'FW', '🔥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '53', 'Port Patch Panel', 'PORT', '➿', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '54', 'Access Point', 'AP', '🛜', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '55', 'Workstation', 'WS', '💻', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '56', 'POS', 'POS', '🏧', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '57', 'Printer', 'PRN', '🖨️', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '58', 'Phone', 'PHONE', '📞', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '59', 'CCTV', 'CCCTV', '🎥', '1');
INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`) VALUES ('5', '60', 'Other', 'OTHER', NULL, '1');

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
  `equipment_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `equipment_status_id` int DEFAULT NULL,
  `equipment_color_id` int DEFAULT NULL,
  `cable_color_id` int DEFAULT NULL,
  `cable_color_hex` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cable_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`company_id`,`port_id_a`,`port_id_b`),
  KEY `port_id_a` (`port_id_a`),
  KEY `port_id_b` (`port_id_b`),
  KEY `cable_color_id` (`cable_color_id`),
  CONSTRAINT `idf_links_ibfk_a` FOREIGN KEY (`port_id_a`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_b` FOREIGN KEY (`port_id_b`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
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
  `active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_device_type_unique` (`company_id`,`idfdevicetype_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `idf_device_type_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idf_device_type`
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'switch', '🔀', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'switch', '🔀', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'switch', '🔀', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'switch', '🔀', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'switch', '🔀', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'patch_panel', '➿', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'patch_panel', '➿', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'patch_panel', '➿', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'patch_panel', '➿', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'patch_panel', '➿', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'ups', '🔋', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'ups', '🔋', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'ups', '🔋', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'ups', '🔋', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'ups', '🔋', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'server', '🖥️', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'server', '🖥️', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'server', '🖥️', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'server', '🖥️', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'server', '🖥️', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'other', '📦', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'other', '📦', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'other', '📦', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'other', '📦', '1', CURRENT_TIMESTAMP, NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'other', '📦', '1', CURRENT_TIMESTAMP, NULL);

-- Table structure for `idf_positions`
DROP TABLE IF EXISTS `idf_positions`;
CREATE TABLE `idf_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `idf_id` int NOT NULL,
  `position_no` tinyint NOT NULL,
  `device_type` int NOT NULL,
  `device_name` varchar(140) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `equipment_id` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `port_count` smallint NOT NULL DEFAULT '0',
  `switch_port_numbering_layout_id` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  `active` tinyint DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_code` (`company_id`,`idf_code`),
  KEY `company_id` (`company_id`),
  KEY `location_id` (`location_id`),
  KEY `rack_id` (`rack_id`),
  CONSTRAINT `idfs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idfs_ibfk_location` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idfs_ibfk_rack` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idfs`
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('1', '1', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('2', '2', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('3', '3', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('4', '4', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58');
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('5', '5', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58');

-- Table structure for `inventory_categories`
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `inventory_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_categories`
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '7', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '13', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '19', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '25', 'Cables - Ethernet', 'CBL-ETH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '8', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '14', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '20', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '26', 'Cables - USB', 'CBL-USB', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '9', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '15', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '21', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '27', 'Adapters', 'ADP', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '10', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '16', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '22', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '28', 'Batteries', 'BAT', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '11', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '17', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '23', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '29', 'Consumables', 'CONS', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '6', 'Other', 'OTH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '12', 'Other', 'OTH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '18', 'Other', 'OTH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '24', 'Other', 'OTH', '1');
INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '30', 'Other', 'OTH', '1');

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
  `last_user_id` int DEFAULT NULL,
  `last_user_manual` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`company_id`,`item_code`),
  KEY `category_id` (`category_id`),
  KEY `manufacturer_id` (`manufacturer_id`),
  KEY `location_id` (`location_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `last_user_id` (`last_user_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`),
  CONSTRAINT `inventory_items_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `inventory_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `inventory_items_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `inventory_items_ibfk_6` FOREIGN KEY (`last_user_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_items`
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_user_id`, `last_user_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', '1', '1', '50', '10', '4.99', NULL, NULL, 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_user_id`, `last_user_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', '7', '1', '50', '10', '4.99', NULL, NULL, 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_user_id`, `last_user_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', '13', '1', '50', '10', '4.99', NULL, NULL, 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_user_id`, `last_user_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', '19', '1', '50', '10', '4.99', NULL, NULL, 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_user_id`, `last_user_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', '25', '1', '50', '10', '4.99', NULL, NULL, 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_it_locations_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `type_id` (`type_id`),
  CONSTRAINT `it_locations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_locations_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `location_types` (`id`)) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `it_locations`
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', NULL, NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', NULL, NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', NULL, NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', NULL, NULL);
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', NULL, NULL);

-- Table structure for `location_types`
DROP TABLE IF EXISTS `location_types`;
CREATE TABLE `location_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `location_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `location_types`
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Branch');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '8', 'Branch');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '15', 'Branch');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '22', 'Branch');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '29', 'Branch');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'DataCenter');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '9', 'DataCenter');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '16', 'DataCenter');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '23', 'DataCenter');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '30', 'DataCenter');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Headquarters');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '10', 'Headquarters');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '17', 'Headquarters');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '24', 'Headquarters');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '31', 'Headquarters');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Office');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '11', 'Office');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '18', 'Office');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '25', 'Office');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '32', 'Office');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'Other');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '12', 'Other');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '19', 'Other');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '26', 'Other');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '33', 'Other');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Remote');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '13', 'Remote');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '20', 'Remote');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '27', 'Remote');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '34', 'Remote');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Warehouse');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('2', '14', 'Warehouse');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('3', '21', 'Warehouse');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('4', '28', 'Warehouse');
INSERT INTO `location_types` (`company_id`, `id`, `name`) VALUES ('5', '35', 'Warehouse');

-- Table structure for `manufacturers`
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE `manufacturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `manufacturers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `manufacturers`
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '9', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '17', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '25', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '33', 'Cisco Systems', 'CSCO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '10', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '18', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '26', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '34', 'Dell Technologies', 'DELL', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '11', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '19', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '27', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '35', 'HP Inc', 'HPE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '12', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '20', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '28', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '36', 'Juniper Networks', 'JNPR', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '13', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '21', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '29', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '37', 'Ubiquiti Networks', 'UBNT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '6', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '14', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '22', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '30', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '38', 'Apple', 'APPLE', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '7', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '15', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '23', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '31', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '39', 'Lenovo', 'LENOVO', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '8', 'Microsoft', 'MSFT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '16', 'Microsoft', 'MSFT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '24', 'Microsoft', 'MSFT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '32', 'Microsoft', 'MSFT', '1');
INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '40', 'Microsoft', 'MSFT', '1');



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
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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

INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, 'https://fls-na.amaz', 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-04-12 16:49:33', '2026-04-13 01:23:57'),
(2, 1, 'Cisco Catalyst C9200L-24P-4G-A', 1, 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-04-12 16:49:33', '2026-04-13 01:23:38'),
(3, 1, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-04-12 16:49:33', '2026-04-13 01:23:33'),
(4, 1, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, 'https://www.bhphotovideo.com/', 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-04-12 16:49:33', '2026-04-13 01:23:20'),
(5, 1, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, 'https://www.bestbuy.com/', 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-04-12 16:49:33', '2026-04-13 01:23:29'),
(7, 1, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-04-12 16:49:33', '2026-04-13 01:20:28'),
(8, 1, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-04-12 16:49:33', '2026-04-13 01:20:22'),
(9, 1, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-04-12 16:49:33', '2026-04-13 01:20:12'),
(10, 1, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', 1599.00, 1, 1, 'https://www.insight.com/', 1, '2026-04-12 16:49:33', '2026-04-12 16:51:50'),
(11, 5, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, NULL, 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-04-12 16:49:34', NULL),
(13, 3, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, NULL, 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-04-12 16:49:34', NULL),
(14, 2, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, NULL, 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-04-12 16:49:34', NULL),
(15, 5, 'Cisco Catalyst C9200L-24P-4G-A', 1, NULL, 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-04-12 16:49:34', NULL),
(17, 3, 'Cisco Catalyst C9200L-24P-4G-A', 1, NULL, 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-04-12 16:49:34', NULL),
(18, 2, 'Cisco Catalyst C9200L-24P-4G-A', 1, NULL, 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-04-12 16:49:34', NULL),
(19, 5, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, NULL, 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-04-12 16:49:34', NULL),
(21, 3, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, NULL, 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-04-12 16:49:34', NULL),
(22, 2, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, NULL, 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-04-12 16:49:34', NULL),
(23, 5, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-04-12 16:49:34', NULL),
(25, 3, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-04-12 16:49:34', NULL),
(26, 2, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-04-12 16:49:34', NULL),
(27, 5, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-04-12 16:49:34', NULL),
(29, 3, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-04-12 16:49:34', NULL),
(30, 2, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-04-12 16:49:34', NULL),
(31, 5, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 1, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-04-12 16:49:34', NULL),
(33, 3, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 1, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-04-12 16:49:34', NULL),
(34, 2, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 1, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-04-12 16:49:34', NULL),
(35, 5, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, NULL, 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-04-12 16:49:34', NULL),
(37, 3, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, NULL, 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-04-12 16:49:34', NULL),
(38, 2, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, NULL, 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-04-12 16:49:34', NULL),
(39, 5, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, NULL, 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-04-12 16:49:34', NULL),
(41, 3, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, NULL, 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-04-12 16:49:34', NULL),
(42, 2, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, NULL, 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-04-12 16:49:34', NULL),
(43, 5, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-04-12 16:49:34', NULL),
(45, 3, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-04-12 16:49:34', NULL),
(46, 2, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-04-12 16:49:34', NULL),
(47, 5, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, NULL, 1599.00, NULL, 1, 'https://www.insight.com/', 1, '2026-04-12 16:49:34', NULL),
(49, 3, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, NULL, 1599.00, NULL, 1, 'https://www.insight.com/', 1, '2026-04-12 16:49:34', NULL),
(50, 2, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, NULL, 1599.00, NULL, 1, 'https://www.insight.com/', 1, '2026-04-12 16:49:34', NULL),
(84, 4, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, NULL, 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-04-12 17:29:32', NULL),
(85, 4, 'Cisco Catalyst C9200L-24P-4G-A', 1, NULL, 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-04-12 17:29:32', NULL),
(86, 4, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, NULL, 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-04-12 17:29:32', NULL),
(87, 4, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-04-12 17:29:32', NULL),
(88, 4, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-04-12 17:29:32', NULL),
(89, 4, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 1, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-04-12 17:29:32', NULL),
(90, 4, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, NULL, 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-04-12 17:29:32', NULL),
(91, 4, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, NULL, 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-04-12 17:29:32', NULL),
(92, 4, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-04-12 17:29:32', NULL),
(93, 4, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, NULL, 1599.00, NULL, 1, 'https://www.insight.com/', 1, '2026-04-12 17:29:32', NULL);


-- Table structure for `patches_updates_status`
DROP TABLE IF EXISTS `patches_updates_status`;
CREATE TABLE `patches_updates_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_closed` tinyint DEFAULT '0',
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patches_updates_status_company_name_unique` (`company_id`,`name`),
  KEY `patches_updates_status_company_idx` (`company_id`),
  CONSTRAINT `patches_updates_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `patches_updates_status`
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('1','1', 'Open', '#FF0000', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('1','2', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('1','3', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('1','4', 'Closed', '#808080', '1', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('2','5', 'Open', '#FF0000', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('2','6', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('2','7', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('2','8', 'Closed', '#808080', '1', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('3','9', 'Open', '#FF0000', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('3','10', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('3','11', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('3','12', 'Closed', '#808080', '1', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('4','13', 'Open', '#FF0000', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('4','14', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('4','15', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('4','16', 'Closed', '#808080', '1', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('5','17', 'Open', '#FF0000', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('5','18', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('5','19', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `patches_updates_status` (`company_id`,`id`, `name`, `color`, `is_closed`, `active`) VALUES ('5','20', 'Closed', '#808080', '1', '1');

-- Table structure for `patches_updates_level`
DROP TABLE IF EXISTS `patches_updates_level`;
CREATE TABLE `patches_updates_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patches_updates_level_company_level_unique` (`company_id`,`level`),
  KEY `patches_updates_level_company_idx` (`company_id`),
  CONSTRAINT `patches_updates_level_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `patches_updates_level`
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('1','1','Critical');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('1','2','High');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('1','3','Medium');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('1','4','Low');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('1','5','Other');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('2','6','Critical');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('2','7','High');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('2','8','Medium');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('2','9','Low');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('2','10','Other');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('3','11','Critical');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('3','12','High');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('3','13','Medium');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('3','14','Low');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('3','15','Other');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('4','16','Critical');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('4','17','High');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('4','18','Medium');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('4','19','Low');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('4','20','Other');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('5','21','Critical');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('5','22','High');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('5','23','Medium');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('5','24','Low');
INSERT INTO `patches_updates_level` (`company_id`,`id`,`level`) VALUES ('5','25','Other');

-- Table structure for `patches_updates`
DROP TABLE IF EXISTS `patches_updates`;
CREATE TABLE `patches_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
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
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patches_updates_company_idx` (`company_id`),
  KEY `patches_updates_equipment_idx` (`equipment_id`),
  KEY `patches_updates_status_idx` (`status_id`),
  KEY `patches_updates_level_idx` (`level_id`),
  KEY `patches_updates_created_by_idx` (`created_by`),
  CONSTRAINT `patches_updates_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `patches_updates_status` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_level` FOREIGN KEY (`level_id`) REFERENCES `patches_updates_level` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `printer_device_types`
DROP TABLE IF EXISTS `printer_device_types`;
CREATE TABLE `printer_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `printer_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `printer_device_types`
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'All-in-One');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '10', 'All-in-One');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '19', 'All-in-One');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '28', 'All-in-One');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '37', 'All-in-One');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Dotmatrix');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '11', 'Dotmatrix');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '20', 'Dotmatrix');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '29', 'Dotmatrix');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '38', 'Dotmatrix');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inkjet');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '12', 'Inkjet');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '21', 'Inkjet');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '30', 'Inkjet');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '39', 'Inkjet');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'Label');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '13', 'Label');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '22', 'Label');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '31', 'Label');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '40', 'Label');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Laser');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '14', 'Laser');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '23', 'Laser');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '32', 'Laser');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '41', 'Laser');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '9', 'Other');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '15', 'Other');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '24', 'Other');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '33', 'Other');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '42', 'Other');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Photo');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '16', 'Photo');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '25', 'Photo');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '34', 'Photo');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '43', 'Photo');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Thermal');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '17', 'Thermal');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '26', 'Thermal');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '35', 'Thermal');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '44', 'Thermal');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Wide-Format');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('2', '18', 'Wide-Format');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('3', '27', 'Wide-Format');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('4', '36', 'Wide-Format');
INSERT INTO `printer_device_types` (`company_id`, `id`, `name`) VALUES ('5', '45', 'Wide-Format');

-- Table structure for `rack_statuses`
DROP TABLE IF EXISTS `rack_statuses`;
CREATE TABLE `rack_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `rack_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `rack_statuses`
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('2', '5', 'Active');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('3', '9', 'Active');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('4', '13', 'Active');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('5', '17', 'Active');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Decommissioned');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Decommissioned');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('3', '10', 'Decommissioned');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('4', '14', 'Decommissioned');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('5', '18', 'Decommissioned');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Full');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('2', '7', 'Full');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('3', '11', 'Full');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('4', '15', 'Full');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('5', '19', 'Full');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Maintenance');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('2', '8', 'Maintenance');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('3', '12', 'Maintenance');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('4', '16', 'Maintenance');
INSERT INTO `rack_statuses` (`company_id`, `id`, `name`) VALUES ('5', '20', 'Maintenance');

-- Table structure for `racks`
DROP TABLE IF EXISTS `racks`;
CREATE TABLE `racks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rack_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rack_code` (`company_id`,`rack_code`),
  KEY `location_id` (`location_id`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `racks_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `racks_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `rack_statuses` (`id`)) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `racks`
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('1', '1', '1', 'Main Rack A', 'RACK-A', '1', '1');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('2', '2', '2', 'Main Rack A', 'RACK-A', '5', '1');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('3', '3', '3', 'Main Rack A', 'RACK-A', '9', '1');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('4', '4', '4', 'Main Rack A', 'RACK-A', '13', '1');
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) VALUES ('5', '5', '5', 'Main Rack A', 'RACK-A', '17', '1');


-- Table structure for `user_sidebar_preferences`
DROP TABLE IF EXISTS `user_sidebar_preferences`;
CREATE TABLE `user_sidebar_preferences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `user_id` int NOT NULL,
  `entry_type` enum('section','item') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sidebar_pref_entry` (`company_id`,`user_id`,`entry_type`,`entry_id`),
  KEY `idx_user_sidebar_pref_company_user_type_order` (`company_id`,`user_id`,`entry_type`,`display_order`),
  CONSTRAINT `fk_user_sidebar_pref_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_sidebar_preferences`
-- Why: seed default sidebar layout for all 5 base companies and rely on table defaults for timestamps.
INSERT INTO `user_sidebar_preferences` (`company_id`, `user_id`, `entry_type`, `entry_id`, `section_id`, `display_order`, `is_visible`, `active`)
SELECT c.company_id, 1 AS user_id, t.entry_type, t.entry_id, t.section_id, t.display_order, 1 AS is_visible, 1 AS active
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
      UNION ALL SELECT 'item' AS entry_type, 'inventory' AS entry_id, 'admin' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'users' AS entry_id, 'admin' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'companies' AS entry_id, 'admin' AS section_id, 2 AS display_order
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
      UNION ALL SELECT 'item' AS entry_type, 'inventory_items' AS entry_id, 'reference_data' AS section_id, 38 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates' AS entry_id, 'reference_data' AS section_id, 39 AS display_order
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
      UNION ALL SELECT 'item' AS entry_type, 'user_companies' AS entry_id, 'reference_data' AS section_id, 51 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'user_roles' AS entry_id, 'reference_data' AS section_id, 52 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'user_sidebar_preferences' AS entry_id, 'reference_data' AS section_id, 53 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'vlans' AS entry_id, 'reference_data' AS section_id, 54 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'warranty_types' AS entry_id, 'reference_data' AS section_id, 55 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_device_types' AS entry_id, 'reference_data' AS section_id, 56 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_modes' AS entry_id, 'reference_data' AS section_id, 57 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_office' AS entry_id, 'reference_data' AS section_id, 58 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_os_types' AS entry_id, 'reference_data' AS section_id, 59 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_os_versions' AS entry_id, 'reference_data' AS section_id, 60 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_ram' AS entry_id, 'reference_data' AS section_id, 61 AS display_order
) AS t
ORDER BY c.company_id, FIELD(t.entry_type, 'section', 'item'), t.display_order, t.entry_id;


-- Table structure for `supplier_statuses`
DROP TABLE IF EXISTS `supplier_statuses`;
CREATE TABLE `supplier_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `supplier_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `supplier_statuses`
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Active');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Active');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('3', '11', 'Active');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('4', '16', 'Active');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('5', '21', 'Active');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Backup');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('2', '7', 'Backup');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('3', '12', 'Backup');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('4', '17', 'Backup');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('5', '22', 'Backup');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Inactive');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('2', '8', 'Inactive');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('3', '13', 'Inactive');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('4', '18', 'Inactive');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('5', '23', 'Inactive');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Other');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('2', '9', 'Other');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('3', '14', 'Other');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('4', '19', 'Other');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('5', '24', 'Other');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Preferred');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('2', '10', 'Preferred');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('3', '15', 'Preferred');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('4', '20', 'Preferred');
INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`) VALUES ('5', '25', 'Preferred');

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`company_id`,`supplier_code`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `supplier_statuses` (`id`)) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `suppliers`
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('1', '1', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '1', '1');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('2', '2', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '6', '1');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('3', '3', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '11', '1');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('4', '4', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '16', '1');
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) VALUES ('5', '5', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '21', '1');

-- Table structure for `cable_colors`
DROP TABLE IF EXISTS `cable_colors`;
CREATE TABLE `cable_colors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `color_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'grey',
  `hex_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `color_name` (`company_id`,`color_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `cable_colors_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `cable_colors`
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '1', 'Gray', '#808080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '11', 'Gray', '#808080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '21', 'Gray', '#808080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '31', 'Gray', '#808080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '41', 'Gray', '#808080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '2', 'Green', '#03b003', 'Printers');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '12', 'Green', '#03b003', 'Printers');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '22', 'Green', '#03b003', 'Printers');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '32', 'Green', '#03b003', 'Printers');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '42', 'Green', '#03b003', 'Printers');
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '3', 'Red', '#ff0000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '13', 'Red', '#ff0000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '23', 'Red', '#ff0000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '33', 'Red', '#ff0000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '43', 'Red', '#ff0000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '4', 'Yellow', '#ffff00', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '14', 'Yellow', '#ffff00', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '24', 'Yellow', '#ffff00', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '34', 'Yellow', '#ffff00', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '44', 'Yellow', '#ffff00', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '5', 'Black', '#000000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '15', 'Black', '#000000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '25', 'Black', '#000000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '35', 'Black', '#000000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '45', 'Black', '#000000', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '6', 'Blue', '#0000ff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '16', 'Blue', '#0000ff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '26', 'Blue', '#0000ff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '36', 'Blue', '#0000ff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '46', 'Blue', '#0000ff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '7', 'White', '#ffffff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '17', 'White', '#ffffff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '27', 'White', '#ffffff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '37', 'White', '#ffffff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '47', 'White', '#ffffff', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '8', 'Orange', '#ffa500', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '18', 'Orange', '#ffa500', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '28', 'Orange', '#ffa500', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '38', 'Orange', '#ffa500', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '48', 'Orange', '#ffa500', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '9', 'Dark Pink', '#800080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '19', 'Dark Pink', '#800080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '29', 'Dark Pink', '#800080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '39', 'Dark Pink', '#800080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '49', 'Dark Pink', '#800080', NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('1', '10', 'Other', NULL, NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('2', '20', 'Other', NULL, NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('3', '30', 'Other', NULL, NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('4', '40', 'Other', NULL, NULL);
INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`) VALUES ('5', '50', 'Other', NULL, NULL);

-- Table structure for `switch_port_numbering_layout`
DROP TABLE IF EXISTS `switch_port_numbering_layout`;
CREATE TABLE `switch_port_numbering_layout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_port_numbering_layout_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_numbering_layout`
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Horizontal');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('2', '3', 'Horizontal');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('3', '5', 'Horizontal');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('4', '7', 'Horizontal');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('5', '9', 'Horizontal');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Vertical');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('2', '4', 'Vertical');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('3', '6', 'Vertical');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('4', '8', 'Vertical');
INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`) VALUES ('5', '10', 'Vertical');

-- Table structure for `switch_port_types`
DROP TABLE IF EXISTS `switch_port_types`;
CREATE TABLE `switch_port_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`company_id`,`type`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `switch_port_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_types`
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '1', 'RJ45');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('2', '4', 'RJ45');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('3', '7', 'RJ45');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('4', '10', 'RJ45');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('5', '13', 'RJ45');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '2', 'SFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('2', '5', 'SFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('3', '8', 'SFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('4', '11', 'SFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('5', '14', 'SFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '3', 'SFP+');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('2', '6', 'SFP+');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('3', '9', 'SFP+');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('4', '12', 'SFP+');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('5', '15', 'SFP+');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('1', '16', 'QSFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('2', '17', 'QSFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('3', '18', 'QSFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('4', '19', 'QSFP');
INSERT INTO `switch_port_types` (`company_id`, `id`, `type`) VALUES ('5', '20', 'QSFP');
ALTER TABLE `idf_ports`
  ADD CONSTRAINT `idf_ports_ibfk_port_type` FOREIGN KEY (`port_type`) REFERENCES `switch_port_types` (`id`);

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
  `vlan_id` int DEFAULT NULL,
  `fiber_port_id` int DEFAULT NULL,
  `fiber_patch_id` int DEFAULT NULL,
  `fiber_rack_id` int DEFAULT NULL,
  `idf_id` int DEFAULT NULL,
  `to_idf_id` int DEFAULT NULL,
  `rack_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_switch_port` (`company_id`,`equipment_id`,`port_type`,`port_number`),
  KEY `company_id` (`company_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `idx_switch_ports_company_port_type` (`company_id`,`port_type`),
  KEY `status_id` (`status_id`),
  KEY `color_id` (`color_id`),
  KEY `vlan_id` (`vlan_id`),
  KEY `idx_switch_ports_fiber_port` (`fiber_port_id`),
  KEY `idx_switch_ports_fiber_patch` (`fiber_patch_id`),
  KEY `idx_switch_ports_fiber_rack` (`fiber_rack_id`),
  KEY `idx_switch_ports_idf` (`idf_id`),
  KEY `idx_switch_ports_to_idf` (`to_idf_id`),
  KEY `idx_switch_ports_rack` (`rack_id`),
  KEY `idx_switch_ports_location` (`location_id`),
  CONSTRAINT `switch_ports_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_ports_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`),
  CONSTRAINT `switch_ports_ibfk_4` FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`),
  CONSTRAINT `switch_ports_ibfk_5` FOREIGN KEY (`company_id`,`port_type`) REFERENCES `switch_port_types` (`company_id`,`type`),
  CONSTRAINT `switch_ports_ibfk_6` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_7` FOREIGN KEY (`fiber_port_id`) REFERENCES `equipment_fiber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_8` FOREIGN KEY (`fiber_patch_id`) REFERENCES `equipment_fiber_patch` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_9` FOREIGN KEY (`fiber_rack_id`) REFERENCES `equipment_fiber_rack` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_10` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_13` FOREIGN KEY (`to_idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_11` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_ports_ibfk_12` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_ports`
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('1', '1', '1', NULL, 'RJ45', '1', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('25', '2', '2', NULL, 'RJ45', '1', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('49', '3', '3', NULL, 'RJ45', '1', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('73', '4', '4', NULL, 'RJ45', '1', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('97', '5', '5', NULL, 'RJ45', '1', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('2', '1', '1', NULL, 'RJ45', '2', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('26', '2', '2', NULL, 'RJ45', '2', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('50', '3', '3', NULL, 'RJ45', '2', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('74', '4', '4', NULL, 'RJ45', '2', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('98', '5', '5', NULL, 'RJ45', '2', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('3', '1', '1', NULL, 'RJ45', '3', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('27', '2', '2', NULL, 'RJ45', '3', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('51', '3', '3', NULL, 'RJ45', '3', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('75', '4', '4', NULL, 'RJ45', '3', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('99', '5', '5', NULL, 'RJ45', '3', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('4', '1', '1', NULL, 'RJ45', '4', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('28', '2', '2', NULL, 'RJ45', '4', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('52', '3', '3', NULL, 'RJ45', '4', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('76', '4', '4', NULL, 'RJ45', '4', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('100', '5', '5', NULL, 'RJ45', '4', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('5', '1', '1', NULL, 'RJ45', '5', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('29', '2', '2', NULL, 'RJ45', '5', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('53', '3', '3', NULL, 'RJ45', '5', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('77', '4', '4', NULL, 'RJ45', '5', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('101', '5', '5', NULL, 'RJ45', '5', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('6', '1', '1', NULL, 'RJ45', '6', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('30', '2', '2', NULL, 'RJ45', '6', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('54', '3', '3', NULL, 'RJ45', '6', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('78', '4', '4', NULL, 'RJ45', '6', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('102', '5', '5', NULL, 'RJ45', '6', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('7', '1', '1', NULL, 'RJ45', '7', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('31', '2', '2', NULL, 'RJ45', '7', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('55', '3', '3', NULL, 'RJ45', '7', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('79', '4', '4', NULL, 'RJ45', '7', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('103', '5', '5', NULL, 'RJ45', '7', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('8', '1', '1', NULL, 'RJ45', '8', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('32', '2', '2', NULL, 'RJ45', '8', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('56', '3', '3', NULL, 'RJ45', '8', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('80', '4', '4', NULL, 'RJ45', '8', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('104', '5', '5', NULL, 'RJ45', '8', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('9', '1', '1', NULL, 'RJ45', '9', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('33', '2', '2', NULL, 'RJ45', '9', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('57', '3', '3', NULL, 'RJ45', '9', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('81', '4', '4', NULL, 'RJ45', '9', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('105', '5', '5', NULL, 'RJ45', '9', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('10', '1', '1', NULL, 'RJ45', '10', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('34', '2', '2', NULL, 'RJ45', '10', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('58', '3', '3', NULL, 'RJ45', '10', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('82', '4', '4', NULL, 'RJ45', '10', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('106', '5', '5', NULL, 'RJ45', '10', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('11', '1', '1', NULL, 'RJ45', '11', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('35', '2', '2', NULL, 'RJ45', '11', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('59', '3', '3', NULL, 'RJ45', '11', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('83', '4', '4', NULL, 'RJ45', '11', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('107', '5', '5', NULL, 'RJ45', '11', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('12', '1', '1', NULL, 'RJ45', '12', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('36', '2', '2', NULL, 'RJ45', '12', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('60', '3', '3', NULL, 'RJ45', '12', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('84', '4', '4', NULL, 'RJ45', '12', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('108', '5', '5', NULL, 'RJ45', '12', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('13', '1', '1', NULL, 'RJ45', '13', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('37', '2', '2', NULL, 'RJ45', '13', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('61', '3', '3', NULL, 'RJ45', '13', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('85', '4', '4', NULL, 'RJ45', '13', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('109', '5', '5', NULL, 'RJ45', '13', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('14', '1', '1', NULL, 'RJ45', '14', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('38', '2', '2', NULL, 'RJ45', '14', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('62', '3', '3', NULL, 'RJ45', '14', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('86', '4', '4', NULL, 'RJ45', '14', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('110', '5', '5', NULL, 'RJ45', '14', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('15', '1', '1', NULL, 'RJ45', '15', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('39', '2', '2', NULL, 'RJ45', '15', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('63', '3', '3', NULL, 'RJ45', '15', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('87', '4', '4', NULL, 'RJ45', '15', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('111', '5', '5', NULL, 'RJ45', '15', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('16', '1', '1', NULL, 'RJ45', '16', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('40', '2', '2', NULL, 'RJ45', '16', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('64', '3', '3', NULL, 'RJ45', '16', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('88', '4', '4', NULL, 'RJ45', '16', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('112', '5', '5', NULL, 'RJ45', '16', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('17', '1', '1', NULL, 'RJ45', '17', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('41', '2', '2', NULL, 'RJ45', '17', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('65', '3', '3', NULL, 'RJ45', '17', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('89', '4', '4', NULL, 'RJ45', '17', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('113', '5', '5', NULL, 'RJ45', '17', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('18', '1', '1', NULL, 'RJ45', '18', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('42', '2', '2', NULL, 'RJ45', '18', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('66', '3', '3', NULL, 'RJ45', '18', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('90', '4', '4', NULL, 'RJ45', '18', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('114', '5', '5', NULL, 'RJ45', '18', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('19', '1', '1', NULL, 'RJ45', '19', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('43', '2', '2', NULL, 'RJ45', '19', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('67', '3', '3', NULL, 'RJ45', '19', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('91', '4', '4', NULL, 'RJ45', '19', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('115', '5', '5', NULL, 'RJ45', '19', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('20', '1', '1', NULL, 'RJ45', '20', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('44', '2', '2', NULL, 'RJ45', '20', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('68', '3', '3', NULL, 'RJ45', '20', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('92', '4', '4', NULL, 'RJ45', '20', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('116', '5', '5', NULL, 'RJ45', '20', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('21', '1', '1', NULL, 'RJ45', '21', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('45', '2', '2', NULL, 'RJ45', '21', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('69', '3', '3', NULL, 'RJ45', '21', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('93', '4', '4', NULL, 'RJ45', '21', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('117', '5', '5', NULL, 'RJ45', '21', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('22', '1', '1', NULL, 'RJ45', '22', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('46', '2', '2', NULL, 'RJ45', '22', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('70', '3', '3', NULL, 'RJ45', '22', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('94', '4', '4', NULL, 'RJ45', '22', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('118', '5', '5', NULL, 'RJ45', '22', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('23', '1', '1', NULL, 'RJ45', '23', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('47', '2', '2', NULL, 'RJ45', '23', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('71', '3', '3', NULL, 'RJ45', '23', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('95', '4', '4', NULL, 'RJ45', '23', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('119', '5', '5', NULL, 'RJ45', '23', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('24', '1', '1', NULL, 'RJ45', '24', '0', '17', '1', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('48', '2', '2', NULL, 'RJ45', '24', '0', '26', '11', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('72', '3', '3', NULL, 'RJ45', '24', '0', '35', '21', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('96', '4', '4', NULL, 'RJ45', '24', '0', '5', '31', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`) VALUES ('120', '5', '5', NULL, 'RJ45', '24', '0', '44', '41', NULL, NULL, NULL, NULL, NULL, '', '2026-03-31 00:39:19');

-- Table structure for `switch_status`
DROP TABLE IF EXISTS `switch_status`;
CREATE TABLE `switch_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown',
  `color_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `status` (`company_id`,`status`),
  KEY `company_id` (`company_id`),
  KEY `color_id` (`color_id`),
  CONSTRAINT `switch_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `switch_status_ibfk_color` FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_status`
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '4', 'Disabled', '1');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '10', 'Disabled', '1');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '19', 'Disabled', '11');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '28', 'Disabled', '21');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '37', 'Disabled', '41');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '2', 'Down', '3');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '11', 'Down', '3');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '20', 'Down', '13');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '29', 'Down', '23');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '38', 'Down', '43');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '6', 'Err-Disabled', '9');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '12', 'Err-Disabled', '9');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '21', 'Err-Disabled', '19');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '30', 'Err-Disabled', '29');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '39', 'Err-Disabled', '49');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '8', 'Faulty', '8');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '13', 'Faulty', '8');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '22', 'Faulty', '18');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '31', 'Faulty', '28');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '40', 'Faulty', '48');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '3', 'Free', '2');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '14', 'Free', '2');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '23', 'Free', '12');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '32', 'Free', '22');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '41', 'Free', '42');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '9', 'Reserved', '4');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '15', 'Reserved', '4');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '24', 'Reserved', '14');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '33', 'Reserved', '24');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '42', 'Reserved', '44');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '7', 'Testing', '6');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '16', 'Testing', '6');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '25', 'Testing', '16');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '34', 'Testing', '26');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '43', 'Testing', '46');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '5', 'Unknown', '1');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '17', 'Unknown', '1');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '26', 'Unknown', '11');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '35', 'Unknown', '21');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '44', 'Unknown', '41');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('4', '1', 'Up', '6');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('1', '18', 'Up', '6');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('2', '27', 'Up', '16');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('3', '36', 'Up', '26');
INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`) VALUES ('5', '45', 'Up', '46');
ALTER TABLE `idf_ports`
  ADD CONSTRAINT `idf_ports_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`);

-- Table structure for `system_access`
DROP TABLE IF EXISTS `system_access`;
CREATE TABLE `system_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_access_company_code` (`company_id`,`code`),
  UNIQUE KEY `uq_system_access_company_name` (`company_id`,`name`),
  KEY `idx_system_access_company` (`company_id`),
  CONSTRAINT `fk_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `system_access`
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('1', '1', 'network_access', 'Network Access', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('16', '2', 'network_access', 'Network Access', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('31', '3', 'network_access', 'Network Access', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('46', '4', 'network_access', 'Network Access', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('61', '5', 'network_access', 'Network Access', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('2', '1', 'micros_emc', 'Micros Emc', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('17', '2', 'micros_emc', 'Micros Emc', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('32', '3', 'micros_emc', 'Micros Emc', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('47', '4', 'micros_emc', 'Micros Emc', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('62', '5', 'micros_emc', 'Micros Emc', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('3', '1', 'opera_username', 'Opera Username', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('18', '2', 'opera_username', 'Opera Username', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('33', '3', 'opera_username', 'Opera Username', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('48', '4', 'opera_username', 'Opera Username', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('63', '5', 'opera_username', 'Opera Username', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('4', '1', 'micros_card', 'Micros Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('19', '2', 'micros_card', 'Micros Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('34', '3', 'micros_card', 'Micros Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('49', '4', 'micros_card', 'Micros Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('64', '5', 'micros_card', 'Micros Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('5', '1', 'pms_id', 'PMS Id', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('20', '2', 'pms_id', 'PMS Id', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('35', '3', 'pms_id', 'PMS Id', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('50', '4', 'pms_id', 'PMS Id', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('65', '5', 'pms_id', 'PMS Id', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('6', '1', 'synergy_mms', 'Synergy Mms', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('21', '2', 'synergy_mms', 'Synergy Mms', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('36', '3', 'synergy_mms', 'Synergy Mms', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('51', '4', 'synergy_mms', 'Synergy Mms', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('66', '5', 'synergy_mms', 'Synergy Mms', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('7', '1', 'hu_the_lobby', 'HU The Lobby', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('22', '2', 'hu_the_lobby', 'HU The Lobby', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('37', '3', 'hu_the_lobby', 'HU The Lobby', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('52', '4', 'hu_the_lobby', 'HU The Lobby', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('67', '5', 'hu_the_lobby', 'HU The Lobby', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('8', '1', 'navision', 'Navision', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('23', '2', 'navision', 'Navision', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('38', '3', 'navision', 'Navision', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('53', '4', 'navision', 'Navision', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('68', '5', 'navision', 'Navision', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('9', '1', 'onq_ri', 'Onq Ri', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('24', '2', 'onq_ri', 'Onq Ri', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('39', '3', 'onq_ri', 'Onq Ri', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('54', '4', 'onq_ri', 'Onq Ri', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('69', '5', 'onq_ri', 'Onq Ri', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('10', '1', 'birchstreet', 'Birchstreet', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('25', '2', 'birchstreet', 'Birchstreet', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('40', '3', 'birchstreet', 'Birchstreet', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('55', '4', 'birchstreet', 'Birchstreet', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('70', '5', 'birchstreet', 'Birchstreet', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('11', '1', 'delphi', 'Delphi', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('26', '2', 'delphi', 'Delphi', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('41', '3', 'delphi', 'Delphi', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('56', '4', 'delphi', 'Delphi', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('71', '5', 'delphi', 'Delphi', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('12', '1', 'omina', 'Omina', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('27', '2', 'omina', 'Omina', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('42', '3', 'omina', 'Omina', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('57', '4', 'omina', 'Omina', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('72', '5', 'omina', 'Omina', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('13', '1', 'vingcard_system', 'Vingcard System', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('28', '2', 'vingcard_system', 'Vingcard System', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('43', '3', 'vingcard_system', 'Vingcard System', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('58', '4', 'vingcard_system', 'Vingcard System', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('73', '5', 'vingcard_system', 'Vingcard System', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('14', '1', 'digital_rev', 'Digital Rev', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('29', '2', 'digital_rev', 'Digital Rev', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('44', '3', 'digital_rev', 'Digital Rev', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('59', '4', 'digital_rev', 'Digital Rev', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('74', '5', 'digital_rev', 'Digital Rev', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('15', '1', 'office_key_card', 'Office Key Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('30', '2', 'office_key_card', 'Office Key Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('45', '3', 'office_key_card', 'Office Key Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('60', '4', 'office_key_card', 'Office Key Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('75', '5', 'office_key_card', 'Office Key Card', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('76', '1', 'email_account', 'Email Account', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('77', '2', 'email_account', 'Email Account', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('78', '3', 'email_account', 'Email Account', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('79', '4', 'email_account', 'Email Account', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('80', '5', 'email_account', 'Email Account', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('81', '1', 'landline_phone', 'Landline Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('82', '2', 'landline_phone', 'Landline Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('83', '3', 'landline_phone', 'Landline Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('84', '4', 'landline_phone', 'Landline Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('85', '5', 'landline_phone', 'Landline Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('86', '1', 'mobile_phone', 'Mobile Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('87', '2', 'mobile_phone', 'Mobile Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('88', '3', 'mobile_phone', 'Mobile Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('89', '4', 'mobile_phone', 'Mobile Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('90', '5', 'mobile_phone', 'Mobile Phone', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('91', '1', 'mobile_email', 'Mobile Email', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('92', '2', 'mobile_email', 'Mobile Email', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('93', '3', 'mobile_email', 'Mobile Email', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('94', '4', 'mobile_email', 'Mobile Email', '1');
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`) VALUES ('95', '5', 'mobile_email', 'Mobile Email', '1');

-- Table structure for `ticket_categories`
DROP TABLE IF EXISTS `ticket_categories`;
CREATE TABLE `ticket_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_categories`
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '1', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '6', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '11', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '16', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '21', 'Hardware Issue', 'HW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '2', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '7', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '12', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '17', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '22', 'Network Problem', 'NET', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '3', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '8', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '13', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '18', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '23', 'Software Issue', 'SW', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '4', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '9', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '14', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '19', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '24', 'Maintenance', 'MAINT', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('1', '5', 'Other', 'OTHER', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('2', '10', 'Other', 'OTHER', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('3', '15', 'Other', 'OTHER', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('4', '20', 'Other', 'OTHER', '1');
INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`) VALUES ('5', '25', 'Other', 'OTHER', '1');

-- Table structure for `ticket_priorities`
DROP TABLE IF EXISTS `ticket_priorities`;
CREATE TABLE `ticket_priorities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_priorities_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_priorities`
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '1', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('2', '6', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('3', '11', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('4', '16', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('5', '21', 'Low', '1', '#0000FF', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '2', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('2', '7', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('3', '12', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('4', '17', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('5', '22', 'Normal', '2', '#00FF00', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '3', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('2', '8', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('3', '13', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('4', '18', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('5', '23', 'High', '3', '#FFA500', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '4', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('2', '9', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('3', '14', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('4', '19', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('5', '24', 'Urgent', '4', '#FF0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('1', '5', 'Critical', '5', '#8B0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('2', '10', 'Critical', '5', '#8B0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('3', '15', 'Critical', '5', '#8B0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('4', '20', 'Critical', '5', '#8B0000', '1');
INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`) VALUES ('5', '25', 'Critical', '5', '#8B0000', '1');

-- Table structure for `ticket_statuses`
DROP TABLE IF EXISTS `ticket_statuses`;
CREATE TABLE `ticket_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_closed` tinyint DEFAULT '0',
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `ticket_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_statuses`
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('2', '5', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('3', '9', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('4', '13', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('5', '17', 'Open', '#FF0000', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '2', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('2', '6', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('3', '10', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('4', '14', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('5', '18', 'In Progress', '#FFA500', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '3', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('2', '7', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('3', '11', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('4', '15', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('5', '19', 'Resolved', '#00FF00', '0', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('1', '4', 'Closed', '#808080', '1', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('2', '8', 'Closed', '#808080', '1', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('3', '12', 'Closed', '#808080', '1', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('4', '16', 'Closed', '#808080', '1', '1');
INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`) VALUES ('5', '20', 'Closed', '#808080', '1', '1');

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  CONSTRAINT `tickets_ibfk_7` FOREIGN KEY (`asset_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `tickets`
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`) VALUES ('1', '1', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '4', '1', '2', '1', '1', '1', '#0969da', NULL, '2026-03-28 19:43:17');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`) VALUES ('2', '2', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '9', '5', '7', '1', '1', '2', '#0969da', NULL, '2026-03-28 19:43:17');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`) VALUES ('3', '3', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '14', '9', '12', '1', '1', '3', '#0969da', NULL, '2026-03-28 19:43:17');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`) VALUES ('4', '4', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '19', '13', '17', '1', '1', '4', '#0969da', NULL, '2026-03-28 19:43:17');
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`) VALUES ('5', '5', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '24', '17', '22', '1', '1', '5', '#0969da', NULL, '2026-03-28 19:43:17');

-- Table structure for `ui_configuration`
DROP TABLE IF EXISTS `ui_configuration`;
CREATE TABLE `ui_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `user_id` int NOT NULL,
  `table_actions_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `new_button_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `export_buttons_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `back_save_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left_right',
  `enable_all_error_reporting` tinyint(1) NOT NULL DEFAULT '1',
  `enable_audit_logs` tinyint(1) NOT NULL DEFAULT '1',
  `records_per_page` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '25',
  `app_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '⚙️ IT Controls',
  `favicon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `equipment_type_sidebar_visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ui_configuration_company_user` (`company_id`,`user_id`),
  CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ui_configuration`
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'left', 'left', 'left', 'left', 1, 1, '25', '⚙️ IT Controls', 'images/favicons/company_1.ico', '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_pos":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES
(2, 2, 1, 'left', 'left', 'left', 'left', 1, 1, '25', '⚙️ IT Controls', 'images/favicons/company_2.ico', '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_pos":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES
(3, 3, 1, 'left', 'left', 'left', 'left', 1, 1, '25', '⚙️ IT Controls', 'images/favicons/company_3.ico', '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_pos":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES
(4, 4, 1, 'left', 'left', 'left', 'left', 1, 1, '25', '⚙️ IT Controls', 'images/favicons/company_4.ico', '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_pos":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES
(5, 5, 1, 'left', 'left', 'left', 'left', 1, 1, '25', '⚙️ IT Controls', 'images/favicons/company_5.ico', '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_pos":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');



-- Table structure for `user_roles`
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `user_roles_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_roles`
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Admin');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Admin');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('3', '11', 'Admin');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('4', '16', 'Admin');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('5', '21', 'Admin');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Helpdesk');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('2', '7', 'Helpdesk');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('3', '12', 'Helpdesk');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('4', '17', 'Helpdesk');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('5', '22', 'Helpdesk');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '3', 'IT Assistant');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('2', '8', 'IT Assistant');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('3', '13', 'IT Assistant');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('4', '18', 'IT Assistant');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('5', '23', 'IT Assistant');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '2', 'IT Manager');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('2', '9', 'IT Manager');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('3', '14', 'IT Manager');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('4', '19', 'IT Manager');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('5', '24', 'IT Manager');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('1', '5', 'User');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('2', '10', 'User');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('3', '15', 'User');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('4', '20', 'User');
INSERT INTO `user_roles` (`company_id`, `id`, `name`) VALUES ('5', '25', 'User');

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `username` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `access_level_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username_per_company` (`company_id`,`username`),
  UNIQUE KEY `email` (`company_id`,`email`),
  UNIQUE KEY `reset_token` (`reset_token`),
  UNIQUE KEY `reset_token_hash` (`reset_token_hash`),
  KEY `idx_users_reset_token_expires_at` (`reset_token_expires_at`),
  KEY `company_id` (`company_id`),
  KEY `role_id` (`role_id`),
  KEY `access_level_id` (`access_level_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`),
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `company_id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `role_id`, `access_level_id`, `active`, `created_at`) VALUES ('1', '1', 'admin', 'admin@techcorp.example', '$2y$12$r6nU8WO3jAsWGvJYIFdIAOOAPDRmBQfEpltxD5UoIwTx3k.K2KPIO', 'System', 'Admin', NULL, '1', '1', '1', '2026-03-28 19:43:17');

-- Table structure for `registration_invitations`
DROP TABLE IF EXISTS `registration_invitations`;
CREATE TABLE `registration_invitations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_code` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invited_by_user_id` int DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `access_level_id` int DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registration_invitations_code` (`invitation_code`),
  KEY `idx_registration_invitations_company_email` (`company_id`,`email`),
  KEY `idx_registration_invitations_active_expires_at` (`active`,`expires_at`),
  KEY `idx_registration_invitations_invited_by` (`invited_by_user_id`),
  KEY `idx_registration_invitations_role` (`role_id`),
  KEY `idx_registration_invitations_access_level` (`access_level_id`),
  CONSTRAINT `fk_registration_invitations_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registration_invitations_invited_by` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_invitations_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_invitations_access_level` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `registration_invitations`
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`) VALUES ('1', '1', 'new.user@techcorp.example', 'INVITE-TECHCORP-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:00');
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`) VALUES ('2', '2', 'new.user@datacenterplus.example', 'INVITE-DATACENTERPLUS-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:10');
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`) VALUES ('3', '3', 'new.user@networksolutions.example', 'INVITE-NETWORKSOLUTIONS-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:20');
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`) VALUES ('4', '4', 'new.user@cloudtech.example', 'INVITE-CLOUDTECH-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:30');
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`) VALUES ('5', '5', 'new.user@enterpriseit.example', 'INVITE-ENTERPRISEIT-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:40');

-- Table structure for `attempts`
-- Why: Unified security telemetry table for login and password reset events (legacy module folders were merged into modules/attempts).
DROP TABLE IF EXISTS `attempts`;
CREATE TABLE `attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `attempt_source` enum('login','password_reset') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_type` enum('success','failure','request','reset') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_source_type_ip_time` (`attempt_source`,`attempt_type`,`ip_address`,`created_at`),
  KEY `idx_attempts_source_type_email_time` (`attempt_source`,`attempt_type`,`email`,`created_at`),
  KEY `idx_attempts_source_type_user_time` (`attempt_source`,`attempt_type`,`user_id`,`created_at`),
  CONSTRAINT `fk_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `user_companies`
DROP TABLE IF EXISTS `user_companies`;
CREATE TABLE `user_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `company_id` int NOT NULL,
  `granted_by_user_id` int DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_companies_user_company` (`user_id`,`company_id`),
  KEY `idx_user_companies_company` (`company_id`),
  KEY `idx_user_companies_granted_by` (`granted_by_user_id`),
  CONSTRAINT `fk_user_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_companies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_companies_granted_by` FOREIGN KEY (`granted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_companies`
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`, `active`) VALUES ('1', '1', NULL, '1');
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`, `active`) VALUES ('1', '2', NULL, '1');
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`, `active`) VALUES ('1', '3', NULL, '1');
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`, `active`) VALUES ('1', '4', NULL, '1');
INSERT INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`, `active`) VALUES ('1', '5', NULL, '1');

-- Table structure for `role_hierarchy`
DROP TABLE IF EXISTS `role_hierarchy`;
CREATE TABLE `role_hierarchy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `hierarchy_order` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_hierarchy_company_role` (`company_id`,`role_id`),
  UNIQUE KEY `uq_role_hierarchy_company_order` (`company_id`,`hierarchy_order`),
  CONSTRAINT `fk_role_hierarchy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_hierarchy_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_hierarchy`
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '1', '1');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('2', '6', '1');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('3', '11', '1');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('4', '16', '1');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('5', '21', '1');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '2', '2');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('2', '9', '2');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('3', '14', '2');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('4', '19', '2');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('5', '24', '2');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '3', '3');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('2', '8', '3');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('3', '13', '3');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('4', '18', '3');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('5', '23', '3');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '4', '4');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('2', '7', '4');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('3', '12', '4');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('4', '17', '4');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('5', '22', '4');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('1', '5', '5');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('2', '10', '5');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('3', '15', '5');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('4', '20', '5');
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) VALUES ('5', '25', '5');

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module_permissions` (`company_id`,`role_id`,`module_name`),
  CONSTRAINT `fk_role_module_permissions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_module_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_module_permissions`
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('1', '1', 'ALL', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('2', '6', 'ALL', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('3', '11', 'ALL', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('4', '16', 'ALL', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('5', '21', 'ALL', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('1', '4', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('2', '7', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('3', '12', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('4', '17', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('5', '22', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('1', '5', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('2', '10', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('3', '15', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('4', '20', 'Tickets', '1', '1', '1', '1', '1', '1');
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) VALUES ('5', '25', 'Tickets', '1', '1', '1', '1', '1', '1');

-- Table structure for `role_assignment_rights`
DROP TABLE IF EXISTS `role_assignment_rights`;
CREATE TABLE `role_assignment_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `role_id` int NOT NULL,
  `can_assign_role_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_assignment_rights` (`company_id`,`role_id`,`can_assign_role_id`),
  CONSTRAINT `fk_role_assignment_rights_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_target_role` FOREIGN KEY (`can_assign_role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_assignment_rights`
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '2');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '6', '9');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '11', '14');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '16', '19');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '21', '24');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '3');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '6', '8');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '11', '13');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '16', '18');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '21', '23');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '4');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '6', '7');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '11', '12');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '16', '17');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '21', '22');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '1', '5');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '6', '10');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '11', '15');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '16', '20');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '21', '25');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '2', '3');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '9', '8');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '14', '13');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '19', '18');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '24', '23');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '2', '4');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '9', '7');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '14', '12');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '19', '17');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '24', '22');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '2', '5');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '9', '10');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '14', '15');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '19', '20');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '24', '25');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '3', '4');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '8', '7');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '13', '12');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '18', '17');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '23', '22');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('1', '3', '5');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('2', '8', '10');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('3', '13', '15');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('4', '18', '20');
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) VALUES ('5', '23', '25');

-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
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
  `changed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_company` (`company_id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_actor_username` (`actor_username`),
  KEY `idx_audit_logs_actor_email` (`actor_email`),
  KEY `idx_audit_logs_module_name` (`module_name`),
  KEY `idx_audit_logs_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_changed_at` (`changed_at`),
  KEY `idx_audit_logs_company_changed` (`company_id`,`changed_at`),
  CONSTRAINT `audit_logs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_logs_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vlans_company_vlan_name` (`company_id`,`vlan_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `vlans_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `vlans`
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) VALUES ('1', '1', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) VALUES ('2', '2', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) VALUES ('3', '3', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) VALUES ('4', '4', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1');
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) VALUES ('5', '5', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1');
ALTER TABLE `idf_ports`
  ADD CONSTRAINT `idf_ports_ibfk_vlan` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL;

-- Table structure for `warranty_types`
DROP TABLE IF EXISTS `warranty_types`;
CREATE TABLE `warranty_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `warranty_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `warranty_types`
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Enterprise');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('2', '7', 'Enterprise');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('3', '13', 'Enterprise');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('4', '19', 'Enterprise');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('5', '25', 'Enterprise');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Extended');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('2', '8', 'Extended');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('3', '14', 'Extended');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('4', '20', 'Extended');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('5', '26', 'Extended');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'None');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('2', '9', 'None');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('3', '15', 'None');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('4', '21', 'None');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('5', '27', 'None');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Other');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('2', '10', 'Other');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('3', '16', 'Other');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('4', '22', 'Other');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('5', '28', 'Other');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Premium');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('2', '11', 'Premium');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('3', '17', 'Premium');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('4', '23', 'Premium');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('5', '29', 'Premium');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Standard');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('2', '12', 'Standard');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('3', '18', 'Standard');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('4', '24', 'Standard');
INSERT INTO `warranty_types` (`company_id`, `id`, `name`) VALUES ('5', '30', 'Standard');

-- Table structure for `workstation_device_types`
DROP TABLE IF EXISTS `workstation_device_types`;
CREATE TABLE `workstation_device_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_device_types`
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'All-in-One');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '9', 'All-in-One');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '17', 'All-in-One');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '25', 'All-in-One');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '33', 'All-in-One');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Desktop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '10', 'Desktop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '18', 'Desktop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '26', 'Desktop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '34', 'Desktop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Laptop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '11', 'Laptop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '19', 'Laptop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '27', 'Laptop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '35', 'Laptop');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Mobile');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '12', 'Mobile');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '20', 'Mobile');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '28', 'Mobile');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '36', 'Mobile');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Other');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '13', 'Other');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '21', 'Other');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '29', 'Other');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '37', 'Other');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'POS');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '14', 'POS');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '22', 'POS');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '30', 'POS');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '38', 'POS');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Tablet');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '15', 'Tablet');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '23', 'Tablet');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '31', 'Tablet');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '39', 'Tablet');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Thin-Client');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('2', '16', 'Thin-Client');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('3', '24', 'Thin-Client');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('4', '32', 'Thin-Client');
INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`) VALUES ('5', '40', 'Thin-Client');

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
  `active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mode_name` (`company_id`,`mode_name`),
  UNIQUE KEY `mode_code` (`company_id`,`mode_code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_modes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_modes`
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '1', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '12', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '23', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '34', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '45', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '2', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '13', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '24', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '35', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '46', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '3', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '14', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '25', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '36', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '47', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '4', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '15', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '26', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '37', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '48', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '5', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '16', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '27', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '38', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '49', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '6', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '17', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '28', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '39', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '50', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '7', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '18', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '29', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '40', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '51', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '8', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '19', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '30', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '41', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '52', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '9', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '20', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '31', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '42', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '53', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '10', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '21', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '32', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '43', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '54', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('1', '11', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('2', '22', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('3', '33', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('4', '44', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1');
INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) VALUES ('5', '55', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1');

ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_workstation_mode` FOREIGN KEY (`workstation_mode_id`) REFERENCES `workstation_modes` (`id`);

-- Table structure for `workstation_office`
DROP TABLE IF EXISTS `workstation_office`;
CREATE TABLE `workstation_office` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_office_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_office`
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '1', 'None');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('2', '5', 'None');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('3', '9', 'None');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('4', '13', 'None');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('5', '17', 'None');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Office 2024 Pro');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('2', '6', 'Office 2024 Pro');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('3', '10', 'Office 2024 Pro');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('4', '14', 'Office 2024 Pro');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('5', '18', 'Office 2024 Pro');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Office 2024 STD');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('2', '7', 'Office 2024 STD');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('3', '11', 'Office 2024 STD');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('4', '15', 'Office 2024 STD');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('5', '19', 'Office 2024 STD');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Office 365');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('2', '8', 'Office 365');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('3', '12', 'Office 365');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('4', '16', 'Office 365');
INSERT INTO `workstation_office` (`company_id`, `id`, `name`) VALUES ('5', '20', 'Office 365');

-- Table structure for `workstation_os_types`
DROP TABLE IF EXISTS `workstation_os_types`;
CREATE TABLE `workstation_os_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_os_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_types`
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '1', 'Windows');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '16', 'Windows');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '31', 'Windows');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '46', 'Windows');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '61', 'Windows');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '2', 'Windows 11');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '17', 'Windows 11');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '32', 'Windows 11');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '47', 'Windows 11');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '62', 'Windows 11');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '3', 'Windows 10');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '18', 'Windows 10');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '33', 'Windows 10');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '48', 'Windows 10');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '63', 'Windows 10');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '4', 'Windows Server');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '19', 'Windows Server');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '34', 'Windows Server');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '49', 'Windows Server');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '64', 'Windows Server');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '5', 'Windows Server 2012');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '20', 'Windows Server 2012');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '35', 'Windows Server 2012');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '50', 'Windows Server 2012');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '65', 'Windows Server 2012');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '6', 'Windows Server 2016');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '21', 'Windows Server 2016');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '36', 'Windows Server 2016');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '51', 'Windows Server 2016');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '66', 'Windows Server 2016');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '7', 'Windows Server 2019');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '22', 'Windows Server 2019');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '37', 'Windows Server 2019');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '52', 'Windows Server 2019');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '67', 'Windows Server 2019');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '8', 'Windows Server 2022');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '23', 'Windows Server 2022');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '38', 'Windows Server 2022');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '53', 'Windows Server 2022');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '68', 'Windows Server 2022');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '9', 'Windows Server 2025');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '24', 'Windows Server 2025');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '39', 'Windows Server 2025');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '54', 'Windows Server 2025');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '69', 'Windows Server 2025');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '10', 'Android');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '25', 'Android');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '40', 'Android');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '55', 'Android');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '70', 'Android');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '11', 'iOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '26', 'iOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '41', 'iOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '56', 'iOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '71', 'iOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '12', 'ChromeOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '27', 'ChromeOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '42', 'ChromeOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '57', 'ChromeOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '72', 'ChromeOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '13', 'Linux');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '28', 'Linux');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '43', 'Linux');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '58', 'Linux');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '73', 'Linux');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '14', 'macOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '29', 'macOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '44', 'macOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '59', 'macOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '74', 'macOS');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('1', '15', 'Other');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('2', '30', 'Other');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('3', '45', 'Other');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('4', '60', 'Other');
INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`) VALUES ('5', '75', 'Other');

-- Table structure for `workstation_os_versions`
DROP TABLE IF EXISTS `workstation_os_versions`;
CREATE TABLE `workstation_os_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_os_versions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_versions`
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '1', '24H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('2', '5', '24H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('3', '9', '24H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('4', '13', '24H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('5', '17', '24H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '2', '25H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('2', '6', '25H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('3', '10', '25H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('4', '14', '25H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('5', '18', '25H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '3', '26H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('2', '7', '26H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('3', '11', '26H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('4', '15', '26H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('5', '19', '26H2');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('1', '4', '10 LTSC');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('2', '8', '10 LTSC');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('3', '12', '10 LTSC');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('4', '16', '10 LTSC');
INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`) VALUES ('5', '20', '10 LTSC');

-- Table structure for `workstation_ram`
DROP TABLE IF EXISTS `workstation_ram`;
CREATE TABLE `workstation_ram` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `workstation_ram_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_ram`
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '1', '4 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('2', '7', '4 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('3', '13', '4 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('4', '19', '4 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('5', '25', '4 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '2', '8 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('2', '8', '8 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('3', '14', '8 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('4', '20', '8 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('5', '26', '8 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '3', '16 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('2', '9', '16 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('3', '15', '16 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('4', '21', '16 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('5', '27', '16 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '4', '32 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('2', '10', '32 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('3', '16', '32 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('4', '22', '32 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('5', '28', '32 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '5', '64 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('2', '11', '64 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('3', '17', '64 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('4', '23', '64 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('5', '29', '64 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('1', '6', '128 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('2', '12', '128 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('3', '18', '128 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('4', '24', '128 GB');
INSERT INTO `workstation_ram` (`company_id`, `id`, `name`) VALUES ('5', '30', '128 GB');

-- Replicate shared table data to all companies
SET @replicate_source_company_id := COALESCE(@replicate_source_company_id, 1);
INSERT IGNORE INTO `access_levels` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `access_levels` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `assignment_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `assignment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `budget_categories` (`company_id`, `name`, `description`, `active`) SELECT c.`id`, t.`name`, t.`description`, t.`active` FROM `budget_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `gl_accounts` (`company_id`, `account_code`, `account_name`, `category_id`, `active`)
SELECT
    c.`id`,
    ga.`account_code`,
    ga.`account_name`,
    target_bc.`id`,
    ga.`active`
FROM `gl_accounts` ga
JOIN `companies` c ON c.`id` <> ga.`company_id`
LEFT JOIN `budget_categories` source_bc ON source_bc.`id` = ga.`category_id`
LEFT JOIN `budget_categories` target_bc ON target_bc.`company_id` = c.`id` AND target_bc.`name` = source_bc.`name`
WHERE ga.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `employee_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `employee_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `employee_positions` (`company_id`, `department_id`, `name`, `description`, `active`)
SELECT
    c.`id`,
    d_target.`id`,
    t.`name`,
    t.`description`,
    t.`active`
FROM `employee_positions` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `departments` d_source ON d_source.`id` = t.`department_id`
LEFT JOIN `departments` d_target ON d_target.`company_id` = c.`id` AND d_target.`name` = d_source.`name`
WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_environment` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_environment` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber_patch` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber_patch` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber_rack` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber_rack` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_fiber_count` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_fiber_count` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_poe` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_poe` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_rj45` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_rj45` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `equipment_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `equipment_types` (`company_id`, `name`, `code`, `field_edit_emoji`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`field_edit_emoji`, t.`active` FROM `equipment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `inventory_categories` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `inventory_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `location_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `location_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `manufacturers` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `manufacturers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `forecast_revisions_status` (`company_id`, `status`, `active`) SELECT c.`id`, t.`status`, t.`active` FROM `forecast_revisions_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `approvals_stage` (`company_id`, `stage`, `active`) SELECT c.`id`, t.`stage`, t.`active` FROM `approvals_stage` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `catalogs` (`company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`) SELECT c.`id`, t.`model`, t.`equipment_type_id`, t.`image_url`, t.`price`, t.`supplier_id`, t.`manufacturer_id`, t.`product_url`, t.`active` FROM `catalogs` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `printer_device_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `printer_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `rack_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `rack_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `supplier_statuses` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `supplier_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `cable_colors` (`company_id`, `color_name`, `hex_color`, `comments`) SELECT c.`id`, t.`color_name`, t.`hex_color`, t.`comments` FROM `cable_colors` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_port_numbering_layout` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `switch_port_numbering_layout` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_port_types` (`company_id`, `type`) SELECT c.`id`, t.`type` FROM `switch_port_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_status` (`company_id`, `status`) SELECT c.`id`, t.`status` FROM `switch_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `ticket_categories` (`company_id`, `name`, `code`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`active` FROM `ticket_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `ticket_priorities` (`company_id`, `name`, `level`, `color`, `active`) SELECT c.`id`, t.`name`, t.`level`, t.`color`, t.`active` FROM `ticket_priorities` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `ticket_statuses` (`company_id`, `name`, `color`, `is_closed`, `active`) SELECT c.`id`, t.`name`, t.`color`, t.`is_closed`, t.`active` FROM `ticket_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `user_roles` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `user_roles` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `warranty_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `warranty_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `idf_device_type` (`company_id`, `idfdevicetype_name`) SELECT c.`id`, t.`idfdevicetype_name` FROM `idf_device_type` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `patches_updates_status` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `patches_updates_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `patches_updates_level` (`company_id`, `level`) SELECT c.`id`, t.`level` FROM `patches_updates_level` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_device_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_modes` (`company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`) SELECT c.`id`, t.`mode_name`, t.`mode_code`, t.`description`, t.`monitor_count`, t.`has_keyboard_mouse`, t.`pos`, t.`active` FROM `workstation_modes` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_office` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_office` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_os_types` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_os_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_os_versions` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_os_versions` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `workstation_ram` (`company_id`, `name`) SELECT c.`id`, t.`name` FROM `workstation_ram` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `departments` (`company_id`, `name`, `code`, `description`, `active`) SELECT c.`id`, t.`name`, t.`code`, t.`description`, t.`active` FROM `departments` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT INTO `employee_onboarding_requests` (`company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `created_at`)
SELECT c.`id`, t.`employee_id`, ep_target.`id`, t.`first_name`, t.`last_name`, t.`department_name`, t.`request_date`, t.`termination_date`, t.`network_access`, t.`micros_emc`, t.`opera`, t.`micros_card`, t.`pms_id`, t.`synergy_mms`, t.`email_account`, t.`landline_phone`, t.`hu_the_lobby`, t.`mobile_phone`, t.`navision`, t.`mobile_email`, t.`onq_ri`, t.`birchstreet`, t.`delphi`, t.`omina`, t.`vingcard_system`, t.`digital_rev`, t.`office_key_card`, t.`office_key_card_dep`, t.`comments`, t.`starting_date`, t.`requested_by`, t.`requested_by_date`, t.`requested_on`, t.`hod_approval`, t.`hod_approval_date`, t.`hrd_approval`, t.`hrd_approval_date`, t.`ism_approval`, t.`ism_approval_date`, t.`gm_approval`, t.`gm_approval_date`, t.`fin_approval`, t.`fin_approval_date`, COALESCE(NULLIF(t.`status_hod`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_hrd`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_ism`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_gm`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_fin`, ''), 'Waiting'), COALESCE(t.`email_sent_hod`, 0), t.`email_sent_hod_at`, COALESCE(t.`email_sent_hrd`, 0), t.`email_sent_hrd_at`, COALESCE(t.`email_sent_ism`, 0), t.`email_sent_ism_at`, COALESCE(t.`email_sent_gm`, 0), t.`email_sent_gm_at`, COALESCE(t.`email_sent_fin`, 0), t.`email_sent_fin_at`, t.`created_at`
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
INSERT IGNORE INTO `equipment` (`company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`)
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
    pdt_target.`id`,
    t.`printer_color_capable`,
    t.`printer_scan`,
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
LEFT JOIN `equipment_poe` poe_source ON poe_source.`id` = t.`switch_poe_id`
LEFT JOIN `equipment_poe` poe_target ON poe_target.`company_id` = c.`id` AND poe_target.`name` = poe_source.`name`
LEFT JOIN `equipment_environment` env_source ON env_source.`id` = t.`switch_environment_id`
LEFT JOIN `equipment_environment` env_target ON env_target.`company_id` = c.`id` AND env_target.`name` = env_source.`name`
WHERE t.`company_id` = @replicate_source_company_id
  AND COALESCE(et_target.`id`, et_fallback.`id`) IS NOT NULL
  AND COALESCE(es_target.`id`, es_fallback.`id`) IS NOT NULL;
INSERT IGNORE INTO `idf_ports` (`company_id`, `position_id`, `port_no`, `port_type`, `label`, `status_id`, `connected_to`, `vlan_id`, `speed_id`, `poe_id`, `cable_color`, `hex_color`, `notes`, `updated_at`) SELECT c.`id`, t.`position_id`, t.`port_no`, t.`port_type`, t.`label`, t.`status_id`, t.`connected_to`, t.`vlan_id`, t.`speed_id`, t.`poe_id`, t.`cable_color`, t.`hex_color`, t.`notes`, t.`updated_at` FROM `idf_ports` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT INTO `idf_device_type` (`company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, t.`idfdevicetype_name`, t.`field_edit_emoji`, t.`active`, t.`created_at`, t.`updated_at`
FROM `idf_device_type` t
JOIN `companies` c ON c.`id` <> t.`company_id`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
    SELECT 1
    FROM `idf_device_type` t_existing
    WHERE t_existing.`company_id` = c.`id`
      AND t_existing.`idfdevicetype_name` = t.`idfdevicetype_name`
  );
INSERT INTO `idf_positions` (`company_id`, `idf_id`, `position_no`, `device_type`, `device_name`, `equipment_id`, `port_count`, `notes`, `created_at`, `updated_at`)
SELECT c.`id`, t.`idf_id`, t.`position_no`, dt_target.`id`, t.`device_name`, t.`equipment_id`, t.`port_count`, t.`notes`, t.`created_at`, t.`updated_at`
FROM `idf_positions` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `idf_device_type` dt_source ON dt_source.`id` = t.`device_type`
LEFT JOIN `idf_device_type` dt_target ON dt_target.`company_id` = c.`id` AND dt_target.`idfdevicetype_name` = dt_source.`idfdevicetype_name`
WHERE t.`company_id` = @replicate_source_company_id
  AND dt_target.`id` IS NOT NULL;
INSERT IGNORE INTO `idfs` (`company_id`, `location_id`, `name`, `idf_code`, `notes`, `created_at`) SELECT c.`id`, t.`location_id`, t.`name`, t.`idf_code`, t.`notes`, t.`created_at` FROM `idfs` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `inventory_items` (`company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_user_id`, `last_user_manual`, `comments`, `location_id`, `supplier_id`, `active`)
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
    t.`last_user_manual`,
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
WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `it_locations` (`company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`) SELECT c.`id`, t.`name`, t.`location_code`, t.`address`, t.`city`, t.`state`, t.`country`, t.`postal_code`, t.`phone`, t.`type_id`, t.`active` FROM `it_locations` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `racks` (`company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`) SELECT c.`id`, t.`location_id`, t.`name`, t.`rack_code`, t.`status_id`, t.`active` FROM `racks` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `suppliers` (`company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`) SELECT c.`id`, t.`name`, t.`supplier_code`, t.`contact_person`, t.`email`, t.`phone`, t.`status_id`, t.`active` FROM `suppliers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `switch_ports` (`company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `updated_at`)
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
INSERT IGNORE INTO `system_access` (`company_id`, `code`, `name`, `active`) SELECT c.`id`, t.`code`, t.`name`, t.`active` FROM `system_access` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`) SELECT c.`id`, ur_target.`id`, rh.`hierarchy_order` FROM `role_hierarchy` rh JOIN `companies` c ON c.`id` <> rh.`company_id` JOIN `user_roles` ur_source ON ur_source.`id` = rh.`role_id` JOIN `user_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rh.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`) SELECT c.`id`, ur_target.`id`, rmp.`module_name`, rmp.`can_view`, rmp.`can_create`, rmp.`can_edit`, rmp.`can_delete`, rmp.`can_import`, rmp.`can_export` FROM `role_module_permissions` rmp JOIN `companies` c ON c.`id` <> rmp.`company_id` JOIN `user_roles` ur_source ON ur_source.`id` = rmp.`role_id` JOIN `user_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rmp.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`) SELECT c.`id`, ur_granter_target.`id`, ur_target_target.`id` FROM `role_assignment_rights` rar JOIN `companies` c ON c.`id` <> rar.`company_id` JOIN `user_roles` ur_granter_source ON ur_granter_source.`id` = rar.`role_id` JOIN `user_roles` ur_target_source ON ur_target_source.`id` = rar.`can_assign_role_id` JOIN `user_roles` ur_granter_target ON ur_granter_target.`company_id` = c.`id` AND ur_granter_target.`name` = ur_granter_source.`name` JOIN `user_roles` ur_target_target ON ur_target_target.`company_id` = c.`id` AND ur_target_target.`name` = ur_target_source.`name` WHERE rar.`company_id` = @replicate_source_company_id;
INSERT IGNORE INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`)
SELECT u.`id`, c.`id`, NULL
FROM `users` u
JOIN `companies` c ON c.`active` = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM `user_companies` uc
    WHERE uc.`user_id` = u.`id` AND uc.`company_id` = c.`id`
);
INSERT IGNORE INTO `tickets` (`company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `created_at`)
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
WHERE t.`company_id` = @replicate_source_company_id
  AND COALESCE(u_creator_target.`id`, u_fallback.`id`) IS NOT NULL;
INSERT IGNORE INTO `ui_configuration` (
    `company_id`,
    `user_id`,
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
    t.`user_id`,
    t.`table_actions_position`,
    t.`new_button_position`,
    t.`export_buttons_position`,
    t.`back_save_position`,
    t.`enable_all_error_reporting`,
    t.`enable_audit_logs`,
    t.`records_per_page`,
    t.`app_name`,
    t.`favicon_path`,
    t.`equipment_type_sidebar_visibility`,
    t.`created_at`,
    t.`updated_at`
FROM `ui_configuration` t
JOIN `companies` c
    ON c.`id` <> t.`company_id`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
      SELECT 1
      FROM `ui_configuration` u
      WHERE u.`company_id` = c.`id`
        AND u.`user_id` = t.`user_id`
  );
INSERT IGNORE INTO `vlans` (`company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`) SELECT c.`id`, t.`vlan_number`, t.`vlan_name`, t.`vlan_color`, t.`subnet`, t.`ip`, t.`comments`, t.`gateway_ip`, t.`active` FROM `vlans` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;
-- Workstations are tenant-specific and reference tenant-bound records.
-- Keep this table empty on bootstrap to avoid cross-company foreign key mismatches.

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
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'employee_position_id', NEW.`employee_position_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'department_name', NEW.`department_name`, 'request_date', NEW.`request_date`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera', NEW.`opera`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'email_account', NEW.`email_account`, 'landline_phone', NEW.`landline_phone`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'mobile_phone', NEW.`mobile_phone`, 'navision', NEW.`navision`, 'mobile_email', NEW.`mobile_email`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_dep', NEW.`office_key_card_dep`, 'comments', NEW.`comments`, 'starting_date', NEW.`starting_date`, 'requested_by', NEW.`requested_by`, 'requested_by_date', NEW.`requested_by_date`, 'requested_on', NEW.`requested_on`, 'hod_approval', NEW.`hod_approval`, 'hod_approval_date', NEW.`hod_approval_date`, 'hrd_approval', NEW.`hrd_approval`, 'hrd_approval_date', NEW.`hrd_approval_date`, 'ism_approval', NEW.`ism_approval`, 'ism_approval_date', NEW.`ism_approval_date`, 'gm_approval', NEW.`gm_approval`, 'gm_approval_date', NEW.`gm_approval_date`, 'fin_approval', NEW.`fin_approval`, 'fin_approval_date', NEW.`fin_approval_date`, 'status_hod', NEW.`status_hod`, 'status_hrd', NEW.`status_hrd`, 'status_ism', NEW.`status_ism`, 'status_gm', NEW.`status_gm`, 'status_fin', NEW.`status_fin`, 'email_sent_hod', NEW.`email_sent_hod`, 'email_sent_hod_at', NEW.`email_sent_hod_at`, 'email_sent_hrd', NEW.`email_sent_hrd`, 'email_sent_hrd_at', NEW.`email_sent_hrd_at`, 'email_sent_ism', NEW.`email_sent_ism`, 'email_sent_ism_at', NEW.`email_sent_ism_at`, 'email_sent_gm', NEW.`email_sent_gm`, 'email_sent_gm_at', NEW.`email_sent_gm_at`, 'email_sent_fin', NEW.`email_sent_fin`, 'email_sent_fin_at', NEW.`email_sent_fin_at`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_update` AFTER UPDATE ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'employee_position_id', OLD.`employee_position_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'department_name', OLD.`department_name`, 'request_date', OLD.`request_date`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera', OLD.`opera`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'email_account', OLD.`email_account`, 'landline_phone', OLD.`landline_phone`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'mobile_phone', OLD.`mobile_phone`, 'navision', OLD.`navision`, 'mobile_email', OLD.`mobile_email`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_dep', OLD.`office_key_card_dep`, 'comments', OLD.`comments`, 'starting_date', OLD.`starting_date`, 'requested_by', OLD.`requested_by`, 'requested_by_date', OLD.`requested_by_date`, 'requested_on', OLD.`requested_on`, 'hod_approval', OLD.`hod_approval`, 'hod_approval_date', OLD.`hod_approval_date`, 'hrd_approval', OLD.`hrd_approval`, 'hrd_approval_date', OLD.`hrd_approval_date`, 'ism_approval', OLD.`ism_approval`, 'ism_approval_date', OLD.`ism_approval_date`, 'gm_approval', OLD.`gm_approval`, 'gm_approval_date', OLD.`gm_approval_date`, 'fin_approval', OLD.`fin_approval`, 'fin_approval_date', OLD.`fin_approval_date`, 'status_hod', OLD.`status_hod`, 'status_hrd', OLD.`status_hrd`, 'status_ism', OLD.`status_ism`, 'status_gm', OLD.`status_gm`, 'status_fin', OLD.`status_fin`, 'email_sent_hod', OLD.`email_sent_hod`, 'email_sent_hod_at', OLD.`email_sent_hod_at`, 'email_sent_hrd', OLD.`email_sent_hrd`, 'email_sent_hrd_at', OLD.`email_sent_hrd_at`, 'email_sent_ism', OLD.`email_sent_ism`, 'email_sent_ism_at', OLD.`email_sent_ism_at`, 'email_sent_gm', OLD.`email_sent_gm`, 'email_sent_gm_at', OLD.`email_sent_gm_at`, 'email_sent_fin', OLD.`email_sent_fin`, 'email_sent_fin_at', OLD.`email_sent_fin_at`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'employee_position_id', NEW.`employee_position_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'department_name', NEW.`department_name`, 'request_date', NEW.`request_date`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera', NEW.`opera`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'email_account', NEW.`email_account`, 'landline_phone', NEW.`landline_phone`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'mobile_phone', NEW.`mobile_phone`, 'navision', NEW.`navision`, 'mobile_email', NEW.`mobile_email`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_dep', NEW.`office_key_card_dep`, 'comments', NEW.`comments`, 'starting_date', NEW.`starting_date`, 'requested_by', NEW.`requested_by`, 'requested_by_date', NEW.`requested_by_date`, 'requested_on', NEW.`requested_on`, 'hod_approval', NEW.`hod_approval`, 'hod_approval_date', NEW.`hod_approval_date`, 'hrd_approval', NEW.`hrd_approval`, 'hrd_approval_date', NEW.`hrd_approval_date`, 'ism_approval', NEW.`ism_approval`, 'ism_approval_date', NEW.`ism_approval_date`, 'gm_approval', NEW.`gm_approval`, 'gm_approval_date', NEW.`gm_approval_date`, 'fin_approval', NEW.`fin_approval`, 'fin_approval_date', NEW.`fin_approval_date`, 'status_hod', NEW.`status_hod`, 'status_hrd', NEW.`status_hrd`, 'status_ism', NEW.`status_ism`, 'status_gm', NEW.`status_gm`, 'status_fin', NEW.`status_fin`, 'email_sent_hod', NEW.`email_sent_hod`, 'email_sent_hod_at', NEW.`email_sent_hod_at`, 'email_sent_hrd', NEW.`email_sent_hrd`, 'email_sent_hrd_at', NEW.`email_sent_hrd_at`, 'email_sent_ism', NEW.`email_sent_ism`, 'email_sent_ism_at', NEW.`email_sent_ism_at`, 'email_sent_gm', NEW.`email_sent_gm`, 'email_sent_gm_at', NEW.`email_sent_gm_at`, 'email_sent_fin', NEW.`email_sent_fin`, 'email_sent_fin_at', NEW.`email_sent_fin_at`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_onboarding_requests_audit_delete` AFTER DELETE ON `employee_onboarding_requests` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_onboarding_requests', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'employee_position_id', OLD.`employee_position_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'department_name', OLD.`department_name`, 'request_date', OLD.`request_date`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera', OLD.`opera`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'email_account', OLD.`email_account`, 'landline_phone', OLD.`landline_phone`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'mobile_phone', OLD.`mobile_phone`, 'navision', OLD.`navision`, 'mobile_email', OLD.`mobile_email`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_dep', OLD.`office_key_card_dep`, 'comments', OLD.`comments`, 'starting_date', OLD.`starting_date`, 'requested_by', OLD.`requested_by`, 'requested_by_date', OLD.`requested_by_date`, 'requested_on', OLD.`requested_on`, 'hod_approval', OLD.`hod_approval`, 'hod_approval_date', OLD.`hod_approval_date`, 'hrd_approval', OLD.`hrd_approval`, 'hrd_approval_date', OLD.`hrd_approval_date`, 'ism_approval', OLD.`ism_approval`, 'ism_approval_date', OLD.`ism_approval_date`, 'gm_approval', OLD.`gm_approval`, 'gm_approval_date', OLD.`gm_approval_date`, 'fin_approval', OLD.`fin_approval`, 'fin_approval_date', OLD.`fin_approval_date`, 'status_hod', OLD.`status_hod`, 'status_hrd', OLD.`status_hrd`, 'status_ism', OLD.`status_ism`, 'status_gm', OLD.`status_gm`, 'status_fin', OLD.`status_fin`, 'email_sent_hod', OLD.`email_sent_hod`, 'email_sent_hod_at', OLD.`email_sent_hod_at`, 'email_sent_hrd', OLD.`email_sent_hrd`, 'email_sent_hrd_at', OLD.`email_sent_hrd_at`, 'email_sent_ism', OLD.`email_sent_ism`, 'email_sent_ism_at', OLD.`email_sent_ism_at`, 'email_sent_gm', OLD.`email_sent_gm`, 'email_sent_gm_at', OLD.`email_sent_gm_at`, 'email_sent_fin', OLD.`email_sent_fin`, 'email_sent_fin_at', OLD.`email_sent_fin_at`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
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

DROP TRIGGER IF EXISTS `trg_employee_positions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employee_positions_audit_update`;
DROP TRIGGER IF EXISTS `trg_employee_positions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employee_positions_audit_insert` AFTER INSERT ON `employee_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_positions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'department_id', NEW.`department_id`, 'name', NEW.`name`, 'description', NEW.`description`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_positions_audit_update` AFTER UPDATE ON `employee_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_positions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'department_id', OLD.`department_id`, 'name', OLD.`name`, 'description', OLD.`description`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'department_id', NEW.`department_id`, 'name', NEW.`name`, 'description', NEW.`description`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employee_positions_audit_delete` AFTER DELETE ON `employee_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employee_positions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'department_id', OLD.`department_id`, 'name', OLD.`name`, 'description', OLD.`description`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
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

DROP TRIGGER IF EXISTS `trg_employees_audit_insert`;
DROP TRIGGER IF EXISTS `trg_employees_audit_update`;
DROP TRIGGER IF EXISTS `trg_employees_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_employees_audit_insert` AFTER INSERT ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employees', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'duplicate', NEW.`duplicate`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'display_name', NEW.`display_name`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'mobile_phone', NEW.`mobile_phone`, 'work_phone', NEW.`work_phone`, 'deck', NEW.`deck`, 'extension', NEW.`extension`, 'employee_code', NEW.`employee_code`, 'external_id', NEW.`external_id`, 'username', NEW.`username`, 'department_id', NEW.`department_id`, 'job_code', NEW.`job_code`, 'job_title', NEW.`job_title`, 'comments', NEW.`comments`, 'request_date', NEW.`request_date`, 'requested_by', NEW.`requested_by`, 'termination_requested_by', NEW.`termination_requested_by`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_department_id', NEW.`office_key_card_department_id`, 'location_id', NEW.`location_id`, 'employment_status_id', NEW.`employment_status_id`, 'workstation_mode_id', NEW.`workstation_mode_id`, 'assignment_type_id', NEW.`assignment_type_id`, 'raw_status_code', NEW.`raw_status_code`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employees_audit_update` AFTER UPDATE ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employees', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'duplicate', OLD.`duplicate`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'display_name', OLD.`display_name`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'mobile_phone', OLD.`mobile_phone`, 'work_phone', OLD.`work_phone`, 'deck', OLD.`deck`, 'extension', OLD.`extension`, 'employee_code', OLD.`employee_code`, 'external_id', OLD.`external_id`, 'username', OLD.`username`, 'department_id', OLD.`department_id`, 'job_code', OLD.`job_code`, 'job_title', OLD.`job_title`, 'comments', OLD.`comments`, 'request_date', OLD.`request_date`, 'requested_by', OLD.`requested_by`, 'termination_requested_by', OLD.`termination_requested_by`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_department_id', OLD.`office_key_card_department_id`, 'location_id', OLD.`location_id`, 'employment_status_id', OLD.`employment_status_id`, 'workstation_mode_id', OLD.`workstation_mode_id`, 'assignment_type_id', OLD.`assignment_type_id`, 'raw_status_code', OLD.`raw_status_code`), JSON_OBJECT('id', NEW.`id`, 'duplicate', NEW.`duplicate`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'display_name', NEW.`display_name`, 'email', NEW.`email`, 'phone', NEW.`phone`, 'mobile_phone', NEW.`mobile_phone`, 'work_phone', NEW.`work_phone`, 'deck', NEW.`deck`, 'extension', NEW.`extension`, 'employee_code', NEW.`employee_code`, 'external_id', NEW.`external_id`, 'username', NEW.`username`, 'department_id', NEW.`department_id`, 'job_code', NEW.`job_code`, 'job_title', NEW.`job_title`, 'comments', NEW.`comments`, 'request_date', NEW.`request_date`, 'requested_by', NEW.`requested_by`, 'termination_requested_by', NEW.`termination_requested_by`, 'termination_date', NEW.`termination_date`, 'network_access', NEW.`network_access`, 'micros_emc', NEW.`micros_emc`, 'opera_username', NEW.`opera_username`, 'micros_card', NEW.`micros_card`, 'pms_id', NEW.`pms_id`, 'synergy_mms', NEW.`synergy_mms`, 'hu_the_lobby', NEW.`hu_the_lobby`, 'navision', NEW.`navision`, 'onq_ri', NEW.`onq_ri`, 'birchstreet', NEW.`birchstreet`, 'delphi', NEW.`delphi`, 'omina', NEW.`omina`, 'vingcard_system', NEW.`vingcard_system`, 'digital_rev', NEW.`digital_rev`, 'office_key_card', NEW.`office_key_card`, 'office_key_card_department_id', NEW.`office_key_card_department_id`, 'location_id', NEW.`location_id`, 'employment_status_id', NEW.`employment_status_id`, 'workstation_mode_id', NEW.`workstation_mode_id`, 'assignment_type_id', NEW.`assignment_type_id`, 'raw_status_code', NEW.`raw_status_code`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_employees_audit_delete` AFTER DELETE ON `employees` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'employees', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'duplicate', OLD.`duplicate`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'display_name', OLD.`display_name`, 'email', OLD.`email`, 'phone', OLD.`phone`, 'mobile_phone', OLD.`mobile_phone`, 'work_phone', OLD.`work_phone`, 'deck', OLD.`deck`, 'extension', OLD.`extension`, 'employee_code', OLD.`employee_code`, 'external_id', OLD.`external_id`, 'username', OLD.`username`, 'department_id', OLD.`department_id`, 'job_code', OLD.`job_code`, 'job_title', OLD.`job_title`, 'comments', OLD.`comments`, 'request_date', OLD.`request_date`, 'requested_by', OLD.`requested_by`, 'termination_requested_by', OLD.`termination_requested_by`, 'termination_date', OLD.`termination_date`, 'network_access', OLD.`network_access`, 'micros_emc', OLD.`micros_emc`, 'opera_username', OLD.`opera_username`, 'micros_card', OLD.`micros_card`, 'pms_id', OLD.`pms_id`, 'synergy_mms', OLD.`synergy_mms`, 'hu_the_lobby', OLD.`hu_the_lobby`, 'navision', OLD.`navision`, 'onq_ri', OLD.`onq_ri`, 'birchstreet', OLD.`birchstreet`, 'delphi', OLD.`delphi`, 'omina', OLD.`omina`, 'vingcard_system', OLD.`vingcard_system`, 'digital_rev', OLD.`digital_rev`, 'office_key_card', OLD.`office_key_card`, 'office_key_card_department_id', OLD.`office_key_card_department_id`, 'location_id', OLD.`location_id`, 'employment_status_id', OLD.`employment_status_id`, 'workstation_mode_id', OLD.`workstation_mode_id`, 'assignment_type_id', OLD.`assignment_type_id`, 'raw_status_code', OLD.`raw_status_code`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_equipment_audit_insert`;
DROP TRIGGER IF EXISTS `trg_equipment_audit_update`;
DROP TRIGGER IF EXISTS `trg_equipment_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_equipment_audit_insert` AFTER INSERT ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_type_id', NEW.`equipment_type_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'location_id', NEW.`location_id`, 'rack_id', NEW.`rack_id`, 'idf_id', NEW.`idf_id`, 'name', NEW.`name`, 'serial_number', NEW.`serial_number`, 'model', NEW.`model`, 'hostname', NEW.`hostname`, 'ip_address', NEW.`ip_address`, 'patch_port', NEW.`patch_port`, 'mac_address', NEW.`mac_address`, 'status_id', NEW.`status_id`, 'purchase_date', NEW.`purchase_date`, 'purchase_cost', NEW.`purchase_cost`, 'warranty_expiry', NEW.`warranty_expiry`, 'certificate_expiry', NEW.`certificate_expiry`, 'warranty_type_id', NEW.`warranty_type_id`, 'printer_device_type_id', NEW.`printer_device_type_id`, 'printer_color_capable', NEW.`printer_color_capable`, 'printer_scan', NEW.`printer_scan`, 'workstation_device_type_id', NEW.`workstation_device_type_id`, 'workstation_os_type_id', NEW.`workstation_os_type_id`, 'workstation_processor', NEW.`workstation_processor`, 'workstation_storage', NEW.`workstation_storage`, 'workstation_os_installed_on', NEW.`workstation_os_installed_on`, 'workstation_ram_id', NEW.`workstation_ram_id`, 'workstation_os_version_id', NEW.`workstation_os_version_id`, 'switch_rj45_id', NEW.`switch_rj45_id`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'switch_fiber_id', NEW.`switch_fiber_id`, 'switch_fiber_patch_id', NEW.`switch_fiber_patch_id`, 'switch_fiber_rack_id', NEW.`switch_fiber_rack_id`, 'switch_fiber_ports_number', NEW.`switch_fiber_ports_number`, 'switch_fiber_port_label', NEW.`switch_fiber_port_label`, 'switch_poe_id', NEW.`switch_poe_id`, 'switch_environment_id', NEW.`switch_environment_id`, 'notes', NEW.`notes`, 'photo_filename', NEW.`photo_filename`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_audit_update` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_type_id', OLD.`equipment_type_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'location_id', OLD.`location_id`, 'rack_id', OLD.`rack_id`, 'idf_id', OLD.`idf_id`, 'name', OLD.`name`, 'serial_number', OLD.`serial_number`, 'model', OLD.`model`, 'hostname', OLD.`hostname`, 'ip_address', OLD.`ip_address`, 'patch_port', OLD.`patch_port`, 'mac_address', OLD.`mac_address`, 'status_id', OLD.`status_id`, 'purchase_date', OLD.`purchase_date`, 'purchase_cost', OLD.`purchase_cost`, 'warranty_expiry', OLD.`warranty_expiry`, 'certificate_expiry', OLD.`certificate_expiry`, 'warranty_type_id', OLD.`warranty_type_id`, 'printer_device_type_id', OLD.`printer_device_type_id`, 'printer_color_capable', OLD.`printer_color_capable`, 'printer_scan', OLD.`printer_scan`, 'workstation_device_type_id', OLD.`workstation_device_type_id`, 'workstation_os_type_id', OLD.`workstation_os_type_id`, 'workstation_processor', OLD.`workstation_processor`, 'workstation_storage', OLD.`workstation_storage`, 'workstation_os_installed_on', OLD.`workstation_os_installed_on`, 'workstation_ram_id', OLD.`workstation_ram_id`, 'workstation_os_version_id', OLD.`workstation_os_version_id`, 'switch_rj45_id', OLD.`switch_rj45_id`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'switch_fiber_id', OLD.`switch_fiber_id`, 'switch_fiber_patch_id', OLD.`switch_fiber_patch_id`, 'switch_fiber_rack_id', OLD.`switch_fiber_rack_id`, 'switch_fiber_ports_number', OLD.`switch_fiber_ports_number`, 'switch_fiber_port_label', OLD.`switch_fiber_port_label`, 'switch_poe_id', OLD.`switch_poe_id`, 'switch_environment_id', OLD.`switch_environment_id`, 'notes', OLD.`notes`, 'photo_filename', OLD.`photo_filename`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_type_id', NEW.`equipment_type_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'location_id', NEW.`location_id`, 'rack_id', NEW.`rack_id`, 'idf_id', NEW.`idf_id`, 'name', NEW.`name`, 'serial_number', NEW.`serial_number`, 'model', NEW.`model`, 'hostname', NEW.`hostname`, 'ip_address', NEW.`ip_address`, 'patch_port', NEW.`patch_port`, 'mac_address', NEW.`mac_address`, 'status_id', NEW.`status_id`, 'purchase_date', NEW.`purchase_date`, 'purchase_cost', NEW.`purchase_cost`, 'warranty_expiry', NEW.`warranty_expiry`, 'certificate_expiry', NEW.`certificate_expiry`, 'warranty_type_id', NEW.`warranty_type_id`, 'printer_device_type_id', NEW.`printer_device_type_id`, 'printer_color_capable', NEW.`printer_color_capable`, 'printer_scan', NEW.`printer_scan`, 'workstation_device_type_id', NEW.`workstation_device_type_id`, 'workstation_os_type_id', NEW.`workstation_os_type_id`, 'workstation_processor', NEW.`workstation_processor`, 'workstation_storage', NEW.`workstation_storage`, 'workstation_os_installed_on', NEW.`workstation_os_installed_on`, 'workstation_ram_id', NEW.`workstation_ram_id`, 'workstation_os_version_id', NEW.`workstation_os_version_id`, 'switch_rj45_id', NEW.`switch_rj45_id`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'switch_fiber_id', NEW.`switch_fiber_id`, 'switch_fiber_patch_id', NEW.`switch_fiber_patch_id`, 'switch_fiber_rack_id', NEW.`switch_fiber_rack_id`, 'switch_fiber_ports_number', NEW.`switch_fiber_ports_number`, 'switch_fiber_port_label', NEW.`switch_fiber_port_label`, 'switch_poe_id', NEW.`switch_poe_id`, 'switch_environment_id', NEW.`switch_environment_id`, 'notes', NEW.`notes`, 'photo_filename', NEW.`photo_filename`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_equipment_audit_delete` AFTER DELETE ON `equipment` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'equipment', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_type_id', OLD.`equipment_type_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'location_id', OLD.`location_id`, 'rack_id', OLD.`rack_id`, 'idf_id', OLD.`idf_id`, 'name', OLD.`name`, 'serial_number', OLD.`serial_number`, 'model', OLD.`model`, 'hostname', OLD.`hostname`, 'ip_address', OLD.`ip_address`, 'patch_port', OLD.`patch_port`, 'mac_address', OLD.`mac_address`, 'status_id', OLD.`status_id`, 'purchase_date', OLD.`purchase_date`, 'purchase_cost', OLD.`purchase_cost`, 'warranty_expiry', OLD.`warranty_expiry`, 'certificate_expiry', OLD.`certificate_expiry`, 'warranty_type_id', OLD.`warranty_type_id`, 'printer_device_type_id', OLD.`printer_device_type_id`, 'printer_color_capable', OLD.`printer_color_capable`, 'printer_scan', OLD.`printer_scan`, 'workstation_device_type_id', OLD.`workstation_device_type_id`, 'workstation_os_type_id', OLD.`workstation_os_type_id`, 'workstation_processor', OLD.`workstation_processor`, 'workstation_storage', OLD.`workstation_storage`, 'workstation_os_installed_on', OLD.`workstation_os_installed_on`, 'workstation_ram_id', OLD.`workstation_ram_id`, 'workstation_os_version_id', OLD.`workstation_os_version_id`, 'switch_rj45_id', OLD.`switch_rj45_id`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'switch_fiber_id', OLD.`switch_fiber_id`, 'switch_fiber_patch_id', OLD.`switch_fiber_patch_id`, 'switch_fiber_rack_id', OLD.`switch_fiber_rack_id`, 'switch_fiber_ports_number', OLD.`switch_fiber_ports_number`, 'switch_fiber_port_label', OLD.`switch_fiber_port_label`, 'switch_poe_id', OLD.`switch_poe_id`, 'switch_environment_id', OLD.`switch_environment_id`, 'notes', OLD.`notes`, 'photo_filename', OLD.`photo_filename`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
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
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_links', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'port_id_a', NEW.`port_id_a`, 'port_id_b', NEW.`port_id_b`, 'equipment_id', NEW.`equipment_id`, 'equipment_hostname', NEW.`equipment_hostname`, 'equipment_port_type', NEW.`equipment_port_type`, 'equipment_port', NEW.`equipment_port`, 'equipment_vlan_id', NEW.`equipment_vlan_id`, 'equipment_label', NEW.`equipment_label`, 'equipment_comments', NEW.`equipment_comments`, 'equipment_status_id', NEW.`equipment_status_id`, 'equipment_color_id', NEW.`equipment_color_id`, 'cable_color_id', NEW.`cable_color_id`, 'cable_color_hex', NEW.`cable_color_hex`, 'cable_label', NEW.`cable_label`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_links_audit_update` AFTER UPDATE ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_links', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'port_id_a', OLD.`port_id_a`, 'port_id_b', OLD.`port_id_b`, 'equipment_id', OLD.`equipment_id`, 'equipment_hostname', OLD.`equipment_hostname`, 'equipment_port_type', OLD.`equipment_port_type`, 'equipment_port', OLD.`equipment_port`, 'equipment_vlan_id', OLD.`equipment_vlan_id`, 'equipment_label', OLD.`equipment_label`, 'equipment_comments', OLD.`equipment_comments`, 'equipment_status_id', OLD.`equipment_status_id`, 'equipment_color_id', OLD.`equipment_color_id`, 'cable_color_id', OLD.`cable_color_id`, 'cable_color_hex', OLD.`cable_color_hex`, 'cable_label', OLD.`cable_label`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'port_id_a', NEW.`port_id_a`, 'port_id_b', NEW.`port_id_b`, 'equipment_id', NEW.`equipment_id`, 'equipment_hostname', NEW.`equipment_hostname`, 'equipment_port_type', NEW.`equipment_port_type`, 'equipment_port', NEW.`equipment_port`, 'equipment_vlan_id', NEW.`equipment_vlan_id`, 'equipment_label', NEW.`equipment_label`, 'equipment_comments', NEW.`equipment_comments`, 'equipment_status_id', NEW.`equipment_status_id`, 'equipment_color_id', NEW.`equipment_color_id`, 'cable_color_id', NEW.`cable_color_id`, 'cable_color_hex', NEW.`cable_color_hex`, 'cable_label', NEW.`cable_label`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_links_audit_delete` AFTER DELETE ON `idf_links` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_links', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'port_id_a', OLD.`port_id_a`, 'port_id_b', OLD.`port_id_b`, 'equipment_id', OLD.`equipment_id`, 'equipment_hostname', OLD.`equipment_hostname`, 'equipment_port_type', OLD.`equipment_port_type`, 'equipment_port', OLD.`equipment_port`, 'equipment_vlan_id', OLD.`equipment_vlan_id`, 'equipment_label', OLD.`equipment_label`, 'equipment_comments', OLD.`equipment_comments`, 'equipment_status_id', OLD.`equipment_status_id`, 'equipment_color_id', OLD.`equipment_color_id`, 'cable_color_id', OLD.`cable_color_id`, 'cable_color_hex', OLD.`cable_color_hex`, 'cable_label', OLD.`cable_label`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idf_device_type_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_device_type_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_device_type_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_device_type_audit_insert` AFTER INSERT ON `idf_device_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_device_type', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idfdevicetype_name', NEW.`idfdevicetype_name`, 'field_edit_emoji', NEW.`field_edit_emoji`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_device_type_audit_update` AFTER UPDATE ON `idf_device_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_device_type', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idfdevicetype_name', OLD.`idfdevicetype_name`, 'field_edit_emoji', OLD.`field_edit_emoji`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idfdevicetype_name', NEW.`idfdevicetype_name`, 'field_edit_emoji', NEW.`field_edit_emoji`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_device_type_audit_delete` AFTER DELETE ON `idf_device_type` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_device_type', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idfdevicetype_name', OLD.`idfdevicetype_name`, 'field_edit_emoji', OLD.`field_edit_emoji`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idf_ports_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_ports_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_ports_audit_insert` AFTER INSERT ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_ports', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'position_id', NEW.`position_id`, 'port_no', NEW.`port_no`, 'port_type', NEW.`port_type`, 'label', NEW.`label`, 'status_id', NEW.`status_id`, 'connected_to', NEW.`connected_to`, 'vlan_id', NEW.`vlan_id`, 'speed_id', NEW.`speed_id`, 'poe_id', NEW.`poe_id`, 'cable_color', NEW.`cable_color`, 'hex_color', NEW.`hex_color`, 'notes', NEW.`notes`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_ports_audit_update` AFTER UPDATE ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_ports', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'position_id', OLD.`position_id`, 'port_no', OLD.`port_no`, 'port_type', OLD.`port_type`, 'label', OLD.`label`, 'status_id', OLD.`status_id`, 'connected_to', OLD.`connected_to`, 'vlan_id', OLD.`vlan_id`, 'speed_id', OLD.`speed_id`, 'poe_id', OLD.`poe_id`, 'cable_color', OLD.`cable_color`, 'hex_color', OLD.`hex_color`, 'notes', OLD.`notes`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'position_id', NEW.`position_id`, 'port_no', NEW.`port_no`, 'port_type', NEW.`port_type`, 'label', NEW.`label`, 'status_id', NEW.`status_id`, 'connected_to', NEW.`connected_to`, 'vlan_id', NEW.`vlan_id`, 'speed_id', NEW.`speed_id`, 'poe_id', NEW.`poe_id`, 'cable_color', NEW.`cable_color`, 'hex_color', NEW.`hex_color`, 'notes', NEW.`notes`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_ports_audit_delete` AFTER DELETE ON `idf_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_ports', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'position_id', OLD.`position_id`, 'port_no', OLD.`port_no`, 'port_type', OLD.`port_type`, 'label', OLD.`label`, 'status_id', OLD.`status_id`, 'connected_to', OLD.`connected_to`, 'vlan_id', OLD.`vlan_id`, 'speed_id', OLD.`speed_id`, 'poe_id', OLD.`poe_id`, 'cable_color', OLD.`cable_color`, 'hex_color', OLD.`hex_color`, 'notes', OLD.`notes`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_idf_positions_audit_insert`;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_update`;
DROP TRIGGER IF EXISTS `trg_idf_positions_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_idf_positions_audit_insert` AFTER INSERT ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_positions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idf_id', NEW.`idf_id`, 'position_no', NEW.`position_no`, 'device_type', NEW.`device_type`, 'device_name', NEW.`device_name`, 'equipment_id', NEW.`equipment_id`, 'port_count', NEW.`port_count`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_positions_audit_update` AFTER UPDATE ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_positions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idf_id', OLD.`idf_id`, 'position_no', OLD.`position_no`, 'device_type', OLD.`device_type`, 'device_name', OLD.`device_name`, 'equipment_id', OLD.`equipment_id`, 'port_count', OLD.`port_count`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'idf_id', NEW.`idf_id`, 'position_no', NEW.`position_no`, 'device_type', NEW.`device_type`, 'device_name', NEW.`device_name`, 'equipment_id', NEW.`equipment_id`, 'port_count', NEW.`port_count`, 'switch_port_numbering_layout_id', NEW.`switch_port_numbering_layout_id`, 'notes', NEW.`notes`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_idf_positions_audit_delete` AFTER DELETE ON `idf_positions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'idf_positions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'idf_id', OLD.`idf_id`, 'position_no', OLD.`position_no`, 'device_type', OLD.`device_type`, 'device_name', OLD.`device_name`, 'equipment_id', OLD.`equipment_id`, 'port_count', OLD.`port_count`, 'switch_port_numbering_layout_id', OLD.`switch_port_numbering_layout_id`, 'notes', OLD.`notes`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
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
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_items', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'item_code', NEW.`item_code`, 'serial', NEW.`serial`, 'category_id', NEW.`category_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'quantity_on_hand', NEW.`quantity_on_hand`, 'quantity_minimum', NEW.`quantity_minimum`, 'price_eur', NEW.`price_eur`, 'last_user_id', NEW.`last_user_id`, 'last_user_manual', NEW.`last_user_manual`, 'comments', NEW.`comments`, 'location_id', NEW.`location_id`, 'supplier_id', NEW.`supplier_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_items_audit_update` AFTER UPDATE ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_items', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'item_code', OLD.`item_code`, 'serial', OLD.`serial`, 'category_id', OLD.`category_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'quantity_on_hand', OLD.`quantity_on_hand`, 'quantity_minimum', OLD.`quantity_minimum`, 'price_eur', OLD.`price_eur`, 'last_user_id', OLD.`last_user_id`, 'last_user_manual', OLD.`last_user_manual`, 'comments', OLD.`comments`, 'location_id', OLD.`location_id`, 'supplier_id', OLD.`supplier_id`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'item_code', NEW.`item_code`, 'serial', NEW.`serial`, 'category_id', NEW.`category_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'quantity_on_hand', NEW.`quantity_on_hand`, 'quantity_minimum', NEW.`quantity_minimum`, 'price_eur', NEW.`price_eur`, 'last_user_id', NEW.`last_user_id`, 'last_user_manual', NEW.`last_user_manual`, 'comments', NEW.`comments`, 'location_id', NEW.`location_id`, 'supplier_id', NEW.`supplier_id`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_inventory_items_audit_delete` AFTER DELETE ON `inventory_items` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'inventory_items', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'item_code', OLD.`item_code`, 'serial', OLD.`serial`, 'category_id', OLD.`category_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'quantity_on_hand', OLD.`quantity_on_hand`, 'quantity_minimum', OLD.`quantity_minimum`, 'price_eur', OLD.`price_eur`, 'last_user_id', OLD.`last_user_id`, 'last_user_manual', OLD.`last_user_manual`, 'comments', OLD.`comments`, 'location_id', OLD.`location_id`, 'supplier_id', OLD.`supplier_id`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
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


DROP TRIGGER IF EXISTS `trg_catalogs_audit_insert`;
DROP TRIGGER IF EXISTS `trg_catalogs_audit_update`;
DROP TRIGGER IF EXISTS `trg_catalogs_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_catalogs_audit_insert` AFTER INSERT ON `catalogs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'catalogs', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'model', NEW.`model`, 'equipment_type_id', NEW.`equipment_type_id`, 'image_url', NEW.`image_url`, 'price', NEW.`price`, 'supplier_id', NEW.`supplier_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'product_url', NEW.`product_url`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_catalogs_audit_update` AFTER UPDATE ON `catalogs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'catalogs', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'model', OLD.`model`, 'equipment_type_id', OLD.`equipment_type_id`, 'image_url', OLD.`image_url`, 'price', OLD.`price`, 'supplier_id', OLD.`supplier_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'product_url', OLD.`product_url`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'model', NEW.`model`, 'equipment_type_id', NEW.`equipment_type_id`, 'image_url', NEW.`image_url`, 'price', NEW.`price`, 'supplier_id', NEW.`supplier_id`, 'manufacturer_id', NEW.`manufacturer_id`, 'product_url', NEW.`product_url`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_catalogs_audit_delete` AFTER DELETE ON `catalogs` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'catalogs', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'model', OLD.`model`, 'equipment_type_id', OLD.`equipment_type_id`, 'image_url', OLD.`image_url`, 'price', OLD.`price`, 'supplier_id', OLD.`supplier_id`, 'manufacturer_id', OLD.`manufacturer_id`, 'product_url', OLD.`product_url`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
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

DROP TRIGGER IF EXISTS `trg_patches_updates_status_audit_insert`;
DROP TRIGGER IF EXISTS `trg_patches_updates_status_audit_update`;
DROP TRIGGER IF EXISTS `trg_patches_updates_status_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_patches_updates_status_audit_insert` AFTER INSERT ON `patches_updates_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates_status', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_status_audit_update` AFTER UPDATE ON `patches_updates_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates_status', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'name', NEW.`name`, 'color', NEW.`color`, 'is_closed', NEW.`is_closed`, 'active', NEW.`active`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_status_audit_delete` AFTER DELETE ON `patches_updates_status` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates_status', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'name', OLD.`name`, 'color', OLD.`color`, 'is_closed', OLD.`is_closed`, 'active', OLD.`active`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_patches_updates_level_audit_insert`;
DROP TRIGGER IF EXISTS `trg_patches_updates_level_audit_update`;
DROP TRIGGER IF EXISTS `trg_patches_updates_level_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_patches_updates_level_audit_insert` AFTER INSERT ON `patches_updates_level` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates_level', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'level', NEW.`level`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_level_audit_update` AFTER UPDATE ON `patches_updates_level` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates_level', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'level', OLD.`level`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'level', NEW.`level`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_level_audit_delete` AFTER DELETE ON `patches_updates_level` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates_level', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'level', OLD.`level`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_patches_updates_audit_insert`;
DROP TRIGGER IF EXISTS `trg_patches_updates_audit_update`;
DROP TRIGGER IF EXISTS `trg_patches_updates_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_patches_updates_audit_insert` AFTER INSERT ON `patches_updates` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'ip', NEW.`ip`, 'id_external', NEW.`id_external`, 'inncode', NEW.`inncode`, 'dest', NEW.`dest`, 'dest_ip', NEW.`dest_ip`, 'severity', NEW.`severity`, 'vuln_description', NEW.`vuln_description`, 'base_score', NEW.`base_score`, 'remediation', NEW.`remediation`, 'cve', NEW.`cve`, 'host_ip', NEW.`host_ip`, 'host_mac_manufacturer', NEW.`host_mac_manufacturer`, 'days_since_last_seen', NEW.`days_since_last_seen`, 'host_health_score', NEW.`host_health_score`, 'host_health_reason', NEW.`host_health_reason`, 'host_resolution_priority', NEW.`host_resolution_priority`, 'host_workload_type', NEW.`host_workload_type`, 'operating_system', NEW.`operating_system`, 'business_function', NEW.`business_function`, 'data_source', NEW.`data_source`, 'date', NEW.`date`, 'last_user_department', NEW.`last_user_department`, 'problem', NEW.`problem`, 'troubleshooting', NEW.`troubleshooting`, 'patches_updates_photos', NEW.`patches_updates_photos`, 'status_id', NEW.`status_id`, 'level_id', NEW.`level_id`, 'created_by', NEW.`created_by`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_audit_update` AFTER UPDATE ON `patches_updates` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'ip', OLD.`ip`, 'id_external', OLD.`id_external`, 'inncode', OLD.`inncode`, 'dest', OLD.`dest`, 'dest_ip', OLD.`dest_ip`, 'severity', OLD.`severity`, 'vuln_description', OLD.`vuln_description`, 'base_score', OLD.`base_score`, 'remediation', OLD.`remediation`, 'cve', OLD.`cve`, 'host_ip', OLD.`host_ip`, 'host_mac_manufacturer', OLD.`host_mac_manufacturer`, 'days_since_last_seen', OLD.`days_since_last_seen`, 'host_health_score', OLD.`host_health_score`, 'host_health_reason', OLD.`host_health_reason`, 'host_resolution_priority', OLD.`host_resolution_priority`, 'host_workload_type', OLD.`host_workload_type`, 'operating_system', OLD.`operating_system`, 'business_function', OLD.`business_function`, 'data_source', OLD.`data_source`, 'date', OLD.`date`, 'last_user_department', OLD.`last_user_department`, 'problem', OLD.`problem`, 'troubleshooting', OLD.`troubleshooting`, 'patches_updates_photos', OLD.`patches_updates_photos`, 'status_id', OLD.`status_id`, 'level_id', OLD.`level_id`, 'created_by', OLD.`created_by`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'ip', NEW.`ip`, 'id_external', NEW.`id_external`, 'inncode', NEW.`inncode`, 'dest', NEW.`dest`, 'dest_ip', NEW.`dest_ip`, 'severity', NEW.`severity`, 'vuln_description', NEW.`vuln_description`, 'base_score', NEW.`base_score`, 'remediation', NEW.`remediation`, 'cve', NEW.`cve`, 'host_ip', NEW.`host_ip`, 'host_mac_manufacturer', NEW.`host_mac_manufacturer`, 'days_since_last_seen', NEW.`days_since_last_seen`, 'host_health_score', NEW.`host_health_score`, 'host_health_reason', NEW.`host_health_reason`, 'host_resolution_priority', NEW.`host_resolution_priority`, 'host_workload_type', NEW.`host_workload_type`, 'operating_system', NEW.`operating_system`, 'business_function', NEW.`business_function`, 'data_source', NEW.`data_source`, 'date', NEW.`date`, 'last_user_department', NEW.`last_user_department`, 'problem', NEW.`problem`, 'troubleshooting', NEW.`troubleshooting`, 'patches_updates_photos', NEW.`patches_updates_photos`, 'status_id', NEW.`status_id`, 'level_id', NEW.`level_id`, 'created_by', NEW.`created_by`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_patches_updates_audit_delete` AFTER DELETE ON `patches_updates` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'patches_updates', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'ip', OLD.`ip`, 'id_external', OLD.`id_external`, 'inncode', OLD.`inncode`, 'dest', OLD.`dest`, 'dest_ip', OLD.`dest_ip`, 'severity', OLD.`severity`, 'vuln_description', OLD.`vuln_description`, 'base_score', OLD.`base_score`, 'remediation', OLD.`remediation`, 'cve', OLD.`cve`, 'host_ip', OLD.`host_ip`, 'host_mac_manufacturer', OLD.`host_mac_manufacturer`, 'days_since_last_seen', OLD.`days_since_last_seen`, 'host_health_score', OLD.`host_health_score`, 'host_health_reason', OLD.`host_health_reason`, 'host_resolution_priority', OLD.`host_resolution_priority`, 'host_workload_type', OLD.`host_workload_type`, 'operating_system', OLD.`operating_system`, 'business_function', OLD.`business_function`, 'data_source', OLD.`data_source`, 'date', OLD.`date`, 'last_user_department', OLD.`last_user_department`, 'problem', OLD.`problem`, 'troubleshooting', OLD.`troubleshooting`, 'patches_updates_photos', OLD.`patches_updates_photos`, 'status_id', OLD.`status_id`, 'level_id', OLD.`level_id`, 'created_by', OLD.`created_by`), NULL, @app_ip_address, @app_user_agent);
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

DROP TRIGGER IF EXISTS `trg_cable_colors_audit_insert`;
DROP TRIGGER IF EXISTS `trg_cable_colors_audit_update`;
DROP TRIGGER IF EXISTS `trg_cable_colors_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_cable_colors_audit_insert` AFTER INSERT ON `cable_colors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'cable_colors', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'color_name', NEW.`color_name`, 'hex_color', NEW.`hex_color`, 'comments', NEW.`comments`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_cable_colors_audit_update` AFTER UPDATE ON `cable_colors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'cable_colors', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'color_name', OLD.`color_name`, 'hex_color', OLD.`hex_color`, 'comments', OLD.`comments`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'color_name', NEW.`color_name`, 'hex_color', NEW.`hex_color`, 'comments', NEW.`comments`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_cable_colors_audit_delete` AFTER DELETE ON `cable_colors` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'cable_colors', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'color_name', OLD.`color_name`, 'hex_color', OLD.`hex_color`, 'comments', OLD.`comments`), NULL, @app_ip_address, @app_user_agent);
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
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_ports', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'port_type', NEW.`port_type`, 'port_number', NEW.`port_number`, 'to_patch_port', NEW.`to_patch_port`, 'status_id', NEW.`status_id`, 'color_id', NEW.`color_id`, 'vlan_id', NEW.`vlan_id`, 'comments', NEW.`comments`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_ports_audit_update` AFTER UPDATE ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_ports', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'port_type', OLD.`port_type`, 'port_number', OLD.`port_number`, 'to_patch_port', OLD.`to_patch_port`, 'status_id', OLD.`status_id`, 'color_id', OLD.`color_id`, 'vlan_id', OLD.`vlan_id`, 'comments', OLD.`comments`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'equipment_id', NEW.`equipment_id`, 'hostname', NEW.`hostname`, 'port_type', NEW.`port_type`, 'port_number', NEW.`port_number`, 'to_patch_port', NEW.`to_patch_port`, 'status_id', NEW.`status_id`, 'color_id', NEW.`color_id`, 'vlan_id', NEW.`vlan_id`, 'comments', NEW.`comments`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_switch_ports_audit_delete` AFTER DELETE ON `switch_ports` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'switch_ports', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'equipment_id', OLD.`equipment_id`, 'hostname', OLD.`hostname`, 'port_type', OLD.`port_type`, 'port_number', OLD.`port_number`, 'to_patch_port', OLD.`to_patch_port`, 'status_id', OLD.`status_id`, 'color_id', OLD.`color_id`, 'vlan_id', OLD.`vlan_id`, 'comments', OLD.`comments`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
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

DELIMITER $$
DROP TRIGGER IF EXISTS `trg_user_sidebar_preferences_audit_insert`$$
DROP TRIGGER IF EXISTS `trg_user_sidebar_preferences_audit_update`$$
DROP TRIGGER IF EXISTS `trg_user_sidebar_preferences_audit_delete`$$

CREATE TRIGGER `trg_user_sidebar_preferences_audit_insert` AFTER INSERT ON `user_sidebar_preferences` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `username`, `user_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_sidebar_preferences', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_user_sidebar_preferences_audit_update` AFTER UPDATE ON `user_sidebar_preferences` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `username`, `user_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_sidebar_preferences', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_user_sidebar_preferences_audit_delete` AFTER DELETE ON `user_sidebar_preferences` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `username`, `user_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'user_sidebar_preferences', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_insert`;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_update`;
DROP TRIGGER IF EXISTS `trg_ui_configuration_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_ui_configuration_audit_insert` AFTER INSERT ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ui_configuration', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'table_actions_position', NEW.`table_actions_position`, 'new_button_position', NEW.`new_button_position`, 'export_buttons_position', NEW.`export_buttons_position`, 'back_save_position', NEW.`back_save_position`, 'enable_all_error_reporting', NEW.`enable_all_error_reporting`, 'enable_audit_logs', NEW.`enable_audit_logs`, 'records_per_page', NEW.`records_per_page`, 'app_name', NEW.`app_name`, 'favicon_path', NEW.`favicon_path`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ui_configuration_audit_update` AFTER UPDATE ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ui_configuration', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'table_actions_position', OLD.`table_actions_position`, 'new_button_position', OLD.`new_button_position`, 'export_buttons_position', OLD.`export_buttons_position`, 'back_save_position', OLD.`back_save_position`, 'enable_all_error_reporting', OLD.`enable_all_error_reporting`, 'enable_audit_logs', OLD.`enable_audit_logs`, 'records_per_page', OLD.`records_per_page`, 'app_name', OLD.`app_name`, 'favicon_path', OLD.`favicon_path`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'user_id', NEW.`user_id`, 'table_actions_position', NEW.`table_actions_position`, 'new_button_position', NEW.`new_button_position`, 'export_buttons_position', NEW.`export_buttons_position`, 'back_save_position', NEW.`back_save_position`, 'enable_all_error_reporting', NEW.`enable_all_error_reporting`, 'enable_audit_logs', NEW.`enable_audit_logs`, 'records_per_page', NEW.`records_per_page`, 'app_name', NEW.`app_name`, 'favicon_path', NEW.`favicon_path`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_ui_configuration_audit_delete` AFTER DELETE ON `ui_configuration` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'ui_configuration', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'user_id', OLD.`user_id`, 'table_actions_position', OLD.`table_actions_position`, 'new_button_position', OLD.`new_button_position`, 'export_buttons_position', OLD.`export_buttons_position`, 'back_save_position', OLD.`back_save_position`, 'enable_all_error_reporting', OLD.`enable_all_error_reporting`, 'enable_audit_logs', OLD.`enable_audit_logs`, 'records_per_page', OLD.`records_per_page`, 'app_name', OLD.`app_name`, 'favicon_path', OLD.`favicon_path`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
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
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'username', NEW.`username`, 'email', NEW.`email`, 'password', NEW.`password`, 'reset_token', NEW.`reset_token`, 'reset_token_hash', NEW.`reset_token_hash`, 'reset_token_expires_at', NEW.`reset_token_expires_at`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'phone', NEW.`phone`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_users_audit_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'username', OLD.`username`, 'email', OLD.`email`, 'password', OLD.`password`, 'reset_token', OLD.`reset_token`, 'reset_token_hash', OLD.`reset_token_hash`, 'reset_token_expires_at', OLD.`reset_token_expires_at`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'phone', OLD.`phone`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'username', NEW.`username`, 'email', NEW.`email`, 'password', NEW.`password`, 'reset_token', NEW.`reset_token`, 'reset_token_hash', NEW.`reset_token_hash`, 'reset_token_expires_at', NEW.`reset_token_expires_at`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'phone', NEW.`phone`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_users_audit_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'username', OLD.`username`, 'email', OLD.`email`, 'password', OLD.`password`, 'reset_token', OLD.`reset_token`, 'reset_token_hash', OLD.`reset_token_hash`, 'reset_token_expires_at', OLD.`reset_token_expires_at`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'phone', OLD.`phone`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_attempts_audit_insert`;
DROP TRIGGER IF EXISTS `trg_attempts_audit_update`;
DROP TRIGGER IF EXISTS `trg_attempts_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_attempts_audit_insert` AFTER INSERT ON `attempts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, (SELECT `company_id` FROM `users` WHERE `id` = NEW.`user_id` LIMIT 1), (SELECT `company_id` FROM `users` WHERE `email` = NEW.`email` LIMIT 1), (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)), @app_user_id, @app_username, @app_email, 'attempts', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'user_id', NEW.`user_id`, 'email', NEW.`email`, 'attempt_source', NEW.`attempt_source`, 'attempt_type', NEW.`attempt_type`, 'ip_address', NEW.`ip_address`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_attempts_audit_update` AFTER UPDATE ON `attempts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, (SELECT `company_id` FROM `users` WHERE `id` = NEW.`user_id` LIMIT 1), (SELECT `company_id` FROM `users` WHERE `id` = OLD.`user_id` LIMIT 1), (SELECT `company_id` FROM `users` WHERE `email` = NEW.`email` LIMIT 1), (SELECT `company_id` FROM `users` WHERE `email` = OLD.`email` LIMIT 1), (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)), @app_user_id, @app_username, @app_email, 'attempts', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'user_id', OLD.`user_id`, 'email', OLD.`email`, 'attempt_source', OLD.`attempt_source`, 'attempt_type', OLD.`attempt_type`, 'ip_address', OLD.`ip_address`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'user_id', NEW.`user_id`, 'email', NEW.`email`, 'attempt_source', NEW.`attempt_source`, 'attempt_type', NEW.`attempt_type`, 'ip_address', NEW.`ip_address`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_attempts_audit_delete` AFTER DELETE ON `attempts` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, (SELECT `company_id` FROM `users` WHERE `id` = OLD.`user_id` LIMIT 1), (SELECT `company_id` FROM `users` WHERE `email` = OLD.`email` LIMIT 1), (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)), @app_user_id, @app_username, @app_email, 'attempts', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'user_id', OLD.`user_id`, 'email', OLD.`email`, 'attempt_source', OLD.`attempt_source`, 'attempt_type', OLD.`attempt_type`, 'ip_address', OLD.`ip_address`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
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
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_module_permissions', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'module_name', NEW.`module_name`, 'can_view', NEW.`can_view`, 'can_create', NEW.`can_create`, 'can_edit', NEW.`can_edit`, 'can_delete', NEW.`can_delete`, 'can_import', NEW.`can_import`, 'can_export', NEW.`can_export`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_module_permissions_audit_update` AFTER UPDATE ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_module_permissions', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'module_name', OLD.`module_name`, 'can_view', OLD.`can_view`, 'can_create', OLD.`can_create`, 'can_edit', OLD.`can_edit`, 'can_delete', OLD.`can_delete`, 'can_import', OLD.`can_import`, 'can_export', OLD.`can_export`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'role_id', NEW.`role_id`, 'module_name', NEW.`module_name`, 'can_view', NEW.`can_view`, 'can_create', NEW.`can_create`, 'can_edit', NEW.`can_edit`, 'can_delete', NEW.`can_delete`, 'can_import', NEW.`can_import`, 'can_export', NEW.`can_export`), @app_ip_address, @app_user_agent);
END$$
CREATE TRIGGER `trg_role_module_permissions_audit_delete` AFTER DELETE ON `role_module_permissions` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'role_module_permissions', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'role_id', OLD.`role_id`, 'module_name', OLD.`module_name`, 'can_view', OLD.`can_view`, 'can_create', OLD.`can_create`, 'can_edit', OLD.`can_edit`, 'can_delete', OLD.`can_delete`, 'can_import', OLD.`can_import`, 'can_export', OLD.`can_export`), NULL, @app_ip_address, @app_user_agent);
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


SET FOREIGN_KEY_CHECKS=1;
