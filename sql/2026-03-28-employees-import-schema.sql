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
SET @db_name := DATABASE();

-- Employee offboarding / access fields
SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='request_date'),
        'SELECT "request_date already exists"',
        'ALTER TABLE `employees` ADD COLUMN `request_date` DATE NULL AFTER `comments`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='requested_by'),
        'SELECT "requested_by already exists"',
        'ALTER TABLE `employees` ADD COLUMN `requested_by` VARCHAR(150) NULL AFTER `request_date`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='termination_requested_by'),
        'SELECT "termination_requested_by already exists"',
        'ALTER TABLE `employees` ADD COLUMN `termination_requested_by` VARCHAR(150) NULL AFTER `requested_by`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='termination_date'),
        'SELECT "termination_date already exists"',
        'ALTER TABLE `employees` ADD COLUMN `termination_date` DATE NULL AFTER `termination_requested_by`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='mobile_phone'),
        'SELECT "mobile_phone already exists"',
        'ALTER TABLE `employees` ADD COLUMN `mobile_phone` VARCHAR(30) NULL AFTER `phone`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='work_phone'),
        'SELECT "work_phone already exists"',
        'ALTER TABLE `employees` ADD COLUMN `work_phone` VARCHAR(30) NULL AFTER `mobile_phone`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='deck'),
        'SELECT "deck already exists"',
        'ALTER TABLE `employees` ADD COLUMN `deck` VARCHAR(100) NULL AFTER `work_phone`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='extension'),
        'SELECT "extension already exists"',
        'ALTER TABLE `employees` ADD COLUMN `extension` VARCHAR(30) NULL AFTER `deck`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='network_access'),
        'SELECT "network_access already exists"',
        'ALTER TABLE `employees` ADD COLUMN `network_access` TINYINT(1) NOT NULL DEFAULT 0 AFTER `termination_date`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='micros_emc'),
        'SELECT "micros_emc already exists"',
        'ALTER TABLE `employees` ADD COLUMN `micros_emc` TINYINT(1) NOT NULL DEFAULT 0 AFTER `network_access`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='opera_username'),
        'SELECT "opera_username already exists"',
        'ALTER TABLE `employees` ADD COLUMN `opera_username` TINYINT(1) NOT NULL DEFAULT 0 AFTER `micros_emc`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='micros_card'),
        'SELECT "micros_card already exists"',
        'ALTER TABLE `employees` ADD COLUMN `micros_card` TINYINT(1) NOT NULL DEFAULT 0 AFTER `opera_username`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='pms_id'),
        'SELECT "pms_id already exists"',
        'ALTER TABLE `employees` ADD COLUMN `pms_id` TINYINT(1) NOT NULL DEFAULT 0 AFTER `micros_card`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='synergy_mms'),
        'SELECT "synergy_mms already exists"',
        'ALTER TABLE `employees` ADD COLUMN `synergy_mms` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pms_id`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='hu_the_lobby'),
        'SELECT "hu_the_lobby already exists"',
        'ALTER TABLE `employees` ADD COLUMN `hu_the_lobby` TINYINT(1) NOT NULL DEFAULT 0 AFTER `synergy_mms`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='navision'),
        'SELECT "navision already exists"',
        'ALTER TABLE `employees` ADD COLUMN `navision` TINYINT(1) NOT NULL DEFAULT 0 AFTER `hu_the_lobby`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='onq_ri'),
        'SELECT "onq_ri already exists"',
        'ALTER TABLE `employees` ADD COLUMN `onq_ri` TINYINT(1) NOT NULL DEFAULT 0 AFTER `navision`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='birchstreet'),
        'SELECT "birchstreet already exists"',
        'ALTER TABLE `employees` ADD COLUMN `birchstreet` TINYINT(1) NOT NULL DEFAULT 0 AFTER `onq_ri`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='delphi'),
        'SELECT "delphi already exists"',
        'ALTER TABLE `employees` ADD COLUMN `delphi` TINYINT(1) NOT NULL DEFAULT 0 AFTER `birchstreet`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='omina'),
        'SELECT "omina already exists"',
        'ALTER TABLE `employees` ADD COLUMN `omina` TINYINT(1) NOT NULL DEFAULT 0 AFTER `delphi`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='vingcard_system'),
        'SELECT "vingcard_system already exists"',
        'ALTER TABLE `employees` ADD COLUMN `vingcard_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `omina`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='digital_rev'),
        'SELECT "digital_rev already exists"',
        'ALTER TABLE `employees` ADD COLUMN `digital_rev` TINYINT(1) NOT NULL DEFAULT 0 AFTER `vingcard_system`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='office_key_card'),
        'SELECT "office_key_card already exists"',
        'ALTER TABLE `employees` ADD COLUMN `office_key_card` TINYINT(1) NOT NULL DEFAULT 0 AFTER `digital_rev`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND COLUMN_NAME='office_key_card_department_id'),
        'SELECT "office_key_card_department_id already exists"',
        'ALTER TABLE `employees` ADD COLUMN `office_key_card_department_id` INT NULL AFTER `office_key_card`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND INDEX_NAME='idx_employees_office_key_department'),
        'SELECT "idx_employees_office_key_department already exists"',
        'ALTER TABLE `employees` ADD INDEX `idx_employees_office_key_department` (`office_key_card_department_id`)')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(EXISTS(
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='employees' AND CONSTRAINT_NAME='fk_employees_office_key_department'
    ),
        'SELECT "fk_employees_office_key_department already exists"',
        'ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_office_key_department` FOREIGN KEY (`office_key_card_department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
