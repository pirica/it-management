-- Bind seed tenant admins (Admin, Admin2–Admin5) to each company's Admin role.
-- Safe on live DBs: idempotent when role_id already correct.
-- Fresh imports get the same UPDATE from db/02_data.sql after employee_roles replication.

UPDATE `employees` e
INNER JOIN `employee_roles` er ON er.`company_id` = e.`company_id` AND er.`name` = 'Admin'
SET e.`role_id` = er.`id`
WHERE e.`username` IN ('Admin', 'Admin2', 'Admin3', 'Admin4', 'Admin5')
  AND (e.`role_id` IS NULL OR e.`role_id` <> er.`id`);
