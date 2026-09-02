-- employees.must_change_password — force password rotation after seed/demo login (ITM-PENTEST-004 mitigation).
-- Apply on existing DBs: php scripts/migrate.php --apply
-- Additive column on employees (parent table — cannot DROP/recreate safely).

SET @emp_has_must_change_password := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'must_change_password'
);
SET @emp_ddl := IF(@emp_has_must_change_password = 0,
    'ALTER TABLE `employees` ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0 AFTER `password`',
    'SELECT 1');
PREPARE emp_stmt FROM @emp_ddl;
EXECUTE emp_stmt;
DEALLOCATE PREPARE emp_stmt;

-- Why: Flag seed Admin/demo accounts on upgraded databases (fresh imports use db/02_data.sql UPDATE).
UPDATE `employees`
SET `must_change_password` = 1
WHERE `username` IN ('Admin', 'Admin2', 'Admin3', 'Admin4', 'Admin5', 'demo1', 'demo2', 'demo3', 'demo4', 'demo5')
  AND `deleted_at` IS NULL;
