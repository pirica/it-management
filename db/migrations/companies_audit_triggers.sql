-- companies audit trigger company_id fallback (live DBs only)
-- Why: COALESCE(@app_company_id, 0) wrote audit_logs.company_id = 0 on INSERT when session vars were unset.
-- Apply manually after backup; mirrors db/03_triggers.sql trg_companies_audit_* blocks.

DROP TRIGGER IF EXISTS `trg_companies_audit_insert`;

DROP TRIGGER IF EXISTS `trg_companies_audit_update`;

DROP TRIGGER IF EXISTS `trg_companies_audit_delete`;

DELIMITER $$

CREATE TRIGGER `trg_companies_audit_insert` AFTER INSERT ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`id`, 0), @app_employee_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'unit_no', NEW.`unit_no`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_companies_audit_update` AFTER UPDATE ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`id`, OLD.`id`, 0), @app_employee_id, @app_username, @app_email, 'companies', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company', OLD.`company`, 'incode', OLD.`incode`, 'city', OLD.`city`, 'country', OLD.`country`, 'phone', OLD.`phone`, 'email', OLD.`email`, 'website', OLD.`website`, 'vat', OLD.`vat`, 'unit_no', OLD.`unit_no`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company', NEW.`company`, 'incode', NEW.`incode`, 'city', NEW.`city`, 'country', NEW.`country`, 'phone', NEW.`phone`, 'email', NEW.`email`, 'website', NEW.`website`, 'vat', NEW.`vat`, 'unit_no', NEW.`unit_no`, 'comments', NEW.`comments`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
END$$

CREATE TRIGGER `trg_companies_audit_delete` AFTER DELETE ON `companies` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`id`, 0), @app_employee_id, @app_username, @app_email, 'companies', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company', OLD.`company`, 'incode', OLD.`incode`, 'city', OLD.`city`, 'country', OLD.`country`, 'phone', OLD.`phone`, 'email', OLD.`email`, 'website', OLD.`website`, 'vat', OLD.`vat`, 'unit_no', OLD.`unit_no`, 'comments', OLD.`comments`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
