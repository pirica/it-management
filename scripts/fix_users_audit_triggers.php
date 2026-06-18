<?php
/**
 * Fix: Users Audit Triggers
 *
 * Updates the database triggers for the 'users' table to prevent
 * recording sensitive fields (like plaintext passwords) in the audit logs.
 */

if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    die("This script can only be run from the CLI.\n");
}

echo "--- Fixing Users Audit Triggers ---\n";

$queries = [
    "DROP TRIGGER IF EXISTS `trg_users_audit_insert` ;",
    "DROP TRIGGER IF EXISTS `trg_users_audit_update` ;",
    "DROP TRIGGER IF EXISTS `trg_users_audit_delete` ;",

    "CREATE TRIGGER `trg_users_audit_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
      INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
      VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'username', NEW.`username`, 'email', NEW.`email`, 'reset_token_expires_at', NEW.`reset_token_expires_at`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'phone', NEW.`phone`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
    END",

    "CREATE TRIGGER `trg_users_audit_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
      INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
      VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'username', OLD.`username`, 'email', OLD.`email`, 'reset_token_expires_at', OLD.`reset_token_expires_at`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'phone', OLD.`phone`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'username', NEW.`username`, 'email', NEW.`email`, 'reset_token_expires_at', NEW.`reset_token_expires_at`, 'first_name', NEW.`first_name`, 'last_name', NEW.`last_name`, 'phone', NEW.`phone`, 'role_id', NEW.`role_id`, 'access_level_id', NEW.`access_level_id`, 'active', NEW.`active`, 'created_at', NEW.`created_at`), @app_ip_address, @app_user_agent);
    END",

    "CREATE TRIGGER `trg_users_audit_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
      INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
      VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_user_id, @app_username, @app_email, 'users', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'username', OLD.`username`, 'email', OLD.`email`, 'reset_token_expires_at', OLD.`reset_token_expires_at`, 'first_name', OLD.`first_name`, 'last_name', OLD.`last_name`, 'phone', OLD.`phone`, 'role_id', OLD.`role_id`, 'access_level_id', OLD.`access_level_id`, 'active', OLD.`active`, 'created_at', OLD.`created_at`), NULL, @app_ip_address, @app_user_agent);
    END"
];

foreach ($queries as $sql) {
    if (!mysqli_query($conn, $sql)) {
        echo "Error executing query: " . mysqli_error($conn) . "\n";
        exit(1);
    }
}

echo "Successfully updated users audit triggers. Sensitive fields are now excluded.\n";
