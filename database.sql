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
    `role` ENUM('admin','it_manager','it_technician','helpdesk','user') DEFAULT 'user',
    `access_level` ENUM('full','read_only','limited') DEFAULT 'read_only',
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
    `type` ENUM('Headquarters','Branch','Warehouse','DataCenter','Office','Remote','Other') DEFAULT 'Office',
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
    `status` ENUM('Active','Inactive','Preferred','Backup') DEFAULT 'Active',
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
    `status` ENUM('Active','Maintenance','Full','Decommissioned') DEFAULT 'Active',
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
    `asset_tag` VARCHAR(80) UNIQUE,
    `asset_code` VARCHAR(50),
    `serial_number` VARCHAR(100),
    `model` VARCHAR(100),
    `hostname` VARCHAR(100),
    `ip_address` VARCHAR(45),
    `mac_address` VARCHAR(45),
    `status` ENUM('Active','Inactive','Maintenance','Faulty','Reserved','Decommissioned','On-Order') DEFAULT 'Active',
    `purchase_date` DATE,
    `purchase_cost` DECIMAL(15, 2),
    `warranty_expiry` DATE,
    `warranty_type` ENUM('Standard','Extended','Premium','Enterprise','None') DEFAULT 'Standard',
    `is_printer` TINYINT DEFAULT 0,
    `printer_device_type` ENUM('Laser','Inkjet','All-in-One','Thermal','Wide-Format','Photo','Label','Dotmatrix','Other'),
    `printer_color_capable` TINYINT DEFAULT 0,
    `printer_print_speed_ppm` INT,
    `is_workstation` TINYINT DEFAULT 0,
    `workstation_device_type` ENUM('Desktop','Laptop','All-in-One','Tablet','Thin-Client','Mobile','POS','Other'),
    `workstation_os_type` ENUM('Windows','macOS','Linux','ChromeOS','iOS','Android','Other'),
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
    INDEX(`status`),
    INDEX(`is_printer`),
    INDEX(`is_workstation`),
    UNIQUE KEY `unique_asset_per_company` (`company_id`, `asset_code`)
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
    `email` VARCHAR(120) UNIQUE,
    `phone` VARCHAR(20),
    `employee_code` VARCHAR(50) UNIQUE,
    `department_id` INT,
    `job_title` VARCHAR(100),
    `location_id` INT,
    `employment_status` ENUM('Active','Inactive','On Leave','Terminated','Contractor') DEFAULT 'Active',
    `active` TINYINT DEFAULT 1,
    FOREIGN KEY(`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY(`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY(`department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY(`location_id`) REFERENCES `it_locations`(`id`),
    INDEX(`company_id`)
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
    `assignment_type` ENUM('Individual','Department','Shared','Pool') DEFAULT 'Individual',
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

SET FOREIGN_KEY_CHECKS=1;
