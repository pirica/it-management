-- IT Management SQL Backup
-- Generated at: 2026-04-26 14:05:08 UTC
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `access_levels`
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Full', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Read Only', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Limited', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', 'Full', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', 'Limited', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Read Only', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', 'Full', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', 'Limited', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', 'Read Only', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', 'Full', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', 'Limited', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', 'Read Only', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', 'Full', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', 'Limited', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `access_levels` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', 'Read Only', '1', '2026-04-26 15:04:49', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `annual_budgets`
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '2026', '48000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '1', '2', '2026', '36000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('3', '2', '4', '4', '2026', '48000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '4', '5', '2026', '36000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('5', '3', '7', '7', '2026', '48000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('6', '3', '7', '8', '2026', '36000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('7', '4', '10', '10', '2026', '48000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('8', '4', '10', '11', '2026', '36000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('9', '5', '13', '13', '2026', '48000.00', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('10', '5', '13', '14', '2026', '36000.00', '1', '1', '2026-04-26 15:04:49', NULL);

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
  CONSTRAINT `approvals_ibfk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approvals_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvals_ibfk_forecast_revision` FOREIGN KEY (`forecast_revision_id`) REFERENCES `forecast_revisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvals_ibfk_stage` FOREIGN KEY (`stage`) REFERENCES `approvals_stage` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvals_ibfk_status` FOREIGN KEY (`status`) REFERENCES `forecast_revisions_status` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approvals`
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '2', '1', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '1', '1', '1', NULL, NULL, 'Draft not submitted yet.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('3', '2', '4', '3', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '3', '3', '1', NULL, NULL, 'Draft not submitted yet.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('5', '3', '6', '5', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('6', '3', '5', '5', '1', NULL, NULL, 'Draft not submitted yet.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('7', '4', '8', '7', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('8', '4', '7', '7', '1', NULL, NULL, 'Draft not submitted yet.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('9', '5', '10', '9', '3', NULL, NULL, 'Awaiting finance validation for submission batch.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('10', '5', '9', '9', '1', NULL, NULL, 'Draft not submitted yet.', '1', '2026-04-26 15:04:49', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approvals_stage`
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'GM Review', 'General manager review stage before final approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('3', '2', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', 'GM Review', 'General manager review stage before final approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('5', '3', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('6', '3', 'GM Review', 'General manager review stage before final approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('7', '4', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('8', '4', 'GM Review', 'General manager review stage before final approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('9', '5', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`, `updated_at`) VALUES ('10', '5', 'GM Review', 'General manager review stage before final approval.', '1', '2026-04-26 15:04:49', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approver_type`
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'GM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'HOD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'ISM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', 'GM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', 'HOD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'ISM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', 'GM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', 'HOD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', 'ISM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', 'GM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', 'HOD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', 'ISM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', 'GM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', 'HOD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', 'ISM Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('16', '1', 'HRD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('17', '2', 'HRD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('18', '3', 'HRD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'HRD Approval', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`, `updated_at`) VALUES ('20', '5', 'HRD Approval', '1', '2026-04-26 15:04:49', NULL);

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
  CONSTRAINT `approvers_ibfk_approver_type` FOREIGN KEY (`approver_type_id`) REFERENCES `approver_type` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approvers_ibfk_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvers_ibfk_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `approvers_ibfk_employee_position` FOREIGN KEY (`employee_position_id`) REFERENCES `employee_positions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `approvers`
INSERT INTO `approvers` (`id`, `company_id`, `employee_id`, `employee_position_id`, `department_id`, `approver_type_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '1', '1', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `approvers` (`id`, `company_id`, `employee_id`, `employee_position_id`, `department_id`, `approver_type_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '2', '2', '1', '2', '1', '2026-04-26 15:04:50', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `assignment_types`
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Individual', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Shared', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', 'Department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', 'Individual', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Shared', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', 'Department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', 'Individual', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', 'Shared', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', 'Department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', 'Individual', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', 'Shared', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', 'Department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', 'Individual', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `assignment_types` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', 'Shared', '1', '2026-04-26 15:04:49', NULL);

-- Table structure for `attempts`
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
  KEY `fk_attempts_user` (`user_id`),
  CONSTRAINT `fk_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
  CONSTRAINT `audit_logs_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `budget_categories`
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Operating Expense', 'Operational expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Capital Expense', 'Capital expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', 'Operating Expense', 'Operational expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Capital Expense', 'Capital expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', 'Operating Expense', 'Operational expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', 'Capital Expense', 'Capital expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', 'Operating Expense', 'Operational expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', 'Capital Expense', 'Capital expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', 'Operating Expense', 'Operational expense accounts', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', 'Capital Expense', 'Capital expense accounts', '1', '2026-04-26 15:04:49', NULL);

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
  CONSTRAINT `cable_colors_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `cable_colors`
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('1', '1', 'Gray', '#808080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('2', '1', 'Green', '#03b003', 'Printers', '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('3', '1', 'Red', '#ff0000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('4', '1', 'Yellow', '#ffff00', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('5', '1', 'Black', '#000000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('6', '1', 'Blue', '#0000ff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('7', '1', 'White', '#ffffff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('8', '1', 'Orange', '#ffa500', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('9', '1', 'Dark Pink', '#800080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('10', '1', 'Other', NULL, NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('11', '2', 'Gray', '#808080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('12', '2', 'Green', '#03b003', 'Printers', '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('13', '2', 'Red', '#ff0000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('14', '2', 'Yellow', '#ffff00', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('15', '2', 'Black', '#000000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('16', '2', 'Blue', '#0000ff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('17', '2', 'White', '#ffffff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('18', '2', 'Orange', '#ffa500', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('19', '2', 'Dark Pink', '#800080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('20', '2', 'Other', NULL, NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('21', '3', 'Gray', '#808080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('22', '3', 'Green', '#03b003', 'Printers', '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('23', '3', 'Red', '#ff0000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('24', '3', 'Yellow', '#ffff00', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('25', '3', 'Black', '#000000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('26', '3', 'Blue', '#0000ff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('27', '3', 'White', '#ffffff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('28', '3', 'Orange', '#ffa500', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('29', '3', 'Dark Pink', '#800080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('30', '3', 'Other', NULL, NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('31', '4', 'Gray', '#808080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('32', '4', 'Green', '#03b003', 'Printers', '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('33', '4', 'Red', '#ff0000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('34', '4', 'Yellow', '#ffff00', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('35', '4', 'Black', '#000000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('36', '4', 'Blue', '#0000ff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('37', '4', 'White', '#ffffff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('38', '4', 'Orange', '#ffa500', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('39', '4', 'Dark Pink', '#800080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('40', '4', 'Other', NULL, NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('41', '5', 'Gray', '#808080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('42', '5', 'Green', '#03b003', 'Printers', '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('43', '5', 'Red', '#ff0000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('44', '5', 'Yellow', '#ffff00', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('45', '5', 'Black', '#000000', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('46', '5', 'Blue', '#0000ff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('47', '5', 'White', '#ffffff', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('48', '5', 'Orange', '#ffa500', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('49', '5', 'Dark Pink', '#800080', NULL, '2026-04-26 15:04:51', NULL);
INSERT INTO `cable_colors` (`id`, `company_id`, `color_name`, `hex_color`, `comments`, `created_at`, `updated_at`) VALUES ('50', '5', 'Other', NULL, NULL, '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `catalogs_ibfk_manufacturer` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `catalogs_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `catalogs`
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', 'https://fls-na.amaz', '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-12 16:49:33', '2026-04-13 01:23:57');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Cisco Catalyst C9200L-24P-4G-A', '1', 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-12 16:49:33', '2026-04-13 01:23:38');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-12 16:49:33', '2026-04-13 01:23:33');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', 'https://www.bhphotovideo.com/', '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-12 16:49:33', '2026-04-13 01:23:20');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', 'https://www.bestbuy.com/', '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-12 16:49:33', '2026-04-13 01:23:29');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('7', '1', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-12 16:49:33', '2026-04-13 01:20:28');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('8', '1', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-12 16:49:33', '2026-04-13 01:20:22');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('9', '1', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-12 16:49:33', '2026-04-13 01:20:12');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('10', '1', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', '1599.00', '1', '1', 'https://www.insight.com/', '1', '2026-04-12 16:49:33', '2026-04-12 16:51:50');
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('11', '5', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', NULL, '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', NULL, '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('14', '2', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', NULL, '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', 'Cisco Catalyst C9200L-24P-4G-A', '1', NULL, '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('17', '3', 'Cisco Catalyst C9200L-24P-4G-A', '1', NULL, '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('18', '2', 'Cisco Catalyst C9200L-24P-4G-A', '1', NULL, '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('19', '5', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', NULL, '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('21', '3', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', NULL, '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('22', '2', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', NULL, '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', NULL, '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('25', '3', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', NULL, '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('26', '2', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', NULL, '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('27', '5', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', NULL, '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('29', '3', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', NULL, '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('30', '2', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', NULL, '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('31', '5', 'D-Link DGS-108 8-Port Gigabit Desktop Switch', '1', NULL, '29.99', NULL, NULL, 'https://www.walmart.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('33', '3', 'D-Link DGS-108 8-Port Gigabit Desktop Switch', '1', NULL, '29.99', NULL, NULL, 'https://www.walmart.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('34', '2', 'D-Link DGS-108 8-Port Gigabit Desktop Switch', '1', NULL, '29.99', NULL, NULL, 'https://www.walmart.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('35', '5', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', NULL, '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('37', '3', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', NULL, '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('38', '2', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', NULL, '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('39', '5', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', NULL, '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('41', '3', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', NULL, '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('42', '2', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', NULL, '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('43', '5', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', NULL, '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('45', '3', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', NULL, '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('46', '2', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', NULL, '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('47', '5', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', NULL, '1599.00', NULL, '1', 'https://www.insight.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('49', '3', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', NULL, '1599.00', NULL, '1', 'https://www.insight.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('50', '2', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', NULL, '1599.00', NULL, '1', 'https://www.insight.com/', '1', '2026-04-12 16:49:34', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('84', '4', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', NULL, '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('85', '4', 'Cisco Catalyst C9200L-24P-4G-A', '1', NULL, '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('86', '4', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', NULL, '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('87', '4', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', NULL, '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('88', '4', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', NULL, '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('89', '4', 'D-Link DGS-108 8-Port Gigabit Desktop Switch', '1', NULL, '29.99', NULL, NULL, 'https://www.walmart.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('90', '4', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', NULL, '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('91', '4', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', NULL, '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('92', '4', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', NULL, '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('93', '4', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', NULL, '1599.00', NULL, '1', 'https://www.insight.com/', '1', '2026-04-12 17:29:32', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('94', '2', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('95', '2', 'Cisco Catalyst C9200L-24P-4G-A', '1', 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('96', '2', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', '1599.00', '1', '1', 'https://www.insight.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('97', '2', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', 'https://fls-na.amaz', '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('98', '2', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', 'https://www.bestbuy.com/', '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('99', '2', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', 'https://www.bhphotovideo.com/', '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('100', '2', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('101', '2', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('102', '2', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('103', '3', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('104', '3', 'Cisco Catalyst C9200L-24P-4G-A', '1', 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('105', '3', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', '1599.00', '1', '1', 'https://www.insight.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('106', '3', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', 'https://fls-na.amaz', '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('107', '3', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', 'https://www.bestbuy.com/', '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('108', '3', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', 'https://www.bhphotovideo.com/', '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('109', '3', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('110', '3', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('111', '3', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('112', '4', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('113', '4', 'Cisco Catalyst C9200L-24P-4G-A', '1', 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('114', '4', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', '1599.00', '1', '1', 'https://www.insight.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('115', '4', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', 'https://fls-na.amaz', '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('116', '4', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', 'https://www.bestbuy.com/', '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('117', '4', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', 'https://www.bhphotovideo.com/', '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('118', '4', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('119', '4', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('120', '4', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('121', '5', 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', '1', 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', '459.99', NULL, NULL, 'https://www.adorama.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('122', '5', 'Cisco Catalyst C9200L-24P-4G-A', '1', 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', '3899.00', NULL, '1', 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('123', '5', 'Cisco Meraki MS120-24P Cloud Managed Switch', '1', 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', '1599.00', '1', '1', 'https://www.insight.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('124', '5', 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', '1', 'https://fls-na.amaz', '500.00', NULL, '3', 'https://www.amazon.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('125', '5', 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', '1', 'https://www.bestbuy.com/', '39.99', NULL, NULL, 'https://www.bestbuy.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('126', '5', 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', '1', 'https://www.bhphotovideo.com/', '329.99', NULL, NULL, 'https://www.bhphotovideo.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('127', '5', 'Ubiquiti Networks UniFi Switch 24 PoE', '1', 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', '379.00', NULL, '5', 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('128', '5', 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', '1', 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', '699.00', NULL, '5', 'https://www.newegg.com/', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES ('129', '5', 'Ubiquiti UniFi Switch USW Pro 24 PoE', '1', 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', '698.99', NULL, '5', 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', '1', '2026-04-26 15:04:53', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `cost_centers`
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '2', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', '4', 'Room Maintenance', 'CC-HK-RM', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '6', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', '7', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', '9', 'Room Maintenance', 'CC-HK-RM', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', '11', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', '12', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', '14', 'Room Maintenance', 'CC-HK-RM', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', '16', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', '17', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', '19', 'Room Maintenance', 'CC-HK-RM', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', '21', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', '22', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', '24', 'Room Maintenance', 'CC-HK-RM', '1', '2026-04-26 15:04:49', NULL);

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
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `departments`
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'IT Operations', 'IT', 'Core IT operations team', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Human Resources', 'HR', 'Human resources department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Housekeeping', 'HK', 'Housekeeping operations', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Front Office', 'FO', 'Front Office', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'IT Operations', 'IT', 'Core IT operations team', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Human Resources', 'HR', 'Human resources department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'Housekeeping', 'HK', 'Housekeeping operations', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Front Office', 'FO', 'Front Office', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'IT Operations', 'IT', 'Core IT operations team', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'Human Resources', 'HR', 'Human resources department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'Housekeeping', 'HK', 'Housekeeping operations', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'Front Office', 'FO', 'Front Office', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'IT Operations', 'IT', 'Core IT operations team', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'Human Resources', 'HR', 'Human resources department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'Housekeeping', 'HK', 'Housekeeping operations', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'Front Office', 'FO', 'Front Office', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'IT Operations', 'IT', 'Core IT operations team', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'Food and Drinks', 'FNB', 'Food and Beverages department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'Human Resources', 'HR', 'Human resources department', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'Housekeeping', 'HK', 'Housekeeping operations', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'Front Office', 'FO', 'Front Office', '1', '2026-04-26 15:04:49', NULL);

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
  CONSTRAINT `employee_onboarding_requests_ibfk_3` FOREIGN KEY (`office_key_card_dep`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_onboarding_requests_ibfk_4` FOREIGN KEY (`employee_position_id`) REFERENCES `employee_positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_onboarding_requests`
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '4', '3', 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', '0', NULL, '0', NULL, '0', NULL, '0', NULL, '0', NULL, '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', '4', '15', 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', '0', NULL, '0', NULL, '0', NULL, '0', NULL, '0', NULL, '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', '4', '6', 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', '0', NULL, '0', NULL, '0', NULL, '0', NULL, '0', NULL, '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', '4', '9', 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', '0', NULL, '0', NULL, '0', NULL, '0', NULL, '0', NULL, '1', '2026-03-28 19:43:17', NULL);
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES ('8', '4', '4', '12', 'NICKY', 'SCHOUTEN', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'NICKY SCHOUTEN', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', '0', NULL, '0', NULL, '0', NULL, '0', NULL, '0', NULL, '1', '2026-03-28 19:43:17', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_positions`
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'IT Manager', 'Leads hotel IT operations and vendor coordination.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '1', 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', '2', 'Trainee', 'Entry-level operational role for hospitality onboarding.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '6', 'IT Manager', 'Leads hotel IT operations and vendor coordination.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', '6', 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', '7', 'Trainee', 'Entry-level operational role for hospitality onboarding.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', '11', 'IT Manager', 'Leads hotel IT operations and vendor coordination.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', '11', 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', '12', 'Trainee', 'Entry-level operational role for hospitality onboarding.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', '16', 'IT Manager', 'Leads hotel IT operations and vendor coordination.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', '16', 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', '17', 'Trainee', 'Entry-level operational role for hospitality onboarding.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', '21', 'IT Manager', 'Leads hotel IT operations and vendor coordination.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', '21', 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', '22', 'Trainee', 'Entry-level operational role for hospitality onboarding.', '1', '2026-04-26 15:04:50', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `employee_statuses`
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Active', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Inactive', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'On Leave', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Terminated', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Contractor', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Active', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Contractor', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Inactive', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'On Leave', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Terminated', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'Active', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Contractor', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'Inactive', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'On Leave', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'Terminated', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'Active', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'Contractor', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'Inactive', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'On Leave', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'Terminated', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'Active', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'Contractor', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'Inactive', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'On Leave', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `employee_statuses` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'Terminated', '1', '2026-04-26 15:04:50', NULL);

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
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_system_access_company_employee` (`company_id`,`employee_id`),
  KEY `idx_employee_system_access_company` (`company_id`),
  KEY `fk_employee_system_access_employee` (`employee_id`),
  CONSTRAINT `fk_employee_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_system_access_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
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
  UNIQUE KEY `uq_equipment_company_name` (`company_id`,`name`),
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
  KEY `switch_fiber_count_id` (`switch_fiber_count_id`),
  KEY `switch_poe_id` (`switch_poe_id`),
  KEY `switch_environment_id` (`switch_environment_id`),
  CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipment_ibfk_10` FOREIGN KEY (`workstation_os_type_id`) REFERENCES `workstation_os_types` (`id`),
  CONSTRAINT `equipment_ibfk_11` FOREIGN KEY (`switch_rj45_id`) REFERENCES `equipment_rj45` (`id`),
  CONSTRAINT `equipment_ibfk_12` FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`),
  CONSTRAINT `equipment_ibfk_13` FOREIGN KEY (`switch_fiber_id`) REFERENCES `equipment_fiber` (`id`),
  CONSTRAINT `equipment_ibfk_14` FOREIGN KEY (`switch_poe_id`) REFERENCES `equipment_poe` (`id`),
  CONSTRAINT `equipment_ibfk_15` FOREIGN KEY (`switch_environment_id`) REFERENCES `equipment_environment` (`id`),
  CONSTRAINT `equipment_ibfk_16` FOREIGN KEY (`switch_fiber_count_id`) REFERENCES `equipment_fiber_count` (`id`),
  CONSTRAINT `equipment_ibfk_17` FOREIGN KEY (`workstation_ram_id`) REFERENCES `workstation_ram` (`id`),
  CONSTRAINT `equipment_ibfk_19` FOREIGN KEY (`workstation_os_version_id`) REFERENCES `workstation_os_versions` (`id`),
  CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`),
  CONSTRAINT `equipment_ibfk_20` FOREIGN KEY (`switch_fiber_patch_id`) REFERENCES `equipment_fiber_patch` (`id`),
  CONSTRAINT `equipment_ibfk_21` FOREIGN KEY (`switch_fiber_rack_id`) REFERENCES `equipment_fiber_rack` (`id`),
  CONSTRAINT `equipment_ibfk_22` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_3` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`),
  CONSTRAINT `equipment_ibfk_5` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipment_ibfk_6` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`),
  CONSTRAINT `equipment_ibfk_7` FOREIGN KEY (`warranty_type_id`) REFERENCES `warranty_types` (`id`),
  CONSTRAINT `equipment_ibfk_8` FOREIGN KEY (`printer_device_type_id`) REFERENCES `printer_device_types` (`id`),
  CONSTRAINT `equipment_ibfk_9` FOREIGN KEY (`workstation_device_type_id`) REFERENCES `workstation_device_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment`
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '2', '1', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, '1', '2025-01-10', '8500.00', NULL, '2027-01-10', '4', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', '2026-03-31 00:39:19');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', '1', '2', '1', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, '1', '2025-01-10', '8500.00', NULL, '2027-01-10', '4', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', '2026-03-31 00:39:19');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', '1', '2', '1', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, '1', '2025-01-10', '8500.00', NULL, '2027-01-10', '4', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', '2026-03-31 00:39:19');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', '1', '2', '1', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, '1', '2025-01-10', '8500.00', NULL, '2027-01-10', '4', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', '2026-03-31 00:39:19');
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_count_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', '1', '2', '1', '1', '1', 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, '1', '2025-01-10', '8500.00', NULL, '2027-01-10', '4', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '2026-03-28 19:43:17', '2026-03-31 00:39:19');

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
  CONSTRAINT `equipment_environment_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_environment`
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Managed', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Unmanaged', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '2', 'Managed', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '2', 'Unmanaged', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '3', 'Managed', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '3', 'Unmanaged', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '4', 'Managed', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '4', 'Unmanaged', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '5', 'Managed', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_environment` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '5', 'Unmanaged', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_fiber_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber`
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'SFP 1 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'SFP+ 10 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'QSFP 40 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '2', 'QSFP 40 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', 'SFP 1 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', 'SFP+ 10 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '3', 'QSFP 40 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '3', 'SFP 1 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', 'SFP+ 10 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '4', 'QSFP 40 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '4', 'SFP 1 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '4', 'SFP+ 10 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '5', 'QSFP 40 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '5', 'SFP 1 Gbps', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '5', 'SFP+ 10 Gbps', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_fiber_count_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_count`
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', '2', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', '3', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', '4', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', '2', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', '3', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', '4', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '3', '2', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '3', '3', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '3', '4', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '4', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '4', '2', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '4', '3', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '4', '4', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '5', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '5', '2', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '5', '3', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_count` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '5', '4', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_fiber_patch_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_patch`
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Patch Panel A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Patch Panel B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '2', 'Patch Panel A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '2', 'Patch Panel B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '3', 'Patch Panel A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '3', 'Patch Panel B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '4', 'Patch Panel A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '4', 'Patch Panel B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '5', 'Patch Panel A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_patch` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '5', 'Patch Panel B', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_fiber_rack_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_fiber_rack`
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Rack A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Rack B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '2', 'Rack A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '2', 'Rack B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '3', 'Rack A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '3', 'Rack B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '4', 'Rack A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '4', 'Rack B', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '5', 'Rack A', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_fiber_rack` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '5', 'Rack B', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_poe_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_poe`
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'PoE (802.3af) - up to 15.4W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'PoE+ (802.3at) - up to 30W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'PoE++ (802.3bt) - up to 60-90W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '2', 'PoE (802.3af) - up to 15.4W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', 'PoE+ (802.3at) - up to 30W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', 'PoE++ (802.3bt) - up to 60-90W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '3', 'PoE (802.3af) - up to 15.4W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '3', 'PoE+ (802.3at) - up to 30W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', 'PoE++ (802.3bt) - up to 60-90W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '4', 'PoE (802.3af) - up to 15.4W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '4', 'PoE+ (802.3at) - up to 30W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '4', 'PoE++ (802.3bt) - up to 60-90W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '5', 'PoE (802.3af) - up to 15.4W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '5', 'PoE+ (802.3at) - up to 30W', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_poe` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '5', 'PoE++ (802.3bt) - up to 60-90W', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_rj45_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_rj45`
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', '8 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', '16 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', '24 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', '48 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', '16 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', '24 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', '48 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', '8 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', '16 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '3', '24 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '3', '48 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '3', '8 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '4', '16 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '4', '24 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '4', '48 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '4', '8 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '5', '16 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '5', '24 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '5', '48 ports', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_rj45` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '5', '8 ports', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_statuses`
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Active', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Inactive', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Maintenance', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Faulty', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'Reserved', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', 'Decommissioned', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '1', 'On-Order', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '1', 'Other', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '2', 'Active', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', 'Decommissioned', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '2', 'Faulty', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '2', 'Inactive', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '2', 'Maintenance', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '2', 'On-Order', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '2', 'Other', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '2', 'Reserved', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '3', 'Active', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '3', 'Decommissioned', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '3', 'Faulty', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '3', 'Inactive', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '3', 'Maintenance', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '3', 'On-Order', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '3', 'Other', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '3', 'Reserved', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '4', 'Active', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '4', 'Decommissioned', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '4', 'Faulty', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '4', 'Inactive', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '4', 'Maintenance', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '4', 'On-Order', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('31', '4', 'Other', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('32', '4', 'Reserved', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('33', '5', 'Active', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('34', '5', 'Decommissioned', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('35', '5', 'Faulty', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('36', '5', 'Inactive', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('37', '5', 'Maintenance', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('38', '5', 'On-Order', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('39', '5', 'Other', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('40', '5', 'Reserved', '2026-04-26 15:04:50', NULL);

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
  CONSTRAINT `equipment_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `equipment_types`
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Switch', 'SWITCH', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Server', 'SRV', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Router', 'RTR', '✳️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Firewall', 'FW', '🔥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Port Patch Panel', 'PORT', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('6', '1', 'Access Point', 'AP', '🛜', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('7', '1', 'Workstation', 'WS', '💻', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('8', '1', 'POS', 'POS', '🏧', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('9', '1', 'Printer', 'PRN', '🖨️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('10', '1', 'Phone', 'PHONE', '📞', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('11', '1', 'CCTV', 'CCCTV', '🎥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('12', '1', 'Other', 'OTHER', NULL, '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('13', '2', 'Switch', 'SWITCH', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('14', '2', 'Server', 'SRV', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('15', '2', 'Router', 'RTR', '✳️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('16', '2', 'Firewall', 'FW', '🔥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('17', '2', 'Port Patch Panel', 'PORT', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('18', '2', 'Access Point', 'AP', '🛜', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('19', '2', 'Workstation', 'WS', '💻', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('20', '2', 'POS', 'POS', '🏧', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('21', '2', 'Printer', 'PRN', '🖨️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('22', '2', 'Phone', 'PHONE', '📞', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('23', '2', 'CCTV', 'CCCTV', '🎥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('24', '2', 'Other', 'OTHER', NULL, '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('25', '3', 'Switch', 'SWITCH', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('26', '3', 'Server', 'SRV', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('27', '3', 'Router', 'RTR', '✳️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('28', '3', 'Firewall', 'FW', '🔥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('29', '3', 'Port Patch Panel', 'PORT', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('30', '3', 'Access Point', 'AP', '🛜', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('31', '3', 'Workstation', 'WS', '💻', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('32', '3', 'POS', 'POS', '🏧', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('33', '3', 'Printer', 'PRN', '🖨️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('34', '3', 'Phone', 'PHONE', '📞', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('35', '3', 'CCTV', 'CCCTV', '🎥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('36', '3', 'Other', 'OTHER', NULL, '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('37', '4', 'Switch', 'SWITCH', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('38', '4', 'Server', 'SRV', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('39', '4', 'Router', 'RTR', '✳️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('40', '4', 'Firewall', 'FW', '🔥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('41', '4', 'Port Patch Panel', 'PORT', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('42', '4', 'Access Point', 'AP', '🛜', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('43', '4', 'Workstation', 'WS', '💻', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('44', '4', 'POS', 'POS', '🏧', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('45', '4', 'Printer', 'PRN', '🖨️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('46', '4', 'Phone', 'PHONE', '📞', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('47', '4', 'CCTV', 'CCCTV', '🎥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('48', '4', 'Other', 'OTHER', NULL, '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('49', '5', 'Switch', 'SWITCH', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('50', '5', 'Server', 'SRV', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('51', '5', 'Router', 'RTR', '✳️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('52', '5', 'Firewall', 'FW', '🔥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('53', '5', 'Port Patch Panel', 'PORT', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('54', '5', 'Access Point', 'AP', '🛜', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('55', '5', 'Workstation', 'WS', '💻', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('56', '5', 'POS', 'POS', '🏧', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('57', '5', 'Printer', 'PRN', '🖨️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('58', '5', 'Phone', 'PHONE', '📞', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('59', '5', 'CCTV', 'CCCTV', '🎥', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `equipment_types` (`id`, `company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('60', '5', 'Other', 'OTHER', NULL, '1', '2026-04-26 15:04:50', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `expenses`
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', '4', '4', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', '7', '7', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', '10', '10', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', '13', '13', '2026-01-15', '3890.00', 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', '1', '1', '2026-04-26 15:04:49', NULL);

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
  CONSTRAINT `forecast_revisions_ibfk_finance_reviewed_by` FOREIGN KEY (`finance_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_ibfk_gl_account` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `forecast_revisions_ibfk_gm_approved_by` FOREIGN KEY (`gm_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_ibfk_status` FOREIGN KEY (`status`) REFERENCES `forecast_revisions_status` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `forecast_revisions_ibfk_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `forecast_revisions_chk_month` CHECK ((`month` between 1 and 12))
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `forecast_revisions`
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '2026', '2', '4200.00', '1', '0', '1', NULL, NULL, 'Draft projection before finance review', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '1', '2', '2026', '2', '3150.00', '2', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('3', '2', '4', '4', '2026', '2', '4200.00', '7', '0', '1', NULL, NULL, 'Draft projection before finance review', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '4', '5', '2026', '2', '3150.00', '8', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('5', '3', '7', '7', '2026', '2', '4200.00', '13', '0', '1', NULL, NULL, 'Draft projection before finance review', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('6', '3', '7', '8', '2026', '2', '3150.00', '14', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('7', '4', '10', '10', '2026', '2', '4200.00', '19', '0', '1', NULL, NULL, 'Draft projection before finance review', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('8', '4', '10', '11', '2026', '2', '3150.00', '20', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('9', '5', '13', '13', '2026', '2', '4200.00', '25', '0', '1', NULL, NULL, 'Draft projection before finance review', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('10', '5', '13', '14', '2026', '2', '3150.00', '26', '0', '1', NULL, NULL, 'Submitted to finance for February forecast', '1', '2026-04-26 15:04:49', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `forecast_revisions_status`
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Draft', 'Draft projection before finance review', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Submitted', 'Submitted to finance for February forecast', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Finance Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Gm Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Approved', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('6', '1', 'Rejected', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Draft', 'Draft projection before finance review', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Submitted', 'Submitted to finance for February forecast', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'Finance Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Gm Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('11', '2', 'Approved', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('12', '2', 'Rejected', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'Draft', 'Draft projection before finance review', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'Submitted', 'Submitted to finance for February forecast', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'Finance Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('16', '3', 'Gm Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('17', '3', 'Approved', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('18', '3', 'Rejected', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'Draft', 'Draft projection before finance review', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'Submitted', 'Submitted to finance for February forecast', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('21', '4', 'Finance Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('22', '4', 'Gm Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('23', '4', 'Approved', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('24', '4', 'Rejected', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'Draft', 'Draft projection before finance review', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('26', '5', 'Submitted', 'Submitted to finance for February forecast', '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('27', '5', 'Finance Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('28', '5', 'Gm Review', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('29', '5', 'Approved', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('30', '5', 'Rejected', NULL, '1', NULL, NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('31', '2', 'Draft', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('32', '2', 'Submitted', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('33', '2', 'Finance Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('34', '2', 'Gm Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('35', '2', 'Approved', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('36', '2', 'Rejected', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('37', '3', 'Draft', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('38', '3', 'Submitted', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('39', '3', 'Finance Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('40', '3', 'Gm Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('41', '3', 'Approved', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('42', '3', 'Rejected', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('43', '4', 'Draft', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('44', '4', 'Submitted', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('45', '4', 'Finance Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('46', '4', 'Gm Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('47', '4', 'Approved', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('48', '4', 'Rejected', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('49', '5', 'Draft', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('50', '5', 'Submitted', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('51', '5', 'Finance Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('52', '5', 'Gm Review', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('53', '5', 'Approved', NULL, '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('54', '5', 'Rejected', NULL, '1', '2026-04-26 15:04:53', NULL);

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
  CONSTRAINT `gl_accounts_ibfk_category` FOREIGN KEY (`category_id`) REFERENCES `budget_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gl_accounts_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `gl_accounts`
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '6100', 'IT Maintenance Contracts', '2', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '6200', 'Software Licensing', '2', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', '7100', 'Capital IT Equipment', '3', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '6100', 'IT Maintenance Contracts', '5', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', '6200', 'Software Licensing', '5', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', '7100', 'Capital IT Equipment', '6', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('7', '3', '6100', 'IT Maintenance Contracts', '8', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('8', '3', '6200', 'Software Licensing', '8', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', '7100', 'Capital IT Equipment', '9', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('10', '4', '6100', 'IT Maintenance Contracts', '11', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('11', '4', '6200', 'Software Licensing', '11', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('12', '4', '7100', 'Capital IT Equipment', '12', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('13', '5', '6100', 'IT Maintenance Contracts', '14', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('14', '5', '6200', 'Software Licensing', '14', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`, `updated_at`) VALUES ('15', '5', '7100', 'Capital IT Equipment', '15', '1', '2026-04-26 15:04:49', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idf_device_type`
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'switch', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'patch_panel', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'ups', '🔋', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'server', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'other', '📦', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'switch', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'patch_panel', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'ups', '🔋', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'server', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'other', '📦', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'switch', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'patch_panel', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'ups', '🔋', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'server', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'other', '📦', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'switch', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'patch_panel', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'ups', '🔋', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'server', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'other', '📦', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'switch', '🔀', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'patch_panel', '➿', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'ups', '🔋', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'server', '🖥️', '1', '2026-04-26 15:04:50', NULL);
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'other', '📦', '1', '2026-04-26 15:04:50', NULL);

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
  KEY `company_id` (`company_id`),
  CONSTRAINT `idf_links_ibfk_a` FOREIGN KEY (`port_id_a`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_b` FOREIGN KEY (`port_id_b`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_cable_color` FOREIGN KEY (`cable_color_id`) REFERENCES `cable_colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_links_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  UNIQUE KEY `pos_port_unique` (`company_id`,`position_id`,`port_no`),
  KEY `company_id` (`company_id`),
  KEY `position_id` (`position_id`),
  KEY `idf_ports_port_type_idx` (`port_type`),
  KEY `idf_ports_status_idx` (`status_id`),
  KEY `idf_ports_vlan_idx` (`vlan_id`),
  KEY `idf_ports_speed_idx` (`speed_id`),
  KEY `idf_ports_poe_idx` (`poe_id`),
  CONSTRAINT `idf_ports_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_ports_ibfk_poe` FOREIGN KEY (`poe_id`) REFERENCES `equipment_poe` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_port_type` FOREIGN KEY (`port_type`) REFERENCES `switch_port_types` (`id`),
  CONSTRAINT `idf_ports_ibfk_position` FOREIGN KEY (`position_id`) REFERENCES `idf_positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_ports_ibfk_speed` FOREIGN KEY (`speed_id`) REFERENCES `equipment_fiber` (`id`) ON DELETE SET NULL,
  CONSTRAINT `idf_ports_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`),
  CONSTRAINT `idf_ports_ibfk_vlan` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `idfs_ibfk_rack` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `idfs`
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58', NULL);
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58', NULL);
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58', NULL);
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58', NULL);
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-03-31 00:25:58', NULL);

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
  CONSTRAINT `inventory_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_categories`
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Cables - USB', 'CBL-USB', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Adapters', 'ADP', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Batteries', 'BAT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Consumables', 'CONS', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('6', '1', 'Other', 'OTH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Cables - USB', 'CBL-USB', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'Adapters', 'ADP', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Batteries', 'BAT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('11', '2', 'Consumables', 'CONS', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('12', '2', 'Other', 'OTH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'Cables - USB', 'CBL-USB', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'Adapters', 'ADP', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('16', '3', 'Batteries', 'BAT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('17', '3', 'Consumables', 'CONS', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('18', '3', 'Other', 'OTH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'Cables - USB', 'CBL-USB', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('21', '4', 'Adapters', 'ADP', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('22', '4', 'Batteries', 'BAT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('23', '4', 'Consumables', 'CONS', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('24', '4', 'Other', 'OTH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('26', '5', 'Cables - USB', 'CBL-USB', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('27', '5', 'Adapters', 'ADP', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('28', '5', 'Batteries', 'BAT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('29', '5', 'Consumables', 'CONS', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `inventory_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('30', '5', 'Other', 'OTH', '1', '2026-04-26 15:04:51', NULL);

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `inventory_items`
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '1', '1', '50', '10', '4.99', 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '7', '1', '50', '10', '4.99', 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '13', '1', '50', '10', '4.99', 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '19', '1', '50', '10', '4.99', 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '25', '1', '50', '10', '4.99', 'Stock for patching and desktop setups', '1', '1', '1', NULL, NULL);

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
  CONSTRAINT `it_locations_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `location_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `location_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `location_types`
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Headquarters', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Branch', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Warehouse', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'DataCenter', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'Office', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', 'Remote', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '1', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', 'Branch', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '2', 'DataCenter', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', 'Headquarters', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '2', 'Office', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '2', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '2', 'Remote', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '2', 'Warehouse', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '3', 'Branch', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '3', 'DataCenter', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '3', 'Headquarters', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '3', 'Office', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '3', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '3', 'Remote', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '3', 'Warehouse', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '4', 'Branch', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '4', 'DataCenter', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '4', 'Headquarters', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '4', 'Office', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '4', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '4', 'Remote', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '4', 'Warehouse', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '5', 'Branch', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '5', 'DataCenter', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('31', '5', 'Headquarters', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('32', '5', 'Office', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('33', '5', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('34', '5', 'Remote', '2026-04-26 15:04:51', NULL);
INSERT INTO `location_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('35', '5', 'Warehouse', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `manufacturers_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `manufacturers`
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Cisco Systems', 'CSCO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Dell Technologies', 'DELL', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'HP Inc', 'HPE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Juniper Networks', 'JNPR', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Ubiquiti Networks', 'UBNT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('6', '1', 'Apple', 'APPLE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('7', '1', 'Lenovo', 'LENOVO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('8', '1', 'Microsoft', 'MSFT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'Cisco Systems', 'CSCO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Dell Technologies', 'DELL', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('11', '2', 'HP Inc', 'HPE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('12', '2', 'Juniper Networks', 'JNPR', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('13', '2', 'Ubiquiti Networks', 'UBNT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('14', '2', 'Apple', 'APPLE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('15', '2', 'Lenovo', 'LENOVO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('16', '2', 'Microsoft', 'MSFT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('17', '3', 'Cisco Systems', 'CSCO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('18', '3', 'Dell Technologies', 'DELL', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('19', '3', 'HP Inc', 'HPE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('20', '3', 'Juniper Networks', 'JNPR', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('21', '3', 'Ubiquiti Networks', 'UBNT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('22', '3', 'Apple', 'APPLE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('23', '3', 'Lenovo', 'LENOVO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('24', '3', 'Microsoft', 'MSFT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('25', '4', 'Cisco Systems', 'CSCO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('26', '4', 'Dell Technologies', 'DELL', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('27', '4', 'HP Inc', 'HPE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('28', '4', 'Juniper Networks', 'JNPR', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('29', '4', 'Ubiquiti Networks', 'UBNT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('30', '4', 'Apple', 'APPLE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('31', '4', 'Lenovo', 'LENOVO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('32', '4', 'Microsoft', 'MSFT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('33', '5', 'Cisco Systems', 'CSCO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('34', '5', 'Dell Technologies', 'DELL', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('35', '5', 'HP Inc', 'HPE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('36', '5', 'Juniper Networks', 'JNPR', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('37', '5', 'Ubiquiti Networks', 'UBNT', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('38', '5', 'Apple', 'APPLE', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('39', '5', 'Lenovo', 'LENOVO', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `manufacturers` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('40', '5', 'Microsoft', 'MSFT', '1', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `monthly_budgets_ibfk_annual_budget` FOREIGN KEY (`annual_budget_id`) REFERENCES `annual_budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_budgets_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_budgets_chk_month` CHECK ((`month` between 1 and 12))
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `monthly_budgets`
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '4000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '2', '1', '3000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('3', '2', '3', '1', '4000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('4', '2', '4', '1', '3000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('5', '3', '5', '1', '4000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('6', '3', '6', '1', '3000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('7', '4', '7', '1', '4000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('8', '4', '8', '1', '3000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('9', '5', '9', '1', '4000.00', '1', '2026-04-26 15:04:49', NULL);
INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`, `updated_at`) VALUES ('10', '5', '10', '1', '3000.00', '1', '2026-04-26 15:04:49', NULL);

-- Table structure for `patches_updates`
DROP TABLE IF EXISTS `patches_updates`;
CREATE TABLE `patches_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  CONSTRAINT `patches_updates_ibfk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_level` FOREIGN KEY (`level_id`) REFERENCES `patches_updates_level` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patches_updates_ibfk_status` FOREIGN KEY (`status_id`) REFERENCES `patches_updates_status` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `patches_updates_level`
DROP TABLE IF EXISTS `patches_updates_level`;
CREATE TABLE `patches_updates_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patches_updates_level_company_idx` (`company_id`),
  CONSTRAINT `patches_updates_level_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `patches_updates_level`
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('1', '1', 'Critical', 'Critical', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('2', '1', 'High', 'High', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('3', '1', 'Medium', 'Medium', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('4', '1', 'Low', 'Low', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('5', '1', 'Other', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('6', '2', 'Critical', 'Critical', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('7', '2', 'High', 'High', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('8', '2', 'Medium', 'Medium', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('9', '2', 'Low', 'Low', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('10', '2', 'Other', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('11', '3', 'Critical', 'Critical', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('12', '3', 'High', 'High', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('13', '3', 'Medium', 'Medium', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('14', '3', 'Low', 'Low', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('15', '3', 'Other', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('16', '4', 'Critical', 'Critical', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('17', '4', 'High', 'High', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('18', '4', 'Medium', 'Medium', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('19', '4', 'Low', 'Low', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('20', '4', 'Other', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('21', '5', 'Critical', 'Critical', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('22', '5', 'High', 'High', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('23', '5', 'Medium', 'Medium', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('24', '5', 'Low', 'Low', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('25', '5', 'Other', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('26', '2', 'Other', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('27', '2', 'Low', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('28', '2', 'Medium', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('29', '2', 'High', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('30', '2', 'Critical', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('31', '3', 'Other', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('32', '3', 'Low', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('33', '3', 'Medium', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('34', '3', 'High', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('35', '3', 'Critical', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('36', '4', 'Other', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('37', '4', 'Low', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('38', '4', 'Medium', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('39', '4', 'High', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('40', '4', 'Critical', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('41', '5', 'Other', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('42', '5', 'Low', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('43', '5', 'Medium', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('44', '5', 'High', NULL, '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_level` (`id`, `company_id`, `name`, `level`, `created_at`, `updated_at`) VALUES ('45', '5', 'Critical', NULL, '2026-04-26 15:04:53', NULL);

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
  KEY `patches_updates_status_company_idx` (`company_id`),
  CONSTRAINT `patches_updates_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `patches_updates_status`
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('10', '3', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('13', '4', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('14', '4', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('15', '4', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('17', '5', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('18', '5', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('19', '5', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('20', '5', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('21', '2', 'Closed', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('22', '2', 'Resolved', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('23', '2', 'In Progress', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('24', '2', 'Open', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('25', '3', 'Closed', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('26', '3', 'Resolved', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('27', '3', 'In Progress', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('28', '3', 'Open', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('29', '4', 'Closed', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('30', '4', 'Resolved', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('31', '4', 'In Progress', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('32', '4', 'Open', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('33', '5', 'Closed', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('34', '5', 'Resolved', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('35', '5', 'In Progress', NULL, '0', '1', '2026-04-26 15:04:53', NULL);
INSERT INTO `patches_updates_status` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('36', '5', 'Open', NULL, '0', '1', '2026-04-26 15:04:53', NULL);

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
  CONSTRAINT `printer_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `printer_device_types`
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Laser', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Inkjet', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'All-in-One', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Thermal', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'Wide-Format', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', 'Photo', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '1', 'Label', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '1', 'Dotmatrix', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '1', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', 'All-in-One', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '2', 'Dotmatrix', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '2', 'Inkjet', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '2', 'Label', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '2', 'Laser', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '2', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '2', 'Photo', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '2', 'Thermal', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '2', 'Wide-Format', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '3', 'All-in-One', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '3', 'Dotmatrix', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '3', 'Inkjet', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '3', 'Label', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '3', 'Laser', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '3', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '3', 'Photo', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '3', 'Thermal', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '3', 'Wide-Format', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '4', 'All-in-One', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '4', 'Dotmatrix', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '4', 'Inkjet', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('31', '4', 'Label', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('32', '4', 'Laser', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('33', '4', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('34', '4', 'Photo', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('35', '4', 'Thermal', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('36', '4', 'Wide-Format', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('37', '5', 'All-in-One', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('38', '5', 'Dotmatrix', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('39', '5', 'Inkjet', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('40', '5', 'Label', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('41', '5', 'Laser', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('42', '5', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('43', '5', 'Photo', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('44', '5', 'Thermal', '2026-04-26 15:04:51', NULL);
INSERT INTO `printer_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('45', '5', 'Wide-Format', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `rack_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `rack_statuses`
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Maintenance', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Full', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Decommissioned', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', 'Decommissioned', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', 'Full', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', 'Maintenance', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '3', 'Decommissioned', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '3', 'Full', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '3', 'Maintenance', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '4', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '4', 'Decommissioned', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '4', 'Full', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '4', 'Maintenance', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '5', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '5', 'Decommissioned', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '5', 'Full', '2026-04-26 15:04:51', NULL);
INSERT INTO `rack_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '5', 'Maintenance', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `racks_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `rack_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `racks`
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'Main Rack A', 'RACK-A', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', '2', 'Main Rack A', 'RACK-A', '5', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', '3', 'Main Rack A', 'RACK-A', '9', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', '4', 'Main Rack A', 'RACK-A', '13', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', '5', 'Main Rack A', 'RACK-A', '17', '1', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `fk_registration_invitations_access_level` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_invitations_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registration_invitations_invited_by` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_invitations_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `registration_invitations`
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'new.user@techcorp.example', 'INVITE-TECHCORP-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:00', NULL);
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'new.user@datacenterplus.example', 'INVITE-DATACENTERPLUS-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:10', NULL);
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'new.user@networksolutions.example', 'INVITE-NETWORKSOLUTIONS-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:20', NULL);
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'new.user@cloudtech.example', 'INVITE-CLOUDTECH-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:30', NULL);
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_user_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'new.user@enterpriseit.example', 'INVITE-ENTERPRISEIT-001', '1', '1', '1', NULL, NULL, '1', '2026-03-28 19:44:40', NULL);

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
  KEY `fk_role_assignment_rights_role` (`role_id`),
  KEY `fk_role_assignment_rights_target_role` (`can_assign_role_id`),
  CONSTRAINT `fk_role_assignment_rights_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_assignment_rights_target_role` FOREIGN KEY (`can_assign_role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_assignment_rights`
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('2', '2', '6', '9', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('3', '3', '11', '14', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('4', '4', '16', '19', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('5', '5', '21', '24', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('6', '1', '1', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('7', '2', '6', '8', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('8', '3', '11', '13', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('9', '4', '16', '18', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('10', '5', '21', '23', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('11', '1', '1', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('12', '2', '6', '7', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('13', '3', '11', '12', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('14', '4', '16', '17', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('15', '5', '21', '22', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('16', '1', '1', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('17', '2', '6', '10', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('18', '3', '11', '15', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('19', '4', '16', '20', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('20', '5', '21', '25', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('21', '1', '2', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('22', '2', '9', '8', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('23', '3', '14', '13', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('24', '4', '19', '18', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('25', '5', '24', '23', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('26', '1', '2', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('27', '2', '9', '7', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('28', '3', '14', '12', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('29', '4', '19', '17', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('30', '5', '24', '22', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('31', '1', '2', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('32', '2', '9', '10', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('33', '3', '14', '15', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('34', '4', '19', '20', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('35', '5', '24', '25', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('36', '1', '3', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('37', '2', '8', '7', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('38', '3', '13', '12', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('39', '4', '18', '17', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('40', '5', '23', '22', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('41', '1', '3', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('42', '2', '8', '10', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('43', '3', '13', '15', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('44', '4', '18', '20', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_assignment_rights` (`id`, `company_id`, `role_id`, `can_assign_role_id`, `created_at`, `updated_at`) VALUES ('45', '5', '23', '25', '2026-04-26 15:04:52', NULL);

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
  KEY `fk_role_hierarchy_role` (`role_id`),
  CONSTRAINT `fk_role_hierarchy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_hierarchy_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_hierarchy`
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('2', '2', '6', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('3', '3', '11', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('4', '4', '16', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('5', '5', '21', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('6', '1', '2', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('7', '2', '9', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('8', '3', '14', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('9', '4', '19', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('10', '5', '24', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('11', '1', '3', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('12', '2', '8', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('13', '3', '13', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('14', '4', '18', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('15', '5', '23', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('16', '1', '4', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('17', '2', '7', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('18', '3', '12', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('19', '4', '17', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('20', '5', '22', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('21', '1', '5', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('22', '2', '10', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('23', '3', '15', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('24', '4', '20', '5', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_hierarchy` (`id`, `company_id`, `role_id`, `hierarchy_order`, `created_at`, `updated_at`) VALUES ('25', '5', '25', '5', '2026-04-26 15:04:52', NULL);

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module_permissions` (`company_id`,`role_id`,`module_name`),
  KEY `fk_role_module_permissions_role` (`role_id`),
  CONSTRAINT `fk_role_module_permissions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_module_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `role_module_permissions`
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'ALL', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('2', '2', '6', 'ALL', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('3', '3', '11', 'ALL', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('4', '4', '16', 'ALL', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('5', '5', '21', 'ALL', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('6', '1', '4', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('7', '2', '7', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('8', '3', '12', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('9', '4', '17', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('10', '5', '22', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('11', '1', '5', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('12', '2', '10', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('13', '3', '15', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('14', '4', '20', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `role_module_permissions` (`id`, `company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES ('15', '5', '25', 'Tickets', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `supplier_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `supplier_statuses`
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Inactive', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Preferred', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Backup', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', 'Backup', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', 'Inactive', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '2', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', 'Preferred', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '3', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '3', 'Backup', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '3', 'Inactive', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '3', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '3', 'Preferred', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '4', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '4', 'Backup', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '4', 'Inactive', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '4', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '4', 'Preferred', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '5', 'Active', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '5', 'Backup', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '5', 'Inactive', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '5', 'Other', '2026-04-26 15:04:51', NULL);
INSERT INTO `supplier_statuses` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '5', 'Preferred', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `supplier_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `suppliers`
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '1', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '6', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '11', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '16', '1', '2026-04-26 15:04:51', NULL);
INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '21', '1', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `switch_port_numbering_layout_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_numbering_layout`
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Vertical', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Horizontal', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '2', 'Horizontal', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '2', 'Vertical', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '3', 'Horizontal', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '3', 'Vertical', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '4', 'Horizontal', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '4', 'Vertical', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '5', 'Horizontal', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_numbering_layout` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '5', 'Vertical', '2026-04-26 15:04:51', NULL);

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
  CONSTRAINT `switch_port_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_port_types`
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('1', '1', 'RJ45', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('2', '1', 'SFP', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('3', '1', 'SFP+', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('4', '2', 'RJ45', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('5', '2', 'SFP', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('6', '2', 'SFP+', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('7', '3', 'RJ45', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('8', '3', 'SFP', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('9', '3', 'SFP+', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('10', '4', 'RJ45', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('11', '4', 'SFP', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('12', '4', 'SFP+', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('13', '5', 'RJ45', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('14', '5', 'SFP', '2026-04-26 15:04:51', NULL);
INSERT INTO `switch_port_types` (`id`, `company_id`, `type`, `created_at`, `updated_at`) VALUES ('15', '5', 'SFP+', '2026-04-26 15:04:51', NULL);

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
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
  CONSTRAINT `switch_ports_ibfk_4` FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`),
  CONSTRAINT `switch_ports_ibfk_5` FOREIGN KEY (`company_id`, `port_type`) REFERENCES `switch_port_types` (`company_id`, `type`),
  CONSTRAINT `switch_ports_ibfk_6` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_ports`
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('1', '1', '1', NULL, 'RJ45', '1', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('2', '1', '1', NULL, 'RJ45', '2', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('3', '1', '1', NULL, 'RJ45', '3', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('4', '1', '1', NULL, 'RJ45', '4', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('5', '1', '1', NULL, 'RJ45', '5', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('6', '1', '1', NULL, 'RJ45', '6', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('7', '1', '1', NULL, 'RJ45', '7', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('8', '1', '1', NULL, 'RJ45', '8', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('9', '1', '1', NULL, 'RJ45', '9', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('10', '1', '1', NULL, 'RJ45', '10', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('11', '1', '1', NULL, 'RJ45', '11', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('12', '1', '1', NULL, 'RJ45', '12', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('13', '1', '1', NULL, 'RJ45', '13', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('14', '1', '1', NULL, 'RJ45', '14', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('15', '1', '1', NULL, 'RJ45', '15', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('16', '1', '1', NULL, 'RJ45', '16', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('17', '1', '1', NULL, 'RJ45', '17', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('18', '1', '1', NULL, 'RJ45', '18', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('19', '1', '1', NULL, 'RJ45', '19', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('20', '1', '1', NULL, 'RJ45', '20', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('21', '1', '1', NULL, 'RJ45', '21', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('22', '1', '1', NULL, 'RJ45', '22', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('23', '1', '1', NULL, 'RJ45', '23', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('24', '1', '1', NULL, 'RJ45', '24', '0', '17', '1', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('25', '2', '2', NULL, 'RJ45', '1', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('26', '2', '2', NULL, 'RJ45', '2', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('27', '2', '2', NULL, 'RJ45', '3', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('28', '2', '2', NULL, 'RJ45', '4', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('29', '2', '2', NULL, 'RJ45', '5', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('30', '2', '2', NULL, 'RJ45', '6', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('31', '2', '2', NULL, 'RJ45', '7', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('32', '2', '2', NULL, 'RJ45', '8', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('33', '2', '2', NULL, 'RJ45', '9', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('34', '2', '2', NULL, 'RJ45', '10', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('35', '2', '2', NULL, 'RJ45', '11', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('36', '2', '2', NULL, 'RJ45', '12', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('37', '2', '2', NULL, 'RJ45', '13', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('38', '2', '2', NULL, 'RJ45', '14', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('39', '2', '2', NULL, 'RJ45', '15', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('40', '2', '2', NULL, 'RJ45', '16', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('41', '2', '2', NULL, 'RJ45', '17', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('42', '2', '2', NULL, 'RJ45', '18', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('43', '2', '2', NULL, 'RJ45', '19', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('44', '2', '2', NULL, 'RJ45', '20', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('45', '2', '2', NULL, 'RJ45', '21', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('46', '2', '2', NULL, 'RJ45', '22', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('47', '2', '2', NULL, 'RJ45', '23', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('48', '2', '2', NULL, 'RJ45', '24', '0', '26', '11', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('49', '3', '3', NULL, 'RJ45', '1', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('50', '3', '3', NULL, 'RJ45', '2', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('51', '3', '3', NULL, 'RJ45', '3', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('52', '3', '3', NULL, 'RJ45', '4', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('53', '3', '3', NULL, 'RJ45', '5', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('54', '3', '3', NULL, 'RJ45', '6', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('55', '3', '3', NULL, 'RJ45', '7', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('56', '3', '3', NULL, 'RJ45', '8', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('57', '3', '3', NULL, 'RJ45', '9', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('58', '3', '3', NULL, 'RJ45', '10', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('59', '3', '3', NULL, 'RJ45', '11', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('60', '3', '3', NULL, 'RJ45', '12', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('61', '3', '3', NULL, 'RJ45', '13', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('62', '3', '3', NULL, 'RJ45', '14', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('63', '3', '3', NULL, 'RJ45', '15', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('64', '3', '3', NULL, 'RJ45', '16', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('65', '3', '3', NULL, 'RJ45', '17', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('66', '3', '3', NULL, 'RJ45', '18', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('67', '3', '3', NULL, 'RJ45', '19', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('68', '3', '3', NULL, 'RJ45', '20', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('69', '3', '3', NULL, 'RJ45', '21', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('70', '3', '3', NULL, 'RJ45', '22', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('71', '3', '3', NULL, 'RJ45', '23', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('72', '3', '3', NULL, 'RJ45', '24', '0', '35', '21', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('73', '4', '4', NULL, 'RJ45', '1', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('74', '4', '4', NULL, 'RJ45', '2', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('75', '4', '4', NULL, 'RJ45', '3', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('76', '4', '4', NULL, 'RJ45', '4', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('77', '4', '4', NULL, 'RJ45', '5', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('78', '4', '4', NULL, 'RJ45', '6', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('79', '4', '4', NULL, 'RJ45', '7', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('80', '4', '4', NULL, 'RJ45', '8', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('81', '4', '4', NULL, 'RJ45', '9', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('82', '4', '4', NULL, 'RJ45', '10', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('83', '4', '4', NULL, 'RJ45', '11', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('84', '4', '4', NULL, 'RJ45', '12', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('85', '4', '4', NULL, 'RJ45', '13', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('86', '4', '4', NULL, 'RJ45', '14', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('87', '4', '4', NULL, 'RJ45', '15', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('88', '4', '4', NULL, 'RJ45', '16', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('89', '4', '4', NULL, 'RJ45', '17', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('90', '4', '4', NULL, 'RJ45', '18', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('91', '4', '4', NULL, 'RJ45', '19', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('92', '4', '4', NULL, 'RJ45', '20', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('93', '4', '4', NULL, 'RJ45', '21', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('94', '4', '4', NULL, 'RJ45', '22', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('95', '4', '4', NULL, 'RJ45', '23', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('96', '4', '4', NULL, 'RJ45', '24', '0', '5', '31', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('97', '5', '5', NULL, 'RJ45', '1', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('98', '5', '5', NULL, 'RJ45', '2', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('99', '5', '5', NULL, 'RJ45', '3', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('100', '5', '5', NULL, 'RJ45', '4', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('101', '5', '5', NULL, 'RJ45', '5', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('102', '5', '5', NULL, 'RJ45', '6', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('103', '5', '5', NULL, 'RJ45', '7', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('104', '5', '5', NULL, 'RJ45', '8', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('105', '5', '5', NULL, 'RJ45', '9', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('106', '5', '5', NULL, 'RJ45', '10', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('107', '5', '5', NULL, 'RJ45', '11', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('108', '5', '5', NULL, 'RJ45', '12', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('109', '5', '5', NULL, 'RJ45', '13', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('110', '5', '5', NULL, 'RJ45', '14', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('111', '5', '5', NULL, 'RJ45', '15', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('112', '5', '5', NULL, 'RJ45', '16', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('113', '5', '5', NULL, 'RJ45', '17', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('114', '5', '5', NULL, 'RJ45', '18', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('115', '5', '5', NULL, 'RJ45', '19', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('116', '5', '5', NULL, 'RJ45', '20', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('117', '5', '5', NULL, 'RJ45', '21', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('118', '5', '5', NULL, 'RJ45', '22', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('119', '5', '5', NULL, 'RJ45', '23', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');
INSERT INTO `switch_ports` (`id`, `company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `label`, `status_id`, `color_id`, `vlan_id`, `comments`, `updated_at`, `created_at`) VALUES ('120', '5', '5', NULL, 'RJ45', '24', '0', '44', '41', NULL, '', '2026-03-31 00:39:19', '2026-04-26 15:04:51');

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
  CONSTRAINT `switch_status_ibfk_color` FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `switch_status_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `switch_status`
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('1', '4', 'Up', '6', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('2', '4', 'Down', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('3', '4', 'Free', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('4', '4', 'Disabled', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('5', '4', 'Unknown', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('6', '4', 'Err-Disabled', '9', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('7', '4', 'Testing', '6', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('8', '4', 'Faulty', '8', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('9', '4', 'Reserved', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('10', '1', 'Disabled', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('11', '1', 'Down', '3', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('12', '1', 'Err-Disabled', '9', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('13', '1', 'Faulty', '8', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('14', '1', 'Free', '2', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('15', '1', 'Reserved', '4', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('16', '1', 'Testing', '6', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('17', '1', 'Unknown', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('18', '1', 'Up', '6', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('19', '2', 'Disabled', '11', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('20', '2', 'Down', '13', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('21', '2', 'Err-Disabled', '19', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('22', '2', 'Faulty', '18', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('23', '2', 'Free', '12', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('24', '2', 'Reserved', '14', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('25', '2', 'Testing', '16', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('26', '2', 'Unknown', '11', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('27', '2', 'Up', '16', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('28', '3', 'Disabled', '21', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('29', '3', 'Down', '23', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('30', '3', 'Err-Disabled', '29', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('31', '3', 'Faulty', '28', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('32', '3', 'Free', '22', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('33', '3', 'Reserved', '24', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('34', '3', 'Testing', '26', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('35', '3', 'Unknown', '21', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('36', '3', 'Up', '26', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('37', '5', 'Disabled', '41', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('38', '5', 'Down', '43', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('39', '5', 'Err-Disabled', '49', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('40', '5', 'Faulty', '48', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('41', '5', 'Free', '42', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('42', '5', 'Reserved', '44', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('43', '5', 'Testing', '46', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('44', '5', 'Unknown', '41', '2026-04-26 15:04:52', NULL);
INSERT INTO `switch_status` (`id`, `company_id`, `status`, `color_id`, `created_at`, `updated_at`) VALUES ('45', '5', 'Up', '46', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `fk_system_access_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `system_access`
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'network_access', 'Network Access', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'micros_emc', 'Micros Emc', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'opera_username', 'Opera Username', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'micros_card', 'Micros Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'pms_id', 'PMS Id', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('6', '1', 'synergy_mms', 'Synergy Mms', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('7', '1', 'hu_the_lobby', 'HU The Lobby', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('8', '1', 'navision', 'Navision', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('9', '1', 'onq_ri', 'Onq Ri', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('10', '1', 'birchstreet', 'Birchstreet', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('11', '1', 'delphi', 'Delphi', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('12', '1', 'omina', 'Omina', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('13', '1', 'vingcard_system', 'Vingcard System', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('14', '1', 'digital_rev', 'Digital Rev', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('15', '1', 'office_key_card', 'Office Key Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('16', '2', 'network_access', 'Network Access', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('17', '2', 'micros_emc', 'Micros Emc', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('18', '2', 'opera_username', 'Opera Username', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('19', '2', 'micros_card', 'Micros Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('20', '2', 'pms_id', 'PMS Id', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('21', '2', 'synergy_mms', 'Synergy Mms', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('22', '2', 'hu_the_lobby', 'HU The Lobby', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('23', '2', 'navision', 'Navision', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('24', '2', 'onq_ri', 'Onq Ri', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('25', '2', 'birchstreet', 'Birchstreet', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('26', '2', 'delphi', 'Delphi', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('27', '2', 'omina', 'Omina', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('28', '2', 'vingcard_system', 'Vingcard System', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('29', '2', 'digital_rev', 'Digital Rev', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('30', '2', 'office_key_card', 'Office Key Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('31', '3', 'network_access', 'Network Access', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('32', '3', 'micros_emc', 'Micros Emc', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('33', '3', 'opera_username', 'Opera Username', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('34', '3', 'micros_card', 'Micros Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('35', '3', 'pms_id', 'PMS Id', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('36', '3', 'synergy_mms', 'Synergy Mms', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('37', '3', 'hu_the_lobby', 'HU The Lobby', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('38', '3', 'navision', 'Navision', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('39', '3', 'onq_ri', 'Onq Ri', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('40', '3', 'birchstreet', 'Birchstreet', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('41', '3', 'delphi', 'Delphi', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('42', '3', 'omina', 'Omina', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('43', '3', 'vingcard_system', 'Vingcard System', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('44', '3', 'digital_rev', 'Digital Rev', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('45', '3', 'office_key_card', 'Office Key Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('46', '4', 'network_access', 'Network Access', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('47', '4', 'micros_emc', 'Micros Emc', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('48', '4', 'opera_username', 'Opera Username', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('49', '4', 'micros_card', 'Micros Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('50', '4', 'pms_id', 'PMS Id', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('51', '4', 'synergy_mms', 'Synergy Mms', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('52', '4', 'hu_the_lobby', 'HU The Lobby', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('53', '4', 'navision', 'Navision', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('54', '4', 'onq_ri', 'Onq Ri', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('55', '4', 'birchstreet', 'Birchstreet', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('56', '4', 'delphi', 'Delphi', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('57', '4', 'omina', 'Omina', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('58', '4', 'vingcard_system', 'Vingcard System', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('59', '4', 'digital_rev', 'Digital Rev', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('60', '4', 'office_key_card', 'Office Key Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('61', '5', 'network_access', 'Network Access', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('62', '5', 'micros_emc', 'Micros Emc', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('63', '5', 'opera_username', 'Opera Username', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('64', '5', 'micros_card', 'Micros Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('65', '5', 'pms_id', 'PMS Id', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('66', '5', 'synergy_mms', 'Synergy Mms', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('67', '5', 'hu_the_lobby', 'HU The Lobby', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('68', '5', 'navision', 'Navision', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('69', '5', 'onq_ri', 'Onq Ri', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('70', '5', 'birchstreet', 'Birchstreet', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('71', '5', 'delphi', 'Delphi', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('72', '5', 'omina', 'Omina', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('73', '5', 'vingcard_system', 'Vingcard System', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('74', '5', 'digital_rev', 'Digital Rev', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('75', '5', 'office_key_card', 'Office Key Card', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('76', '1', 'email_account', 'Email Account', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('77', '2', 'email_account', 'Email Account', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('78', '3', 'email_account', 'Email Account', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('79', '4', 'email_account', 'Email Account', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('80', '5', 'email_account', 'Email Account', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('81', '1', 'landline_phone', 'Landline Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('82', '2', 'landline_phone', 'Landline Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('83', '3', 'landline_phone', 'Landline Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('84', '4', 'landline_phone', 'Landline Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('85', '5', 'landline_phone', 'Landline Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('86', '1', 'mobile_phone', 'Mobile Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('87', '2', 'mobile_phone', 'Mobile Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('88', '3', 'mobile_phone', 'Mobile Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('89', '4', 'mobile_phone', 'Mobile Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('90', '5', 'mobile_phone', 'Mobile Phone', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('91', '1', 'mobile_email', 'Mobile Email', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('92', '2', 'mobile_email', 'Mobile Email', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('93', '3', 'mobile_email', 'Mobile Email', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('94', '4', 'mobile_email', 'Mobile Email', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`, `updated_at`) VALUES ('95', '5', 'mobile_email', 'Mobile Email', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `ticket_categories_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_categories`
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Hardware Issue', 'HW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Network Problem', 'NET', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Software Issue', 'SW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Maintenance', 'MAINT', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Other', 'OTHER', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Hardware Issue', 'HW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Network Problem', 'NET', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Software Issue', 'SW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'Maintenance', 'MAINT', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Other', 'OTHER', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'Hardware Issue', 'HW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Network Problem', 'NET', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'Software Issue', 'SW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'Maintenance', 'MAINT', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'Other', 'OTHER', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'Hardware Issue', 'HW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'Network Problem', 'NET', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'Software Issue', 'SW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'Maintenance', 'MAINT', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'Other', 'OTHER', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'Hardware Issue', 'HW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'Network Problem', 'NET', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'Software Issue', 'SW', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'Maintenance', 'MAINT', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_categories` (`id`, `company_id`, `name`, `code`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'Other', 'OTHER', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `ticket_priorities_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_priorities`
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Low', '1', '#0000FF', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Normal', '2', '#00FF00', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'High', '3', '#FFA500', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Urgent', '4', '#FF0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Critical', '5', '#8B0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Low', '1', '#0000FF', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Normal', '2', '#00FF00', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'High', '3', '#FFA500', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'Urgent', '4', '#FF0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'Critical', '5', '#8B0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'Low', '1', '#0000FF', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Normal', '2', '#00FF00', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'High', '3', '#FFA500', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'Urgent', '4', '#FF0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'Critical', '5', '#8B0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'Low', '1', '#0000FF', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'Normal', '2', '#00FF00', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'High', '3', '#FFA500', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'Urgent', '4', '#FF0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'Critical', '5', '#8B0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'Low', '1', '#0000FF', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'Normal', '2', '#00FF00', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'High', '3', '#FFA500', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'Urgent', '4', '#FF0000', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_priorities` (`id`, `company_id`, `name`, `level`, `color`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'Critical', '5', '#8B0000', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `ticket_statuses_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `ticket_statuses`
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('5', '2', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('9', '3', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('10', '3', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('13', '4', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('14', '4', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('15', '4', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('17', '5', 'Open', '#FF0000', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('18', '5', 'In Progress', '#FFA500', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('19', '5', 'Resolved', '#00FF00', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `ticket_statuses` (`id`, `company_id`, `name`, `color`, `is_closed`, `active`, `created_at`, `updated_at`) VALUES ('20', '5', 'Closed', '#808080', '1', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `tickets_ibfk_7` FOREIGN KEY (`asset_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `tickets`
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`, `updated_at`) VALUES ('1', '1', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '4', '1', '2', '1', '1', '1', '#0969da', NULL, '2026-03-28 19:43:17', NULL);
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`, `updated_at`) VALUES ('2', '2', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '9', '5', '7', '1', '1', '2', '#0969da', NULL, '2026-03-28 19:43:17', NULL);
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`, `updated_at`) VALUES ('3', '3', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '14', '9', '12', '1', '1', '3', '#0969da', NULL, '2026-03-28 19:43:17', NULL);
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`, `updated_at`) VALUES ('4', '4', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '19', '13', '17', '1', '1', '4', '#0969da', NULL, '2026-03-28 19:43:17', NULL);
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_user_id`, `assigned_to_user_id`, `asset_id`, `ui_color`, `tickets_photos`, `created_at`, `updated_at`) VALUES ('5', '5', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '24', '17', '22', '1', '1', '5', '#0969da', NULL, '2026-03-28 19:43:17', NULL);

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
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'left', 'left', 'left', 'left', '1', '1', '25', '⚙️ IT Controls', 'images/favicons/company_1.ico', '{\"is_access_point\":1,\"is_cctv\":1,\"is_firewall\":1,\"is_other\":1,\"is_phone\":1,\"is_port_patch_panel\":1,\"is_pos\":1,\"is_printer\":1,\"is_router\":1,\"is_server\":1,\"is_switch\":1,\"is_workstation\":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES ('2', '2', '1', 'left', 'left', 'left', 'left', '1', '1', '25', '⚙️ IT Controls', 'images/favicons/company_2.ico', '{\"is_access_point\":1,\"is_cctv\":1,\"is_firewall\":1,\"is_other\":1,\"is_phone\":1,\"is_port_patch_panel\":1,\"is_pos\":1,\"is_printer\":1,\"is_router\":1,\"is_server\":1,\"is_switch\":1,\"is_workstation\":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES ('3', '3', '1', 'left', 'left', 'left', 'left', '1', '1', '25', '⚙️ IT Controls', 'images/favicons/company_3.ico', '{\"is_access_point\":1,\"is_cctv\":1,\"is_firewall\":1,\"is_other\":1,\"is_phone\":1,\"is_port_patch_panel\":1,\"is_pos\":1,\"is_printer\":1,\"is_router\":1,\"is_server\":1,\"is_switch\":1,\"is_workstation\":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES ('4', '4', '1', 'left', 'left', 'left', 'left', '1', '1', '25', '⚙️ IT Controls', 'images/favicons/company_4.ico', '{\"is_access_point\":1,\"is_cctv\":1,\"is_firewall\":1,\"is_other\":1,\"is_phone\":1,\"is_port_patch_panel\":1,\"is_pos\":1,\"is_printer\":1,\"is_router\":1,\"is_server\":1,\"is_switch\":1,\"is_workstation\":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');
INSERT INTO `ui_configuration` (`id`, `company_id`, `user_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`) VALUES ('5', '5', '1', 'left', 'left', 'left', 'left', '1', '1', '25', '⚙️ IT Controls', 'images/favicons/company_5.ico', '{\"is_access_point\":1,\"is_cctv\":1,\"is_firewall\":1,\"is_other\":1,\"is_phone\":1,\"is_port_patch_panel\":1,\"is_pos\":1,\"is_printer\":1,\"is_router\":1,\"is_server\":1,\"is_switch\":1,\"is_workstation\":1}', '2026-03-28 19:43:17', '2026-03-28 19:43:17');

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
  CONSTRAINT `fk_user_companies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_companies_granted_by` FOREIGN KEY (`granted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_companies`
INSERT INTO `user_companies` (`id`, `user_id`, `company_id`, `granted_by_user_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', NULL, '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_companies` (`id`, `user_id`, `company_id`, `granted_by_user_id`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', '2', NULL, '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_companies` (`id`, `user_id`, `company_id`, `granted_by_user_id`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', '3', NULL, '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_companies` (`id`, `user_id`, `company_id`, `granted_by_user_id`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', '4', NULL, '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_companies` (`id`, `user_id`, `company_id`, `granted_by_user_id`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', '5', NULL, '1', '2026-04-26 15:04:52', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `user_roles`
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Admin', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'IT Manager', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'IT Assistant', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'Helpdesk', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'User', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('6', '2', 'Admin', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('7', '2', 'Helpdesk', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('8', '2', 'IT Assistant', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('9', '2', 'IT Manager', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('10', '2', 'User', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('11', '3', 'Admin', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('12', '3', 'Helpdesk', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('13', '3', 'IT Assistant', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('14', '3', 'IT Manager', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('15', '3', 'User', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('16', '4', 'Admin', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('17', '4', 'Helpdesk', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('18', '4', 'IT Assistant', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('19', '4', 'IT Manager', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('20', '4', 'User', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('21', '5', 'Admin', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('22', '5', 'Helpdesk', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('23', '5', 'IT Assistant', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('24', '5', 'IT Manager', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `user_roles` (`id`, `company_id`, `name`, `active`, `created_at`, `updated_at`) VALUES ('25', '5', 'User', '1', '2026-04-26 15:04:52', NULL);

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
INSERT INTO `users` (`id`, `company_id`, `username`, `email`, `password`, `reset_token`, `reset_token_hash`, `reset_token_expires_at`, `first_name`, `last_name`, `phone`, `role_id`, `access_level_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'admin', 'admin@techcorp.example', '$2y$12$r6nU8WO3jAsWGvJYIFdIAOOAPDRmBQfEpltxD5UoIwTx3k.K2KPIO', NULL, NULL, NULL, 'System', 'Admin', NULL, '1', '1', '1', '2026-03-28 19:43:17', NULL);

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
  CONSTRAINT `vlans_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `vlans`
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `warranty_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `warranty_types`
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Standard', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Extended', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Premium', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Enterprise', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'None', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', 'Enterprise', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', 'Extended', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '2', 'None', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '2', 'Premium', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '2', 'Standard', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '3', 'Enterprise', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '3', 'Extended', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '3', 'None', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '3', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '3', 'Premium', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '3', 'Standard', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '4', 'Enterprise', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '4', 'Extended', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '4', 'None', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '4', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '4', 'Premium', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '4', 'Standard', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '5', 'Enterprise', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '5', 'Extended', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '5', 'None', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '5', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '5', 'Premium', '2026-04-26 15:04:52', NULL);
INSERT INTO `warranty_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '5', 'Standard', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `workstation_device_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_device_types`
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Desktop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Laptop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'All-in-One', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Tablet', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'Thin-Client', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', 'Mobile', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '1', 'POS', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '1', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '2', 'All-in-One', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', 'Desktop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '2', 'Laptop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '2', 'Mobile', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '2', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '2', 'POS', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '2', 'Tablet', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '2', 'Thin-Client', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '3', 'All-in-One', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '3', 'Desktop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '3', 'Laptop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '3', 'Mobile', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '3', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '3', 'POS', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '3', 'Tablet', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '3', 'Thin-Client', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '4', 'All-in-One', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '4', 'Desktop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '4', 'Laptop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '4', 'Mobile', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '4', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '4', 'POS', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('31', '4', 'Tablet', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('32', '4', 'Thin-Client', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('33', '5', 'All-in-One', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('34', '5', 'Desktop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('35', '5', 'Laptop', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('36', '5', 'Mobile', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('37', '5', 'Other', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('38', '5', 'POS', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('39', '5', 'Tablet', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_device_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('40', '5', 'Thin-Client', '2026-04-26 15:04:52', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_modes`
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('6', '1', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('7', '1', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('8', '1', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('9', '1', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('10', '1', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('11', '1', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('12', '2', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('13', '2', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('14', '2', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('15', '2', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('16', '2', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('17', '2', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('18', '2', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('19', '2', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('20', '2', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('21', '2', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('22', '2', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('23', '3', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('24', '3', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('25', '3', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('26', '3', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('27', '3', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('28', '3', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('29', '3', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('30', '3', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('31', '3', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('32', '3', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('33', '3', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('34', '4', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('35', '4', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('36', '4', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('37', '4', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('38', '4', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('39', '4', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('40', '4', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('41', '4', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('42', '4', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('43', '4', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('44', '4', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('45', '5', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('46', '5', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('47', '5', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('48', '5', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('49', '5', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('50', '5', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('51', '5', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('52', '5', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('53', '5', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('54', '5', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-04-26 15:04:52', NULL);
INSERT INTO `workstation_modes` (`id`, `company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`, `updated_at`) VALUES ('55', '5', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-04-26 15:04:52', NULL);

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
  CONSTRAINT `workstation_office_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_office`
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'None', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Office 2024 STD', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Office 2024 Pro', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Office 365', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', 'None', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', 'Office 2024 Pro', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', 'Office 2024 STD', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', 'Office 365', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', 'None', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '3', 'Office 2024 Pro', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '3', 'Office 2024 STD', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '3', 'Office 365', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '4', 'None', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '4', 'Office 2024 Pro', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '4', 'Office 2024 STD', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '4', 'Office 365', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '5', 'None', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '5', 'Office 2024 Pro', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '5', 'Office 2024 STD', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_office` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '5', 'Office 365', '2026-04-26 15:04:53', NULL);

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
  CONSTRAINT `workstation_os_types_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_types`
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', 'Windows', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', 'Windows 11', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', 'Windows 10', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', 'Windows Server', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', 'Windows Server 2012', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', 'Windows Server 2016', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '1', 'Windows Server 2019', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '1', 'Windows Server 2022', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '1', 'Windows Server 2025', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '1', 'Android', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '1', 'iOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '1', 'ChromeOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '1', 'Linux', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '1', 'macOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '1', 'Other', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '2', 'Windows', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '2', 'Windows 11', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '2', 'Windows 10', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '2', 'Windows Server', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '2', 'Windows Server 2012', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '2', 'Windows Server 2016', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '2', 'Windows Server 2019', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '2', 'Windows Server 2022', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '2', 'Windows Server 2025', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '2', 'Android', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '2', 'iOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '2', 'ChromeOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '2', 'Linux', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '2', 'macOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '2', 'Other', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('31', '3', 'Windows', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('32', '3', 'Windows 11', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('33', '3', 'Windows 10', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('34', '3', 'Windows Server', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('35', '3', 'Windows Server 2012', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('36', '3', 'Windows Server 2016', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('37', '3', 'Windows Server 2019', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('38', '3', 'Windows Server 2022', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('39', '3', 'Windows Server 2025', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('40', '3', 'Android', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('41', '3', 'iOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('42', '3', 'ChromeOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('43', '3', 'Linux', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('44', '3', 'macOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('45', '3', 'Other', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('46', '4', 'Windows', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('47', '4', 'Windows 11', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('48', '4', 'Windows 10', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('49', '4', 'Windows Server', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('50', '4', 'Windows Server 2012', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('51', '4', 'Windows Server 2016', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('52', '4', 'Windows Server 2019', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('53', '4', 'Windows Server 2022', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('54', '4', 'Windows Server 2025', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('55', '4', 'Android', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('56', '4', 'iOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('57', '4', 'ChromeOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('58', '4', 'Linux', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('59', '4', 'macOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('60', '4', 'Other', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('61', '5', 'Windows', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('62', '5', 'Windows 11', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('63', '5', 'Windows 10', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('64', '5', 'Windows Server', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('65', '5', 'Windows Server 2012', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('66', '5', 'Windows Server 2016', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('67', '5', 'Windows Server 2019', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('68', '5', 'Windows Server 2022', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('69', '5', 'Windows Server 2025', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('70', '5', 'Android', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('71', '5', 'iOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('72', '5', 'ChromeOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('73', '5', 'Linux', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('74', '5', 'macOS', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_types` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('75', '5', 'Other', '2026-04-26 15:04:53', NULL);

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
  CONSTRAINT `workstation_os_versions_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_os_versions`
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', '24H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', '25H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', '26H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', '10 LTSC', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '2', '24H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '2', '25H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', '26H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', '10 LTSC', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '3', '24H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '3', '25H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '3', '26H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '3', '10 LTSC', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '4', '24H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '4', '25H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '4', '26H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '4', '10 LTSC', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '5', '24H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '5', '25H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '5', '26H2', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_os_versions` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '5', '10 LTSC', '2026-04-26 15:04:53', NULL);

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
  CONSTRAINT `workstation_ram_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for `workstation_ram`
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('1', '1', '4 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('2', '1', '8 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '1', '16 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('4', '1', '32 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('5', '1', '64 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('6', '1', '128 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('7', '2', '4 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('8', '2', '8 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('9', '2', '16 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('10', '2', '32 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('11', '2', '64 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('12', '2', '128 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('13', '3', '4 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('14', '3', '8 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('15', '3', '16 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('16', '3', '32 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('17', '3', '64 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('18', '3', '128 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('19', '4', '4 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('20', '4', '8 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('21', '4', '16 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('22', '4', '32 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('23', '4', '64 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('24', '4', '128 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('25', '5', '4 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('26', '5', '8 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('27', '5', '16 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('28', '5', '32 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('29', '5', '64 GB', '2026-04-26 15:04:53', NULL);
INSERT INTO `workstation_ram` (`id`, `company_id`, `name`, `created_at`, `updated_at`) VALUES ('30', '5', '128 GB', '2026-04-26 15:04:53', NULL);

SET FOREIGN_KEY_CHECKS=1;
