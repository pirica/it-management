-- Ticket canned responses (destructive replacement for existing DBs).
DROP TABLE IF EXISTS `ticket_canned_responses`;
CREATE TABLE `ticket_canned_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_ticket_canned_responses_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_canned_responses_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `ticket_canned_responses` (`company_id`, `title`, `body`, `category_id`, `active`, `created_at`) VALUES
(1, 'Acknowledge receipt', 'Thank you for contacting IT. We have received your request and will respond shortly.', 5, 1, '2026-01-01 00:00:01'),
(1, 'Request more information', 'Could you provide additional details (screenshots, error messages, or steps to reproduce) so we can assist you faster?', 5, 1, '2026-01-01 00:00:01'),
(1, 'Resolved — please confirm', 'We believe this issue has been resolved. Please reply if you still need assistance.', 5, 1, '2026-01-01 00:00:01');
