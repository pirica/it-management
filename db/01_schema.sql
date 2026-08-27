-- IT Management SQL Backup
-- Schema (DDL only). Import before 02_data.sql and 03_triggers.sql.

DROP DATABASE IF EXISTS `itmanagement`;

CREATE DATABASE `itmanagement` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `itmanagement`;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

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
  `sso_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sso_jit_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sso_provider` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ldap',
  `sso_config_json_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `asset_disposal_approval_required` tinyint(1) NOT NULL DEFAULT '0',
  `vault_org_recovery_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `vault_org_recovery_passphrase_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vault_org_recovery_escrow_key_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  KEY `active` (`active`),
  KEY `idx_companies_sso_enabled` (`sso_enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Table structure for `company_module_share`
DROP TABLE IF EXISTS `company_module_share`;

CREATE TABLE `company_module_share` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_id` int NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_module_share` (`company_id`,`module_id`),
  CONSTRAINT `fk_cms_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cms_module` FOREIGN KEY (`module_id`) REFERENCES `modules_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `configuration_item_types`
DROP TABLE IF EXISTS `configuration_item_types`;

CREATE TABLE `configuration_item_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuration_item_types_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `idx_configuration_item_types_source_slug` (`company_id`,`source_slug`),
  CONSTRAINT `configuration_item_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `configuration_items`
DROP TABLE IF EXISTS `configuration_items`;

CREATE TABLE `configuration_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ci_type_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_ref` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuration_items_external_ref` (`company_id`,`external_ref`),
  KEY `company_id` (`company_id`),
  KEY `ci_type_id` (`ci_type_id`),
  KEY `idx_configuration_items_record` (`company_id`,`record_module_slug`,`record_id`),
  CONSTRAINT `configuration_items_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `configuration_items_ibfk_ci_type` FOREIGN KEY (`ci_type_id`) REFERENCES `configuration_item_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `configuration_item_relationships`
DROP TABLE IF EXISTS `configuration_item_relationships`;

CREATE TABLE `configuration_item_relationships` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `parent_ci_id` int NOT NULL,
  `child_ci_id` int NOT NULL,
  `relationship_type` enum('depends_on','hosts','connects_to','runs_on') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'depends_on',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_configuration_item_relationships_edge` (`company_id`,`parent_ci_id`,`child_ci_id`,`relationship_type`),
  KEY `company_id` (`company_id`),
  KEY `parent_ci_id` (`parent_ci_id`),
  KEY `child_ci_id` (`child_ci_id`),
  CONSTRAINT `configuration_item_relationships_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `configuration_item_relationships_ibfk_parent` FOREIGN KEY (`parent_ci_id`) REFERENCES `configuration_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `configuration_item_relationships_ibfk_child` FOREIGN KEY (`child_ci_id`) REFERENCES `configuration_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `change_requests`
DROP TABLE IF EXISTS `change_request_approvals`;
DROP TABLE IF EXISTS `change_request_cab_members`;
DROP TABLE IF EXISTS `change_request_settings`;
DROP TABLE IF EXISTS `change_request_configuration_items`;

DROP TABLE IF EXISTS `change_requests`;

