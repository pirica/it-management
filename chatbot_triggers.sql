DELIMITER $$

DROP TRIGGER IF EXISTS `trg_knowledge_base_audit_insert`$$
CREATE TRIGGER `trg_knowledge_base_audit_insert` AFTER INSERT ON `knowledge_base` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'knowledge_base', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'category', NEW.`category`, 'title', NEW.`title`, 'content', NEW.`content`, 'active', NEW.`active`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_knowledge_base_audit_update`$$
CREATE TRIGGER `trg_knowledge_base_audit_update` AFTER UPDATE ON `knowledge_base` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'knowledge_base', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'category', OLD.`category`, 'title', OLD.`title`, 'content', OLD.`content`, 'active', OLD.`active`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'category', NEW.`category`, 'title', NEW.`title`, 'content', NEW.`content`, 'active', NEW.`active`, 'created_by', NEW.`created_by`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_knowledge_base_audit_delete`$$
CREATE TRIGGER `trg_knowledge_base_audit_delete` AFTER DELETE ON `knowledge_base` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'knowledge_base', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'category', OLD.`category`, 'title', OLD.`title`, 'content', OLD.`content`, 'active', OLD.`active`, 'created_by', OLD.`created_by`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_it_settings_audit_insert`$$
CREATE TRIGGER `trg_it_settings_audit_insert` AFTER INSERT ON `it_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_settings', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'contact_email', NEW.`contact_email`, 'contact_phone', NEW.`contact_phone`, 'hours_of_operation', NEW.`hours_of_operation`, 'escalation_procedure', NEW.`escalation_procedure`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_it_settings_audit_update`$$
CREATE TRIGGER `trg_it_settings_audit_update` AFTER UPDATE ON `it_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_settings', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'contact_email', OLD.`contact_email`, 'contact_phone', OLD.`contact_phone`, 'hours_of_operation', OLD.`hours_of_operation`, 'escalation_procedure', OLD.`escalation_procedure`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'contact_email', NEW.`contact_email`, 'contact_phone', NEW.`contact_phone`, 'hours_of_operation', NEW.`hours_of_operation`, 'escalation_procedure', NEW.`escalation_procedure`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
END$$

DROP TRIGGER IF EXISTS `trg_it_settings_audit_delete`$$
CREATE TRIGGER `trg_it_settings_audit_delete` AFTER DELETE ON `it_settings` FOR EACH ROW BEGIN
  INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
  VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'it_settings', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'contact_email', OLD.`contact_email`, 'contact_phone', OLD.`contact_phone`, 'hours_of_operation', OLD.`hours_of_operation`, 'escalation_procedure', OLD.`escalation_procedure`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
END$$

DELIMITER ;
