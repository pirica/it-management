-- Todo vault: encrypt personal task title/description at rest (see modules/todo/todo_vault_helpers.php).
-- Live DBs: full CREATE TABLE swap (no ALTER). Fresh installs use the same shape in db/01_schema.sql.
-- Why: title_hash backfill uses SHA2(TRIM(title), 256) to match todo_text_hash() for legacy plaintext rows.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `todo_new` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `due_date` datetime DEFAULT NULL,
  `reminder_at` datetime DEFAULT NULL,
  `repeat_pattern` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to_employee_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed` tinyint NOT NULL DEFAULT '0',
  `importance` tinyint NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_todo_company_scope` (`company_id`, `created_by`, `id`),
  KEY `company_id` (`company_id`),
  KEY `category_id` (`category_id`),
  KEY `department_id` (`department_id`),
  KEY `assigned_to_employee_id` (`assigned_to_employee_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `todo_ibfk_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `todo_new` (
  `id`,
  `company_id`,
  `title`,
  `title_hash`,
  `description`,
  `due_date`,
  `reminder_at`,
  `repeat_pattern`,
  `category_id`,
  `department_id`,
  `assigned_to_employee_id`,
  `completed`,
  `importance`,
  `active`,
  `deleted_by`,
  `deleted_at`,
  `created_by`,
  `created_at`,
  `updated_by`,
  `updated_at`
)
SELECT
  `id`,
  `company_id`,
  `title`,
  SHA2(TRIM(`title`), 256),
  `description`,
  `due_date`,
  `reminder_at`,
  `repeat_pattern`,
  `category_id`,
  `department_id`,
  `assigned_to_employee_id`,
  `completed`,
  `importance`,
  `active`,
  `deleted_by`,
  `deleted_at`,
  `created_by`,
  `created_at`,
  `updated_by`,
  `updated_at`
FROM `todo`;

DROP TABLE `todo`;
RENAME TABLE `todo_new` TO `todo`;

SET FOREIGN_KEY_CHECKS = 1;
