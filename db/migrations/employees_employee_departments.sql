-- employee_departments: extra department folder access for Explorer (primary remains employees.department_id).
DROP TABLE IF EXISTS `employee_departments`;

CREATE TABLE `employee_departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `department_id` int NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_departments_scope` (`company_id`,`employee_id`,`department_id`),
  KEY `idx_employee_departments_employee` (`employee_id`),
  KEY `idx_employee_departments_department` (`department_id`),
  CONSTRAINT `fk_employee_departments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_departments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employee_departments` (`company_id`, `employee_id`, `department_id`, `active`, `created_by`)
SELECT e.`company_id`, e.`id`, e.`department_id`, 1, e.`id`
FROM `employees` e
WHERE e.`department_id` IS NOT NULL
  AND e.`deleted_at` IS NULL;
