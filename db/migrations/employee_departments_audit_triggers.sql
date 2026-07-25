-- employee_departments audit triggers (live DBs only)
-- Why: table added in employees_employee_departments.sql; audit triggers live in db/03_triggers.sql for fresh imports.
-- Apply manually after backup when trg_employee_departments_audit_* are missing.

DROP TRIGGER IF EXISTS `trg_employee_departments_audit_insert`;

DROP TRIGGER IF EXISTS `trg_employee_departments_audit_update`;

DROP TRIGGER IF EXISTS `trg_employee_departments_audit_delete`;

DELIMITER $$

CREATE TRIGGER `trg_employee_departments_audit_insert` AFTER INSERT ON `employee_departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_departments', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'department_id', NEW.`department_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_employee_departments_audit_update` AFTER UPDATE ON `employee_departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_departments', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'department_id', OLD.`department_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'department_id', NEW.`department_id`, 'active', NEW.`active`, 'deleted_by', NEW.`deleted_by`, 'deleted_at', NEW.`deleted_at`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`, 'updated_by', NEW.`updated_by`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_employee_departments_audit_delete` AFTER DELETE ON `employee_departments` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_departments', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'department_id', OLD.`department_id`, 'active', OLD.`active`, 'deleted_by', OLD.`deleted_by`, 'deleted_at', OLD.`deleted_at`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`, 'updated_by', OLD.`updated_by`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
