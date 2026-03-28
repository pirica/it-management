-- Employee import schema update
-- Run this script against an existing database before using the Employees import page.

SET @db_name := DATABASE();

-- Add new import-driven columns if they are missing.
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='display_name'
        ),
        'SELECT "display_name already exists"',
        'ALTER TABLE `employees` ADD COLUMN `display_name` VARCHAR(150) NULL AFTER `last_name`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='hilton_id'
        ),
        'SELECT "hilton_id already exists"',
        'ALTER TABLE `employees` ADD COLUMN `hilton_id` VARCHAR(50) NULL AFTER `employee_code`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='username'
        ),
        'SELECT "username already exists"',
        'ALTER TABLE `employees` ADD COLUMN `username` VARCHAR(100) NULL AFTER `hilton_id`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='job_code'
        ),
        'SELECT "job_code already exists"',
        'ALTER TABLE `employees` ADD COLUMN `job_code` VARCHAR(120) NULL AFTER `department_id`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='raw_status_code'
        ),
        'SELECT "raw_status_code already exists"',
        'ALTER TABLE `employees` ADD COLUMN `raw_status_code` VARCHAR(20) NULL AFTER `employment_status_id`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Replace global unique indexes with per-company uniqueness for multi-tenant safety.
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='email'
        ),
        'ALTER TABLE `employees` DROP INDEX `email`',
        'SELECT "email index not present"'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='employee_code'
        ),
        'ALTER TABLE `employees` DROP INDEX `employee_code`',
        'SELECT "employee_code index not present"'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='idx_employees_hilton_id'
        ),
        'SELECT "idx_employees_hilton_id already exists"',
        'ALTER TABLE `employees` ADD INDEX `idx_employees_hilton_id` (`hilton_id`)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='idx_employees_username'
        ),
        'SELECT "idx_employees_username already exists"',
        'ALTER TABLE `employees` ADD INDEX `idx_employees_username` (`username`)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='uq_employees_email_per_company'
        ),
        'SELECT "uq_employees_email_per_company already exists"',
        'ALTER TABLE `employees` ADD UNIQUE KEY `uq_employees_email_per_company` (`company_id`,`email`)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='uq_employees_code_per_company'
        ),
        'SELECT "uq_employees_code_per_company already exists"',
        'ALTER TABLE `employees` ADD UNIQUE KEY `uq_employees_code_per_company` (`company_id`,`employee_code`)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
