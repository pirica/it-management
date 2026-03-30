-- IDF module migration

DROP TABLE IF EXISTS `idf_links`;
DROP TABLE IF EXISTS `idf_ports`;
DROP TABLE IF EXISTS `idf_positions`;
DROP TABLE IF EXISTS `idfs`;

CREATE TABLE `idfs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `location_id` INT NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `idf_code` VARCHAR(60) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `location_id` (`location_id`),
  UNIQUE KEY `idf_code` (`idf_code`),
  CONSTRAINT `idfs_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idfs_ibfk_location` FOREIGN KEY (`location_id`) REFERENCES `it_locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idf_positions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `idf_id` INT NOT NULL,
  `position_no` TINYINT NOT NULL,
  `device_type` ENUM('switch','patch_panel','ups','server','other') NOT NULL DEFAULT 'other',
  `device_name` VARCHAR(140) NOT NULL,
  `equipment_id` INT DEFAULT NULL,
  `port_count` SMALLINT NOT NULL DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idf_pos_unique` (`idf_id`,`position_no`),
  KEY `idf_id` (`idf_id`),
  KEY `equipment_id` (`equipment_id`),
  CONSTRAINT `idf_positions_ibfk_idf` FOREIGN KEY (`idf_id`) REFERENCES `idfs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_positions_ibfk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idf_ports` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `position_id` INT NOT NULL,
  `port_no` SMALLINT NOT NULL,
  `port_type` ENUM('RJ45','SFP','SFP+','LC','SC','OTHER') NOT NULL DEFAULT 'RJ45',
  `label` VARCHAR(120) DEFAULT NULL,
  `status` ENUM('free','used','reserved','down','unknown') NOT NULL DEFAULT 'unknown',
  `connected_to` VARCHAR(180) DEFAULT NULL,
  `vlan` VARCHAR(40) DEFAULT NULL,
  `speed` VARCHAR(40) DEFAULT NULL,
  `poe` VARCHAR(40) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_port_unique` (`position_id`,`port_no`),
  KEY `position_id` (`position_id`),
  CONSTRAINT `idf_ports_ibfk_position` FOREIGN KEY (`position_id`) REFERENCES `idf_positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idf_links` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `port_id_a` INT NOT NULL,
  `port_id_b` INT NOT NULL,
  `cable_color` VARCHAR(40) NOT NULL DEFAULT 'yellow',
  `cable_label` VARCHAR(120) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`port_id_a`,`port_id_b`),
  KEY `port_id_a` (`port_id_a`),
  KEY `port_id_b` (`port_id_b`),
  CONSTRAINT `idf_links_ibfk_a` FOREIGN KEY (`port_id_a`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idf_links_ibfk_b` FOREIGN KEY (`port_id_b`) REFERENCES `idf_ports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
