-- Network Discovery v2 — profiles, staging queue, chunked scheduled scans.
DROP TABLE IF EXISTS `network_discovery_staging`;

DROP TABLE IF EXISTS `network_discovery_profiles`;

CREATE TABLE `network_discovery_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subnet_ids_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON array of ip_subnets.id',
  `schedule_cron` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'minute hour dom month dow',
  `snmp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `auto_create_policy` enum('none','review','equipment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'review',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_run_at` timestamp NULL DEFAULT NULL,
  `scan_in_progress` tinyint(1) NOT NULL DEFAULT '0',
  `scan_offset` int NOT NULL DEFAULT '0',
  `scan_ips_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of IPv4 strings while chunked scan runs',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_network_discovery_profiles_company_name` (`company_id`,`name`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `network_discovery_profiles_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `network_discovery_staging` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `profile_id` int NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mac_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hostname_guess` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `probe_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','promoted','dismissed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `promoted_equipment_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_network_discovery_staging_profile_ip` (`company_id`,`profile_id`,`ip_address`),
  KEY `profile_id` (`profile_id`),
  KEY `promoted_equipment_id` (`promoted_equipment_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `network_discovery_staging_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `network_discovery_staging_ibfk_profile` FOREIGN KEY (`profile_id`) REFERENCES `network_discovery_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `network_discovery_staging_ibfk_equipment` FOREIGN KEY (`promoted_equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
