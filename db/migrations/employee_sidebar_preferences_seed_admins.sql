-- Reassign sidebar layout rows seeded with employee_id=1 on foreign companies to each tenant seed admin (Admin2–Admin5).
-- Idempotent: safe when rows already point at the correct employee.

UPDATE `employee_sidebar_preferences` esp
INNER JOIN `employees` e
  ON e.`company_id` = esp.`company_id`
 AND e.`username` LIKE 'Admin%'
 AND e.`deleted_at` IS NULL
SET esp.`employee_id` = e.`id`
WHERE esp.`employee_id` <> e.`id`;
