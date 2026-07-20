-- IT Management SQL Backup
-- Audit triggers. Import after 02_data.sql (single MySQL session with 01 + 02).

USE `itmanagement`;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

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

-- Private events (empty shared_with_json): title/description/location encrypted at rest; title_hash = SHA-256(plaintext title). Shared events keep plaintext for recipients.
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

        INSERT INTO bookmarks (company_id, employee_id, title, url, url_hash, shared, active)
        SELECT 
            NEW.company_id,
            NEW.id,
            b.title,
            b.url,
            SHA2(b.url, 256),
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
              AND bk.url_hash = SHA2(b.url, 256)
        );

    ELSEIF EXISTS (
        SELECT 1 
        FROM employee_roles ur
        WHERE ur.id = NEW.role_id
          AND ur.company_id = NEW.company_id
          AND LOWER(ur.name) = 'admin'
    ) THEN

        INSERT INTO bookmarks (company_id, employee_id, title, url, url_hash, shared, active)
        SELECT 
            NEW.company_id,
            NEW.id,
            b.title,
            b.url,
            SHA2(b.url, 256),
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
              AND bk.url_hash = SHA2(b.url, 256)
        );

    END IF;
END$$

DELIMITER ;

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

SET FOREIGN_KEY_CHECKS=1;
