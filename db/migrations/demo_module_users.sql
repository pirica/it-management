-- Demo single-module users (demo1–demo5) for company 1.
-- Live DB: run after pulling seed helpers, or use:
--   php scripts/fast_create_acc.php --seed-demo-bundle --company=1

INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`)
SELECT 1, seed.`role_name`, '2026-01-01 00:00:01'
FROM (
  SELECT 'Demo Tickets' AS `role_name`
  UNION ALL SELECT 'Demo Audit'
  UNION ALL SELECT 'Demo Visitors'
  UNION ALL SELECT 'Demo Request Password'
  UNION ALL SELECT 'Demo Equipment'
) seed
WHERE NOT EXISTS (
  SELECT 1 FROM `employee_roles` er WHERE er.`company_id` = 1 AND er.`name` = seed.`role_name`
);

INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`)
SELECT er.`company_id`, er.`id`, seed.`module_name`, 1, 1, 1, 1, 1, 1, NOW()
FROM `employee_roles` er
INNER JOIN (
  SELECT 'Demo Tickets' AS `role_name`, 'Tickets' AS `module_name`
  UNION ALL SELECT 'Demo Audit', 'Audit Logs'
  UNION ALL SELECT 'Demo Visitors', 'Visitors Access Log'
  UNION ALL SELECT 'Demo Request Password', 'Request Password'
  UNION ALL SELECT 'Demo Equipment', 'Equipment'
) seed ON seed.`role_name` = er.`name`
WHERE er.`company_id` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `role_module_permissions` rmp
    WHERE rmp.`company_id` = er.`company_id` AND rmp.`role_id` = er.`id` AND rmp.`module_name` = seed.`module_name`
  );

INSERT INTO `employees` (`company_id`, `first_name`, `last_name`, `display_name`, `work_email`, `password`, `username`, `role_id`, `access_level_id`, `employment_status_id`, `active`, `created_at`)
SELECT 1, seed.`first_name`, 'Demo', seed.`display_name`, seed.`work_email`, seed.`password_hash`, seed.`username`, er.`id`, al.`id`, es.`id`, 1, NOW()
FROM (
  SELECT 'demo1' AS `username`, 'Demo1' AS `first_name`, 'Demo1 Demo' AS `display_name`, 'demo1@demo.example.com' AS `work_email`, '$2y$10$TbFFjehZcAC0SGdr3GLfSOXmUvp2QWMkRSrXp92jWas7gmvRSiYOW' AS `password_hash`, 'Demo Tickets' AS `role_name`
  UNION ALL SELECT 'demo2', 'Demo2', 'Demo2 Demo', 'demo2@demo.example.com', '$2y$10$IPFBQTONOpLrY9rwud/t0uu46Hrj5YPE72yBG5TRoKw3kF1b/xuBW', 'Demo Audit'
  UNION ALL SELECT 'demo3', 'Demo3', 'Demo3 Demo', 'demo3@demo.example.com', '$2y$10$YHc784pJBe5hxkD0Q1ZqSeU4wibUHeN6y5c7JGwQQb2m1N/EYLBjK', 'Demo Visitors'
  UNION ALL SELECT 'demo4', 'Demo4', 'Demo4 Demo', 'demo4@demo.example.com', '$2y$10$Y3f6oYePBLVF5eiVNtO3MezpCC3vm9kbmlSBFalgN1RDRc1NFJB9u', 'Demo Request Password'
  UNION ALL SELECT 'demo5', 'Demo5', 'Demo5 Demo', 'demo5@demo.example.com', '$2y$10$f9K1IJIQwdLIBuf1foR7seDqx6Brt8r49tlmtcynSxnjctjvL1rR2', 'Demo Equipment'
) seed
INNER JOIN `employee_roles` er ON er.`company_id` = 1 AND er.`name` = seed.`role_name`
INNER JOIN `access_levels` al ON al.`company_id` = 1 AND al.`name` = 'Limited'
INNER JOIN `employee_statuses` es ON es.`company_id` = 1 AND es.`name` = 'Active'
WHERE NOT EXISTS (
  SELECT 1 FROM `employees` e WHERE e.`company_id` = 1 AND LOWER(e.`username`) = LOWER(seed.`username`)
);

INSERT INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `active`, `created_at`)
SELECT e.`id`, e.`company_id`, NULL, 1, NOW()
FROM `employees` e
WHERE e.`username` IN ('demo1', 'demo2', 'demo3', 'demo4', 'demo5')
  AND NOT EXISTS (
    SELECT 1 FROM `employee_companies` ec WHERE ec.`employee_id` = e.`id` AND ec.`company_id` = e.`company_id`
  );

INSERT INTO `ui_configuration` (`company_id`, `employee_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `enable_chatbot`, `enable_auto_scaffolding`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`)
SELECT e.`company_id`, e.`id`, 'left', 'left', 'left', 'left', 1, 1, 1, 0, '25', '⚙️ IT Controls', 'images/favicons/company_1.ico', '{"is_access_point":1, "is_cctv":1, "is_firewall":1, "is_other":1, "is_phone":1, "is_port_patch_panel":1, "is_printer":1, "is_router":1, "is_server":1, "is_switch":1, "is_workstation":1}', NOW(), NULL
FROM `employees` e
WHERE e.`username` IN ('demo1', 'demo2', 'demo3', 'demo4', 'demo5')
  AND NOT EXISTS (
    SELECT 1 FROM `ui_configuration` uc WHERE uc.`company_id` = e.`company_id` AND uc.`employee_id` = e.`id`
  );
