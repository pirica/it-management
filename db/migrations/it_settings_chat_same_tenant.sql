-- it_settings: add chat_same_tenant (Live Chat peer tenant policy; default ON).
-- Why: Settings → All roles toggles same-tenant-only Chat with per company.
-- Warning: DROP TABLE removes existing it_settings rows — back up before apply; re-run db/02_data.sql IT Settings INSERT block if needed.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `it_settings`;

CREATE TABLE `it_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours_of_operation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escalation_procedure` text COLLATE utf8mb4_unicode_ci,
  `chat_same_tenant` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`),
  CONSTRAINT `it_settings_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `it_settings` (`company_id`, `contact_email`, `contact_phone`, `hours_of_operation`, `escalation_procedure`, `chat_same_tenant`) VALUES
(1, 'it-support@techcorp.example', '+1-212-555-0199', '24/7', 'For critical outages, call the On-Call Manager at +1-212-555-0911.', 1),
(2, 'support@datacenterplus.example', '+1-972-555-0200', '08:00 - 18:00 CST', 'Issues unresolved after 4 hours should be escalated to the IT Director.', 1),
(3, 'help@networksolutions.example', '+1-415-555-0300', '09:00 - 17:00 PST', 'Please submit a ticket via the portal for escalation.', 1),
(4, 'it@cloudtech.example', '+1-206-555-0400', '24/7', 'Contact the Level 2 support team via Slack #it-escalations.', 1),
(5, 'it-ops@enterpriseit.example', '+1-617-555-0500', '08:00 - 20:00 EST', 'Standard escalation through the ticketing system.', 1);
