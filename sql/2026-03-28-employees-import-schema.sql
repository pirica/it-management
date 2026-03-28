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

-- Create onboarding table for structured HR request data if missing.
CREATE TABLE IF NOT EXISTS `employee_onboarding_requests`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `employee_id` INT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `position_title` VARCHAR(150),
    `department_name` VARCHAR(150),
    `request_date` DATE,
    `termination_date` DATE NULL,
    `network_access` VARCHAR(120),
    `micros_emc` VARCHAR(120),
    `opera` VARCHAR(120),
    `micros_card` VARCHAR(120),
    `pms_id` VARCHAR(120),
    `synergy_mms` VARCHAR(120),
    `email_account` VARCHAR(160),
    `landline_phone` VARCHAR(60),
    `hu_the_lobby` VARCHAR(160),
    `mobile_phone` VARCHAR(80),
    `navision` VARCHAR(120),
    `mobile_email` VARCHAR(160),
    `onq_ri` VARCHAR(120),
    `birchstreet` VARCHAR(120),
    `delphi` VARCHAR(120),
    `omina` VARCHAR(120),
    `vingcard_system` VARCHAR(120),
    `digital_rev` VARCHAR(120),
    `office_key_card` VARCHAR(150),
    `comments` TEXT,
    `starting_date` DATE NULL,
    `requested_by` VARCHAR(150),
    `requested_on` DATE NULL,
    `hod_approval` VARCHAR(150),
    `hrd_approval` VARCHAR(150),
    `ism_approval` VARCHAR(150),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
    INDEX(`company_id`),
    INDEX(`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Ensure essential departments for incoming hotel data exist.
INSERT INTO `departments` (`company_id`,`name`,`code`,`description`,`manager_user_id`,`location_id`,`active`)
SELECT 1,'Food and Drinks','FNB','Food and Beverages department',1,1,1
WHERE NOT EXISTS (SELECT 1 FROM `departments` WHERE `company_id`=1 AND `name`='Food and Drinks');

INSERT INTO `departments` (`company_id`,`name`,`code`,`description`,`manager_user_id`,`location_id`,`active`)
SELECT 1,'Human Resources','HR','Human resources department',1,1,1
WHERE NOT EXISTS (SELECT 1 FROM `departments` WHERE `company_id`=1 AND `name`='Human Resources');

INSERT INTO `departments` (`company_id`,`name`,`code`,`description`,`manager_user_id`,`location_id`,`active`)
SELECT 1,'Housekeeping','HK','Housekeeping operations',1,1,1
WHERE NOT EXISTS (SELECT 1 FROM `departments` WHERE `company_id`=1 AND `name`='Housekeeping');

-- Seed/update employees shared in onboarding files.
INSERT INTO `employees` (`company_id`,`first_name`,`last_name`,`display_name`,`email`,`employee_code`,`hilton_id`,`username`,`department_id`,`job_code`,`job_title`,`location_id`,`active`,`employment_status_id`,`raw_status_code`)
SELECT
    1,'Marcelo','Batista','Marcelo Batista','marcelo.costeira@icloud.com',NULL,'2295111','Marcelo Batista',
    (SELECT id FROM departments WHERE company_id=1 AND name='Housekeeping' LIMIT 1),
    'Room Attendant','Housekeeping Public Area Attendant',1,0,2,'I'
WHERE NOT EXISTS (
    SELECT 1 FROM employees WHERE company_id=1 AND (email='marcelo.costeira@icloud.com' OR hilton_id='2295111')
);

INSERT INTO `employees` (`company_id`,`first_name`,`last_name`,`display_name`,`email`,`employee_code`,`hilton_id`,`username`,`department_id`,`job_code`,`job_title`,`location_id`,`active`,`employment_status_id`,`raw_status_code`)
SELECT
    1,'Rafaela','Cruz','Rafaela Cruz','cruzrafaela86@gmail.com','A02F0122','A02F0122','A02F0122',
    (SELECT id FROM departments WHERE company_id=1 AND name='Human Resources' LIMIT 1),
    'HR Co-Ordinator','HR Trainee',1,1,1,'A'
WHERE NOT EXISTS (
    SELECT 1 FROM employees WHERE company_id=1 AND (email='cruzrafaela86@gmail.com' OR employee_code='A02F0122' OR hilton_id='A02F0122')
);

INSERT INTO `employees` (`company_id`,`first_name`,`last_name`,`display_name`,`email`,`employee_code`,`hilton_id`,`username`,`department_id`,`job_code`,`job_title`,`location_id`,`active`,`employment_status_id`,`raw_status_code`)
SELECT
    1,'Nicky','Schouten','NICKY SCHOUTEN','302325432@student.rocmondriaan.nl',NULL,NULL,NULL,
    (SELECT id FROM departments WHERE company_id=1 AND name='Food and Drinks' LIMIT 1),
    'TRAINEE','TRAINEE',1,1,1,'A'
WHERE NOT EXISTS (
    SELECT 1 FROM employees WHERE company_id=1 AND (email='302325432@student.rocmondriaan.nl' OR display_name='NICKY SCHOUTEN')
);

-- Store provided onboarding request details for Nicky Schouten.
INSERT INTO `employee_onboarding_requests` (`company_id`,`employee_id`,`first_name`,`last_name`,`position_title`,`department_name`,`request_date`,`termination_date`,`network_access`,`micros_emc`,`opera`,`micros_card`,`pms_id`,`synergy_mms`,`email_account`,`landline_phone`,`hu_the_lobby`,`mobile_phone`,`navision`,`mobile_email`,`onq_ri`,`birchstreet`,`delphi`,`omina`,`vingcard_system`,`digital_rev`,`office_key_card`,`comments`,`starting_date`,`requested_by`,`requested_on`,`hod_approval`,`hrd_approval`,`ism_approval`)
SELECT
    1,
    (SELECT id FROM employees WHERE company_id=1 AND email='302325432@student.rocmondriaan.nl' LIMIT 1),
    'NICKY','SCHOUTEN','TRAINEE','FOOD AND DRINKS','2026-03-24',NULL,'N/A','N/A','N/A','Waiter','N/A','N/A','N/A','N/A',
    'NICKY SCHOUTEN','N/A','N/A','N/A','N/A','N/A','N/A','Via HR','N/A','N/A','Room Service',
    'Starting date: 16/03/2026 || 302325432@student.rocmondriaan.nl','2026-03-16','ALEXANDRANUNES','2026-03-24',
    'Sonia Costa','Pedro Mendes','Kenneth Starreveld'
WHERE NOT EXISTS (
    SELECT 1 FROM employee_onboarding_requests
    WHERE company_id=1 AND first_name='NICKY' AND last_name='SCHOUTEN' AND request_date='2026-03-24'
);

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
