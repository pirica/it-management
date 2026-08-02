-- Hotel booking distribution API: channels, mappings, reservation links, ARI event log (new tables).
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `hotel_booking_distribution_ari_events`;
DROP TABLE IF EXISTS `hotel_booking_distribution_reservations`;
DROP TABLE IF EXISTS `hotel_booking_distribution_mappings`;
DROP TABLE IF EXISTS `hotel_booking_distribution_channels`;

CREATE TABLE `hotel_booking_distribution_channels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `standard` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'itm_native',
  `api_key_prefix` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `webhook_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hourly_rate_limit` int NOT NULL DEFAULT '1000',
  `api_requests_count` int NOT NULL DEFAULT '0',
  `api_window_started_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_channel_company_code` (`company_id`,`channel_code`),
  KEY `company_id` (`company_id`),
  KEY `idx_hb_dist_channel_api_prefix` (`api_key_prefix`),
  CONSTRAINT `hb_dist_channel_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_mappings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `entity_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_id` int NOT NULL,
  `external_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_map_channel_entity` (`channel_id`,`entity_type`,`internal_id`),
  UNIQUE KEY `uq_hb_dist_map_channel_external` (`channel_id`,`entity_type`,`external_code`),
  KEY `company_id` (`company_id`),
  KEY `channel_id` (`channel_id`),
  CONSTRAINT `hb_dist_map_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_map_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_booking_id` int NOT NULL,
  `external_reservation_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_dist_res_channel_external` (`channel_id`,`external_reservation_id`),
  KEY `company_id` (`company_id`),
  KEY `hotel_booking_id` (`hotel_booking_id`),
  KEY `channel_id` (`channel_id`),
  CONSTRAINT `hb_dist_res_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_res_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_res_ibfk_booking` FOREIGN KEY (`hotel_booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_booking_distribution_ari_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `channel_id` int NOT NULL,
  `hotel_id` int NOT NULL,
  `room_type_id` int DEFAULT NULL,
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inbound',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `request_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `response_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `channel_id` (`channel_id`),
  KEY `hotel_id` (`hotel_id`),
  KEY `idx_hb_dist_ari_created` (`company_id`,`channel_id`,`created_at`),
  CONSTRAINT `hb_dist_ari_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_ibfk_channel` FOREIGN KEY (`channel_id`) REFERENCES `hotel_booking_distribution_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hb_dist_ari_ibfk_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotel_booking_hotels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
