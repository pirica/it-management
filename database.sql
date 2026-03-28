-- Complete IT Management System Database
DROP DATABASE IF EXISTS `itmanagement`;
CREATE DATABASE `itmanagement` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `itmanagement`;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `companies`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `company_code` VARCHAR(50) UNIQUE,
    `industry` VARCHAR(100),
    `website` VARCHAR(255),
    `phone` VARCHAR(20),
    `email` VARCHAR(100),
    `address` VARCHAR(255),
    `city` VARCHAR(100),
    `state` VARCHAR(100),
    `country` VARCHAR(100),
    `postal_code` VARCHAR(20),
    `active` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX(`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `username` VARCHAR(80) NOT NULL,
    `email` VARCHAR(120) UNIQUE,
    `password` VARCHAR(255),
    `first_name` VARCHAR(50),
    `last_name` VARCHAR(50),
    `phone` VARCHAR(20),
    `role_id` INT NOT NULL,
    `access_level_id` INT NOT NULL,
    `active` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX(`company_id`),
    UNIQUE KEY `unique_username_per_company` (`company_id`, `username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `it_locations`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `location_code` VARCHAR(50),
    `address` VARCHAR(255),
    `city` VARCHAR(100),
    `state` VARCHAR(100),
    `country` VARCHAR(100),
    `postal_code` VARCHAR(20),
    `phone` VARCHAR(20),
    `type_id` INT NOT NULL,
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipment_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(50) UNIQUE,
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `manufacturers`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `code` VARCHAR(50) UNIQUE,
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `supplier_code` VARCHAR(50) UNIQUE,
    `contact_person` VARCHAR(100),
    `email` VARCHAR(100),
    `phone` VARCHAR(20),
    `status_id` INT NOT NULL,
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vlans`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `vlan_number` INT,
    `vlan_name` VARCHAR(100) NOT NULL,
    `cable_color` VARCHAR(50),
    `subnet` VARCHAR(20),
    `gateway_ip` VARCHAR(45),
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `racks`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `rack_code` VARCHAR(50) UNIQUE,
    `status_id` INT NOT NULL,
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`location_id`) REFERENCES `it_locations`(`id`) ON DELETE CASCADE,
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipment`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `equipment_type_id` INT NOT NULL,
    `manufacturer_id` INT,
    `location_id` INT,
    `rack_id` INT,
    `name` VARCHAR(255) NOT NULL,
    `serial_number` VARCHAR(100),
    `model` VARCHAR(100),
    `hostname` VARCHAR(100),
    `ip_address` VARCHAR(45),
    `mac_address` VARCHAR(45),
    `status_id` INT NOT NULL,
    `purchase_date` DATE,
    `purchase_cost` DECIMAL(15, 2),
    `warranty_expiry` DATE,
    `warranty_type_id` INT NOT NULL,
    `is_printer` TINYINT DEFAULT 0,
    `printer_device_type_id` INT,
    `printer_color_capable` TINYINT DEFAULT 0,
    `printer_print_speed_ppm` INT,
    `is_workstation` TINYINT DEFAULT 0,
    `workstation_device_type_id` INT,
    `workstation_os_type_id` INT,
    `workstation_processor` VARCHAR(100),
    `workstation_memory_gb` INT,
    `notes` LONGTEXT,
    `photo_filename` VARCHAR(255),
    `active` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`equipment_type_id`) REFERENCES `equipment_types`(`id`),
    FOREIGN KEY(`manufacturer_id`) REFERENCES `manufacturers`(`id`),
    FOREIGN KEY(`location_id`) REFERENCES `it_locations`(`id`),
    FOREIGN KEY(`rack_id`) REFERENCES `racks`(`id`) ON DELETE SET NULL,
    INDEX(`company_id`),
    INDEX(`status_id`),
    INDEX(`is_printer`),
    INDEX(`is_workstation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50),
    `description` TEXT,
    `manager_user_id` INT,
    `location_id` INT,
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`manager_user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY(`location_id`) REFERENCES `it_locations`(`id`),
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employees`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `user_id` INT,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `display_name` VARCHAR(150),
    `email` VARCHAR(120),
    `phone` VARCHAR(20),
    `employee_code` VARCHAR(50),
    `hilton_id` VARCHAR(50),
    `username` VARCHAR(100),
    `department_id` INT,
    `job_code` VARCHAR(120),
    `job_title` VARCHAR(100),
    `location_id` INT,
    `employment_status_id` INT NOT NULL,
    `raw_status_code` VARCHAR(20),
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY(`department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY(`location_id`) REFERENCES `it_locations`(`id`),
    INDEX(`company_id`),
    INDEX `idx_employees_hilton_id` (`hilton_id`),
    INDEX `idx_employees_username` (`username`),
    UNIQUE KEY `uq_employees_email_per_company` (`company_id`,`email`),
    UNIQUE KEY `uq_employees_code_per_company` (`company_id`,`employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_onboarding_requests`(
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

CREATE TABLE `workstation_modes`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `mode_name` VARCHAR(100) NOT NULL UNIQUE,
    `mode_code` VARCHAR(50) UNIQUE,
    `description` TEXT,
    `monitor_count` INT DEFAULT 0,
    `max_users` INT DEFAULT 1,
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workstations`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `equipment_id` INT NOT NULL,
    `workstation_code` VARCHAR(50) UNIQUE,
    `workstation_mode_id` INT,
    `assigned_to_employee_id` INT,
    `assigned_to_department_id` INT,
    `assignment_type_id` INT NOT NULL,
    `desk_location` VARCHAR(255),
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`workstation_mode_id`) REFERENCES `workstation_modes`(`id`),
    FOREIGN KEY(`assigned_to_employee_id`) REFERENCES `employees`(`id`),
    FOREIGN KEY(`assigned_to_department_id`) REFERENCES `departments`(`id`),
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `ticket_categories`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) UNIQUE,
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_statuses`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `color` VARCHAR(7),
    `is_closed` TINYINT DEFAULT 0,
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ticket_priorities`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `level` INT,
    `color` VARCHAR(7),
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tickets`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `ticket_code` VARCHAR(80) UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT,
    `category_id` INT,
    `status_id` INT,
    `priority_id` INT,
    `created_by_user_id` INT NOT NULL,
    `assigned_to_user_id` INT,
    `asset_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`category_id`) REFERENCES `ticket_categories`(`id`),
    FOREIGN KEY(`status_id`) REFERENCES `ticket_statuses`(`id`),
    FOREIGN KEY(`priority_id`) REFERENCES `ticket_priorities`(`id`),
    FOREIGN KEY(`created_by_user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY(`assigned_to_user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY(`asset_id`) REFERENCES `equipment`(`id`) ON DELETE SET NULL,
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_categories`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) UNIQUE,
    `active` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_items`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `item_code` VARCHAR(80) UNIQUE,
    `sku` VARCHAR(100),
    `category_id` INT,
    `manufacturer_id` INT,
    `quantity_on_hand` INT NOT NULL DEFAULT 0,
    `quantity_minimum` INT DEFAULT 5,
    `unit_cost` DECIMAL(10, 2),
    `location_id` INT,
    `supplier_id` INT,
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`category_id`) REFERENCES `inventory_categories`(`id`),
    FOREIGN KEY(`manufacturer_id`) REFERENCES `manufacturers`(`id`),
    FOREIGN KEY(`location_id`) REFERENCES `it_locations`(`id`),
    FOREIGN KEY(`supplier_id`) REFERENCES `suppliers`(`id`),
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Lookup tables for ENUM normalization and FK relationships
CREATE TABLE `user_roles`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `access_levels`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `location_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_statuses`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rack_statuses`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipment_statuses`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `warranty_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `printer_device_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workstation_device_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `workstation_os_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_statuses`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assignment_types`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `switch_ports`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `port_number` INT NOT NULL,
    `label` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('uplink','empty','down','unknown') NOT NULL DEFAULT 'unknown',
    `color` ENUM('green','red','yellow','black','blue','white','orange','purple') NOT NULL DEFAULT 'black',
    `comments` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_switch_port` (`company_id`, `port_number`),
    INDEX(`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `equipment_types` (`name`, `code`) VALUES
('Switch', 'SWITCH'),('Server', 'SRV'),('Router', 'RTR'),('Firewall', 'FW'),
('PDU', 'PDU'),('Access Point', 'AP'),('Workstation', 'WS'),('Printer', 'PRN'),
('Phone System', 'PHONE'),('Camera', 'CAM'),('Other', 'OTHER');

INSERT INTO `manufacturers` (`name`, `code`) VALUES
('Cisco Systems', 'CSCO'),('Dell Technologies', 'DELL'),('HP Inc', 'HPE'),
('Juniper Networks', 'JNPR'),('Ubiquiti Networks', 'UBNT'),('Apple', 'APPLE'),
('Lenovo', 'LENOVO'),('Microsoft', 'MSFT');

INSERT INTO `ticket_categories` (`name`, `code`) VALUES
('Hardware Issue', 'HW'),('Network Problem', 'NET'),('Software Issue', 'SW'),
('Maintenance', 'MAINT'),('Other', 'OTHER');

INSERT INTO `ticket_statuses` (`name`, `color`, `is_closed`) VALUES
('Open', '#FF0000', 0),('In Progress', '#FFA500', 0),('Resolved', '#00FF00', 0),('Closed', '#808080', 1);

INSERT INTO `ticket_priorities` (`name`, `level`, `color`) VALUES
('Low', 1, '#0000FF'),('Normal', 2, '#00FF00'),('High', 3, '#FFA500'),('Urgent', 4, '#FF0000'),('Critical', 5, '#8B0000');

INSERT INTO `inventory_categories` (`name`, `code`) VALUES
('Cables - Ethernet', 'CBL-ETH'),('Cables - USB', 'CBL-USB'),('Adapters', 'ADP'),
('Batteries', 'BAT'),('Consumables', 'CONS'),('Other', 'OTH');

INSERT INTO `workstation_modes` (`mode_name`, `mode_code`, `description`, `monitor_count`, `max_users`) VALUES
('POS Only', 'MODE-POS', 'Point of Sale Terminal', 0, 1),
('Computer + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', 1, 1),
('Computer + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', 2, 1),
('Laptop Only', 'MODE-LAP', 'Single Laptop', 0, 1),
('Laptop + Docking', 'MODE-LAP-DOCK', 'Laptop with Docking Station', 2, 1),
('All-in-One', 'MODE-AIO', 'All-in-One Device', 0, 1),
('Shared Setup', 'MODE-SHARED', 'Shared Workstation', 1, 4);

INSERT INTO `companies` (`name`, `company_code`, `industry`, `city`, `country`) VALUES
('TechCorp Global', 'TC-001', 'Technology', 'New York', 'USA'),
('DataCenter Plus', 'DCP-001', 'Data Center', 'Dallas', 'USA'),
('Network Solutions', 'NSI-001', 'Networking', 'San Francisco', 'USA'),
('CloudTech Services', 'CTS-001', 'Cloud', 'Seattle', 'USA'),
('Enterprise IT', 'ESL-001', 'Enterprise', 'Boston', 'USA');



-- Populate lookup tables
INSERT INTO `user_roles` (`name`) VALUES ('admin'),('it_manager'),('it_technician'),('helpdesk'),('user');
INSERT INTO `access_levels` (`name`) VALUES ('full'),('read_only'),('limited');
INSERT INTO `location_types` (`name`) VALUES ('Headquarters'),('Branch'),('Warehouse'),('DataCenter'),('Office'),('Remote'),('Other');
INSERT INTO `supplier_statuses` (`name`) VALUES ('Active'),('Inactive'),('Preferred'),('Backup');
INSERT INTO `rack_statuses` (`name`) VALUES ('Active'),('Maintenance'),('Full'),('Decommissioned');
INSERT INTO `equipment_statuses` (`name`) VALUES ('Active'),('Inactive'),('Maintenance'),('Faulty'),('Reserved'),('Decommissioned'),('On-Order');
INSERT INTO `warranty_types` (`name`) VALUES ('Standard'),('Extended'),('Premium'),('Enterprise'),('None');
INSERT INTO `printer_device_types` (`name`) VALUES ('Laser'),('Inkjet'),('All-in-One'),('Thermal'),('Wide-Format'),('Photo'),('Label'),('Dotmatrix'),('Other');
INSERT INTO `workstation_device_types` (`name`) VALUES ('Desktop'),('Laptop'),('All-in-One'),('Tablet'),('Thin-Client'),('Mobile'),('POS'),('Other');
INSERT INTO `workstation_os_types` (`name`) VALUES ('Windows'),('macOS'),('Linux'),('ChromeOS'),('iOS'),('Android'),('Other');
INSERT INTO `employee_statuses` (`name`) VALUES ('Active'),('Inactive'),('On Leave'),('Terminated'),('Contractor');
INSERT INTO `assignment_types` (`name`) VALUES ('Individual'),('Department'),('Shared'),('Pool');

-- Add lookup-table foreign key relationships
ALTER TABLE `users`
    ADD FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`),
    ADD FOREIGN KEY (`access_level_id`) REFERENCES `access_levels`(`id`);

ALTER TABLE `it_locations`
    ADD FOREIGN KEY (`type_id`) REFERENCES `location_types`(`id`);

ALTER TABLE `suppliers`
    ADD FOREIGN KEY (`status_id`) REFERENCES `supplier_statuses`(`id`);

ALTER TABLE `racks`
    ADD FOREIGN KEY (`status_id`) REFERENCES `rack_statuses`(`id`);

ALTER TABLE `equipment`
    ADD FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses`(`id`),
    ADD FOREIGN KEY (`warranty_type_id`) REFERENCES `warranty_types`(`id`),
    ADD FOREIGN KEY (`printer_device_type_id`) REFERENCES `printer_device_types`(`id`),
    ADD FOREIGN KEY (`workstation_device_type_id`) REFERENCES `workstation_device_types`(`id`),
    ADD FOREIGN KEY (`workstation_os_type_id`) REFERENCES `workstation_os_types`(`id`);

ALTER TABLE `employees`
    ADD FOREIGN KEY (`employment_status_id`) REFERENCES `employee_statuses`(`id`);

ALTER TABLE `workstations`
    ADD FOREIGN KEY (`assignment_type_id`) REFERENCES `assignment_types`(`id`);

-- Example data for each main table
INSERT INTO `it_locations` (`company_id`,`name`,`location_code`,`city`,`country`,`active`,`type_id`) VALUES (1,'HQ NYC','LOC-NY-01','New York','USA',1,1);
INSERT INTO `users` (`company_id`,`username`,`email`,`password`,`first_name`,`last_name`,`active`,`role_id`,`access_level_id`) VALUES
(1,'admin_tc','admin@techcorp.example','$2y$10$abcdefghijklmnopqrstuv','System','Admin',1,1,1);
INSERT INTO `suppliers` (`company_id`,`name`,`supplier_code`,`contact_person`,`email`,`phone`,`active`,`status_id`) VALUES
(1,'Global IT Supply','SUP-001','Jane Doe','sales@globalit.example','+1-555-0100',1,1);
INSERT INTO `vlans` (`company_id`,`vlan_number`,`vlan_name`,`cable_color`,`subnet`,`gateway_ip`,`active`) VALUES
(1,10,'Office Network','#2E86DE','192.168.10.0/24','192.168.10.1',1);

-- Seed 48 switch ports for company 1
INSERT INTO `switch_ports` (`company_id`, `port_number`, `label`, `status`, `color`, `comments`) VALUES
(1,1,'Port 1','unknown','black',''),(1,2,'Port 2','unknown','black',''),(1,3,'Port 3','unknown','black',''),(1,4,'Port 4','unknown','black',''),
(1,5,'Port 5','unknown','black',''),(1,6,'Port 6','unknown','black',''),(1,7,'Port 7','unknown','black',''),(1,8,'Port 8','unknown','black',''),
(1,9,'Port 9','unknown','black',''),(1,10,'Port 10','unknown','black',''),(1,11,'Port 11','unknown','black',''),(1,12,'Port 12','unknown','black',''),
(1,13,'Port 13','unknown','black',''),(1,14,'Port 14','unknown','black',''),(1,15,'Port 15','unknown','black',''),(1,16,'Port 16','unknown','black',''),
(1,17,'Port 17','unknown','black',''),(1,18,'Port 18','unknown','black',''),(1,19,'Port 19','unknown','black',''),(1,20,'Port 20','unknown','black',''),
(1,21,'Port 21','unknown','black',''),(1,22,'Port 22','unknown','black',''),(1,23,'Port 23','unknown','black',''),(1,24,'Port 24','unknown','black',''),
(1,25,'Port 25','unknown','black',''),(1,26,'Port 26','unknown','black',''),(1,27,'Port 27','unknown','black',''),(1,28,'Port 28','unknown','black',''),
(1,29,'Port 29','unknown','black',''),(1,30,'Port 30','unknown','black',''),(1,31,'Port 31','unknown','black',''),(1,32,'Port 32','unknown','black',''),
(1,33,'Port 33','unknown','black',''),(1,34,'Port 34','unknown','black',''),(1,35,'Port 35','unknown','black',''),(1,36,'Port 36','unknown','black',''),
(1,37,'Port 37','unknown','black',''),(1,38,'Port 38','unknown','black',''),(1,39,'Port 39','unknown','black',''),(1,40,'Port 40','unknown','black',''),
(1,41,'Port 41','unknown','black',''),(1,42,'Port 42','unknown','black',''),(1,43,'Port 43','unknown','black',''),(1,44,'Port 44','unknown','black',''),
(1,45,'Port 45','unknown','black',''),(1,46,'Port 46','unknown','black',''),(1,47,'Port 47','unknown','black',''),(1,48,'Port 48','unknown','black','');

INSERT INTO `racks` (`company_id`,`location_id`,`name`,`rack_code`,`active`,`status_id`) VALUES
(1,1,'Main Rack A','RACK-A',1,1);
INSERT INTO `equipment` (`company_id`,`equipment_type_id`,`manufacturer_id`,`location_id`,`rack_id`,`name`,`serial_number`,`model`,`hostname`,`ip_address`,`purchase_date`,`purchase_cost`,`active`,`status_id`,`warranty_type_id`,`printer_device_type_id`,`workstation_device_type_id`,`workstation_os_type_id`) VALUES
(1,2,2,1,1,'Primary File Server','SN-SRV-001','PowerEdge R760','srv-file-01','192.168.10.20','2025-01-10',8500.00,1,1,4,NULL,NULL,NULL);
INSERT INTO `departments` (`company_id`,`name`,`code`,`description`,`manager_user_id`,`location_id`,`active`) VALUES
(1,'IT Operations','ITOPS','Core IT operations team',1,1,1),
(1,'Food and Drinks','FNB','Food and Beverages department',1,1,1),
(1,'Human Resources','HR','Human resources department',1,1,1),
(1,'Housekeeping','HK','Housekeeping operations',1,1,1);
INSERT INTO `employees` (`company_id`,`user_id`,`first_name`,`last_name`,`display_name`,`email`,`phone`,`employee_code`,`hilton_id`,`username`,`department_id`,`job_code`,`job_title`,`location_id`,`active`,`employment_status_id`,`raw_status_code`) VALUES
(1,1,'Alex','Morgan','Alex Morgan','alex.morgan@techcorp.example','+1-555-0140','EMP-1001',NULL,'admin_tc',1,'IT Manager','IT Manager',1,1,1,'A'),
(1,NULL,'Marcelo','Batista','Marcelo Batista','marcelo.costeira@icloud.com',NULL,NULL,'2295111','Marcelo Batista',4,'Room Attendant','Housekeeping Public Area Attendant',1,0,2,'I'),
(1,NULL,'Rafaela','Cruz','Rafaela Cruz','cruzrafaela86@gmail.com',NULL,'A02F0122','A02F0122','A02F0122',3,'HR Co-Ordinator','HR Trainee',1,1,1,'A'),
(1,NULL,'Nicky','Schouten','NICKY SCHOUTEN','302325432@student.rocmondriaan.nl',NULL,NULL,NULL,NULL,2,'TRAINEE','TRAINEE',1,1,1,'A');
INSERT INTO `employee_onboarding_requests` (`company_id`,`employee_id`,`first_name`,`last_name`,`position_title`,`department_name`,`request_date`,`termination_date`,`network_access`,`micros_emc`,`opera`,`micros_card`,`pms_id`,`synergy_mms`,`email_account`,`landline_phone`,`hu_the_lobby`,`mobile_phone`,`navision`,`mobile_email`,`onq_ri`,`birchstreet`,`delphi`,`omina`,`vingcard_system`,`digital_rev`,`office_key_card`,`comments`,`starting_date`,`requested_by`,`requested_on`,`hod_approval`,`hrd_approval`,`ism_approval`) VALUES
(1,4,'NICKY','SCHOUTEN','TRAINEE','FOOD AND DRINKS','2026-03-24',NULL,'N/A','N/A','N/A','Waiter','N/A','N/A','N/A','N/A','NICKY SCHOUTEN','N/A','N/A','N/A','N/A','N/A','N/A','Via HR','N/A','N/A','Room Service','Starting date: 16/03/2026 || 302325432@student.rocmondriaan.nl','2026-03-16','ALEXANDRANUNES','2026-03-24','Sonia Costa','Pedro Mendes','Kenneth Starreveld');
INSERT INTO `workstations` (`company_id`,`equipment_id`,`workstation_code`,`workstation_mode_id`,`assigned_to_employee_id`,`assigned_to_department_id`,`desk_location`,`active`,`assignment_type_id`) VALUES
(1,1,'WS-001',1,1,1,'HQ-Desk-14',1,1);
INSERT INTO `tickets` (`company_id`,`ticket_code`,`title`,`description`,`category_id`,`status_id`,`priority_id`,`created_by_user_id`,`assigned_to_user_id`,`asset_id`) VALUES
(1,'TCK-0001','Server patching required','Patch cycle for file server',4,1,2,1,1,1);
INSERT INTO `inventory_items` (`company_id`,`name`,`item_code`,`sku`,`category_id`,`manufacturer_id`,`quantity_on_hand`,`quantity_minimum`,`unit_cost`,`location_id`,`supplier_id`,`active`) VALUES
(1,'Cat6 Cable 2m','INV-CAT6-2M','SKU-CAT6-2M',1,1,50,10,4.99,1,1,1);

SET FOREIGN_KEY_CHECKS=1;
