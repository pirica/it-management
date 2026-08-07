-- Allow Admin role Personalized Sidebar hide/unhide; keep Helpdesk sidebar_show for required modules.
UPDATE `employee_roles` SET `sidebar_show` = 0 WHERE LOWER(TRIM(`name`)) = 'admin';
UPDATE `employee_roles` SET `sidebar_show` = 1 WHERE LOWER(TRIM(`name`)) = 'helpdesk';