CREATE TABLE `change_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `source_configuration_item_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `change_type` enum('standard','normal','emergency') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `risk_level` enum('low','medium','high','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `rollback_plan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ticket_id` int DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected','implemented','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `scheduled_start` date DEFAULT NULL,
  `scheduled_end` date DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_requests_company_title` (`company_id`,`title`),
  KEY `company_id` (`company_id`),
  KEY `source_configuration_item_id` (`source_configuration_item_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `idx_change_requests_status` (`company_id`,`status`),
  KEY `idx_change_requests_scheduled` (`company_id`,`scheduled_start`),
  CONSTRAINT `change_requests_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_requests_ibfk_source_ci` FOREIGN KEY (`source_configuration_item_id`) REFERENCES `configuration_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `change_requests_ibfk_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `change_request_configuration_items`
CREATE TABLE `change_request_configuration_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `change_request_id` int NOT NULL,
  `configuration_item_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_configuration_items` (`company_id`,`change_request_id`,`configuration_item_id`),
  KEY `change_request_id` (`change_request_id`),
  KEY `configuration_item_id` (`configuration_item_id`),
  CONSTRAINT `change_request_configuration_items_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_configuration_items_ibfk_request` FOREIGN KEY (`change_request_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_configuration_items_ibfk_ci` FOREIGN KEY (`configuration_item_id`) REFERENCES `configuration_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `change_request_cab_members`
DROP TABLE IF EXISTS `change_request_cab_members`;

CREATE TABLE `change_request_cab_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_cab_members` (`company_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `change_request_cab_members_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_cab_members_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `change_request_approvals`
DROP TABLE IF EXISTS `change_request_approvals`;

CREATE TABLE `change_request_approvals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `change_request_id` int NOT NULL,
  `approver_employee_id` int NOT NULL,
  `decision` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `decided_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_approvals` (`company_id`,`change_request_id`,`approver_employee_id`),
  KEY `change_request_id` (`change_request_id`),
  KEY `approver_employee_id` (`approver_employee_id`),
  CONSTRAINT `change_request_approvals_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_approvals_ibfk_request` FOREIGN KEY (`change_request_id`) REFERENCES `change_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `change_request_approvals_ibfk_approver` FOREIGN KEY (`approver_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `change_request_settings`
DROP TABLE IF EXISTS `change_request_settings`;

CREATE TABLE `change_request_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `reminder_days_before` int NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_request_settings_company` (`company_id`),
  CONSTRAINT `change_request_settings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Table structure for `integration_accounts`
DROP TABLE IF EXISTS `integration_accounts`;

CREATE TABLE `integration_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `nominal_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `current_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gl_account_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_integration_accounts_company_nominal` (`company_id`,`nominal_code`),
  KEY `company_id` (`company_id`),
  KEY `parent_id` (`parent_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `integration_accounts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `integration_accounts_ibfk_parent` FOREIGN KEY (`parent_id`) REFERENCES `integration_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `integration_accounts_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `bank_accounts`
DROP TABLE IF EXISTS `bank_accounts`;

CREATE TABLE `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `institution_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `account_number` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_accounts_company_institution_account` (`company_id`,`institution_name`,`account_name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `bank_accounts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Table structure for `tax_rates`
DROP TABLE IF EXISTS `tax_rates`;

CREATE TABLE `tax_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_percent` decimal(5,2) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_rates_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `tax_rates_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `paid_statuses`
DROP TABLE IF EXISTS `paid_statuses`;

CREATE TABLE `paid_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paid_statuses_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `paid_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `payment_modes`
DROP TABLE IF EXISTS `payment_modes`;

CREATE TABLE `payment_modes` (
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
  UNIQUE KEY `uq_payment_modes_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `payment_modes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `bills`
DROP TABLE IF EXISTS `bills`;

CREATE TABLE `bills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `document_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `posted_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `exchange_rate` decimal(12,6) NOT NULL DEFAULT '1.000000',
  `paid_status_id` int NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sub_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_due` decimal(12,2) NOT NULL DEFAULT '0.00',
  `supplier_id` int DEFAULT NULL,
  `purchase_order_ids_json` json DEFAULT NULL,
  `cost_center_id` int DEFAULT NULL,
  `gl_account_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bills_company_document` (`company_id`,`document_number`),
  KEY `company_id` (`company_id`),
  KEY `paid_status_id` (`paid_status_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `bills_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bills_ibfk_paid_status` FOREIGN KEY (`paid_status_id`) REFERENCES `paid_statuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bills_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bills_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bills_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `bill_line_items`
DROP TABLE IF EXISTS `bill_line_items`;

CREATE TABLE `bill_line_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `bill_id` int NOT NULL,
  `line_number` int NOT NULL DEFAULT '1',
  `tax_rate_id` int DEFAULT NULL,
  `tax_rate_snapshot` decimal(5,2) DEFAULT NULL,
  `integration_account_id` int DEFAULT NULL,
  `gl_account_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `unit_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sub_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tracking_category_ids_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bill_line_items_scope` (`company_id`,`bill_id`,`line_number`),
  KEY `company_id` (`company_id`),
  KEY `bill_id` (`bill_id`),
  KEY `tax_rate_id` (`tax_rate_id`),
  KEY `integration_account_id` (`integration_account_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `bill_line_items_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_line_items_ibfk_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_line_items_ibfk_tax_rate` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bill_line_items_ibfk_integration_account` FOREIGN KEY (`integration_account_id`) REFERENCES `integration_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bill_line_items_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `finance_payment_allocations`
DROP TABLE IF EXISTS `finance_payment_allocations`;

CREATE TABLE `finance_payment_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `bank_account_id` int DEFAULT NULL,
  `bill_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `payment_mode_id` int DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `memo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `bill_id` (`bill_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `payment_mode_id` (`payment_mode_id`),
  CONSTRAINT `finance_payment_allocations_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_payment_allocations_ibfk_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_payment_allocations_ibfk_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_payment_allocations_ibfk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_payment_allocations_ibfk_payment_mode` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `finance_attachments`
DROP TABLE IF EXISTS `finance_attachments`;

CREATE TABLE `finance_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `parent_table` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int NOT NULL,
  `storage_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `mime_type` varchar(127) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `parent_scope` (`company_id`,`parent_table`,`parent_id`),
  CONSTRAINT `finance_attachments_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `customer_statuses`
DROP TABLE IF EXISTS `customers`;

DROP TABLE IF EXISTS `customer_statuses`;

CREATE TABLE `customer_statuses` (
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
  UNIQUE KEY `uq_customer_statuses_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `customer_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  UNIQUE KEY `uq_customers_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `customers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customers_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `customer_statuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `invoices`
DROP TABLE IF EXISTS `invoices`;

CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_order_ids_json` json DEFAULT NULL,
  `document_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `posted_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `exchange_rate` decimal(12,6) NOT NULL DEFAULT '1.000000',
  `paid_status_id` int NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sub_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_due` decimal(12,2) NOT NULL DEFAULT '0.00',
  `memo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cost_center_id` int DEFAULT NULL,
  `gl_account_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_company_document` (`company_id`,`document_number`),
  KEY `company_id` (`company_id`),
  KEY `paid_status_id` (`paid_status_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `customer_id` (`customer_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `invoices_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_paid_status` FOREIGN KEY (`paid_status_id`) REFERENCES `paid_statuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoices_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `invoice_line_items`
DROP TABLE IF EXISTS `invoice_line_items`;

CREATE TABLE `invoice_line_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `line_number` int NOT NULL DEFAULT '1',
  `tax_rate_id` int DEFAULT NULL,
  `tax_rate_snapshot` decimal(5,2) DEFAULT NULL,
  `integration_account_id` int DEFAULT NULL,
  `gl_account_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `unit_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sub_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tracking_category_ids_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_line_items_scope` (`company_id`,`invoice_id`,`line_number`),
  KEY `company_id` (`company_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `tax_rate_id` (`tax_rate_id`),
  KEY `integration_account_id` (`integration_account_id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `invoice_line_items_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_line_items_ibfk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_line_items_ibfk_tax_rate` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_line_items_ibfk_integration_account` FOREIGN KEY (`integration_account_id`) REFERENCES `integration_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_line_items_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `expense_recurrence`
DROP TABLE IF EXISTS `expenses`;

DROP TABLE IF EXISTS `expense_recurrence`;

CREATE TABLE `expense_recurrence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_recurrence_company_code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `expense_recurrence_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `expenses`
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `gl_account_id` int NOT NULL,
  `cost_center_id` int NOT NULL,
  `date` date NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `posting_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `purchase_order` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_accepted` tinyint(1) NOT NULL DEFAULT '1',
  `quotation_order` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotation_order_accepted` tinyint(1) NOT NULL DEFAULT '1',
  `net_amount` decimal(12,2) DEFAULT NULL,
  `vat_amount` decimal(12,2) DEFAULT NULL,
  `total_discount` decimal(12,2) DEFAULT '0.00',
  `tax_rate_id` int DEFAULT NULL,
  `tax_rate_snapshot` decimal(5,2) DEFAULT NULL,
  `payment_mode_id` int DEFAULT NULL,
  `paid_status_id` int NOT NULL,
  `bill_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `supplier_id` int DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `exchange_rate` decimal(12,6) NOT NULL DEFAULT '1.000000',
  `amount` decimal(12,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `invoice_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expense_recurrence_id` int DEFAULT NULL,
  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
  `next_run_date` date DEFAULT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `recurrence_source_expense_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expenses_company_scope` (`company_id`,`gl_account_id`,`posting_date`,`invoice_number`),
  KEY `company_id` (`company_id`),
  KEY `cost_center_id` (`cost_center_id`),
  KEY `gl_account_id` (`gl_account_id`),
  KEY `tax_rate_id` (`tax_rate_id`),
  KEY `payment_mode_id` (`payment_mode_id`),
  KEY `paid_status_id` (`paid_status_id`),
  KEY `bill_id` (`bill_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `expense_recurrence_id` (`expense_recurrence_id`),
  KEY `recurrence_source_expense_id` (`recurrence_source_expense_id`),
  CONSTRAINT `expenses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_ibfk_tax_rate` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_payment_mode` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_paid_status` FOREIGN KEY (`paid_status_id`) REFERENCES `paid_statuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_ibfk_bill` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_expense_recurrence` FOREIGN KEY (`expense_recurrence_id`) REFERENCES `expense_recurrence` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_recurrence_source` FOREIGN KEY (`recurrence_source_expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Table structure for `email_smtp_configurations`
DROP TABLE IF EXISTS `webmail_email_reads`;

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
  `imap_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_port` int NOT NULL DEFAULT '143',
  `inbound_ticket_enabled` tinyint NOT NULL DEFAULT '0',
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

-- Table structure for `emails`
CREATE TABLE `emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `smtp_config_id` int DEFAULT NULL,
  `to_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cc_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sent','failed','received') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `is_star` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
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

-- Per-employee read state for Webmail (private; no audit triggers).
CREATE TABLE `webmail_email_reads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `email_id` int NOT NULL,
  `read_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webmail_email_reads_scope` (`company_id`,`employee_id`,`email_id`),
  KEY `idx_webmail_email_reads_email` (`email_id`),
  KEY `idx_webmail_email_reads_employee` (`company_id`,`employee_id`),
  CONSTRAINT `webmail_email_reads_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webmail_email_reads_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webmail_email_reads_ibfk_email` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-employee compose signatures for Webmail (private; no audit triggers).
CREATE TABLE `webmail_signatures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webmail_signatures_employee` (`company_id`,`employee_id`),
  CONSTRAINT `webmail_signatures_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webmail_signatures_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `sso_subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_n` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,

  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vault_key_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vault_org_recovery_consent_at` timestamp NULL DEFAULT NULL,
  `vault_org_recovery_consent_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vault_key_escrow_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `totp_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT '0',
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
  KEY `idx_employees_company_sso_subject` (`company_id`,`sso_subject`),
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

-- Table structure for `employee_notifications`
DROP TABLE IF EXISTS `employee_notifications`;

CREATE TABLE `employee_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `action_url` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_notifications_inbox` (`company_id`,`employee_id`,`is_read`,`created_at`),
  KEY `idx_employee_notifications_module` (`company_id`,`module_slug`,`record_id`),
  CONSTRAINT `fk_employee_notifications_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_employee_notifications_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `lifecycle_stage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'in_service',
  `depreciation_start_date` date DEFAULT NULL,
  `useful_life_months` int DEFAULT NULL,
  `salvage_value` decimal(15,2) DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `disposal_pending_at` timestamp NULL DEFAULT NULL,
  `disposal_pending_date` date DEFAULT NULL,
  `disposal_pending_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `disposal_pending_by` int DEFAULT NULL,
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
  `is_collapsed` tinyint(1) NOT NULL DEFAULT '0',
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
  `first_response_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `sla_response_due_at` timestamp NULL DEFAULT NULL,
  `sla_resolve_due_at` timestamp NULL DEFAULT NULL,
  `sla_response_breached_at` timestamp NULL DEFAULT NULL,
  `sla_resolve_breached_at` timestamp NULL DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `merged_into_ticket_id` int DEFAULT NULL,
  `csat_score` tinyint DEFAULT NULL,
  `csat_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `csat_submitted_at` timestamp NULL DEFAULT NULL,
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
  KEY `idx_tickets_merged_into` (`merged_into_ticket_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tickets_merged_into` FOREIGN KEY (`merged_into_ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `ticket_statuses` (`id`),
  CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`),
  CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`created_by_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `tickets_ibfk_6` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `tickets_ibfk_7` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_inbound_email_messages`
DROP TABLE IF EXISTS `ticket_inbound_email_messages`;

CREATE TABLE `ticket_inbound_email_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_id` int DEFAULT NULL,
  `email_log_id` int DEFAULT NULL,
  `from_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `processed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_inbound_email_messages_scope` (`company_id`,`message_id`),
  KEY `idx_ticket_inbound_email_messages_ticket` (`company_id`,`ticket_id`),
  KEY `email_log_id` (`email_log_id`),
  CONSTRAINT `ticket_inbound_email_messages_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_inbound_email_messages_ibfk_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_inbound_email_messages_ibfk_email_log` FOREIGN KEY (`email_log_id`) REFERENCES `emails` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_inbound_email_routing_rules`
DROP TABLE IF EXISTS `ticket_inbound_email_routing_rules`;

CREATE TABLE `ticket_inbound_email_routing_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `keyword` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `priority_id` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_inbound_email_routing_rules_scope` (`company_id`,`keyword`),
  KEY `idx_ticket_inbound_email_routing_rules_sort` (`company_id`,`sort_order`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `category_id` (`category_id`),
  KEY `priority_id` (`priority_id`),
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_assignee` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ticket_inbound_email_routing_rules_ibfk_priority` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_activity`
DROP TABLE IF EXISTS `ticket_activity`;

CREATE TABLE `ticket_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `actor_employee_id` int DEFAULT NULL,
  `event_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_activity_ticket` (`company_id`,`ticket_id`,`created_at`),
  KEY `idx_ticket_activity_type` (`company_id`,`event_type`),
  CONSTRAINT `fk_ticket_activity_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_activity_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_ticket_activity_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `master_tickets` (global rollup — no company_id; tenant via problems.master_ticket_id)
DROP TABLE IF EXISTS `master_ticket_updates`;
DROP TABLE IF EXISTS `master_tickets`;

CREATE TABLE `master_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `root_cause` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `summary_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `problems`
DROP TABLE IF EXISTS `problems`;

CREATE TABLE `problems` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `root_cause` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('investigating','known_error','resolved','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'investigating',
  `owner_employee_id` int DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `knowledge_base_id` int DEFAULT NULL,
  `master_ticket_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_problems_company_title` (`company_id`,`title`),
  KEY `idx_problems_company_status` (`company_id`,`status`,`deleted_at`),
  KEY `idx_problems_owner` (`company_id`,`owner_employee_id`),
  KEY `idx_problems_master_ticket` (`master_ticket_id`,`company_id`,`deleted_at`),
  KEY `knowledge_base_id` (`knowledge_base_id`),
  CONSTRAINT `fk_problems_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problems_owner` FOREIGN KEY (`owner_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_problems_knowledge_base` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_base` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_problems_master_ticket` FOREIGN KEY (`master_ticket_id`) REFERENCES `master_tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `problem_ticket_links`
DROP TABLE IF EXISTS `problem_ticket_links`;

CREATE TABLE `problem_ticket_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `problem_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `linked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `linked_by` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_problem_ticket_links_scope` (`company_id`,`problem_id`,`ticket_id`),
  KEY `idx_problem_ticket_links_ticket` (`company_id`,`ticket_id`,`deleted_at`),
  CONSTRAINT `fk_problem_ticket_links_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problem_ticket_links_problem` FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problem_ticket_links_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_problem_ticket_links_linked_by` FOREIGN KEY (`linked_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `known_errors`
DROP TABLE IF EXISTS `known_errors`;

CREATE TABLE `known_errors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `problem_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `workaround` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symptom_keywords` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `knowledge_base_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_known_errors_company_problem_title` (`company_id`,`problem_id`,`title`),
  KEY `idx_known_errors_problem` (`company_id`,`problem_id`,`deleted_at`),
  KEY `knowledge_base_id` (`knowledge_base_id`),
  CONSTRAINT `fk_known_errors_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_known_errors_problem` FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_known_errors_knowledge_base` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_base` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `master_ticket_updates` (append-only history — no company_id)
DROP TABLE IF EXISTS `master_ticket_updates`;

CREATE TABLE `master_ticket_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `master_ticket_id` int NOT NULL,
  `event_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes_json` json DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `actor_employee_id` int DEFAULT NULL,
  `actor_company_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_master_ticket_updates_master` (`master_ticket_id`,`created_at`),
  CONSTRAINT `fk_master_ticket_updates_master` FOREIGN KEY (`master_ticket_id`) REFERENCES `master_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_master_ticket_updates_actor` FOREIGN KEY (`actor_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_master_ticket_updates_actor_company` FOREIGN KEY (`actor_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_comments`
DROP TABLE IF EXISTS `ticket_comments`;

CREATE TABLE `ticket_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photos_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_comments_ticket` (`company_id`,`ticket_id`,`created_at`),
  CONSTRAINT `fk_ticket_comments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_comments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_ticket_comments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `ticket_questionnaires`
DROP TABLE IF EXISTS `ticket_survey_answers`;
DROP TABLE IF EXISTS `ticket_surveys`;
DROP TABLE IF EXISTS `ticket_questionnaire_questions`;
DROP TABLE IF EXISTS `ticket_questionnaires`;

CREATE TABLE `ticket_questionnaires` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` int DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_questionnaires_company_name` (`company_id`,`name`),
  KEY `idx_ticket_questionnaires_company_default` (`company_id`,`is_default`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_ticket_questionnaires_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_questionnaires_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_questionnaire_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `questionnaire_id` int NOT NULL,
  `sort_order` tinyint unsigned NOT NULL,
  `question_text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_type` enum('rating_1_5','text') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rating_1_5',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_questionnaire_questions_order` (`company_id`,`questionnaire_id`,`sort_order`),
  KEY `questionnaire_id` (`questionnaire_id`),
  CONSTRAINT `fk_ticket_questionnaire_questions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_questionnaire_questions_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `ticket_questionnaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_surveys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `questionnaire_id` int NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `respondent_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `average_score` decimal(4,1) DEFAULT NULL,
  `accept_feedback` tinyint(1) DEFAULT NULL,
  `issued_by_employee_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_surveys_token` (`token`),
  KEY `idx_ticket_surveys_company_ticket` (`company_id`,`ticket_id`),
  KEY `idx_ticket_surveys_company_completed` (`company_id`,`completed_at`),
  KEY `ticket_id` (`ticket_id`),
  KEY `questionnaire_id` (`questionnaire_id`),
  KEY `issued_by_employee_id` (`issued_by_employee_id`),
  CONSTRAINT `fk_ticket_surveys_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_surveys_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_surveys_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `ticket_questionnaires` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ticket_surveys_issued_by` FOREIGN KEY (`issued_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_survey_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `survey_id` int NOT NULL,
  `question_id` int NOT NULL,
  `question_text_snapshot` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint unsigned NOT NULL,
  `answer_rating` tinyint DEFAULT NULL,
  `answer_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_survey_answers_survey_question` (`survey_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `fk_ticket_survey_answers_survey` FOREIGN KEY (`survey_id`) REFERENCES `ticket_surveys` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_survey_answers_question` FOREIGN KEY (`question_id`) REFERENCES `ticket_questionnaire_questions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_settings`
DROP TABLE IF EXISTS `ticket_settings`;

CREATE TABLE `ticket_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `auto_issue_survey_on_close` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'When 1, issue post-ticket questionnaire when status moves to closed',
  `survey_send_email_on_issue` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'When 1, email requester when a survey invite is issued (auto or manual)',
  `sla_enabled_on_create` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'When 1, stamp SLA due dates on new tickets from ticket_sla_policies',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_settings_company` (`company_id`),
  CONSTRAINT `fk_ticket_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `ticket_canned_responses`
DROP TABLE IF EXISTS `ticket_canned_responses`;

-- Table structure for `automation_rules`
DROP TABLE IF EXISTS `automation_rules`;

CREATE TABLE `automation_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_slug` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conditions_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actions_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `enabled` tinyint(1) DEFAULT '1',
  `last_run_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_automation_rules_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `trigger_slug` (`trigger_slug`),
  CONSTRAINT `fk_automation_rules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `automation_rule_runs`
DROP TABLE IF EXISTS `automation_rule_runs`;

CREATE TABLE `automation_rule_runs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `rule_id` int NOT NULL,
  `status` enum('pending','success','failed','skipped') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `context_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ran_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `rule_id` (`rule_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_automation_rule_runs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_automation_rule_runs_rule` FOREIGN KEY (`rule_id`) REFERENCES `automation_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_canned_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_canned_responses_company_title` (`company_id`,`title`),
  KEY `category_id` (`category_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_ticket_canned_responses_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_canned_responses_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_sla_policies`
DROP TABLE IF EXISTS `ticket_sla_policies`;

CREATE TABLE `ticket_sla_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `priority_id` int NOT NULL,
  `response_minutes` int NOT NULL,
  `resolve_minutes` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_sla_policies_company_priority` (`company_id`,`priority_id`),
  CONSTRAINT `fk_ticket_sla_policies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_sla_policies_priority` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ticket_sla_escalation_rules`
DROP TABLE IF EXISTS `ticket_sla_escalation_rules`;

CREATE TABLE `ticket_sla_escalation_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `priority_id` int NOT NULL,
  `breach_type` enum('response','resolve','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `escalate_to_employee_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_sla_escalation_scope` (`company_id`,`priority_id`,`breach_type`),
  KEY `idx_ticket_sla_escalation_assignee` (`escalate_to_employee_id`),
  CONSTRAINT `fk_ticket_sla_escalation_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ticket_sla_escalation_priority` FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities` (`id`),
  CONSTRAINT `fk_ticket_sla_escalation_employee` FOREIGN KEY (`escalate_to_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `live_chat_conversations`
DROP TABLE IF EXISTS `live_chat_conversations`;

CREATE TABLE `live_chat_conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_type` enum('live_agent','chat_with') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_id` int DEFAULT NULL,
  `requester_employee_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `status` enum('waiting','active','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `rating` tinyint unsigned DEFAULT NULL,
  `storage_rel_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_chat_conv_company` (`company_id`,`status`,`created_at`),
  KEY `idx_live_chat_conv_ticket` (`company_id`,`ticket_id`),
  KEY `requester_employee_id` (`requester_employee_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  CONSTRAINT `fk_live_chat_conv_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_conv_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  CONSTRAINT `fk_live_chat_conv_requester` FOREIGN KEY (`requester_employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_live_chat_conv_assigned` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `live_chat_participants`
DROP TABLE IF EXISTS `live_chat_participants`;

CREATE TABLE `live_chat_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `role` enum('requester','agent','peer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'peer',
  `last_read_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_chat_participant` (`conversation_id`,`employee_id`),
  KEY `idx_live_chat_part_employee` (`company_id`,`employee_id`),
  CONSTRAINT `fk_live_chat_part_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_part_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_part_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `live_chat_messages`
DROP TABLE IF EXISTS `live_chat_messages`;

CREATE TABLE `live_chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `sender_employee_id` int NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachments_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_live_chat_msg_conv` (`conversation_id`,`created_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_live_chat_msg_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_msg_sender` FOREIGN KEY (`sender_employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `live_chat_typing`
DROP TABLE IF EXISTS `live_chat_typing`;

CREATE TABLE `live_chat_typing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `expires_at` timestamp NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_chat_typing` (`conversation_id`,`employee_id`),
  KEY `idx_live_chat_typing_expires` (`expires_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_live_chat_typing_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_live_chat_typing_conv` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_live_chat_typing_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `ui_configuration`
DROP TABLE IF EXISTS `ui_configuration`;

CREATE TABLE `ui_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `table_actions_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `new_button_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `export_buttons_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `back_save_position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `enable_all_error_reporting` tinyint(1) NOT NULL DEFAULT '1',
  `enable_audit_logs` tinyint(1) NOT NULL DEFAULT '1',
  `enable_chatbot` tinyint(1) NOT NULL DEFAULT '1',
  `enable_sidebar_section_collapse` tinyint(1) NOT NULL DEFAULT '1',
  `enable_auto_scaffolding` tinyint(1) NOT NULL DEFAULT '0',
  `records_per_page` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '25',
  `app_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '⚙️ IT Controls',
  `favicon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `equipment_type_sidebar_visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `module_icon_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dashboard_widget_prefs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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

-- Table structure for `api_key_scopes`
DROP TABLE IF EXISTS `api_key_scopes`;

CREATE TABLE `api_key_scopes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `ui_configuration_id` int NOT NULL,
  `scope_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_key_scopes_config_slug` (`company_id`,`ui_configuration_id`,`scope_slug`),
  KEY `idx_api_key_scopes_company` (`company_id`),
  KEY `ui_configuration_id` (`ui_configuration_id`),
  CONSTRAINT `fk_api_key_scopes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_api_key_scopes_ui_configuration` FOREIGN KEY (`ui_configuration_id`) REFERENCES `ui_configuration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `employee_roles`
DROP TABLE IF EXISTS `employee_roles`;

CREATE TABLE `employee_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `sidebar_show` tinyint(1) NOT NULL DEFAULT '1',
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

-- Table structure for `employee_departments`
DROP TABLE IF EXISTS `employee_departments`;

CREATE TABLE `employee_departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `department_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_departments_scope` (`company_id`,`employee_id`,`department_id`),
  KEY `idx_employee_departments_employee` (`employee_id`),
  KEY `idx_employee_departments_department` (`department_id`),
  CONSTRAINT `fk_employee_departments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_departments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;

CREATE TABLE `audit_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int DEFAULT NULL,
  `actor_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint NOT NULL,
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
  `employee_id` int NOT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `title_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `location` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` int DEFAULT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `shared_with_json` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_company_scope` (`company_id`, `employee_id`, `id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `category_id` (`category_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  CONSTRAINT `events_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_ibfk_assigned_to` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
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
-- Private folder names: CHANGE name to TEXT; ADD name_hash char(64); backfill name_hash from SHA2(name,256) for shared rows or re-encrypt private names via app.
-- Table structure for `bookmarks`
DROP TABLE IF EXISTS `bookmarks`;

CREATE TABLE `bookmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
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
  UNIQUE KEY `uq_bookmarks_employee_url` (`company_id`, `employee_id`, `url_hash`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  KEY `folder_id` (`folder_id`),
  CONSTRAINT `bookmarks_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmarks_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookmarks_ibfk_folder` FOREIGN KEY (`folder_id`) REFERENCES `bookmark_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for private_contacts
DROP TABLE IF EXISTS `private_contacts`;

CREATE TABLE `private_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `name_prefix` text DEFAULT NULL,
  `first_name` text DEFAULT NULL,
  `middle_name` text DEFAULT NULL,
  `last_name` text DEFAULT NULL,
  `name_suffix` text DEFAULT NULL,
  `phonetic_first_name` text DEFAULT NULL,
  `phonetic_middle_name` text DEFAULT NULL,
  `phonetic_last_name` text DEFAULT NULL,
  `nickname` text DEFAULT NULL,
  `file_as` text DEFAULT NULL,
  `email1_label` text DEFAULT NULL,
  `email1_value` text DEFAULT NULL,
  `phone1_label` text DEFAULT NULL,
  `phone1_value` text DEFAULT NULL,
  `address1_label` text DEFAULT NULL,
  `address1_country` text DEFAULT NULL,
  `address1_street` text DEFAULT NULL,
  `address1_extended` text DEFAULT NULL,
  `address1_city` text DEFAULT NULL,
  `address1_region` text DEFAULT NULL,
  `address1_postcode` text DEFAULT NULL,
  `address1_po_box` text DEFAULT NULL,
  `organization_name` text DEFAULT NULL,
  `organization_title` text DEFAULT NULL,
  `organization_department` text DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `event1_label` text DEFAULT NULL,
  `event1_value` date DEFAULT NULL,
  `relation1_label` text DEFAULT NULL,
  `relation1_value` text DEFAULT NULL,
  `website1_label` text DEFAULT NULL,
  `website1_value` text DEFAULT NULL,
  `custom_field1_label` text DEFAULT NULL,
  `custom_field1_value` text DEFAULT NULL,
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
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
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
  UNIQUE KEY `uq_todo_company_scope` (`company_id`, `created_by`, `id`),
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
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `title_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
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

-- Private notes (empty shared_with_json): title/content/checklist_json encrypted at rest; title_hash = SHA-256(plaintext title). Shared notes keep plaintext for recipients.
-- Table structure for `note_labels`
DROP TABLE IF EXISTS `note_labels`;

CREATE TABLE `note_labels` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `note_id` INT DEFAULT NULL,
  `label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
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

-- Table structure for `search_index` (phase 2 command-palette denormalized search)
DROP TABLE IF EXISTS `search_index`;

CREATE TABLE `search_index` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_search` (`company_id`,`module_slug`,`record_id`),
  FULLTEXT KEY `ft_search` (`title`,`subtitle`,`keywords`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `search_index_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `share_sessions`
DROP TABLE IF EXISTS `share_sessions`;

CREATE TABLE `share_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` INT DEFAULT NULL,
  `scope_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope_path_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `share_code` CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `access_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_share_sessions_access_token` (`access_token`),
  KEY `idx_share_sessions_code_active` (`share_code`, `expires_at`),
  KEY `idx_share_sessions_module_record` (`company_id`, `module_slug`, `employee_id`, `record_id`),
  KEY `idx_share_sessions_module_scope` (`company_id`, `module_slug`, `employee_id`, `scope_path_hash`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `share_sessions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `share_sessions_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unified temporary QR / 6-digit share snapshots (private-data exempt; payload_json is plaintext until expiry).

-- Table structure for `qr_code_scans` (scan analytics — no audit triggers)
DROP TABLE IF EXISTS `qr_code_scans`;

-- Table structure for `qr_codes` (employee-scoped QR generator library)
DROP TABLE IF EXISTS `qr_codes`;

CREATE TABLE `qr_codes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_slug` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `encoding_mode` ENUM('static', 'dynamic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dynamic',
  `payload_json` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `encoded_payload` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `design_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_count` INT NOT NULL DEFAULT 0,
  `short_url_id` INT DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_codes_company_employee_title` (`company_id`, `employee_id`, `title`),
  UNIQUE KEY `uq_qr_codes_access_token` (`access_token`),
  KEY `idx_qr_codes_employee` (`company_id`, `employee_id`, `deleted_at`),
  KEY `idx_qr_codes_short_url` (`short_url_id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `qr_codes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qr_codes_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `qr_code_scans` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `qr_code_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `scanned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `user_agent` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qr_code_scans_code` (`qr_code_id`, `scanned_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `qr_code_scans_ibfk_code` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qr_code_scans_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `qr_design_templates` (employee-scoped QR design presets)
DROP TABLE IF EXISTS `qr_design_templates`;

CREATE TABLE `qr_design_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `name` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `design_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_design_templates_company_employee_name` (`company_id`, `employee_id`, `name`),
  KEY `idx_qr_design_templates_employee` (`company_id`, `employee_id`, `deleted_at`),
  CONSTRAINT `qr_design_templates_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `qr_design_templates_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `short_url_clicks` (click analytics — no audit triggers)
DROP TABLE IF EXISTS `short_url_clicks`;

-- Table structure for `short_urls` (employee-scoped URL shortener library)
DROP TABLE IF EXISTS `short_urls`;

-- Table structure for `short_url_settings` (per-company defaults)
DROP TABLE IF EXISTS `short_url_settings`;

CREATE TABLE `short_url_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `default_expiry_days` INT DEFAULT NULL,
  `custom_code_min_length` TINYINT NOT NULL DEFAULT 4,
  `require_https_destination` TINYINT(1) NOT NULL DEFAULT 0,
  `analytics_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_password_protect` TINYINT(1) NOT NULL DEFAULT 1,
  `public_base_url` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_short_url_settings_company` (`company_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `short_url_settings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `short_urls` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_url` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `access_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `password_hash` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `click_count` INT NOT NULL DEFAULT 0,
  `qr_code_id` INT DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_short_urls_company_code` (`company_id`, `short_code`),
  UNIQUE KEY `uq_short_urls_access_token` (`access_token`),
  KEY `idx_short_urls_employee` (`company_id`, `employee_id`, `deleted_at`),
  KEY `qr_code_id` (`qr_code_id`),
  KEY `company_id` (`company_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `short_urls_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `short_urls_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `short_urls_ibfk_qr_code` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `short_url_clicks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `short_url_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `clicked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `user_agent` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_short_url_clicks_url` (`short_url_id`, `clicked_at`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `short_url_clicks_ibfk_url` FOREIGN KEY (`short_url_id`) REFERENCES `short_urls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `short_url_clicks_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
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
  `chat_same_tenant` tinyint(1) NOT NULL DEFAULT '1',
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

-- Table structure for `appointment_visit_reasons`
DROP TABLE IF EXISTS `appointment_visit_reasons`;

CREATE TABLE `appointment_visit_reasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment_visit_reasons_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_appointment_visit_reasons_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `appointment_settings`
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
  `booking_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'When 0, employees cannot book or reschedule appointments',
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

-- Table structure for `appointment_business_hours`
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
  CONSTRAINT `fk_appointment_business_hours_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `appointment_type`
DROP TABLE IF EXISTS `appointment_type`;

CREATE TABLE `appointment_type` (
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
  CONSTRAINT `fk_appointment_type_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `appointments`
DROP TABLE IF EXISTS `appointments`;

CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `visit_reason_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `appointment_type_id` int NOT NULL,
  `assigned_to_employee_id` int DEFAULT NULL,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('scheduled','cancelled','completed','no_show') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
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
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `idx_appointments_company_date` (`company_id`,`appointment_date`,`start_time`),
  UNIQUE KEY `uq_appointments_company_booking_lock` (`company_id`,`booking_lock`),
  CONSTRAINT `fk_appointments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_appointments_assigned_to` FOREIGN KEY (`assigned_to_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appointments_visit_reason` FOREIGN KEY (`visit_reason_id`) REFERENCES `appointment_visit_reasons` (`id`),
  CONSTRAINT `fk_appointments_appointment_type` FOREIGN KEY (`appointment_type_id`) REFERENCES `appointment_type` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `approval_inbox_items`
DROP TABLE IF EXISTS `approval_inbox_items`;
CREATE TABLE `approval_inbox_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `module_slug` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `approval_stage` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_employee_id` int DEFAULT NULL,
  `assignee_employee_id` int DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_at` datetime DEFAULT NULL,
  `action_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approval_inbox_items_stage` (`company_id`,`module_slug`,`record_id`,`approval_stage`),
  KEY `idx_approval_inbox_assignee_status` (`company_id`,`assignee_employee_id`,`status`),
  KEY `idx_approval_inbox_module_record` (`company_id`,`module_slug`,`record_id`),
  CONSTRAINT `fk_approval_inbox_items_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_approval_inbox_items_requester` FOREIGN KEY (`requester_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_approval_inbox_items_assignee` FOREIGN KEY (`assignee_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `vault_org_recovery_requests`
DROP TABLE IF EXISTS `vault_org_recovery_requests`;

CREATE TABLE `vault_org_recovery_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `status` enum('pending','completed','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `legal_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `consent_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consent_verified_at` datetime DEFAULT NULL,
  `request_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `completion_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requester_employee_id` int DEFAULT NULL,
  `completed_by_employee_id` int DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vault_org_recovery_company_status` (`company_id`,`status`),
  KEY `idx_vault_org_recovery_employee` (`company_id`,`employee_id`),
  CONSTRAINT `fk_vault_org_recovery_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vault_org_recovery_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vault_org_recovery_requester` FOREIGN KEY (`requester_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vault_org_recovery_completed_by` FOREIGN KEY (`completed_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Hotel booking module tables (append to 01_schema.sql)

DROP TABLE IF EXISTS `hotel_booking_distribution_webhook_queue`;
DROP TABLE IF EXISTS `hotel_booking_distribution_ari_restrictions`;
DROP TABLE IF EXISTS `hotel_booking_distribution_rate_plan_mappings`;
DROP TABLE IF EXISTS `hotel_booking_distribution_ari_events`;
DROP TABLE IF EXISTS `hotel_booking_distribution_reservations`;
DROP TABLE IF EXISTS `hotel_booking_distribution_mappings`;
DROP TABLE IF EXISTS `hotel_booking_distribution_channels`;
DROP TABLE IF EXISTS `hotel_booking_payment_events`;
DROP TABLE IF EXISTS `hotel_bookings`;
DROP TABLE IF EXISTS `hotel_booking_portal_users`;
DROP TABLE IF EXISTS `booking_rooms_type_photos`;
DROP TABLE IF EXISTS `hotel_booking_room_photos`;
DROP TABLE IF EXISTS `hotel_booking_housekeeping_maintenance`;
DROP TABLE IF EXISTS `hotel_booking_room_utilities`;
DROP TABLE IF EXISTS `hotel_booking_amenities`;
DROP TABLE IF EXISTS `hotel_booking_rooms`;
DROP TABLE IF EXISTS `hotel_booking_hotel_nearby`;
DROP TABLE IF EXISTS `hotel_booking_hotel_photos`;
DROP TABLE IF EXISTS `hotel_booking_portal_rate_plans`;
DROP TABLE IF EXISTS `hotel_booking_room_type_rate_overrides`;
DROP TABLE IF EXISTS `hotel_booking_room_type_blocks`;
DROP TABLE IF EXISTS `hotel_booking_special_rate_codes`;
DROP TABLE IF EXISTS `hotel_booking_special_rates`;
DROP TABLE IF EXISTS `hotel_booking_hotels`;
DROP TABLE IF EXISTS `booking_rooms_types`;
DROP TABLE IF EXISTS `hotel_booking_housekeeping_maintenance_status`;
DROP TABLE IF EXISTS `hotel_booking_housekeeping_statuses`;
DROP TABLE IF EXISTS `hotel_bookings_history`;
DROP TABLE IF EXISTS `hotel_bookings_present`;
DROP TABLE IF EXISTS `hotel_bookings_future`;
DROP TABLE IF EXISTS `hotel_booking_settings`;

CREATE TABLE `hotel_bookings_future` (
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
  UNIQUE KEY `uq_hotel_bookings_future_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_bookings_future_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_bookings_present` (
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
  UNIQUE KEY `uq_hotel_bookings_present_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_bookings_present_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_bookings_history` (
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
  UNIQUE KEY `uq_hotel_bookings_history_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_bookings_history_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_rooms_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bed_summary` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_size_sqm` decimal(8,2) DEFAULT NULL,
  `max_adults` int NOT NULL DEFAULT '2',
  `max_children` int NOT NULL DEFAULT '1',
  `max_babies` int NOT NULL DEFAULT '1',
  `max_total_guests` int NOT NULL DEFAULT '4',
  `included_adults_per_room` int NOT NULL DEFAULT '2',
  `child_max_age` int NOT NULL DEFAULT '12',
  `min_adults` int NOT NULL DEFAULT '1',
  `allow_mixed_types_in_group` tinyint(1) NOT NULL DEFAULT '1',
  `max_rooms_per_booking` int DEFAULT NULL,
  `portal_extra_adult_supplement_percent` decimal(5,2) DEFAULT NULL,
  `portal_child_nightly_supplement` decimal(10,2) DEFAULT NULL,
  `portal_baby_nightly_supplement` decimal(10,2) DEFAULT NULL,
  `portal_included_children_free` tinyint(1) NOT NULL DEFAULT '0',
  `portal_single_occupancy_discount_percent` decimal(5,2) DEFAULT NULL,
  `min_stay_nights` int NOT NULL DEFAULT '1',
  `max_stay_nights` int DEFAULT NULL,
  `min_advance_booking_days` int NOT NULL DEFAULT '0',
  `max_advance_booking_days` int DEFAULT NULL,
  `closed_to_arrival_days` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_to_departure_days` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_bookable` tinyint(1) NOT NULL DEFAULT '1',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `adults_only` tinyint(1) NOT NULL DEFAULT '0',
  `smoking_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `accessible_room` tinyint(1) NOT NULL DEFAULT '0',
  `extra_bed_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `max_extra_beds` int NOT NULL DEFAULT '0',
  `crib_included` tinyint(1) NOT NULL DEFAULT '0',
  `pets_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `pet_max_weight_kg` int DEFAULT NULL,
  `pet_non_refundable_fee` decimal(10,2) DEFAULT NULL,
  `portal_pet_daily_fee` decimal(10,2) DEFAULT NULL,
  `filter_tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_bullets` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `upgrade_to_room_type_id` int DEFAULT NULL,
  `upgrade_price_per_night` decimal(12,2) DEFAULT NULL,
  `upgrade_pitch` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_rooms_types_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  KEY `upgrade_to_room_type_id` (`upgrade_to_room_type_id`),
  CONSTRAINT `booking_rooms_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_rooms_types_ibfk_upgrade` FOREIGN KEY (`upgrade_to_room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_housekeeping_statuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_hex` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_hk_status_company_name` (`company_id`,`name`),
  UNIQUE KEY `uq_hotel_booking_hk_status_company_code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_booking_hk_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_housekeeping_maintenance_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_hk_maint_status_company_code` (`company_id`,`code`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hb_hk_maint_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_hotels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reservations_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviews_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `portal_breakfast_adult_price_per_night` decimal(10,2) NOT NULL DEFAULT '30.00',
  `portal_breakfast_child_price_per_night` decimal(10,2) NOT NULL DEFAULT '20.00',
  `portal_child_nightly_supplement` decimal(10,2) NOT NULL DEFAULT '22.00',
  `portal_extra_adult_supplement_percent` decimal(5,2) NOT NULL DEFAULT '35.00',
  `portal_pet_daily_fee` decimal(10,2) NOT NULL DEFAULT '50.00',
  `portal_breakfast_child_age_min` int NOT NULL DEFAULT '11',
  `portal_breakfast_child_age_max` int NOT NULL DEFAULT '17',
  `parking_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pets_policy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_hotels_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_booking_hotels_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_special_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `rate_slug` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_special_rates_slug` (`company_id`,`hotel_id`,`rate_slug`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `hotel_booking_special_rates_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_special_rates_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_special_rate_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `rate_slug` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_special_rate_codes` (`company_id`,`hotel_id`,`rate_slug`,`code`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `idx_hb_special_rate_codes_lookup` (`company_id`,`hotel_id`,`rate_slug`,`code`,`active`),
  CONSTRAINT `hotel_booking_special_rate_codes_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_special_rate_codes_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_room_type_rate_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `price_per_night` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  KEY `idx_hb_rt_rate_override_range` (`company_id`,`hotel_id`,`room_type_id`,`start_date`,`end_date`),
  CONSTRAINT `hb_rt_rate_override_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_rt_rate_override_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_rt_rate_override_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_room_type_blocks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  KEY `idx_hb_rt_block_range` (`company_id`,`hotel_id`,`room_type_id`,`start_date`,`end_date`),
  CONSTRAINT `hb_rt_block_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_rt_block_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_rt_block_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `hotel_booking_hotel_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_cover` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `hotel_booking_hotel_photos_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_hotel_photos_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_hotel_nearby` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `place_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `distance_km` decimal(8,2) NOT NULL DEFAULT '0.00',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `hotel_booking_hotel_nearby_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_hotel_nearby_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_room_type_base_prices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `price_per_night` decimal(12,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_room_type_base_prices` (`company_id`,`hotel_id`,`room_type_id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `hb_room_type_base_prices_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_room_type_base_prices_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_room_type_base_prices_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `housekeeping_status_id` int DEFAULT NULL,
  `room_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_persons` int NOT NULL DEFAULT '2',
  `num_beds` int NOT NULL DEFAULT '1',
  `size_sqm` decimal(8,2) DEFAULT NULL,
  `view_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connecting_room_id` int DEFAULT NULL,
  `connected_to` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smoking_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `accessible_room` tinyint(1) NOT NULL DEFAULT '0',
  `is_out_of_order` tinyint(1) NOT NULL DEFAULT '0',
  `is_out_of_service` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_rooms_company_hotel_number` (`company_id`,`hotel_id`,`room_number`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  KEY `housekeeping_status_id` (`housekeeping_status_id`),
  KEY `connecting_room_id` (`connecting_room_id`),
  CONSTRAINT `hotel_booking_rooms_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_rooms_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hotel_booking_rooms_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hotel_booking_rooms_ibfk_hk` FOREIGN KEY (`housekeeping_status_id`) REFERENCES `hotel_booking_housekeeping_statuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_booking_rooms_ibfk_connecting` FOREIGN KEY (`connecting_room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_housekeeping_maintenance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `room_id` int NOT NULL,
  `from_date` date NOT NULL,
  `through_date` date NOT NULL,
  `return_status_id` int DEFAULT NULL,
  `maintenance_status_id` int DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `room_id` (`room_id`),
  KEY `return_status_id` (`return_status_id`),
  KEY `maintenance_status_id` (`maintenance_status_id`),
  CONSTRAINT `hb_hk_maint_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_hk_maint_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hb_hk_maint_ibfk_return` FOREIGN KEY (`return_status_id`) REFERENCES `hotel_booking_housekeeping_statuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hb_hk_maint_ibfk_status` FOREIGN KEY (`maintenance_status_id`) REFERENCES `hotel_booking_housekeeping_maintenance_status` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_room_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `room_id` int NOT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_cover` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `hotel_booking_room_photos_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_room_photos_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_rooms_type_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_cover` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `booking_rooms_type_photos_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_rooms_type_photos_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_amenities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_amenities_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `hotel_booking_amenities_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_room_utilities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `room_id` int NOT NULL,
  `amenity_id` int DEFAULT NULL,
  `icon_class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `company_id` (`company_id`),
  KEY `room_id` (`room_id`),
  KEY `amenity_id` (`amenity_id`),
  CONSTRAINT `hotel_booking_room_utilities_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_room_utilities_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_room_utilities_ibfk_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `hotel_booking_amenities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `room_id` int NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('unpaid','pending','paid','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `stripe_checkout_session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `guest_confirmation_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth2` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `future_status_id` int DEFAULT NULL,
  `present_status_id` int DEFAULT NULL,
  `history_status_id` int DEFAULT NULL,
  `portal_rate_plan_id` int DEFAULT NULL,
  `internal_rate_code` enum('','use','comp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `booking_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `customer_id` (`customer_id`),
  KEY `room_id` (`room_id`),
  KEY `idx_hotel_bookings_dates` (`company_id`,`check_in`,`check_out`),
  KEY `future_status_id` (`future_status_id`),
  KEY `present_status_id` (`present_status_id`),
  KEY `history_status_id` (`history_status_id`),
  KEY `portal_rate_plan_id` (`portal_rate_plan_id`),
  UNIQUE KEY `uq_hotel_bookings_company_guest_confirmation` (`company_id`,`guest_confirmation_code`),
  CONSTRAINT `hotel_bookings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_bookings_ibfk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hotel_bookings_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `hotel_bookings_ibfk_future` FOREIGN KEY (`future_status_id`) REFERENCES `hotel_bookings_future` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_bookings_ibfk_present` FOREIGN KEY (`present_status_id`) REFERENCES `hotel_bookings_present` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_bookings_ibfk_history` FOREIGN KEY (`history_status_id`) REFERENCES `hotel_bookings_history` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_bookings_ibfk_portal_rate_plan` FOREIGN KEY (`portal_rate_plan_id`) REFERENCES `hotel_booking_portal_rate_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_last_rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `booking_id` int NOT NULL,
  `room_id` int DEFAULT NULL,
  `room_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hotel_id` int DEFAULT NULL,
  `hotel_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type_id` int DEFAULT NULL,
  `room_type_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_last_rooms_company_booking` (`company_id`,`booking_id`),
  KEY `company_id` (`company_id`),
  KEY `booking_id` (`booking_id`),
  KEY `room_id` (`room_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `hotel_booking_last_rooms_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_booking` FOREIGN KEY (`booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_booking_rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hotel_booking_last_rooms_ibfk_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_channels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `standard` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'itm_native',
  `api_key_prefix` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `webhook_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_api_username` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_api_password_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `partner_property_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_sandbox_mode` tinyint(1) NOT NULL DEFAULT '0',
  `webhook_signing_secret_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `outbound_webhook_api_key_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_ari_push_checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webhook_max_attempts` int NOT NULL DEFAULT '5',
  `hourly_rate_limit` int NOT NULL DEFAULT '1000',
  `api_requests_count` int NOT NULL DEFAULT '0',
  `api_window_started_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_channel_company_code` (`company_id`,`channel_code`),
  KEY `company_id` (`company_id`),
  KEY `idx_hb_dist_channel_api_prefix` (`api_key_prefix`),
  CONSTRAINT `hb_dist_channel_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_mappings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `entity_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_id` int NOT NULL,
  `external_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_map_company_channel_entity` (`company_id`,`channel_id`,`entity_type`,`internal_id`),
  UNIQUE KEY `uq_hb_dist_map_company_channel_external` (`company_id`,`channel_id`,`entity_type`,`external_code`),
  KEY `company_id` (`company_id`),
  KEY `channel_id` (`channel_id`),
  CONSTRAINT `hb_dist_map_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_map_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_booking_id` int NOT NULL,
  `external_reservation_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `ack_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ack_at` timestamp NULL DEFAULT NULL,
  `nack_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_message_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_res_company_channel_external` (`company_id`,`channel_id`,`external_reservation_id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_booking_id` (`hotel_booking_id`),
  KEY `channel_id` (`channel_id`),
  KEY `idx_hb_dist_res_ack` (`channel_id`,`ack_status`),
  CONSTRAINT `hb_dist_res_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_res_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_res_ibfk_booking` FOREIGN KEY (`hotel_booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_ari_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int DEFAULT NULL,
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inbound',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `request_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `response_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `channel_id` (`channel_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `idx_hb_dist_ari_created` (`company_id`,`channel_id`,`created_at`),
  CONSTRAINT `hb_dist_ari_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_rate_plan_mappings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `portal_rate_plan_id` int NOT NULL,
  `external_rate_plan_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_los` int NOT NULL DEFAULT '1',
  `max_los` int DEFAULT NULL,
  `price_multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `restrictions_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_rp_company_channel_plan` (`company_id`,`channel_id`,`portal_rate_plan_id`),
  UNIQUE KEY `uq_hb_dist_rp_company_channel_external` (`company_id`,`channel_id`,`external_rate_plan_code`),
  KEY `company_id` (`company_id`),
  KEY `hotel_id` (`hotel_id`),
  CONSTRAINT `hb_dist_rp_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_rp_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_rp_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_rp_ibfk_plan` FOREIGN KEY (`portal_rate_plan_id`) REFERENCES `hotel_booking_portal_rate_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_ari_restrictions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int NOT NULL,
  `rate_plan_mapping_id` int DEFAULT NULL,
  `stay_date` date NOT NULL,
  `min_los` int NOT NULL DEFAULT '1',
  `max_los` int DEFAULT NULL,
  `closed_to_arrival` tinyint(1) NOT NULL DEFAULT '0',
  `closed_to_departure` tinyint(1) NOT NULL DEFAULT '0',
  `stop_sell` tinyint(1) NOT NULL DEFAULT '0',
  `derived_price_multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `base_price_override` decimal(10,2) DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_ari_restr_day` (`company_id`,`channel_id`,`hotel_id`,`room_type_id`,`stay_date`,`rate_plan_mapping_id`),
  KEY `company_id` (`company_id`),
  KEY `channel_id` (`channel_id`),
  CONSTRAINT `hb_dist_ari_restr_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_ibfk_channel_restr` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_restr_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_restr_ibfk_room_type` FOREIGN KEY (`room_type_id`) REFERENCES `booking_rooms_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_restr_ibfk_rp` FOREIGN KEY (`rate_plan_mapping_id`) REFERENCES `hotel_booking_distribution_rate_plan_mappings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_webhook_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_id` int DEFAULT NULL,
  `direction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outbound',
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/json; charset=utf-8',
  `payload_body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempt_count` int NOT NULL DEFAULT '0',
  `max_attempts` int NOT NULL DEFAULT '5',
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `last_http_code` int DEFAULT NULL,
  `last_error` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `channel_id` (`channel_id`),
  KEY `idx_hb_dist_whq_retry` (`status`,`next_retry_at`),
  CONSTRAINT `hb_dist_whq_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_whq_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_portal_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_portal_users_company_email` (`company_id`,`email`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `hotel_booking_portal_users_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_booking_portal_users_ibfk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_payment_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `booking_id` int NOT NULL,
  `event_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `booking_id` (`booking_id`),
  KEY `idx_hb_payment_events_company_booking` (`company_id`,`booking_id`),
  CONSTRAINT `hb_payment_events_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_payment_events_ibfk_booking` FOREIGN KEY (`booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `public_portal_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `stripe_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `stripe_mode` enum('test','live') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'test',
  `stripe_publishable_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_secret_key_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `stripe_webhook_signing_secret_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deposit_percent` decimal(5,2) NOT NULL DEFAULT '100.00',
  `welcome_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `welcome_subtitle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accessible_features_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `airport_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price_footnote` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviews_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tourist_tax_per_person_per_night` decimal(10,2) NOT NULL DEFAULT '2.00',
  `portal_max_discount_percent` decimal(5,2) NOT NULL DEFAULT '50.00',
  `portal_tourist_tax_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Tourist tax',
  `portal_price_includes_tax_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incl. tax',
  `portal_price_includes_tax_long_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incl. taxes',
  `portal_default_rate_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Best available rate',
  `portal_breakfast_rate_label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Breakfast included',
  `portal_default_pet_max_weight_kg` int NOT NULL DEFAULT '30',
  `portal_maps_base_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://maps.google.com/?q=',
  `portal_calendar_month_horizon` int NOT NULL DEFAULT '14',
  `portal_phone_example` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '+351912345678',
  `portal_direct_book_banner_text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Book direct for the best available rate and flexible stay options.',
  `portal_rating_title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Guest rating',
  `portal_rating_subtitle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ' — based on recent stays',
  `portal_step_label_room` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `portal_step_label_rate` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Select a Rate',
  `portal_step_label_customize` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Customize Your Stay',
  `portal_step_label_payment` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Payment and Guest Details',
  `portal_default_room_image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/images/room-5.jpg',
  `portal_room_type_code_fallback_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_occupancy_max_rooms` int NOT NULL DEFAULT '4',
  `portal_occupancy_max_adults` int NOT NULL DEFAULT '12',
  `portal_occupancy_max_children` int NOT NULL DEFAULT '6',
  `portal_occupancy_max_babies` int NOT NULL DEFAULT '3',
  `portal_default_included_adults_per_room` int NOT NULL DEFAULT '2',
  `portal_cancellation_policy_not_found_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cancellation_policy/404.html',
  `portal_manage_booking_label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Manage my booking',
  `portal_accessible_room_banner_text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Accessible rooms are available at this property. Use Room Filters and select Accessible room to narrow results.',
  `portal_disabled_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Public booking portal is disabled for this hotel.',
  `portal_step_progress_template` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Step {step} of {total}',
  `free_cancellation_days_before_check_in` int NOT NULL DEFAULT '5',
  `calendar_month_advance_days_left` int NOT NULL DEFAULT '3',
  `show_discount_strikethrough` tinyint(1) NOT NULL DEFAULT '1',
  `portal_complimentary_min_rooms_paid` int NOT NULL DEFAULT '0',
  `portal_complimentary_rooms_free` int NOT NULL DEFAULT '1',
  `portal_confirmation_email_guest` tinyint(1) NOT NULL DEFAULT '1',
  `portal_confirmation_email_reservations` tinyint(1) NOT NULL DEFAULT '1',
  `portal_show_room_number_on_confirmation` tinyint(1) NOT NULL DEFAULT '0',
  `portal_hide_upgrade_upsell_when_multi_room` tinyint(1) NOT NULL DEFAULT '1',
  `portal_money_symbol` enum('EUR','GBP','USD') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `portal_money_symbol_suffix` tinyint(1) NOT NULL DEFAULT '1',
  `portal_money_symbol_prefix` tinyint(1) NOT NULL DEFAULT '0',
  `portal_show_internal_rates` tinyint(1) NOT NULL DEFAULT '0',
  `portal_date_format` enum('european_ddmmyyyy','us_mmddyyyy','iso_yyyymmdd') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'european_ddmmyyyy',
  `portal_time_format` enum('h24','h12') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'h24',
  `portal_datetime_european1_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `portal_datetime_european2_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `portal_datetime_iso_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `portal_datetime_readable_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `portal_datetime_format_default` enum('european1','european2','iso','readable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'european2',
  `portal_accessible_banner_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `portal_accessibility_options_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `urlaccessibilitypep` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://localhost/it-management/booking/accessibility/pep.html',
  `urlmybooking` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'https://localhost/it-management/booking/users/bookings.php',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_settings_company` (`company_id`),
  CONSTRAINT `hotel_booking_settings_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `scheduled_reports`;
DROP TABLE IF EXISTS `saved_report_views`;

DROP TABLE IF EXISTS `hotel_booking_portal_ui_copy_home`;
CREATE TABLE `hotel_booking_portal_ui_copy_home` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `portal_ui_home_welcome_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_from_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_details_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_view_details_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_loading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_amenity_wifi_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_amenity_pool_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_amenity_fitness_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_parking_self_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_parking_valet_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_nearby_empty` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_airport_empty` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_price_footnote_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_favorite_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_favorite_aria_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_directions_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_visit_website_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_info_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_email_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_description_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_read_more` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_read_less` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_overview_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_amenities_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_check_in_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_check_out_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_currency_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_parking_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_pets_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_currency_euro_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_location_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_nearby_tab` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_airport_tab` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_accessible_features_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_select_dates_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_select_dates_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_read_reviews_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_read_reviews_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_guest_rating_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_guest_rating_subtitle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_default_rate_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_price_includes_tax_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_select_copy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_price_copy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_brand_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_night_singular` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_nights_plural` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_room_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_logout_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_edit_stay_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_stay_bar_hotel_tooltip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_stay_bar_dates_tooltip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_stay_bar_occupancy_change_tooltip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_stay_bar_occupancy_readonly_tooltip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_manage_booking_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_step_progress_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_your_room_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_chrome_reservation_room_title_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_modal_close` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_opens_in_new_tab` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_gallery_prev` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_gallery_next` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_gallery_aria` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_prev_month` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_next_month` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_choose_room` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_cancel` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_loading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_load_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_hint_checkin` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_hint_checkout` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_hint_unavailable` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_explore_filters` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_summary_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_sun` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_mon` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_tue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_wed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_thu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_fri` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_dates_weekday_sat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_address_new_tab_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_general_info_email_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_reservations_email_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_home_call_hotel_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_portal_ui_copy_home_company` (`company_id`),
  CONSTRAINT `hotel_booking_portal_ui_copy_home_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hotel_booking_portal_ui_copy_step1`;
CREATE TABLE `hotel_booking_portal_ui_copy_step1` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `portal_ui_step1_room_not_available` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_king_bed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_twin_beds` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_queen_bed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_garden_view` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_city_view` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_balcony` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_accessible` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filter_smoking` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_multi_room_banner_lead` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_multi_room_banner_types_hint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_multi_room_slot_guests` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_multi_room_slot_room` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_filters_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_special_rates_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_types_found` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_types_sold_out` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_sold_out_badge` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_sold_out_capacity` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_sold_out_dates` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_sold_out_mixed_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_connecting_room_card_banner` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_connecting_room_detail_banner` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_per_night_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_select_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_not_available_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_hotel_details_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_occupancy_modal_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_occupancy_rooms_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_occupancy_adults_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_occupancy_children_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_occupancy_babies_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_occupancy_modal_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_apply_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_filters_modal_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_apply_filters_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_clear_filters_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_standard_rate_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_internal_rate_price_zero_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_rate_program_use_points` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_rate_program_travel_agents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_rate_program_aaa` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_rate_program_senior` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_rate_program_government` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_code_rate_promo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_code_rate_group` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_code_rate_corporate` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_code_rate_member` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_internal_rate_use_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_internal_rate_comp_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_view_room_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_direct_book_banner` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_accessible_room_banner` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_policy_adults_only` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_policy_smoking` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_policy_accessible` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_policy_crib` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_policy_extra_bed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_highlight_guests` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_highlight_room_layout` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_highlight_bathroom` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_highlight_kitchen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_hotel_amenities_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_highlights_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_more_room_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_for_your_comfort` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_book_from_prefix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_book_room_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_desc_lead_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_desc_extra_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_spec_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_types_shown` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_amenity_pool_fallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_rate_programs_legend` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_apply_special_rates_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_apply_special_rates_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_size_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_view_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_max_occupancy_prefix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_adult_singular` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_adult_plural` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_child_singular` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_child_plural` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_children_age_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_baby_singular` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_baby_plural` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_sleeps_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_children_welcome_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_room_babies_welcome` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step1_comfort_fallback_list` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_portal_ui_copy_step1_company` (`company_id`),
  CONSTRAINT `hotel_booking_portal_ui_copy_step1_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hotel_booking_portal_ui_copy_checkout`;
CREATE TABLE `hotel_booking_portal_ui_copy_checkout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `portal_ui_step2_select_rate_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_breakfast_disclaimer_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_tourist_tax_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_tourist_tax_note_multi_room` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_no_rate_plans` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_breakfast_not_included` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_breakfast_addon_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_per_night` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_stay_total_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_select_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_your_rooms_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_pay_badge_pay_at_hotel` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_pay_badge_non_refundable` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_back_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_pep_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_upgrade_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_upgrade_pitch_default` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_per_night_meta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_special_requests_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_no_special_requests` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_traveling_with_pet` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_pet_policy_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_service_animal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_need_prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_pep_intro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_pep_document_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_pep_ack_checkbox` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_comments_placeholder` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_shared_guarantee_disclaimer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_continue_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_continue_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_none` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_mobility` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_hearing` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_visual` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_cognitive` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_accessibility_other` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step3_additional_comments_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_session_expired` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_name_email_required` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_invalid_email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_invalid_phone` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_invalid_dates` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_pricing_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_save_guest_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_booking_failed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_total_due_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_full_name_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_email_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_phone_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_phone_hint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_payment_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_pay_stripe` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_pay_at_hotel` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_check_in_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_check_out_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_agreement_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_agree_checkbox` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_book_reservation_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_terms_alert` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step4_back_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_step2_multi_room_rated_intro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_portal_ui_copy_checkout_company` (`company_id`),
  CONSTRAINT `hotel_booking_portal_ui_copy_checkout_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hotel_booking_portal_ui_copy_confirm`;
CREATE TABLE `hotel_booking_portal_ui_copy_confirm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `portal_ui_confirm_reservation_summary_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_subject_to_approval` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_rate_prefix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_change_rate_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_complimentary_credit` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_total_room_charges` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_traveling_with_pet` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_total_taxes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_total_for_stay` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_tax_per_person_suffix` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_room_slot_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_your_rooms_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_reservation_confirmed_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_reservation_cancelled_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_lead_confirmed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_lead_cancelled` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_lead_cancelled_named` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_confirmation_number` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_auth_code` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_status_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_status_cancelled` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_guests_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_room_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_rate_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_number_of_nights` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_total_for_stay_detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_received` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_received_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_pending_lead` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_pending_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_options_lead` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_options_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_at_hotel_lead` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_at_hotel_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_manage_hint_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_save_pdf_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_return_home` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancelled_notice` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_your_reservation_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_reservation_notes_heading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_guest_comments_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_pet_hint_daily_fee` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancellation_policy_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancellation_loading` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancellation_load_fail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_change_booking_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_change_booking_modal_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancel_booking_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancel_confirm_prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancel_already_cancelled` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancel_not_available` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_change_room_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_stripe_success` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_stripe_cancel` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_stripe_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_cancel_success` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_lookup_intro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_last_name_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_confirmation_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_auth_code_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_find_reservation_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_otp_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_otp_intro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_otp_code_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_otp_placeholder` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_verify_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_back_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_lookup_failure` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_invalid_otp` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_verification_failure` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_rate_limit` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_otp_rate_limit` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_missing_fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_cancel_requires_otp` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_cancel_failed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_manage_reload_after_cancel_failed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_empty_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_empty_lead` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_payment_empty_cta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_pdf_not_found_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_pdf_not_found_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_confirm_pdf_page_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_sign_in_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_email_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_password_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_sign_in_button` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_register_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_home_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_sign_in_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_invalid_csrf` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_invalid_credentials` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_register_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_full_name_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_phone_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_all_fields_required` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_register_customer_failed` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `portal_ui_auth_email_registered` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_booking_portal_ui_copy_confirm_company` (`company_id`),
  CONSTRAINT `hotel_booking_portal_ui_copy_confirm_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE `saved_report_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `module_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shared_scope` enum('private','department','company') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'private',
  `share_department_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_report_views_company_employee_name` (`company_id`,`employee_id`,`name`),
  KEY `idx_saved_report_views_company_module` (`company_id`,`module_slug`),
  KEY `idx_saved_report_views_employee` (`employee_id`),
  KEY `idx_saved_report_views_share_dept` (`company_id`,`share_department_id`),
  CONSTRAINT `fk_saved_report_views_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_report_views_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheduled_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `report_slug` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `saved_view_id` int DEFAULT NULL,
  `schedule_cron` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'minute hour dom month dow',
  `recipients_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` enum('pdf','xlsx') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pdf',
  `last_sent_at` datetime DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scheduled_reports_company_slug` (`company_id`,`report_slug`),
  KEY `idx_scheduled_reports_company_enabled` (`company_id`,`enabled`),
  KEY `idx_scheduled_reports_saved_view` (`saved_view_id`),
  CONSTRAINT `fk_scheduled_reports_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheduled_reports_saved_view` FOREIGN KEY (`saved_view_id`) REFERENCES `saved_report_views` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `integration_webhook_deliveries`;
DROP TABLE IF EXISTS `integration_webhooks`;

CREATE TABLE `integration_webhooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Comma-separated subscribed event types',
  `secret_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_attempts` int NOT NULL DEFAULT '5',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_integration_webhooks_company_name` (`company_id`,`name`),
  KEY `idx_integration_webhooks_company` (`company_id`),
  CONSTRAINT `fk_integration_webhooks_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `integration_webhook_deliveries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `webhook_id` int NOT NULL,
  `event_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','delivered','failed','dead') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempt_count` int NOT NULL DEFAULT '0',
  `max_attempts` int NOT NULL DEFAULT '5',
  `next_retry_at` datetime DEFAULT NULL,
  `last_http_code` int DEFAULT NULL,
  `last_error` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_integration_webhook_deliveries_queue` (`status`,`next_retry_at`),
  KEY `idx_integration_webhook_deliveries_webhook` (`webhook_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_integration_webhook_deliveries_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_integration_webhook_deliveries_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `integration_webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `equipment_lifecycle_events`;

CREATE TABLE `equipment_lifecycle_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `event_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_lifecycle_events_equipment` (`company_id`,`equipment_id`),
  CONSTRAINT `fk_equipment_lifecycle_events_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_equipment_lifecycle_events_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration runner history (scripts/migrate.php)
DROP TABLE IF EXISTS `schema_migrations`;

CREATE TABLE `schema_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

