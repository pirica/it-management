-- IT Management SQL Backup
-- Seed and replication data. Import after 01_schema.sql, before 03_triggers.sql.

USE `itmanagement`;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Full', '2026-01-01 00:00:01');

INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Limited', '2026-01-01 00:00:01');

INSERT INTO `access_levels` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Read Only', '2026-01-01 00:00:01');

INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Department', '2026-01-01 00:00:01');

INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Individual', '2026-01-01 00:00:01');

INSERT INTO `assignment_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Shared', '2026-01-01 00:00:01');

-- Data for `companies`
INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('1', 'TechCorp Global', 'TC001', 'New York', 'USA', '+1-212-555-0101', 'info@techcorp.example', 'https://techcorp.example', 'US-TC-1001', NULL, 'Head office company profile', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('2', 'DataCenter Plus', 'DCP001', 'Dallas', 'USA', '+1-972-555-0102', 'contact@datacenterplus.example', 'https://datacenterplus.example', 'US-DCP-1002', NULL, '', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('3', 'Network Solutions', 'NSI001', 'San Francisco', 'USA', '+1-415-555-0103', 'hello@networksolutions.example', 'https://networksolutions.example', 'US-NSI-1003', NULL, '', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('4', 'CloudTech Services', 'CTS001', 'Seattle', 'USA', '+1-206-555-0104', 'support@cloudtech.example', 'https://cloudtech.example', 'US-CTS-1004', NULL, '', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `companies` (`id`, `company`, `incode`, `city`, `country`, `phone`, `email`, `website`, `vat`, `unit_no`, `comments`, `active`, `created_at`, `updated_at`) VALUES ('5', 'Enterprise IT', 'EIT001', 'Boston', 'USA', '+1-617-555-0105', 'office@enterpriseit.example', 'https://enterpriseit.example', 'US-EIT-1005', NULL, '', '1', '2026-01-01 00:00:01', NULL);

-- Data for `modules_registry`
INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("access_levels", "Access Levels", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("alerts", "Alerts", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("annual_budgets", "Annual Budgets", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approvals", "Approvals", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approvals_stage", "Approvals Stage", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approver_type", "Approver Type", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("approvers", "Approvers", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("assignment_types", "Assignment Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("attempts", "Attempts", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("audit_logs", "Audit Logs", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("backup_tape_log", "Backup Tape Log File", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("birthdays", "Birthdays", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("bookmark_folders", "Bookmark Folders", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("bookmarks", "Bookmarks", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("budget_categories", "Budget Categories", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("budget_report", "Budget Report", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("cable_colors", "Cable Colors", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("calendar", "Calendar", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("catalogs", "Catalogs", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("companies", "Companies", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("company_module_access", "Company Module Access", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("share_modules", "Share Modules", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("contacts", "Contacts", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("cost_centers", "Cost Centers", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("departments", "Departments", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_assignment_history", "Employee Assignment History", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_onboarding_requests", "Employee Onboarding Requests", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_positions", "Employee Positions", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_statuses", "Employee Statuses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_type", "Employee Type", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("emails", "Email Management", 0, 1, "📧");

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_system_access", "Employee System Access", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employees", "Employees", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment", "Equipment", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_environment", "Equipment Environment", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber", "Equipment Fiber", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber_count", "Equipment Fiber Count", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber_patch", "Equipment Fiber Patch", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_fiber_rack", "Equipment Fiber Rack", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_poe", "Equipment Poe", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_rj45", "Equipment Rj45", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_statuses", "Equipment Statuses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("equipment_types", "Equipment Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("event_categories", "Event Categories", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("events", "Events", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("expenses", "Expenses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("expiring", "Expiring", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("explorer", "Explorer", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_designer", "Floor Designer", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_designer_points", "Floor Designer Points", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plan_folders", "Floor Plan Folders", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plan_item_tags", "Floor Plan Item Tags", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plan_tags", "Floor Plan Tags", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("floor_plans", "Floor Plans", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("forecast_revisions", "Forecast Revisions", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("forecast_revisions_status", "Forecast Revisions Status", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("gl_accounts", "Gl Accounts", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_device_type", "Idf Device Type", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_links", "Idf Links", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_ports", "Idf Ports", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idf_positions", "Idf Positions", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("idfs", "Idfs", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("inventory_categories", "Inventory Categories", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("inventory_items", "Inventory Items", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ip_addresses", "Ip Addresses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ip_subnets", "Ip Subnets", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_access_point", "Is Access Point", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_cctv", "Is Cctv", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_firewall", "Is Firewall", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_other", "Is Other", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_phone", "Is Phone", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_port_patch_panel", "Is Port Patch Panel", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_pos", "Is Pos", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_printer", "Is Printer", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_router", "Is Router", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_server", "Is Server", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_switch", "Is Switch", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("is_workstation", "Is Workstation", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("license_management", "License Management", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("it_locations", "It Locations", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("location_types", "Location Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("manufacturers", "Manufacturers", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("modules_registry", "Modules Registry", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("monthly_budgets", "Monthly Budgets", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("note_labels", "Note Labels", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("notes", "Notes", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("org_chart", "Org Chart", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ops_report", "Ops Report", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("password_entries", "Password Entries", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("password_folders", "Password Folders", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("passwords", "Passwords", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("patches_updates", "Patches Updates", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("patches_updates_level", "Patches Updates Level", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("patches_updates_status", "Patches Updates Status", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("printer_device_types", "Printer Device Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("private_contacts", "Private Contacts", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("rack_planner", "Rack Planner", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("rack_statuses", "Rack Statuses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("racks", "Racks", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("registration_invitations", "Registration Invitations", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("resignations", "Resignations", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("rj45_speed", "Rj45 Speed", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("reports", "Reports Hub", 0, 1, "📊");

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("role_assignment_rights", "Role Assignment Rights", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("role_hierarchy", "Role Hierarchy", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("role_module_permissions", "Role Module Permissions", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("roles_permissions", "Roles & Permissions", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("settings", "Settings", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("supplier_statuses", "Supplier Statuses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("suppliers", "Suppliers", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_port_numbering_layout", "Switch Port Numbering Layout", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_port_types", "Switch Port Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_ports", "Switch Ports", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("switch_status", "Switch Status", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("system_access", "System Access", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ticket_categories", "Ticket Categories", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ticket_priorities", "Ticket Priorities", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ticket_statuses", "Ticket Statuses", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("tickets", "Tickets", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("todo", "Todo", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("todo_categories", "Todo Categories", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("ui_configuration", "Ui Configuration", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_companies", "Employee Companies", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_roles", "Employee Roles", 1, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_sidebar_preferences", "Employee Sidebar Preferences", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("visitors_access_log", "Visitors Access Log", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("vlans", "Vlans", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("warranty_types", "Warranty Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_device_types", "Workstation Device Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_modes", "Workstation Modes", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_office", "Workstation Office", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_os_types", "Workstation Os Types", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_os_versions", "Workstation Os Versions", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("workstation_ram", "Workstation Ram", 0, 1);

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("import", "Bulk Import", 1, 1, "📥");

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("system_status", "System Status", 1, 1, "🖥️");

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("knowledge_base", "Knowledge Base", 0, 1, "🧩");

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("it_settings", "IT Settings", 0, 1, "⚙️");

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`) VALUES ("request_password", "Request Password", 0, 1, "🔑");

-- Data for `company_module_access`
INSERT INTO `company_module_access` (`company_id`, `module_id`, `enabled`)
SELECT c.`id`, mr.`id`, 1
FROM `companies` c
CROSS JOIN `modules_registry` mr
WHERE c.`active` = 1;

-- Data for `company_module_share` (QR/code share matrix — seed enabled rows only for share-capable module slugs)
INSERT INTO `company_module_share` (`company_id`, `module_id`, `enabled`)
SELECT c.`id`, mr.`id`, 1
FROM `companies` c
CROSS JOIN `modules_registry` mr
WHERE c.`active` = 1
  AND mr.`module_slug` IN (
    'notes', 'passwords', 'bookmarks', 'todo', 'events',
    'private_contacts', 'explorer', 'floor_plans', 'rack_planner',
    'employees', 'departments', 'equipment', 'catalogs', 'license_management',
    'inventory_items', 'suppliers', 'alerts', 'tickets', 'patches_updates', 'ops_report',
    'annual_budgets', 'approvals', 'approvals_stage', 'approver_type', 'approvers',
    'budget_categories', 'cost_centers', 'expenses', 'forecast_revisions',
    'forecast_revisions_status', 'gl_accounts', 'monthly_budgets'
  );

INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'IT Operations', 'IT', 'Core IT operations team', 'it-ops@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');

INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Food and Drinks', 'FNB', 'Food and Beverages department', 'fnb@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');

INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Human Resources', 'HR', 'Human resources department', 'hr@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');

INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Housekeeping', 'HK', 'Housekeeping operations', 'housekeeping@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');

INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `description`, `email`, `phone`, `dect`, `extension`, `active`, `created_at`) VALUES (NULL, '1', 'Front Office', 'FO', 'Front Office', 'frontoffice@example.com', NULL, NULL, NULL, '1', '2026-01-01 00:00:01');

INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Revenue', 'Revenue-related general ledger accounts', '1', '2026-01-01 00:00:01');

INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Operating Expense', 'Operational expense accounts', '1', '2026-01-01 00:00:01');

INSERT INTO `budget_categories` (`id`, `company_id`, `name`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Capital Expense', 'Capital expense accounts', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '1', '1', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '2', '6', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '3', '11', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '4', '16', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '5', '21', 'Infrastructure', 'CC-IT-INFRA', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '1', '2', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '2', '7', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '3', '12', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '4', '17', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '5', '22', 'Restaurant Operations', 'CC-FNB-OPS', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '1', '4', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '2', '9', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '3', '14', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '4', '19', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');

INSERT INTO `cost_centers` (`id`, `company_id`, `department_id`, `name`, `code`, `active`, `created_at`) VALUES (NULL, '5', '24', 'Room Maintenance', 'CC-HK-RM', '1', '2026-01-01 00:00:01');

INSERT INTO `gl_accounts` (`id`, `company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, seed.`account_code`, seed.`account_name`, bc.`id`, 1, '2026-01-01 00:00:01'
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, '6100' AS `account_code`, 'IT Maintenance Contracts' AS `account_name`, 'Operating Expense' AS `category_name`
  UNION ALL SELECT 2, 2, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 3, 3, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 4, 4, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 5, 5, '6100', 'IT Maintenance Contracts', 'Operating Expense'
  UNION ALL SELECT 6, 1, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 7, 2, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 8, 3, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 9, 4, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 10, 5, '6200', 'Software Licensing', 'Operating Expense'
  UNION ALL SELECT 11, 1, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 12, 2, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 13, 3, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 14, 4, '7100', 'Capital IT Equipment', 'Capital Expense'
  UNION ALL SELECT 15, 5, '7100', 'Capital IT Equipment', 'Capital Expense'
) seed
INNER JOIN `budget_categories` bc
  ON bc.`company_id` = seed.`company_id`
 AND bc.`name` = seed.`category_name`
ORDER BY seed.`sort_key`;

INSERT INTO `annual_budgets` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `amount`, `created_by`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, cc.`id`, ga.`id`, seed.`year`, seed.`amount`, NULL, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, 2026 AS `year`, 48000.00 AS `amount`, 'Admin' AS `created_username`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin2', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin3', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin4', '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 48000.00, 'Admin5', '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin', '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 2, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin2', '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 3, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin3', '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 4, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin4', '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 5, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 36000.00, 'Admin5', '2026-01-01 00:00:01'
  UNION ALL SELECT 11, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2025, 45000.00, 'Admin', '2025-01-01 00:00:01'
  UNION ALL SELECT 12, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2025, 33000.00, 'Admin', '2025-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
ORDER BY seed.`sort_key`;

INSERT INTO `monthly_budgets` (`id`, `company_id`, `annual_budget_id`, `month`, `amount`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, ab.`id`, seed.`month`, seed.`amount`, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, 2026 AS `year`, 1 AS `month`, 4000.00 AS `amount`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 1, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 3, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 4, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 5, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 11, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 6, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 12, 1, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 7, 4000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 13, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 14, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 3, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 15, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 4, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 16, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 5, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 17, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 6, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 18, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 7, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 19, 2, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 20, 3, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 21, 4, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
  UNION ALL SELECT 22, 5, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 1, 3000.00, '2026-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
INNER JOIN `annual_budgets` ab
  ON ab.`company_id` = seed.`company_id`
 AND ab.`cost_center_id` = cc.`id`
 AND ab.`gl_account_id` = ga.`id`
 AND ab.`year` = seed.`year`
ORDER BY seed.`sort_key`;

INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`) VALUES
(NULL, 1, 1, 1, '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 1, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-02-12', 2450.00, 'Network switch refresh spares', 'INV-IT-2026-0002', 1, 1, '2026-02-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-03-18', 3125.50, 'Endpoint security subscription', 'INV-IT-2026-0003', 1, 1, '2026-03-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-04-09', 1980.00, 'UPS battery replacement', 'INV-IT-2026-0004', 1, 1, '2026-04-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-05-22', 4275.00, 'Wi-Fi controller licence renewal', 'INV-IT-2026-0005', 1, 1, '2026-05-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-06-11', 2650.00, 'Helpdesk tooling annual fee', 'INV-IT-2026-0006', 1, 1, '2026-06-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2026-07-08', 3510.00, 'Server rack PDU upgrade', 'INV-IT-2026-0007', 1, 1, '2026-07-01 00:00:01', NULL),
(NULL, 1, 1, 1, '2025-07-14', 2990.00, 'Prior-year July infrastructure spend', 'INV-IT-2025-0007', 1, 1, '2025-07-01 00:00:01', NULL);

INSERT INTO `expenses` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `date`, `amount`, `description`, `invoice_number`, `created_by`, `active`, `created_at`, `updated_at`)
SELECT NULL, seed.`company_id`, cc.`id`, ga.`id`, seed.`expense_date`, seed.`amount`, seed.`description`, seed.`invoice_number`, NULL, 1, seed.`created_at`, NULL
FROM (
  SELECT 1 AS `sort_key`, 2 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, '2026-01-15' AS `expense_date`, 3890.00 AS `amount`, 'Quarterly preventive maintenance contract renewal' AS `description`, 'INV-IT-2026-0001' AS `invoice_number`, 'Admin2' AS `created_username`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 'Admin3', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 'Admin4', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', '2026-01-15', 3890.00, 'Quarterly preventive maintenance contract renewal', 'INV-IT-2026-0001', 'Admin5', '2026-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
ORDER BY seed.`sort_key`;

INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', NULL, 'General', '1', '2026-01-01 00:00:01');

INSERT INTO `floor_plan_folders` (`id`, `company_id`, `parent_folder_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', '1', 'Level 1', '1', '2026-01-01 00:00:01');

INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', 'Ground Floor', '1', '2026-01-01 00:00:01');

INSERT INTO `floor_plan_tags` (`id`, `company_id`, `name`, `active`, `created_at`) VALUES (NULL, '1', 'Building A', '1', '2026-01-01 00:00:01');

-- Data for `forecast_revisions_status`
INSERT INTO `forecast_revisions_status` (`id`, `company_id`, `status`, `notes`, `active`, `created_at`, `updated_at`) VALUES (NULL, 1, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 1, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Draft', 'Draft projection before finance review', 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Submitted', 'Submitted to finance for February forecast', 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Finance Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Gm Review', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Approved', NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, 'Rejected', NULL, 1, '2026-01-01 00:00:01', NULL);

INSERT INTO `forecast_revisions` (`id`, `company_id`, `cost_center_id`, `gl_account_id`, `year`, `month`, `forecast_amount`, `status`, `locked`, `submitted_by`, `finance_reviewed_by`, `gm_approved_by`, `notes`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, cc.`id`, ga.`id`, seed.`year`, seed.`month`, seed.`forecast_amount`, frs.`id`, 0, NULL, NULL, NULL, seed.`notes`, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Infrastructure' AS `cost_center_name`, 'CC-IT-INFRA' AS `cost_center_code`, '6100' AS `account_code`, 2026 AS `year`, 2 AS `month`, 4200.00 AS `forecast_amount`, 'Draft' AS `status_name`, 'Admin' AS `submitted_username`, 'Draft projection before finance review' AS `notes`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin2', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin3', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin4', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Infrastructure', 'CC-IT-INFRA', '6100', 2026, 2, 4200.00, 'Draft', 'Admin5', 'Draft projection before finance review', '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 2, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin2', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 3, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin3', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 4, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin4', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 5, 'Infrastructure', 'CC-IT-INFRA', '6200', 2026, 2, 3150.00, 'Submitted', 'Admin5', 'Submitted to finance for February forecast', '2026-01-01 00:00:01'
) seed
INNER JOIN `cost_centers` cc
  ON cc.`company_id` = seed.`company_id`
 AND cc.`name` = seed.`cost_center_name`
 AND cc.`code` = seed.`cost_center_code`
INNER JOIN `gl_accounts` ga
  ON ga.`company_id` = seed.`company_id`
 AND ga.`account_code` = seed.`account_code`
INNER JOIN `forecast_revisions_status` frs
  ON frs.`company_id` = seed.`company_id`
 AND frs.`status` = seed.`status_name`
ORDER BY seed.`sort_key`;

INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'Finance Review', 'Finance team review stage before general manager approval.', '1', '2026-01-01 00:00:01');

INSERT INTO `approvals_stage` (`id`, `company_id`, `stage`, `description`, `active`, `created_at`) VALUES (NULL, '1', 'GM Review', 'General manager review stage before final approval.', '1', '2026-01-01 00:00:01');

INSERT INTO `approvals` (`id`, `company_id`, `forecast_revision_id`, `stage`, `status`, `approved_by`, `approved_at`, `comments`, `active`, `created_at`)
SELECT NULL, seed.`company_id`, fr.`id`, aps.`id`, frs.`id`, NULL, NULL, seed.`comments`, 1, seed.`created_at`
FROM (
  SELECT 1 AS `sort_key`, 1 AS `company_id`, 'Submitted to finance for February forecast' AS `forecast_note`, 'Finance Review' AS `stage_name`, 'Finance Review' AS `status_name`, 'Awaiting finance validation for submission batch.' AS `comments`, '2026-01-01 00:00:01' AS `created_at`
  UNION ALL SELECT 2, 2, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 3, 3, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 4, 4, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 5, 5, 'Submitted to finance for February forecast', 'Finance Review', 'Finance Review', 'Awaiting finance validation for submission batch.', '2026-01-01 00:00:01'
  UNION ALL SELECT 6, 1, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 7, 2, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 8, 3, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 9, 4, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
  UNION ALL SELECT 10, 5, 'Draft projection before finance review', 'Finance Review', 'Draft', 'Draft not submitted yet.', '2026-01-01 00:00:01'
) seed
INNER JOIN `forecast_revisions` fr
  ON fr.`company_id` = seed.`company_id`
 AND fr.`notes` = seed.`forecast_note`
INNER JOIN `approvals_stage` aps
  ON aps.`company_id` = seed.`company_id`
 AND aps.`stage` = seed.`stage_name`
INNER JOIN `forecast_revisions_status` frs
  ON frs.`company_id` = seed.`company_id`
 AND frs.`status` = seed.`status_name`
ORDER BY seed.`sort_key`;

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'GM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'HOD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'ISM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'GM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'HOD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'ISM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'GM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'HOD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'ISM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'GM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'HOD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'ISM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'GM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'HOD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'ISM Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '1', 'HRD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '2', 'HRD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '3', 'HRD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '4', 'HRD Approval', '1', '2026-01-01 00:00:01');

INSERT INTO `approver_type` (`id`, `company_id`, `approver_type_description`, `active`, `created_at`) VALUES (NULL, '5', 'HRD Approval', '1', '2026-01-01 00:00:01');

-- Data for `employee_onboarding_requests`
INSERT INTO `employee_onboarding_requests` (`id`, `company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `active`, `created_at`, `updated_at`) VALUES 
(NULL, 1, NULL, 3, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 5, NULL, 15, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 2, NULL, 6, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 3, NULL, 9, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL),
(NULL, 4, NULL, 12, 'SAMPLE', 'NAME', 'FOOD AND DRINKS', '2026-03-24', NULL, 'N/A', 'N/A', 'N/A', 'Waiter', 'N/A', 'N/A', 'N/A', 'N/A', 'SAMPLE NAME', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'Via HR', 'N/A', 'N/A', 'Room Service', NULL, 'Starting date: 16/03/2026 || email@student.com', '2026-03-16', 'HR Recruiter Name', '2026-03-24', '2026-03-24', 'HOD Name', NULL, 'HR Name', NULL, 'ISM Manager Name', NULL, 'GM Name', NULL, 'FIN Name', NULL, 'Waiting', 'Waiting', 'Waiting', 'Waiting', 'Waiting', 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 1, '2026-01-01 00:00:01', NULL);

INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Active', '2026-01-01 00:00:01');

INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Contractor', '2026-01-01 00:00:01');

INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Inactive', '2026-01-01 00:00:01');

INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'On Leave', '2026-01-01 00:00:01');

INSERT INTO `employee_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', NULL, 'Terminated', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('1', '1', 'Team member', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('1', '2', 'Internship', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('2', '3', 'Team member', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('2', '4', 'Internship', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('3', '5', 'Team member', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('3', '6', 'Internship', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('4', '7', 'Team member', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('4', '8', 'Internship', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('5', '9', 'Team member', '2026-01-01 00:00:01');

INSERT INTO `employee_type` (`company_id`, `id`, `name_type`, `created_at`) VALUES ('5', '10', 'Internship', '2026-01-01 00:00:01');

INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');

INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('2', '2', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');

INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('3', '3', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');

INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('4', '4', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');

INSERT INTO `email_smtp_configurations` (`id`, `company_id`, `config_name`, `smtp_host`, `smtp_port`, `username`, `password_encrypted`, `from_email`, `from_name`, `imap_port`, `pop3_port`, `pop3_tls_mode`, `pop3_require_secure_connection`, `is_default`, `active`, `created_at`, `updated_at`) VALUES ('5', '5', 'IT Manager', 'ABestaQuadrada', '25', 'noreply@example.com', NULL, 'noreply@example.com', 'Mail Manager', '143', '110', 'None', '0', '1', '1', '2026-06-18 01:00:00', '2026-06-19 22:49:25');

INSERT INTO `emails` (`id`, `company_id`, `smtp_config_id`, `to_email`, `subject`, `status`, `details`, `sent_at`, `active`, `created_at`) VALUES ('1', '1', '1', 'nelson.salvador@gmail.com', 'Test Email from IT Manager Pro', 'sent', NULL, '2026-06-18 02:06:00', '1', '2026-06-18 02:06:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'warranty_expiry', '1', '30', 'admin@company.com, it@company.com', '1', '2026-06-18 02:00:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'license_expiry', '1', '30', 'admin@company.com', '1', '2026-06-18 02:00:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'certificate_expiry', '0', '30', NULL, '1', '2026-06-18 02:00:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'alerts_expiry', '0', '30', NULL, '1', '2026-06-18 02:00:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'notes_reminder', '0', '0', NULL, '1', '2026-06-18 02:00:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'todo_deadline', '0', '0', NULL, '1', '2026-06-18 02:00:00');

INSERT INTO `email_alert_rules` (`company_id`, `rule_slug`, `enabled`, `days_before`, `notify_emails`, `active`, `created_at`) VALUES ('1', 'events_datetime', '0', '0', NULL, '1', '2026-06-18 02:00:00');

INSERT INTO `employee_positions` (`id`, `company_id`, `department_id`, `name`, `description`, `active`, `created_at`) VALUES (1, 1, 1, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(2, 1, 1, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(3, 1, 2, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(4, 2, 6, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(5, 2, 6, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(6, 2, 7, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(7, 3, 11, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(8, 3, 11, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(9, 3, 12, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(10, 4, 16, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(11, 4, 16, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(12, 4, 17, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01'),
(13, 5, 21, 'IT Manager', 'Leads hotel IT operations and vendor coordination.', 1, '2026-01-01 00:00:01'),
(14, 5, 21, 'System Administrator', 'Maintains hotel servers, PMS integrations, and backups.', 1, '2026-01-01 00:00:01'),
(15, 5, 22, 'Trainee', 'Entry-level operational role for hospitality onboarding.', 1, '2026-01-01 00:00:01');

-- Why: Multi-company seed admins need access_levels + employee_statuses on companies 2–5 before employees INSERT subqueries run.
SET @replicate_source_company_id := COALESCE(@replicate_source_company_id, 1);

INSERT IGNORE INTO `access_levels` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `access_levels` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `employee_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `employee_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT INTO `employees` (`id`, `duplicate`, `company_id`, `first_name`, `last_name`, `display_name`, `work_email`, `personal_email`, `theme`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `mobile_phone`, `external_number`, `dect`, `extension`, `employee_code`, `external_id`, `password`, `vault_key_hash`, `reset_token`, `reset_token_hash`, `reset_token_expires_at`, `role_id`, `access_level_id`, `username`, `department_id`, `job_code`, `comments`, `request_date`, `start_date`, `requested_by`, `termination_requested_by`, `termination_date`, `network_access`, `micros_emc`, `opera_username`, `micros_card`, `pms_id`, `synergy_mms`, `hu_the_lobby`, `navision`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_department_id`, `workstation_mode_id`, `assignment_type_id`, `location_id`, `employment_status_id`, `employee_position_id`, `reports_to`, `on_contacts`, `on_orgchart`, `photo`, `employee_type_id`, `birthday`, `hide_year`, `is_hidden`, `raw_status_code`, `created_at`, `updated_at`) VALUES
(NULL, 0, 1, 'System', 'Admin', 'System Admin1', 'admin@techcorp.example1.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 1 AND `name` = 'Full' LIMIT 1), 'Admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 1 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 2, 'System', 'Admin', 'System Admin2', 'admin@techcorp.example2.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 2 AND `name` = 'Full' LIMIT 1), 'Admin2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 2 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 3, 'System', 'Admin', 'System Admin3', 'admin@techcorp.example3.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 3 AND `name` = 'Full' LIMIT 1), 'Admin3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 3 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 4, 'System', 'Admin', 'System Admin4', 'admin@techcorp.example4.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 4 AND `name` = 'Full' LIMIT 1), 'Admin4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 4 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL),
(NULL, 0, 5, 'System', 'Admin', 'System Admin5', 'admin@techcorp.example5.com', NULL, 'light', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$uICOCOSxZPMi8xEcyJKTjuupQ.MiicyPXuh..kzO.J8VWlfYoqJAi', NULL, NULL, NULL, NULL, NULL, (SELECT `id` FROM `access_levels` WHERE `company_id` = 5 AND `name` = 'Full' LIMIT 1), 'Admin5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, (SELECT `id` FROM `employee_statuses` WHERE `company_id` = 5 AND `name` = 'Active' LIMIT 1), NULL, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, '2026-01-01 00:00:01', NULL);

-- Data for `equipment`
-- Why: Relative warranty keeps company-1 email alert runner / verify_emails_module in the default 30-day window after import.
INSERT INTO `equipment` (`id`, `company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `idf_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`) VALUES (1, 1, 2, NULL, 1, NULL, NULL, 'Primary File Server', 'SN-SRV-001', 'PowerEdge R760', 'srv-file-01', '192.168.10.20', NULL, NULL, 1, NULL, NULL, 1, '2026-06-05', 8500.00, DATE_ADD(CURDATE(), INTERVAL 14 DAY), NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-01 00:00:01', '2026-04-26 22:07:32');

INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Managed', '2026-01-01 00:00:01');

INSERT INTO `equipment_environment` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Unmanaged', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'QSFP 40 Gbps', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'SFP 1 Gbps', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'SFP+ 10 Gbps', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Patch Panel A', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_patch` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Patch Panel B', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Rack A', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_rack` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Rack B', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '2', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '3', '2026-01-01 00:00:01');

INSERT INTO `equipment_fiber_count` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '4', '2026-01-01 00:00:01');

INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('1', '1', 'PoE (802.3af)', 'Up to 15.4W', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('1', '2', 'PoE+ (802.3at)', 'Up to 30W', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_poe` (`company_id`, `id`, `name`, `watts`, `active`, `created_at`) VALUES ('1', '3', 'PoE++ (802.3bt)', 'Up to 60-90W', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '16 ports', '2026-01-01 00:00:01');

INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '24 ports', '2026-01-01 00:00:01');

INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '48 ports', '2026-01-01 00:00:01');

INSERT INTO `equipment_rj45` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '8 ports', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('1', '1', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('2', '1', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('3', '1', 'Cat6', '10 Gbps (up to 55 m), 1 Gbps (100 m)', '250 MHz', '55 m @ 10G', 'Good for most offices/homes.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('4', '1', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('5', '1', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('6', '1', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('7', '2', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('8', '2', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('10', '2', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('11', '2', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('12', '2', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('13', '3', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('14', '3', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('16', '3', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('17', '3', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('18', '3', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('19', '4', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('20', '4', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('22', '4', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('23', '4', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('24', '4', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('25', '5', 'Cat5', '100 Mbps', '100 MHz', '100 m', 'Obsolete; not recommended.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('26', '5', 'Cat5e', '1 Gbps', '100 MHz', '100 m', 'Common in older homes; supports Gigabit.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('28', '5', 'Cat6a', '10 Gbps', '500 MHz', '100 m', 'Best price/performance; future-proof.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('29', '5', 'Cat7', '10 Gbps (100 m), up to 40 Gbps (<=50 m)', '600 MHz', '100 m', 'Shielded; used in EMI-heavy environments.', '1', '2026-01-01 00:00:01');

INSERT INTO `rj45_speed` (`id`, `company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) VALUES ('30', '5', 'Cat8', '25-40 Gbps', '2000 MHz', '30 m', 'Data centers; short-run high-speed links.', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Active', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Decommissioned', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Faulty', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Inactive', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Maintenance', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'On-Order', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Other', '2026-01-01 00:00:01');

INSERT INTO `equipment_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Reserved', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '1', 'Switch', 'SWITCH', '🔀', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '2', 'Server', 'SRV', '🖥️', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '3', 'Router', 'RTR', '✳️', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '4', 'Firewall', 'FW', '🔥', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '5', 'Port Patch Panel', 'PORT', '➿', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '6', 'Access Point', 'AP', '🛜', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '7', 'Workstation', 'WS', '💻', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '8', 'POS', 'POS', '🏧', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '9', 'Printer', 'PRN', '🖨️', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '10', 'Phone', 'PHONE', '📞', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '11', 'CCTV', 'CCCTV', '🎥', '1', '2026-01-01 00:00:01');

INSERT INTO `equipment_types` (`company_id`, `id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) VALUES ('1', '12', 'Other', 'OTHER', NULL, '1', '2026-01-01 00:00:01');

-- Data for `idf_device_type`
INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'switch', '🔀', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('2', '1', 'patch_panel', '➿', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('3', '1', 'ups', '🔋', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('4', '1', 'server', '🖥️', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('5', '1', 'other', '📦', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('26', '1', 'firewall', '🛡️', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('27', '1', 'router', '📡', '1', '2026-01-01 00:00:01', NULL);

INSERT INTO `idf_device_type` (`id`, `company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`) VALUES ('28', '1', 'pdu', '🔌', '1', '2026-01-01 00:00:01', NULL);

-- Data for `idfs`
INSERT INTO `idfs` (`id`, `company_id`, `location_id`, `rack_id`, `name`, `idf_code`, `notes`, `active`, `created_at`) VALUES ('1', '1', '1', '1', 'FO B1.2', 'IDF B1.2', 'FO', '1', '2026-01-01 00:00:01');

INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '1', 'Cables - Ethernet', 'CBL-ETH', '1', '2026-01-01 00:00:01');

INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '2', 'Cables - USB', 'CBL-USB', '1', '2026-01-01 00:00:01');

INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '3', 'Adapters', 'ADP', '1', '2026-01-01 00:00:01');

INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '4', 'Batteries', 'BAT', '1', '2026-01-01 00:00:01');

INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '5', 'Consumables', 'CONS', '1', '2026-01-01 00:00:01');

INSERT INTO `inventory_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '6', 'Other', 'OTH', '1', '2026-01-01 00:00:01');

-- Data for `inventory_items`
INSERT INTO `inventory_items` (`id`, `company_id`, `name`, `item_code`, `serial`, `storage_date`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_employee_id`, `last_employee_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`, `updated_at`) VALUES (1, 1, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 1, 1, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 1, 1, 1, '2026-01-01 00:00:01', '2026-05-17 05:08:05'),
(2, 2, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 7, 9, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 2, 2, 1, '2026-01-01 00:00:01', '2026-05-17 05:08:05'),
(3, 3, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 13, 17, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 3, 3, 1, '2026-01-01 00:00:01', '2026-05-17 05:07:05'),
(4, 4, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 19, 25, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 4, 4, 1, '2026-01-01 00:00:01', '2026-05-17 05:05:19'),
(5, 5, 'Cat6 Cable 2m', 'INV-CAT6-2M', 'SER-CAT6-2M', '2024-01-15', 25, 33, 50, 10, 4.99, NULL, NULL, 'Stock for patching and desktop setups', 5, 5, 1, '2026-01-01 00:00:01', '2026-05-17 05:07:27');

INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '1', 'Per User', '1', '2026-01-01 00:00:01');

INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '2', 'Per Device', '1', '2026-01-01 00:00:01');

INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '3', 'Enterprise', '1', '2026-01-01 00:00:01');

INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '4', 'Subscription', '1', '2026-01-01 00:00:01');

INSERT INTO `license_types` (`company_id`, `id`, `name`, `active`, `created_at`) VALUES ('1', '5', 'Other', '1', '2026-01-01 00:00:01');

-- Data for `it_locations`
INSERT INTO `it_locations` (`id`, `company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`, `updated_at`) VALUES ('1', '1', 'HQ NYC', 'LOC-NY-01', NULL, 'New York', NULL, 'USA', NULL, NULL, '1', '1', '2026-01-01 00:00:01', NULL);

-- Data for `location_types`
INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Branch', '2026-01-01 00:00:01');

INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'DataCenter', '2026-01-01 00:00:01');

INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Headquarters', '2026-01-01 00:00:01');

INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Office', '2026-01-01 00:00:01');

INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'Other', '2026-01-01 00:00:01');

INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Remote', '2026-01-01 00:00:01');

INSERT INTO `location_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Warehouse', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '1', 'Cisco Systems', 'CSCO', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '2', 'Dell Technologies', 'DELL', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '3', 'HP Inc', 'HPE', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '4', 'Juniper Networks', 'JNPR', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '5', 'Ubiquiti Networks', 'UBNT', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '6', 'Apple', 'APPLE', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '7', 'Lenovo', 'LENOVO', '1', '2026-01-01 00:00:01');

INSERT INTO `manufacturers` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '8', 'Microsoft', 'MSFT', '1', '2026-01-01 00:00:01');

--
-- Dumping data for table `catalogs`
--
INSERT INTO `catalogs` (`id`, `company_id`, `model`, `equipment_type_id`, `image_url`, `price`, `supplier_id`, `manufacturer_id`, `product_url`, `active`, `created_at`, `updated_at`) VALUES (1, 1, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 1, 'https://fls-na.amaz', 500.00, NULL, 3, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:57'),
(2, 1, 'Cisco Catalyst C9200L-24P-4G-A', 1, 'https://webobjects2.cdw.com/is/image/CDW/5404745?$product_minithumb$', 3899.00, NULL, 1, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:38'),
(3, 1, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 1, 'https://c1.neweggimages.com/WebResource/Themes/logo_newegg_400400.png', 699.00, NULL, 5, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:33'),
(4, 1, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 1, 'https://www.bhphotovideo.com/', 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:20'),
(5, 1, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 1, 'https://www.bestbuy.com/', 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:23:29'),
(7, 1, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 1, 'https://media.officedepot.com/image/upload/w_130,h_63,c_fill/assets/OfficeDepot_OfficeMax.png', 698.99, NULL, 5, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', '2026-04-13 01:20:28'),
(8, 1, 'Ubiquiti Networks UniFi Switch 24 PoE', 1, 'https://media.sweetwater.com/m/products/image/3c5509fab3bELb9Waebi8c1dQ7M237dDRNdrmnkr.jpg?quality=82&width=1080&height=1080&fit=bounds&canvas=1080%2C1080&ha=3c5509fab31c1f6d', 379.00, NULL, 5, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', '2026-04-13 01:20:22'),
(9, 1, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 1, 'https://www.adorama.com/images/cms/36471Adorama-OG-Preview_30309.jpg', 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', '2026-04-13 01:20:12'),
(10, 1, 'Cisco Meraki MS120-24P Cloud Managed Switch', 1, 'https://www.insight.com/content/dam/insight-web/en_US/thumbnail/insight-thumbnail.png', 1599.00, 1, 1, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', '2026-04-12 16:51:50'),
(11, 5, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 49, NULL, 500.00, NULL, 35, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(13, 3, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 25, NULL, 500.00, NULL, 19, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(14, 2, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 13, NULL, 500.00, NULL, 11, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(15, 5, 'Cisco Catalyst C9200L-24P-4G-A', 49, NULL, 3899.00, NULL, 33, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(17, 3, 'Cisco Catalyst C9200L-24P-4G-A', 25, NULL, 3899.00, NULL, 17, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(18, 2, 'Cisco Catalyst C9200L-24P-4G-A', 13, NULL, 3899.00, NULL, 9, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(19, 5, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 49, NULL, 699.00, NULL, 37, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(21, 3, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 25, NULL, 699.00, NULL, 21, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(22, 2, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 13, NULL, 699.00, NULL, 13, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(23, 5, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 49, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(25, 3, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 25, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(26, 2, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 13, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(27, 5, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 49, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(29, 3, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 25, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(30, 2, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 13, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(31, 5, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 49, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(33, 3, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 25, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(34, 2, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 13, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(35, 5, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 49, NULL, 698.99, NULL, 37, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(37, 3, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 25, NULL, 698.99, NULL, 21, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(38, 2, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 13, NULL, 698.99, NULL, 13, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(39, 5, 'Ubiquiti Networks UniFi Switch 24 PoE', 49, NULL, 379.00, NULL, 37, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(41, 3, 'Ubiquiti Networks UniFi Switch 24 PoE', 25, NULL, 379.00, NULL, 21, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(42, 2, 'Ubiquiti Networks UniFi Switch 24 PoE', 13, NULL, 379.00, NULL, 13, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(43, 5, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 49, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(45, 3, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 25, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(46, 2, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 13, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(47, 5, 'Cisco Meraki MS120-24P Cloud Managed Switch', 49, NULL, 1599.00, NULL, 33, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL),
(49, 3, 'Cisco Meraki MS120-24P Cloud Managed Switch', 25, NULL, 1599.00, NULL, 17, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL),
(50, 2, 'Cisco Meraki MS120-24P Cloud Managed Switch', 13, NULL, 1599.00, NULL, 9, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL),
(84, 4, 'HPE Instant On 1830 48G 24p Class4 PoE 4SFP 370W', 37, NULL, 500.00, NULL, 27, 'https://www.amazon.com/', 1, '2026-01-01 00:00:01', NULL),
(85, 4, 'Cisco Catalyst C9200L-24P-4G-A', 37, NULL, 3899.00, NULL, 25, 'https://www.cdw.com/product/cisco-catalyst-9200l-24port-poe-4x1/5404745', 1, '2026-01-01 00:00:01', NULL),
(86, 4, 'Ubiquiti UniFi Switch Pro 24 PoE (USW-Pro-24-PoE)', 37, NULL, 699.00, NULL, 29, 'https://www.newegg.com/', 1, '2026-01-01 00:00:01', NULL),
(87, 4, 'TP-Link Omada TL-SG2428P 24-Port Gigabit PoE+', 37, NULL, 329.99, NULL, NULL, 'https://www.bhphotovideo.com/', 1, '2026-01-01 00:00:01', NULL),
(88, 4, 'NETGEAR GS108 8-Port Gigabit Ethernet Unmanaged Switch', 37, NULL, 39.99, NULL, NULL, 'https://www.bestbuy.com/', 1, '2026-01-01 00:00:01', NULL),
(89, 4, 'D-Link DGS-108 8-Port Gigabit Desktop Switch', 37, NULL, 29.99, NULL, NULL, 'https://www.walmart.com/', 1, '2026-01-01 00:00:01', NULL),
(90, 4, 'Ubiquiti UniFi Switch USW Pro 24 PoE', 37, NULL, 698.99, NULL, 29, 'https://www.officedepot.com/a/products/5901320/Ubiquiti-UniFi-Switch-USW-Pro-24/', 1, '2026-01-01 00:00:01', NULL),
(91, 4, 'Ubiquiti Networks UniFi Switch 24 PoE', 37, NULL, 379.00, NULL, 29, 'https://www.sweetwater.com/store/detail/USW24POE--ubiquiti-networks-unifi-switch-24-poe', 1, '2026-01-01 00:00:01', NULL),
(92, 4, 'Aruba Instant On 1930 24G 4SFP+ (JL682A)', 37, NULL, 459.99, NULL, NULL, 'https://www.adorama.com/', 1, '2026-01-01 00:00:01', NULL),
(93, 4, 'Cisco Meraki MS120-24P Cloud Managed Switch', 37, NULL, 1599.00, NULL, 25, 'https://www.insight.com/', 1, '2026-01-01 00:00:01', NULL);

INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '2', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '3', 'Resolved', '#00FF00', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_status` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '4', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '1', 'Critical', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '2', 'High', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '3', 'Medium', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '4', 'Low', '2026-01-01 00:00:01');

INSERT INTO `patches_updates_level` (`company_id`, `id`, `level`, `created_at`) VALUES ('1', '5', 'Other', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'All-in-One', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Dotmatrix', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Inkjet', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'Label', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Laser', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '9', 'Other', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Photo', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Thermal', '2026-01-01 00:00:01');

INSERT INTO `printer_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Wide-Format', '2026-01-01 00:00:01');

INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Active', '2026-01-01 00:00:01');

INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Decommissioned', '2026-01-01 00:00:01');

INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Full', '2026-01-01 00:00:01');

INSERT INTO `rack_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Maintenance', '2026-01-01 00:00:01');

INSERT INTO `racks` (`id`, `company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) VALUES ('1', '1', '1', 'Main Rack A', 'RACK-A', '1', '1', '2026-01-01 00:00:01');

-- Data for `employee_sidebar_preferences`
-- Why: seed default sidebar layout per tenant seed admin (Admin, Admin2–Admin5), not employee_id=1 on every company.
INSERT INTO `employee_sidebar_preferences` (`company_id`, `employee_id`, `entry_type`, `entry_id`, `section_id`, `display_order`, `is_visible`, `active`)
SELECT e.`company_id`, e.`id`, t.`entry_type`, t.`entry_id`, t.`section_id`, t.`display_order`, 1 AS `is_visible`, 1 AS `active`
FROM `employees` e
INNER JOIN (
      SELECT 'section' AS entry_type, 'dashboard' AS entry_id, NULL AS section_id, 0 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'management' AS entry_id, NULL AS section_id, 1 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'employee' AS entry_id, NULL AS section_id, 2 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'budgeting' AS entry_id, NULL AS section_id, 3 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'admin' AS entry_id, NULL AS section_id, 4 AS display_order
      UNION ALL SELECT 'section' AS entry_type, 'reference_data' AS entry_id, NULL AS section_id, 5 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'dashboard_link' AS entry_id, 'dashboard' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'settings' AS entry_id, 'dashboard' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment' AS entry_id, 'management' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_workstation' AS entry_id, 'management' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_server' AS entry_id, 'management' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_switch' AS entry_id, 'management' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_printer' AS entry_id, 'management' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_pos' AS entry_id, 'management' AS section_id, 5 AS display_order
            UNION ALL SELECT 'item' AS entry_type, 'tickets' AS entry_id, 'management' AS section_id, 6 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_other' AS entry_id, 'management' AS section_id, 7 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_router' AS entry_id, 'management' AS section_id, 8 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_port_patch_panel' AS entry_id, 'management' AS section_id, 9 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_cctv' AS entry_id, 'management' AS section_id, 10 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_phone' AS entry_id, 'management' AS section_id, 11 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_firewall' AS entry_id, 'management' AS section_id, 12 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'is_access_point' AS entry_id, 'management' AS section_id, 13 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employees' AS entry_id, 'employee' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_system_access' AS entry_id, 'employee' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'system_access' AS entry_id, 'employee' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'departments' AS entry_id, 'employee' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_assignment_history' AS entry_id, 'employee' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'budget_categories' AS entry_id, 'budgeting' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'cost_centers' AS entry_id, 'budgeting' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'gl_accounts' AS entry_id, 'budgeting' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'annual_budgets' AS entry_id, 'budgeting' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'monthly_budgets' AS entry_id, 'budgeting' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'forecast_revisions' AS entry_id, 'budgeting' AS section_id, 5 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'forecast_revisions_status' AS entry_id, 'budgeting' AS section_id, 6 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approvals' AS entry_id, 'budgeting' AS section_id, 7 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approvals_stage' AS entry_id, 'budgeting' AS section_id, 8 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'expenses' AS entry_id, 'budgeting' AS section_id, 9 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'budget_report' AS entry_id, 'budgeting' AS section_id, 10 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'inventory_items' AS entry_id, 'admin' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'companies' AS entry_id, 'admin' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'it_locations' AS entry_id, 'reference_data' AS section_id, 0 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'location_types' AS entry_id, 'reference_data' AS section_id, 1 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_types' AS entry_id, 'reference_data' AS section_id, 2 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_statuses' AS entry_id, 'reference_data' AS section_id, 3 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'manufacturers' AS entry_id, 'reference_data' AS section_id, 4 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'catalogs' AS entry_id, 'reference_data' AS section_id, 5 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'suppliers' AS entry_id, 'reference_data' AS section_id, 6 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'supplier_statuses' AS entry_id, 'reference_data' AS section_id, 7 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'racks' AS entry_id, 'reference_data' AS section_id, 8 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idfs' AS entry_id, 'reference_data' AS section_id, 9 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'rack_statuses' AS entry_id, 'reference_data' AS section_id, 10 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_status' AS entry_id, 'reference_data' AS section_id, 11 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'cable_colors' AS entry_id, 'reference_data' AS section_id, 12 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ticket_categories' AS entry_id, 'reference_data' AS section_id, 13 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ticket_statuses' AS entry_id, 'reference_data' AS section_id, 14 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ticket_priorities' AS entry_id, 'reference_data' AS section_id, 15 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_statuses' AS entry_id, 'reference_data' AS section_id, 16 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_positions' AS entry_id, 'reference_data' AS section_id, 17 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approver_type' AS entry_id, 'reference_data' AS section_id, 18 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'approvers' AS entry_id, 'reference_data' AS section_id, 19 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'audit_logs' AS entry_id, 'reference_data' AS section_id, 20 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'access_levels' AS entry_id, 'reference_data' AS section_id, 21 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'assignment_types' AS entry_id, 'reference_data' AS section_id, 22 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'attempts' AS entry_id, 'reference_data' AS section_id, 23 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_onboarding_requests' AS entry_id, 'reference_data' AS section_id, 24 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_environment' AS entry_id, 'reference_data' AS section_id, 25 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber' AS entry_id, 'reference_data' AS section_id, 26 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber_count' AS entry_id, 'reference_data' AS section_id, 27 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber_patch' AS entry_id, 'reference_data' AS section_id, 28 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_fiber_rack' AS entry_id, 'reference_data' AS section_id, 29 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_poe' AS entry_id, 'reference_data' AS section_id, 30 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'equipment_rj45' AS entry_id, 'reference_data' AS section_id, 31 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'expiring' AS entry_id, 'reference_data' AS section_id, 32 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_device_type' AS entry_id, 'reference_data' AS section_id, 33 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_links' AS entry_id, 'reference_data' AS section_id, 34 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_ports' AS entry_id, 'reference_data' AS section_id, 35 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'idf_positions' AS entry_id, 'reference_data' AS section_id, 36 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'inventory_categories' AS entry_id, 'reference_data' AS section_id, 37 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates' AS entry_id, 'reference_data' AS section_id, 38 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates_level' AS entry_id, 'reference_data' AS section_id, 40 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'patches_updates_status' AS entry_id, 'reference_data' AS section_id, 41 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'printer_device_types' AS entry_id, 'reference_data' AS section_id, 42 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'registration_invitations' AS entry_id, 'reference_data' AS section_id, 43 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'role_assignment_rights' AS entry_id, 'reference_data' AS section_id, 44 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'role_hierarchy' AS entry_id, 'reference_data' AS section_id, 45 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'role_module_permissions' AS entry_id, 'reference_data' AS section_id, 46 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_ports' AS entry_id, 'reference_data' AS section_id, 47 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_port_numbering_layout' AS entry_id, 'reference_data' AS section_id, 48 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'switch_port_types' AS entry_id, 'reference_data' AS section_id, 49 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'ui_configuration' AS entry_id, 'reference_data' AS section_id, 50 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_companies' AS entry_id, 'reference_data' AS section_id, 51 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_roles' AS entry_id, 'reference_data' AS section_id, 52 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'employee_sidebar_preferences' AS entry_id, 'reference_data' AS section_id, 53 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'vlans' AS entry_id, 'reference_data' AS section_id, 54 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'warranty_types' AS entry_id, 'reference_data' AS section_id, 55 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_device_types' AS entry_id, 'reference_data' AS section_id, 56 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_modes' AS entry_id, 'reference_data' AS section_id, 57 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_office' AS entry_id, 'reference_data' AS section_id, 58 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_os_types' AS entry_id, 'reference_data' AS section_id, 59 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_os_versions' AS entry_id, 'reference_data' AS section_id, 60 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'workstation_ram' AS entry_id, 'reference_data' AS section_id, 61 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'floor_plans' AS entry_id, 'reference_data' AS section_id, 62 AS display_order
      UNION ALL SELECT 'item' AS entry_type, 'rj45_speed' AS entry_id, 'reference_data' AS section_id, 63 AS display_order
) AS t
WHERE e.`username` LIKE 'Admin%'
  AND e.`deleted_at` IS NULL
ORDER BY e.`company_id`, FIELD(t.`entry_type`, 'section', 'item'), t.`display_order`, t.`entry_id`;

INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Active', '2026-01-01 00:00:01');

INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Backup', '2026-01-01 00:00:01');

INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Inactive', '2026-01-01 00:00:01');

INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Other', '2026-01-01 00:00:01');

INSERT INTO `supplier_statuses` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Preferred', '2026-01-01 00:00:01');

INSERT INTO `suppliers` (`id`, `company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) VALUES ('1', '1', 'Global IT Supply', 'SUP-001', 'Jane Doe', 'sales@globalit.example', '+1-555-0100', '1', '1', '2026-01-01 00:00:01');

-- Why: Relative expiry dates keep license alert seeds inside the default 30-day runner window after import.
INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('1', '1', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '1', '1', '1', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');

INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('2', '2', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '6', '1', '2', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');

INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('3', '3', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '11', '1', '3', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');

INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('4', '4', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '16', '1', '4', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');

INSERT INTO `license_management` (`id`, `company_id`, `name`, `license_key`, `license_type_id`, `quantity`, `supplier_id`, `purchase_date`, `expiry_date`, `price`, `active`, `notes`, `created_at`) VALUES ('5', '5', 'Microsoft 365 E3', 'XXXXX-XXXXX-XXXXX', '21', '1', '5', '2025-01-15', DATE_ADD(CURDATE(), INTERVAL 20 DAY), '150.00', '1', 'Sample per-user subscription', '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '1', 'Gray', '#808080', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '2', 'Green', '#03b003', 'Printers', '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '3', 'Red', '#ff0000', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '4', 'Yellow', '#ffff00', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '5', 'Black', '#000000', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '6', 'Blue', '#0000ff', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '7', 'White', '#ffffff', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '8', 'Orange', '#ffa500', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '9', 'Dark Pink', '#800080', NULL, '2026-01-01 00:00:01');

INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`) VALUES ('1', '10', 'Other', NULL, NULL, '2026-01-01 00:00:01');

INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Horizontal', '2026-01-01 00:00:01');

INSERT INTO `switch_port_numbering_layout` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Vertical', '2026-01-01 00:00:01');

INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '1', 'RJ45', '2026-01-01 00:00:01');

INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '2', 'SFP', '2026-01-01 00:00:01');

INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '3', 'Door', '2026-01-01 00:00:01');

INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`) VALUES ('1', '16', 'Access Point', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '10', 'Disabled', '1', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '11', 'Down', '3', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '12', 'Err-Disabled', '9', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '13', 'Faulty', '8', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '14', 'Free', '2', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '15', 'Reserved', '4', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '16', 'Testing', '6', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '17', 'Unknown', '1', '2026-01-01 00:00:01');

INSERT INTO `switch_status` (`company_id`, `id`, `status`, `color_id`, `created_at`) VALUES ('1', '18', 'Up', '6', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('1', '1', 'network_access', 'Network Access', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('2', '1', 'micros_emc', 'Micros Emc', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('3', '1', 'opera_username', 'Opera Username', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('4', '1', 'micros_card', 'Micros Card', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('5', '1', 'pms_id', 'PMS Id', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('6', '1', 'synergy_mms', 'Synergy Mms', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('7', '1', 'hu_the_lobby', 'HU The Lobby', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('8', '1', 'navision', 'Navision', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('9', '1', 'onq_ri', 'Onq Ri', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('10', '1', 'birchstreet', 'Birchstreet', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('11', '1', 'delphi', 'Delphi', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('12', '1', 'omina', 'Omina', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('13', '1', 'vingcard_system', 'Vingcard System', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('14', '1', 'digital_rev', 'Digital Rev', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('15', '1', 'office_key_card', 'Office Key Card', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('76', '1', 'email_account', 'Email Account', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('81', '1', 'landline_phone', 'Landline Phone', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('86', '1', 'mobile_phone', 'Mobile Phone', '1', '2026-01-01 00:00:01');

INSERT INTO `system_access` (`id`, `company_id`, `code`, `name`, `active`, `created_at`) VALUES ('91', '1', 'mobile_email', 'Mobile Email', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '1', 'Hardware Issue', 'HW', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '2', 'Network Problem', 'NET', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '3', 'Software Issue', 'SW', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '4', 'Maintenance', 'MAINT', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_categories` (`company_id`, `id`, `name`, `code`, `active`, `created_at`) VALUES ('1', '5', 'Other', 'OTHER', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '1', 'Low', '1', '#0000FF', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '2', 'Normal', '2', '#00FF00', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '3', 'High', '3', '#FFA500', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '4', 'Urgent', '4', '#FF0000', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_priorities` (`company_id`, `id`, `name`, `level`, `color`, `active`, `created_at`) VALUES ('1', '5', 'Critical', '5', '#8B0000', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '1', 'Open', '#FF0000', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '2', 'In Progress', '#FFA500', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `ticket_statuses` (`company_id`, `id`, `name`, `color`, `is_closed`, `active`, `created_at`) VALUES ('1', '4', 'Closed', '#808080', '1', '1', '2026-01-01 00:00:01');

-- Historical note for existing DBs (do not run on fresh import — columns are in CREATE TABLE above):
-- ALTER TABLE `employees` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `raw_status_code`;
-- ALTER TABLE `equipment` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `photo_filename`;
-- ALTER TABLE `patches_updates` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `due_date`;
-- ALTER TABLE `tickets` ADD COLUMN `active` tinyint(1) DEFAULT '1' AFTER `tickets_photos`;
-- Data for `tickets`
INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('1', '1', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '4', '1', '2', '1', '1', '1', NULL, '2026-01-01 00:00:01');

INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('2', '2', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '9', '5', '7', '1', '1', '2', NULL, '2026-01-01 00:00:01');

INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('3', '3', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '14', '9', '12', '1', '1', '3', NULL, '2026-01-01 00:00:01');

INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('4', '4', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '19', '13', '17', '1', '1', '4', NULL, '2026-01-01 00:00:01');

INSERT INTO `tickets` (`id`, `company_id`, `ticket_external_code`, `title`, `description`, `category_id`, `status_id`, `priority_id`, `created_by_employee_id`, `assigned_to_employee_id`, `equipment_id`, `tickets_photos`, `created_at`) VALUES ('5', '5', 'TCK-0001', 'Server patching required', 'Patch cycle for file server', '24', '17', '22', '1', '1', '5', NULL, '2026-01-01 00:00:01');

-- Data for `ui_configuration`
-- Why: Per-company UI defaults belong to that tenant's seed Admin employee (not employee_id=1 for every company).
INSERT INTO `ui_configuration` (`company_id`, `employee_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`, `enable_all_error_reporting`, `enable_audit_logs`, `enable_chatbot`, `enable_auto_scaffolding`, `records_per_page`, `app_name`, `favicon_path`, `equipment_type_sidebar_visibility`, `created_at`, `updated_at`)
SELECT e.`company_id`, e.`id`, 'left', 'left', 'left', 'left', 1, 1, 1, 0, '25', '⚙️ IT Controls', CONCAT('images/favicons/company_', e.`company_id`, '.ico'), '{"is_access_point":1, "is_cctv":1, "is_firewall":1, "is_other":1, "is_phone":1, "is_port_patch_panel":1, "is_printer":1, "is_router":1, "is_server":1, "is_switch":1, "is_workstation":1}', '2026-01-01 00:00:01', NULL
FROM `employees` e
WHERE e.`username` LIKE 'Admin%';

INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'Admin', '2026-01-01 00:00:01');

INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'IT Manager', '2026-01-01 00:00:01');

INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'IT Assistant', '2026-01-01 00:00:01');

INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'Helpdesk', '2026-01-01 00:00:01');

INSERT INTO `employee_roles` (`company_id`, `name`, `created_at`) VALUES (1, 'User', '2026-01-01 00:00:01');

-- Data for `registration_invitations`
INSERT INTO `registration_invitations` (`id`, `company_id`, `email`, `invitation_code`, `invited_by_employee_id`, `role_id`, `access_level_id`, `expires_at`, `accepted_at`, `active`, `created_at`)
SELECT seed.`id`, seed.`company_id`, seed.`email`, seed.`invitation_code`, inviter.`id`, er.`id`, al.`id`, NULL, NULL, 1, '2026-01-01 00:00:01'
FROM (
  SELECT 1 AS `id`, 1 AS `company_id`, 'new.user@techcorp.example' AS `email`, 'INVITE-TECHCORP-001' AS `invitation_code`, 'Admin' AS `inviter_username`, 'Admin' AS `role_name`, 'Full' AS `access_name`
  UNION ALL SELECT 2, 2, 'new.user@datacenterplus.example', 'INVITE-DATACENTERPLUS-001', 'Admin2', 'Admin', 'Full'
  UNION ALL SELECT 3, 3, 'new.user@networksolutions.example', 'INVITE-NETWORKSOLUTIONS-001', 'Admin3', 'Admin', 'Full'
  UNION ALL SELECT 4, 4, 'new.user@cloudtech.example', 'INVITE-CLOUDTECH-001', 'Admin4', 'Admin', 'Full'
  UNION ALL SELECT 5, 5, 'new.user@enterpriseit.example', 'INVITE-ENTERPRISEIT-001', 'Admin5', 'Admin', 'Full'
) seed
LEFT JOIN `employees` inviter
  ON inviter.`company_id` = seed.`company_id`
 AND inviter.`username` = seed.`inviter_username`
LEFT JOIN `employee_roles` er
  ON er.`company_id` = seed.`company_id`
 AND er.`name` = seed.`role_name`
LEFT JOIN `access_levels` al
  ON al.`company_id` = seed.`company_id`
 AND al.`name` = seed.`access_name`
ORDER BY seed.`id`;

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'login', 'success', '192.168.1.10', '2026-01-01 08:00:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'unknown@example.com', '0', 'login', 'failure', '10.0.0.55', '2026-01-01 08:05:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '0', 'login', 'failure', '192.168.1.10', '2026-01-01 08:06:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'admin@techcorp.example', '1', 'password_reset', 'request', '192.168.1.20', '2026-01-02 09:00:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'password_reset', 'reset', '192.168.1.20', '2026-01-02 09:15:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'password_reset', 'success', '192.168.1.20', '2026-01-02 09:16:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'wrong@example.com', '0', 'password_reset', 'failure', '203.0.113.8', '2026-01-03 10:00:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'login', 'success', '127.0.0.1', '2026-01-03 11:00:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES (NULL, 'guest@example.com', '0', 'login', 'failure', '172.16.0.4', '2026-01-04 14:30:01');

INSERT INTO `attempts` (`employee_id`, `email`, `active`, `attempt_source`, `attempt_type`, `ip_address`, `created_at`) VALUES ('1', 'admin@techcorp.example', '1', 'login', 'success', '192.168.1.50', '2026-01-05 07:45:01');

UPDATE `attempts` SET `company_id` = COALESCE(
  (SELECT `company_id` FROM `employees` WHERE `id` = `employee_id` LIMIT 1),
  (SELECT `company_id` FROM `employees` WHERE `work_email` = `email` LIMIT 1),
  (SELECT `id` FROM `companies` ORDER BY `id` ASC LIMIT 1)
) WHERE `company_id` IS NULL;

-- Why: Each seed admin gets home-company access; TechCorp Admin (company 1) also gets companies 2–5 for tenant switcher / MBQA.
INSERT INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `active`, `created_at`)
SELECT e.`id`, e.`company_id`, NULL, 1, '2026-01-01 00:00:01'
FROM `employees` e
WHERE e.`username` LIKE 'Admin%';

INSERT INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `active`, `created_at`)
SELECT e.`id`, c.`id`, NULL, 1, '2026-01-01 00:00:01'
FROM `employees` e
CROSS JOIN `companies` c
WHERE e.`company_id` = 1
  AND e.`username` = 'Admin'
  AND c.`id` BETWEEN 2 AND 5;

-- Why: Hierarchy uses role name lookups (no hardcoded role ids).
INSERT INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`, `created_at`)
SELECT er.`company_id`, er.`id`, ord.`hierarchy_order`, '2026-01-01 00:00:01'
FROM (
  SELECT 'Admin' AS `name`, 1 AS `hierarchy_order`
  UNION ALL SELECT 'IT Manager', 2
  UNION ALL SELECT 'IT Assistant', 3
  UNION ALL SELECT 'Helpdesk', 4
  UNION ALL SELECT 'User', 5
) ord
INNER JOIN `employee_roles` er ON er.`name` = ord.`name`;

-- Why: Permission seeds resolve role_id by tenant + role name (no hardcoded ids).
INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`)
SELECT er.`company_id`, er.`id`, 'ALL', 1, 1, 1, 1, 1, 1, '2026-01-01 00:00:01'
FROM `employee_roles` er
WHERE er.`name` = 'Admin';

INSERT INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`)
SELECT er.`company_id`, er.`id`, 'Tickets', 1, 1, 1, 1, 1, 1, '2026-01-01 00:00:01'
FROM `employee_roles` er
WHERE er.`name` IN ('Helpdesk', 'User');

-- Why: Assignment rights resolve both sides by role name within the same company_id.
INSERT INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`, `created_at`)
SELECT src.`company_id`, src.`id`, tgt.`id`, '2026-01-01 00:00:01'
FROM `employee_roles` src
INNER JOIN `employee_roles` tgt ON tgt.`company_id` = src.`company_id`
INNER JOIN (
  SELECT 'Admin' AS `role_name`, 'IT Manager' AS `target_name`
  UNION ALL SELECT 'Admin', 'IT Assistant'
  UNION ALL SELECT 'Admin', 'Helpdesk'
  UNION ALL SELECT 'Admin', 'User'
  UNION ALL SELECT 'IT Manager', 'IT Assistant'
  UNION ALL SELECT 'IT Manager', 'Helpdesk'
  UNION ALL SELECT 'IT Manager', 'User'
  UNION ALL SELECT 'IT Assistant', 'Helpdesk'
  UNION ALL SELECT 'IT Assistant', 'User'
) map ON map.`role_name` = src.`name` AND map.`target_name` = tgt.`name`;

INSERT INTO `vlans` (`id`, `company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) VALUES ('1', '1', '1', 'Factory Default', '#2E86DE', '192.168.10.0/24', '192.168.10.10', 'Primary office VLAN', '192.168.10.1', '1', '2026-01-01 00:00:01');

INSERT INTO `ip_subnets` (`company_id`, `vlan_id`, `cidr`, `network_ip`, `prefix_length`, `gateway_ip`, `dns1_ip`, `dns2_ip`, `dhcp_enabled`, `description`, `active`, `created_at`) VALUES ('1', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('2', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('3', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('4', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01'),
('5', NULL, '192.168.10.0/24', '192.168.10.0', '24', '192.168.10.1', NULL, NULL, '1', NULL, '1', '2026-01-01 00:00:01');

INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Enterprise', '2026-01-01 00:00:01');

INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Extended', '2026-01-01 00:00:01');

INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'None', '2026-01-01 00:00:01');

INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Other', '2026-01-01 00:00:01');

INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Premium', '2026-01-01 00:00:01');

INSERT INTO `warranty_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Standard', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'All-in-One', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Desktop', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Laptop', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Mobile', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Other', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'POS', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Tablet', '2026-01-01 00:00:01');

INSERT INTO `workstation_device_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Thin-Client', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '1', 'Desktop + 1 Monitor', 'MODE-PC-1MON', 'Desktop with 1 Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '2', 'Desktop + 2 Monitors', 'MODE-PC-2MON', 'Desktop with 2 Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '3', 'Laptop Only', 'MODE-LAP', 'Single Laptop', '0', '0', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '4', 'All-in-One', 'MODE-AIO', 'All-in-One Device', '0', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '5', 'Shared Setup', 'MODE-SHARED', 'Shared Workstation', '1', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '6', 'Laptop + Dock', 'MODE-LAP-DOCK', 'Laptop with Docking Station', '0', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '7', 'Laptop + Dock + Monitor', 'MODE-LAP-DOCK1', 'Laptop with Docking Station & Monitor', '1', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '8', 'Laptop + Dock + Monitors', 'MODE-LAP-DOCK2', 'Laptop with Docking Station & Monitors', '2', '1', '0', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '9', 'POS Only', 'MODE-POS', 'Point of Sale Terminal', '0', '0', '1', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '10', 'POS + Desktop', 'MODE-POS1', 'Point of Sale Terminal + Desktop', '1', '1', '1', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_modes` (`company_id`, `id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) VALUES ('1', '11', 'POS + Laptop', 'MODE-POS2', 'Point of Sale Terminal + Laptop', '0', '1', '1', '1', '2026-01-01 00:00:01');

INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'None', '2026-01-01 00:00:01');

INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Office 2024 Pro', '2026-01-01 00:00:01');

INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Office 2024 STD', '2026-01-01 00:00:01');

INSERT INTO `workstation_office` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Office 365', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', 'Windows', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', 'Windows 11', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', 'Windows 10', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', 'Windows Server', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', 'Windows Server 2012', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', 'Windows Server 2016', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '7', 'Windows Server 2019', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '8', 'Windows Server 2022', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '9', 'Windows Server 2025', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '10', 'Android', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '11', 'iOS', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '12', 'ChromeOS', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '13', 'Linux', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '14', 'macOS', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_types` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '15', 'Other', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '24H2', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '25H2', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '26H2', '2026-01-01 00:00:01');

INSERT INTO `workstation_os_versions` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '10 LTSC', '2026-01-01 00:00:01');

INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '1', '4 GB', '2026-01-01 00:00:01');

INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '2', '8 GB', '2026-01-01 00:00:01');

INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '3', '16 GB', '2026-01-01 00:00:01');

INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '4', '32 GB', '2026-01-01 00:00:01');

INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '5', '64 GB', '2026-01-01 00:00:01');

INSERT INTO `workstation_ram` (`company_id`, `id`, `name`, `created_at`) VALUES ('1', '6', '128 GB', '2026-01-01 00:00:01');

-- Replicate shared table data to all companies
SET @replicate_source_company_id := COALESCE(@replicate_source_company_id, 1);

INSERT IGNORE INTO `access_levels` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `access_levels` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `assignment_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `assignment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `budget_categories` (`company_id`, `name`, `description`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`description`, t.`active`, '2026-01-01 00:00:01' FROM `budget_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `floor_plan_folders` (`company_id`, `parent_folder_id`, `name`, `active`, `created_at`)
SELECT c.`id`, NULL, t.`name`, t.`active`, '2026-01-01 00:00:01'
FROM `floor_plan_folders` t
JOIN `companies` c ON c.`id` <> t.`company_id`
WHERE t.`company_id` = @replicate_source_company_id AND t.`parent_folder_id` IS NULL;

INSERT IGNORE INTO `floor_plan_tags` (`company_id`, `name`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`active`, '2026-01-01 00:00:01' FROM `floor_plan_tags` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `gl_accounts` (`company_id`, `account_code`, `account_name`, `category_id`, `active`, `created_at`)
SELECT
    c.`id`,
    ga.`account_code`,
    ga.`account_name`,
    target_bc.`id`,
    ga.`active`,
    '2026-01-01 00:00:01'
FROM `gl_accounts` ga
JOIN `companies` c ON c.`id` <> ga.`company_id`
LEFT JOIN `budget_categories` source_bc ON source_bc.`id` = ga.`category_id`
LEFT JOIN `budget_categories` target_bc ON target_bc.`company_id` = c.`id` AND target_bc.`name` = source_bc.`name`
WHERE ga.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `employee_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `employee_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `employee_positions` (`company_id`, `department_id`, `name`, `description`, `active`, `created_at`)
SELECT
    c.`id`,
    d_target.`id`,
    t.`name`,
    t.`description`,
    t.`active`,
    '2026-01-01 00:00:01'
FROM `employee_positions` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `departments` d_source ON d_source.`id` = t.`department_id`
LEFT JOIN `departments` d_target ON d_target.`company_id` = c.`id` AND d_target.`name` = d_source.`name`
WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_environment` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_environment` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_fiber` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_fiber_patch` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber_patch` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_fiber_rack` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber_rack` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_fiber_count` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_fiber_count` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_poe` (`company_id`, `name`, `watts`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`watts`, t.`active`, '2026-01-01 00:00:01' FROM `equipment_poe` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_rj45` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_rj45` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `rj45_speed` (`company_id`, `cable_type`, `max_speed`, `bandwidth`, `max_distance_full_speed`, `notes`, `active`, `created_at`) SELECT c.`id`, t.`cable_type`, t.`max_speed`, t.`bandwidth`, t.`max_distance_full_speed`, t.`notes`, t.`active`, '2026-01-01 00:00:01' FROM `rj45_speed` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `equipment_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `equipment_types` (`company_id`, `name`, `code`, `field_edit_emoji`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`field_edit_emoji`, t.`active`, '2026-01-01 00:00:01' FROM `equipment_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `inventory_categories` (`company_id`, `name`, `code`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`active`, '2026-01-01 00:00:01' FROM `inventory_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `location_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `location_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `manufacturers` (`company_id`, `name`, `code`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`active`, '2026-01-01 00:00:01' FROM `manufacturers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `forecast_revisions_status` (`company_id`, `status`, `active`, `created_at`) SELECT c.`id`, t.`status`, t.`active`, '2026-01-01 00:00:01' FROM `forecast_revisions_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `approvals_stage` (`company_id`, `stage`, `active`, `created_at`) SELECT c.`id`, t.`stage`, t.`active`, '2026-01-01 00:00:01' FROM `approvals_stage` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

-- Why: catalogs are seeded per tenant in the INSERT block above (with tenant FK ids). Replicating company-1 rows here duplicated models and kept company-1 equipment_type_id/manufacturer_id/supplier_id values.
INSERT IGNORE INTO `printer_device_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `printer_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `rack_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `rack_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `supplier_statuses` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `supplier_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `cable_colors` (`company_id`, `color_name`, `hex_color`, `comments`, `created_at`) SELECT c.`id`, t.`color_name`, t.`hex_color`, t.`comments`, '2026-01-01 00:00:01' FROM `cable_colors` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `switch_port_numbering_layout` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `switch_port_numbering_layout` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `switch_port_types` (`company_id`, `type`, `created_at`) SELECT c.`id`, t.`type`, '2026-01-01 00:00:01' FROM `switch_port_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `switch_status` (`company_id`, `status`, `created_at`) SELECT c.`id`, t.`status`, '2026-01-01 00:00:01' FROM `switch_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `ticket_categories` (`company_id`, `name`, `code`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`active`, '2026-01-01 00:00:01' FROM `ticket_categories` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `ticket_priorities` (`company_id`, `name`, `level`, `color`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`level`, t.`color`, t.`active`, '2026-01-01 00:00:01' FROM `ticket_priorities` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `ticket_statuses` (`company_id`, `name`, `color`, `is_closed`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`color`, t.`is_closed`, t.`active`, '2026-01-01 00:00:01' FROM `ticket_statuses` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `employee_roles` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `employee_roles` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

-- Why: Seed admins (username LIKE Admin%) insert before employee_roles; bind tenant Admin role_id after replication so companies 2–5 have roles.
UPDATE `employees` e
INNER JOIN `employee_roles` er ON er.`company_id` = e.`company_id` AND er.`name` = 'Admin'
SET e.`role_id` = er.`id`
WHERE e.`username` LIKE 'Admin%';

INSERT IGNORE INTO `warranty_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `warranty_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `license_types` (`company_id`, `name`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`active`, '2026-01-01 00:00:01' FROM `license_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `idf_device_type` (`company_id`, `idfdevicetype_name`, `created_at`) SELECT c.`id`, t.`idfdevicetype_name`, '2026-01-01 00:00:01' FROM `idf_device_type` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `patches_updates_status` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `patches_updates_status` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `patches_updates_level` (`company_id`, `level`, `created_at`) SELECT c.`id`, t.`level`, '2026-01-01 00:00:01' FROM `patches_updates_level` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `workstation_device_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_device_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `workstation_modes` (`company_id`, `mode_name`, `mode_code`, `description`, `monitor_count`, `has_keyboard_mouse`, `pos`, `active`, `created_at`) SELECT c.`id`, t.`mode_name`, t.`mode_code`, t.`description`, t.`monitor_count`, t.`has_keyboard_mouse`, t.`pos`, t.`active`, '2026-01-01 00:00:01' FROM `workstation_modes` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `workstation_office` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_office` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `workstation_os_types` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_os_types` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `workstation_os_versions` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_os_versions` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `workstation_ram` (`company_id`, `name`, `created_at`) SELECT c.`id`, t.`name`, '2026-01-01 00:00:01' FROM `workstation_ram` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `departments` (`company_id`, `name`, `code`, `description`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`code`, t.`description`, t.`active`, '2026-01-01 00:00:01' FROM `departments` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT INTO `employee_onboarding_requests` (`company_id`, `employee_id`, `employee_position_id`, `first_name`, `last_name`, `department_name`, `request_date`, `termination_date`, `network_access`, `micros_emc`, `opera`, `micros_card`, `pms_id`, `synergy_mms`, `email_account`, `landline_phone`, `hu_the_lobby`, `mobile_phone`, `navision`, `mobile_email`, `onq_ri`, `birchstreet`, `delphi`, `omina`, `vingcard_system`, `digital_rev`, `office_key_card`, `office_key_card_dep`, `comments`, `starting_date`, `requested_by`, `requested_by_date`, `requested_on`, `hod_approval`, `hod_approval_date`, `hrd_approval`, `hrd_approval_date`, `ism_approval`, `ism_approval_date`, `gm_approval`, `gm_approval_date`, `fin_approval`, `fin_approval_date`, `status_hod`, `status_hrd`, `status_ism`, `status_gm`, `status_fin`, `email_sent_hod`, `email_sent_hod_at`, `email_sent_hrd`, `email_sent_hrd_at`, `email_sent_ism`, `email_sent_ism_at`, `email_sent_gm`, `email_sent_gm_at`, `email_sent_fin`, `email_sent_fin_at`, `created_at`)
SELECT c.`id`, t.`employee_id`, ep_target.`id`, t.`first_name`, t.`last_name`, t.`department_name`, t.`request_date`, t.`termination_date`, t.`network_access`, t.`micros_emc`, t.`opera`, t.`micros_card`, t.`pms_id`, t.`synergy_mms`, t.`email_account`, t.`landline_phone`, t.`hu_the_lobby`, t.`mobile_phone`, t.`navision`, t.`mobile_email`, t.`onq_ri`, t.`birchstreet`, t.`delphi`, t.`omina`, t.`vingcard_system`, t.`digital_rev`, t.`office_key_card`, t.`office_key_card_dep`, t.`comments`, t.`starting_date`, t.`requested_by`, t.`requested_by_date`, t.`requested_on`, t.`hod_approval`, t.`hod_approval_date`, t.`hrd_approval`, t.`hrd_approval_date`, t.`ism_approval`, t.`ism_approval_date`, t.`gm_approval`, t.`gm_approval_date`, t.`fin_approval`, t.`fin_approval_date`, COALESCE(NULLIF(t.`status_hod`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_hrd`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_ism`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_gm`, ''), 'Waiting'), COALESCE(NULLIF(t.`status_fin`, ''), 'Waiting'), COALESCE(t.`email_sent_hod`, 0), t.`email_sent_hod_at`, COALESCE(t.`email_sent_hrd`, 0), t.`email_sent_hrd_at`, COALESCE(t.`email_sent_ism`, 0), t.`email_sent_ism_at`, COALESCE(t.`email_sent_gm`, 0), t.`email_sent_gm_at`, COALESCE(t.`email_sent_fin`, 0), t.`email_sent_fin_at`, '2026-01-01 00:00:01'
FROM `employee_onboarding_requests` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `employee_positions` ep_source ON ep_source.`id` = t.`employee_position_id`
LEFT JOIN `employee_positions` ep_target ON ep_target.`company_id` = c.`id` AND ep_target.`name` = ep_source.`name`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
      SELECT 1
      FROM `employee_onboarding_requests` e
      WHERE e.`company_id` = c.`id`
        AND COALESCE(e.`employee_id`, 0) = COALESCE(t.`employee_id`, 0)
        AND COALESCE(e.`first_name`, '') = COALESCE(t.`first_name`, '')
        AND COALESCE(e.`last_name`, '') = COALESCE(t.`last_name`, '')
        AND COALESCE(e.`starting_date`, '1000-01-01') = COALESCE(t.`starting_date`, '1000-01-01')
        AND COALESCE(e.`request_date`, '1000-01-01') = COALESCE(t.`request_date`, '1000-01-01')
  );

-- Why: department_id and supplier_id resolve by name on the target company; unmatched or NULL source rows stay NULL (same FK remap pattern as location/rack). assigned_to_employee_id stays NULL (no employee remap).
INSERT IGNORE INTO `equipment` (`company_id`, `equipment_type_id`, `manufacturer_id`, `location_id`, `rack_id`, `name`, `serial_number`, `model`, `hostname`, `ip_address`, `patch_port`, `mac_address`, `department_id`, `supplier_id`, `assigned_to_employee_id`, `status_id`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `certificate_expiry`, `warranty_type_id`, `printer_device_type_id`, `printer_color_capable`, `printer_scan`, `workstation_device_type_id`, `workstation_os_type_id`, `workstation_office_id`, `workstation_processor`, `workstation_storage`, `workstation_os_installed_on`, `workstation_ram_id`, `workstation_os_version_id`, `rj45_speed_id`, `switch_rj45_id`, `switch_port_numbering_layout_id`, `switch_fiber_id`, `switch_fiber_patch_id`, `switch_fiber_rack_id`, `switch_fiber_ports_number`, `switch_fiber_port_label`, `switch_poe_id`, `switch_environment_id`, `notes`, `photo_filename`, `created_at`, `updated_at`)
SELECT
    c.`id`,
    COALESCE(et_target.`id`, et_fallback.`id`),
    m_target.`id`,
    l_target.`id`,
    r_target.`id`,
    t.`name`, t.`serial_number`, t.`model`, t.`hostname`, t.`ip_address`, t.`patch_port`, t.`mac_address`,
    dept_target.`id`,
    supp_target.`id`,
    NULL,
    COALESCE(es_target.`id`, es_fallback.`id`),
    t.`purchase_date`, t.`purchase_cost`, t.`warranty_expiry`, t.`certificate_expiry`,
    wt_target.`id`,
    pdt_target.`id`,
    t.`printer_color_capable`,
    t.`printer_scan`,
    wdt_target.`id`,
    wot_target.`id`,
    wo_target.`id`,
    t.`workstation_processor`, t.`workstation_storage`, t.`workstation_os_installed_on`,
    wr_target.`id`,
    wov_target.`id`,
    rj45_speed_target.`id`,
    rj45_target.`id`,
    spnl_target.`id`,
    fiber_target.`id`,
    fiber_patch_target.`id`,
    fiber_rack_target.`id`,
    t.`switch_fiber_ports_number`,
    t.`switch_fiber_port_label`,
    poe_target.`id`,
    env_target.`id`,
    t.`notes`, t.`photo_filename`, '2026-01-01 00:00:01', t.`updated_at`
FROM `equipment` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `equipment_types` et_source ON et_source.`id` = t.`equipment_type_id`
LEFT JOIN `equipment_types` et_target ON et_target.`company_id` = c.`id` AND et_target.`name` = et_source.`name`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `equipment_types`
    GROUP BY `company_id`
) et_fallback ON et_fallback.`company_id` = c.`id`
LEFT JOIN `manufacturers` m_source ON m_source.`id` = t.`manufacturer_id`
LEFT JOIN `manufacturers` m_target ON m_target.`company_id` = c.`id` AND m_target.`name` = m_source.`name`
LEFT JOIN `it_locations` l_source ON l_source.`id` = t.`location_id`
LEFT JOIN `it_locations` l_target ON l_target.`company_id` = c.`id` AND l_target.`name` = l_source.`name`
LEFT JOIN `racks` r_source ON r_source.`id` = t.`rack_id`
LEFT JOIN `racks` r_target ON r_target.`company_id` = c.`id` AND r_target.`name` = r_source.`name`
LEFT JOIN `departments` dept_source ON dept_source.`id` = t.`department_id`
LEFT JOIN `departments` dept_target ON dept_target.`company_id` = c.`id` AND dept_target.`name` = dept_source.`name`
LEFT JOIN `suppliers` supp_source ON supp_source.`id` = t.`supplier_id`
LEFT JOIN `suppliers` supp_target ON supp_target.`company_id` = c.`id` AND supp_target.`name` = supp_source.`name`
LEFT JOIN `equipment_statuses` es_source ON es_source.`id` = t.`status_id`
LEFT JOIN `equipment_statuses` es_target ON es_target.`company_id` = c.`id` AND es_target.`name` = es_source.`name`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `equipment_statuses`
    GROUP BY `company_id`
) es_fallback ON es_fallback.`company_id` = c.`id`
LEFT JOIN `warranty_types` wt_source ON wt_source.`id` = t.`warranty_type_id`
LEFT JOIN `warranty_types` wt_target ON wt_target.`company_id` = c.`id` AND wt_target.`name` = wt_source.`name`
LEFT JOIN `printer_device_types` pdt_source ON pdt_source.`id` = t.`printer_device_type_id`
LEFT JOIN `printer_device_types` pdt_target ON pdt_target.`company_id` = c.`id` AND pdt_target.`name` = pdt_source.`name`
LEFT JOIN `workstation_device_types` wdt_source ON wdt_source.`id` = t.`workstation_device_type_id`
LEFT JOIN `workstation_device_types` wdt_target ON wdt_target.`company_id` = c.`id` AND wdt_target.`name` = wdt_source.`name`
LEFT JOIN `workstation_os_types` wot_source ON wot_source.`id` = t.`workstation_os_type_id`
LEFT JOIN `workstation_os_types` wot_target ON wot_target.`company_id` = c.`id` AND wot_target.`name` = wot_source.`name`
LEFT JOIN `workstation_office` wo_source ON wo_source.`id` = t.`workstation_office_id`
LEFT JOIN `workstation_office` wo_target ON wo_target.`company_id` = c.`id` AND wo_target.`name` = wo_source.`name`
LEFT JOIN `workstation_ram` wr_source ON wr_source.`id` = t.`workstation_ram_id`
LEFT JOIN `workstation_ram` wr_target ON wr_target.`company_id` = c.`id` AND wr_target.`name` = wr_source.`name`
LEFT JOIN `workstation_os_versions` wov_source ON wov_source.`id` = t.`workstation_os_version_id`
LEFT JOIN `workstation_os_versions` wov_target ON wov_target.`company_id` = c.`id` AND wov_target.`name` = wov_source.`name`
LEFT JOIN `rj45_speed` rj45_speed_source ON rj45_speed_source.`id` = t.`rj45_speed_id`
LEFT JOIN `rj45_speed` rj45_speed_target ON rj45_speed_target.`company_id` = c.`id` AND rj45_speed_target.`cable_type` = rj45_speed_source.`cable_type`
LEFT JOIN `equipment_rj45` rj45_source ON rj45_source.`id` = t.`switch_rj45_id`
LEFT JOIN `equipment_rj45` rj45_target ON rj45_target.`company_id` = c.`id` AND rj45_target.`name` = rj45_source.`name`
LEFT JOIN `switch_port_numbering_layout` spnl_source ON spnl_source.`id` = t.`switch_port_numbering_layout_id`
LEFT JOIN `switch_port_numbering_layout` spnl_target ON spnl_target.`company_id` = c.`id` AND spnl_target.`name` = spnl_source.`name`
LEFT JOIN `equipment_fiber` fiber_source ON fiber_source.`id` = t.`switch_fiber_id`
LEFT JOIN `equipment_fiber` fiber_target ON fiber_target.`company_id` = c.`id` AND fiber_target.`name` = fiber_source.`name`
LEFT JOIN `equipment_fiber_patch` fiber_patch_source ON fiber_patch_source.`id` = t.`switch_fiber_patch_id`
LEFT JOIN `equipment_fiber_patch` fiber_patch_target ON fiber_patch_target.`company_id` = c.`id` AND fiber_patch_target.`name` = fiber_patch_source.`name`
LEFT JOIN `equipment_fiber_rack` fiber_rack_source ON fiber_rack_source.`id` = t.`switch_fiber_rack_id`
LEFT JOIN `equipment_fiber_rack` fiber_rack_target ON fiber_rack_target.`company_id` = c.`id` AND fiber_rack_target.`name` = fiber_rack_source.`name`
LEFT JOIN `equipment_poe` poe_source ON poe_source.`id` = t.`switch_poe_id`
LEFT JOIN `equipment_poe` poe_target ON poe_target.`company_id` = c.`id` AND poe_target.`name` = poe_source.`name`
LEFT JOIN `equipment_environment` env_source ON env_source.`id` = t.`switch_environment_id`
LEFT JOIN `equipment_environment` env_target ON env_target.`company_id` = c.`id` AND env_target.`name` = env_source.`name`
WHERE t.`company_id` = @replicate_source_company_id
  AND COALESCE(et_target.`id`, et_fallback.`id`) IS NOT NULL
  AND COALESCE(es_target.`id`, es_fallback.`id`) IS NOT NULL;

INSERT IGNORE INTO `idf_ports` (`company_id`, `position_id`, `port_no`, `port_type`, `label`, `status_id`, `connected_to`, `vlan_id`, `speed_id`, `rj45_speed_id`, `poe_id`, `cable_color`, `hex_color`, `notes`, `created_at`, `updated_at`) SELECT c.`id`, t.`position_id`, t.`port_no`, t.`port_type`, t.`label`, t.`status_id`, t.`connected_to`, t.`vlan_id`, t.`speed_id`, t.`rj45_speed_id`, t.`poe_id`, t.`cable_color`, t.`hex_color`, t.`notes`, '2026-01-01 00:00:01', t.`updated_at` FROM `idf_ports` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT INTO `idf_device_type` (`company_id`, `idfdevicetype_name`, `field_edit_emoji`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, t.`idfdevicetype_name`, t.`field_edit_emoji`, t.`active`, '2026-01-01 00:00:01', t.`updated_at`
FROM `idf_device_type` t
JOIN `companies` c ON c.`id` <> t.`company_id`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
    SELECT 1
    FROM `idf_device_type` t_existing
    WHERE t_existing.`company_id` = c.`id`
      AND t_existing.`idfdevicetype_name` = t.`idfdevicetype_name`
  );

INSERT INTO `idf_positions` (`company_id`, `idf_id`, `position_no`, `device_type`, `device_name`, `equipment_id`, `rj45_count`, `sfp_count`, `price`, `notes`, `created_at`, `updated_at`)
SELECT c.`id`, t.`idf_id`, t.`position_no`, dt_target.`id`, t.`device_name`, t.`equipment_id`, t.`rj45_count`, t.`sfp_count`, t.`price`, t.`notes`, '2026-01-01 00:00:01', t.`updated_at`
FROM `idf_positions` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `idf_device_type` dt_source ON dt_source.`id` = t.`device_type`
LEFT JOIN `idf_device_type` dt_target ON dt_target.`company_id` = c.`id` AND dt_target.`idfdevicetype_name` = dt_source.`idfdevicetype_name`
WHERE t.`company_id` = @replicate_source_company_id
  AND dt_target.`id` IS NOT NULL;

INSERT IGNORE INTO `idfs` (`company_id`, `location_id`, `name`, `idf_code`, `notes`, `created_at`) SELECT c.`id`, t.`location_id`, t.`name`, t.`idf_code`, t.`notes`, '2026-01-01 00:00:01' FROM `idfs` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `inventory_items` (`company_id`, `name`, `item_code`, `serial`, `category_id`, `manufacturer_id`, `quantity_on_hand`, `quantity_minimum`, `price_eur`, `last_employee_id`, `last_employee_manual`, `comments`, `location_id`, `supplier_id`, `active`, `created_at`)
SELECT
    c.`id`,
    t.`name`,
    t.`item_code`,
    t.`serial`,
    ic_target.`id`,
    m_target.`id`,
    t.`quantity_on_hand`,
    t.`quantity_minimum`,
    t.`price_eur`,
    NULL,
    t.`last_employee_manual`,
    t.`comments`,
    l_target.`id`,
    s_target.`id`,
    t.`active`,
    '2026-01-01 00:00:01'
FROM `inventory_items` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `inventory_categories` ic_source ON ic_source.`id` = t.`category_id`
LEFT JOIN `inventory_categories` ic_target ON ic_target.`company_id` = c.`id` AND ic_target.`name` = ic_source.`name`
LEFT JOIN `manufacturers` m_source ON m_source.`id` = t.`manufacturer_id`
LEFT JOIN `manufacturers` m_target ON m_target.`company_id` = c.`id` AND m_target.`name` = m_source.`name`
LEFT JOIN `it_locations` l_source ON l_source.`id` = t.`location_id`
LEFT JOIN `it_locations` l_target ON l_target.`company_id` = c.`id` AND l_target.`name` = l_source.`name`
LEFT JOIN `suppliers` s_source ON s_source.`id` = t.`supplier_id`
LEFT JOIN `suppliers` s_target ON s_target.`company_id` = c.`id` AND s_target.`name` = s_source.`name`
WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `it_locations` (`company_id`, `name`, `location_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `type_id`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`location_code`, t.`address`, t.`city`, t.`state`, t.`country`, t.`postal_code`, t.`phone`, t.`type_id`, t.`active`, '2026-01-01 00:00:01' FROM `it_locations` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `racks` (`company_id`, `location_id`, `name`, `rack_code`, `status_id`, `active`, `created_at`) SELECT c.`id`, t.`location_id`, t.`name`, t.`rack_code`, t.`status_id`, t.`active`, '2026-01-01 00:00:01' FROM `racks` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `suppliers` (`company_id`, `name`, `supplier_code`, `contact_person`, `email`, `phone`, `status_id`, `active`, `created_at`) SELECT c.`id`, t.`name`, t.`supplier_code`, t.`contact_person`, t.`email`, t.`phone`, t.`status_id`, t.`active`, '2026-01-01 00:00:01' FROM `suppliers` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `switch_ports` (`company_id`, `equipment_id`, `hostname`, `port_type`, `port_number`, `to_patch_port`, `status_id`, `color_id`, `vlan_id`, `fiber_port_id`, `fiber_patch_id`, `fiber_rack_id`, `idf_id`, `comments`, `created_at`, `updated_at`)
SELECT
    c.`id`,
    e_target.`id`,
    t.`hostname`,
    t.`port_type`,
    t.`port_number`,
    t.`to_patch_port`,
    COALESCE(ss_target.`id`, ss_fallback.`id`),
    COALESCE(sc_target.`id`, sc_fallback.`id`),
    v_target.`id`,
    t.`fiber_port_id`,
    t.`fiber_patch_id`,
    t.`fiber_rack_id`,
    t.`idf_id`,
    t.`comments`,
    '2026-01-01 00:00:01',
    t.`updated_at`
FROM `switch_ports` t
JOIN `companies` c ON c.`id` <> t.`company_id`
LEFT JOIN `equipment` e_source ON e_source.`id` = t.`equipment_id`
LEFT JOIN `equipment` e_target ON e_target.`company_id` = c.`id` AND e_target.`name` = e_source.`name`
LEFT JOIN `switch_status` ss_source ON ss_source.`id` = t.`status_id`
LEFT JOIN `switch_status` ss_target ON ss_target.`company_id` = c.`id` AND ss_target.`status` = ss_source.`status`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `switch_status`
    GROUP BY `company_id`
) ss_fallback ON ss_fallback.`company_id` = c.`id`
LEFT JOIN `cable_colors` sc_source ON sc_source.`id` = t.`color_id`
LEFT JOIN `cable_colors` sc_target ON sc_target.`company_id` = c.`id` AND sc_target.`color_name` = sc_source.`color_name`
LEFT JOIN (
    SELECT `company_id`, MIN(`id`) AS `id`
    FROM `cable_colors`
    GROUP BY `company_id`
) sc_fallback ON sc_fallback.`company_id` = c.`id`
LEFT JOIN `vlans` v_source ON v_source.`id` = t.`vlan_id`
LEFT JOIN `vlans` v_target ON v_target.`company_id` = c.`id` AND v_target.`vlan_number` = v_source.`vlan_number`
WHERE t.`company_id` = @replicate_source_company_id
  AND COALESCE(ss_target.`id`, ss_fallback.`id`) IS NOT NULL
  AND COALESCE(sc_target.`id`, sc_fallback.`id`) IS NOT NULL;

INSERT IGNORE INTO `system_access` (`company_id`, `code`, `name`, `active`, `created_at`) SELECT c.`id`, t.`code`, t.`name`, t.`active`, '2026-01-01 00:00:01' FROM `system_access` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `role_hierarchy` (`company_id`, `role_id`, `hierarchy_order`, `created_at`) SELECT c.`id`, ur_target.`id`, rh.`hierarchy_order`, '2026-01-01 00:00:01' FROM `role_hierarchy` rh JOIN `companies` c ON c.`id` <> rh.`company_id` JOIN `employee_roles` ur_source ON ur_source.`id` = rh.`role_id` JOIN `employee_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rh.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `role_module_permissions` (`company_id`, `role_id`, `module_name`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`, `created_at`) SELECT c.`id`, ur_target.`id`, rmp.`module_name`, rmp.`can_view`, rmp.`can_create`, rmp.`can_edit`, rmp.`can_delete`, rmp.`can_import`, rmp.`can_export`, '2026-01-01 00:00:01' FROM `role_module_permissions` rmp JOIN `companies` c ON c.`id` <> rmp.`company_id` JOIN `employee_roles` ur_source ON ur_source.`id` = rmp.`role_id` JOIN `employee_roles` ur_target ON ur_target.`company_id` = c.`id` AND ur_target.`name` = ur_source.`name` WHERE rmp.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `role_assignment_rights` (`company_id`, `role_id`, `can_assign_role_id`, `created_at`) SELECT c.`id`, ur_granter_target.`id`, ur_target_target.`id`, '2026-01-01 00:00:01' FROM `role_assignment_rights` rar JOIN `companies` c ON c.`id` <> rar.`company_id` JOIN `employee_roles` ur_granter_source ON ur_granter_source.`id` = rar.`role_id` JOIN `employee_roles` ur_target_source ON ur_target_source.`id` = rar.`can_assign_role_id` JOIN `employee_roles` ur_granter_target ON ur_granter_target.`company_id` = c.`id` AND ur_granter_target.`name` = ur_granter_source.`name` JOIN `employee_roles` ur_target_target ON ur_target_target.`company_id` = c.`id` AND ur_target_target.`name` = ur_target_source.`name` WHERE rar.`company_id` = @replicate_source_company_id;

INSERT IGNORE INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `created_at`)
SELECT e.`id`, e.`company_id`, NULL, '2026-01-01 00:00:01'
FROM `employees` e
WHERE e.`password` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `employee_companies` uc
    WHERE uc.`employee_id` = e.`id` AND uc.`company_id` = e.`company_id`
  );

INSERT IGNORE INTO `ui_configuration` (
    `company_id`,
    `employee_id`,
    `table_actions_position`,
    `new_button_position`,
    `export_buttons_position`,
    `back_save_position`,
    `enable_all_error_reporting`,
    `enable_audit_logs`,
    `records_per_page`,
    `app_name`,
    `favicon_path`,
    `equipment_type_sidebar_visibility`,
    `created_at`,
    `updated_at`
)
SELECT
    c.`id`,
    e_target.`id`,
    t.`table_actions_position`,
    t.`new_button_position`,
    t.`export_buttons_position`,
    t.`back_save_position`,
    t.`enable_all_error_reporting`,
    t.`enable_audit_logs`,
    t.`records_per_page`,
    t.`app_name`,
    CONCAT('images/favicons/company_', c.`id`, '.ico'),
    t.`equipment_type_sidebar_visibility`,
    '2026-01-01 00:00:01',
    t.`updated_at`
FROM `ui_configuration` t
JOIN `companies` c
    ON c.`id` <> t.`company_id`
JOIN `employees` e_source
    ON e_source.`id` = t.`employee_id`
JOIN `employees` e_target
    ON e_target.`company_id` = c.`id`
   AND e_target.`username` = e_source.`username`
WHERE t.`company_id` = @replicate_source_company_id
  AND NOT EXISTS (
      SELECT 1
      FROM `ui_configuration` u
      WHERE u.`company_id` = c.`id`
        AND u.`employee_id` = e_target.`id`
  );

INSERT IGNORE INTO `vlans` (`company_id`, `vlan_number`, `vlan_name`, `vlan_color`, `subnet`, `ip`, `comments`, `gateway_ip`, `active`, `created_at`) SELECT c.`id`, t.`vlan_number`, t.`vlan_name`, t.`vlan_color`, t.`subnet`, t.`ip`, t.`comments`, t.`gateway_ip`, t.`active`, '2026-01-01 00:00:01' FROM `vlans` t JOIN `companies` c ON c.`id` <> t.`company_id` WHERE t.`company_id` = @replicate_source_company_id;

-- Why: Remove catalog rows whose FK parents belong to another company (legacy replicate row or partial import).
DELETE c FROM `catalogs` c
INNER JOIN `equipment_types` et ON et.id = c.equipment_type_id
WHERE c.company_id > 0 AND et.company_id > 0 AND c.company_id <> et.company_id;

DELETE c FROM `catalogs` c
INNER JOIN `manufacturers` m ON m.id = c.manufacturer_id
WHERE c.manufacturer_id IS NOT NULL AND c.company_id > 0 AND m.company_id > 0 AND c.company_id <> m.company_id;

DELETE c FROM `catalogs` c
INNER JOIN `suppliers` s ON s.id = c.supplier_id
WHERE c.supplier_id IS NOT NULL AND c.company_id > 0 AND s.company_id > 0 AND c.company_id <> s.company_id;

INSERT INTO `rack_planner` (`company_id`, `employee_id`, `id`, `name`, `rack_units`, `layout_json`, `notes`, `status_id`, `active`, `created_at`) VALUES (1, 1, 1, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 1.', 1, 1, '2026-01-01 00:00:01'),
(2, 2, 2, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 2.', 5, 1, '2026-01-01 00:00:01'),
(3, 3, 3, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 3.', 9, 1, '2026-01-01 00:00:01'),
(4, 4, 4, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 4.', 13, 1, '2026-01-01 00:00:01'),
(5, 5, 5, 'Core Rack A', 42, '{"version":1,"units":42,"devices":[]}', 'Sample empty rack plan for company 5.', 17, 1, '2026-01-01 00:00:01');

-- Private-data exempt: events may store vault-encrypted title/description/location (no audit_logs triggers).
-- Data for `event_categories`
INSERT INTO `event_categories` (`company_id`, `name`, `color`) VALUES
(1, 'Meeting', '#3b82f6'),
(1, 'Maintenance', '#ef4444'),
(1, 'Holiday', '#10b981'),
(1, 'Other', '#6b7280'),
(2, 'Meeting', '#3b82f6'),
(2, 'Maintenance', '#ef4444'),
(2, 'Holiday', '#10b981'),
(2, 'Other', '#6b7280'),
(3, 'Meeting', '#3b82f6'),
(3, 'Maintenance', '#ef4444'),
(3, 'Holiday', '#10b981'),
(3, 'Other', '#6b7280'),
(4, 'Meeting', '#3b82f6'),
(4, 'Maintenance', '#ef4444'),
(4, 'Holiday', '#10b981'),
(4, 'Other', '#6b7280'),
(5, 'Meeting', '#3b82f6'),
(5, 'Maintenance', '#ef4444'),
(5, 'Holiday', '#10b981'),
(5, 'Other', '#6b7280');

-- Data for `events`
INSERT INTO `events` (`company_id`, `employee_id`, `assigned_to_employee_id`, `created_by`, `title`, `title_hash`, `description`, `start_datetime`, `end_datetime`, `location`, `category_id`, `active`) VALUES
(1, 1, 1, 1, 'Project Kickoff', 'fd3be7accd3a3a8c43472283ed4dd18dc3e70d95c16861e8ed2c7de79a727de1', 'Initial meeting for the new project', '2026-05-01 09:00:00', '2026-05-01 11:00:00', 'Meeting Room A', 1, 1),
(1, 1, NULL, NULL, 'Server Maintenance', 'f78625785dce5adc93b013e1962ff81f09c6f4908c1277469c95d5cfc02c96cd', 'Monthly server updates and backup verification', '2026-05-15 22:00:00', '2026-05-16 02:00:00', 'Data Center', 2, 1),
(1, 1, NULL, NULL, 'Team Lunch', 'af65330d1edc5952b1f4283b470c6d2a5b8a14f334a785016235f16c45986610', 'Monthly team building lunch', '2026-05-20 12:00:00', '2026-05-20 13:30:00', 'Local Restaurant', 4, 1);

-- Reports Hub sample data: ops_report daily trend + YoY anchors (company 1)
INSERT INTO `ops_report` (`company_id`, `report_date`, `occupancy_pct`, `average_daily_rate`, `revpar`, `room_revenue`, `fb_revenue`, `spa_revenue`, `kids_club_revenue`, `hsk_revenue`, `fo_upgrade_rooms`, `total_revenue`, `active`, `created_at`) VALUES
(1, '2025-01-15', '70.0', 165.00, 115.50, 32798.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 52900.00, 1, '2025-01-15 08:00:00'),
(1, '2025-02-15', '70.0', 165.00, 115.50, 33356.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 53800.00, 1, '2025-02-15 08:00:00'),
(1, '2025-03-15', '70.0', 165.00, 115.50, 33914.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 54700.00, 1, '2025-03-15 08:00:00'),
(1, '2025-04-15', '70.0', 165.00, 115.50, 34472.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 55600.00, 1, '2025-04-15 08:00:00'),
(1, '2025-05-15', '70.0', 165.00, 115.50, 35030.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 56500.00, 1, '2025-05-15 08:00:00'),
(1, '2025-06-15', '70.0', 165.00, 115.50, 35588.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 57400.00, 1, '2025-06-15 08:00:00'),
(1, '2025-07-15', '70.0', 165.00, 115.50, 36146.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 58300.00, 1, '2025-07-15 08:00:00'),
(1, '2025-08-15', '70.0', 165.00, 115.50, 36704.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 59200.00, 1, '2025-08-15 08:00:00'),
(1, '2025-09-15', '70.0', 165.00, 115.50, 37262.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 60100.00, 1, '2025-09-15 08:00:00'),
(1, '2025-10-15', '70.0', 165.00, 115.50, 37820.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 61000.00, 1, '2025-10-15 08:00:00'),
(1, '2025-11-15', '70.0', 165.00, 115.50, 38378.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 61900.00, 1, '2025-11-15 08:00:00'),
(1, '2025-12-15', '70.0', 165.00, 115.50, 38936.00, 9800.00, 2400.00, 600.00, 1100.00, 800.00, 62800.00, 1, '2025-12-15 08:00:00'),
(1, '2026-01-15', '74.0', 172.00, 127.28, 35168.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 54950.00, 1, '2026-01-15 08:00:00'),
(1, '2026-02-15', '74.0', 172.00, 127.28, 35776.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 55900.00, 1, '2026-02-15 08:00:00'),
(1, '2026-03-15', '74.0', 172.00, 127.28, 36384.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 56850.00, 1, '2026-03-15 08:00:00'),
(1, '2026-04-15', '74.0', 172.00, 127.28, 36992.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 57800.00, 1, '2026-04-15 08:00:00'),
(1, '2026-05-15', '74.0', 172.00, 127.28, 37600.00, 10200.00, 2600.00, 700.00, 1250.00, 850.00, 58750.00, 1, '2026-05-15 08:00:00'),
(1, '2026-06-17', '72.0', 175.00, 126.00, 38000.00, 11000.00, 2800.00, 650.00, 1200.00, 900.00, 54550.00, 1, '2026-06-17 08:00:00'),
(1, '2026-06-18', '73.0', 178.00, 129.94, 38150.00, 11040.00, 2815.00, 655.00, 1210.00, 908.00, 54778.00, 1, '2026-06-18 08:00:00'),
(1, '2026-06-19', '74.0', 181.00, 133.94, 38300.00, 11080.00, 2830.00, 660.00, 1220.00, 916.00, 55006.00, 1, '2026-06-19 08:00:00'),
(1, '2026-06-20', '75.0', 184.00, 138.00, 38450.00, 11120.00, 2845.00, 665.00, 1230.00, 924.00, 55234.00, 1, '2026-06-20 08:00:00'),
(1, '2026-06-21', '76.0', 187.00, 142.12, 38600.00, 11160.00, 2860.00, 670.00, 1240.00, 932.00, 55462.00, 1, '2026-06-21 08:00:00'),
(1, '2026-06-22', '77.0', 175.00, 134.75, 38750.00, 11200.00, 2875.00, 675.00, 1250.00, 940.00, 55690.00, 1, '2026-06-22 08:00:00'),
(1, '2026-06-23', '78.0', 178.00, 138.84, 38900.00, 11240.00, 2890.00, 680.00, 1260.00, 948.00, 55918.00, 1, '2026-06-23 08:00:00'),
(1, '2026-06-24', '79.0', 181.00, 142.99, 39050.00, 11280.00, 2905.00, 685.00, 1270.00, 956.00, 56146.00, 1, '2026-06-24 08:00:00'),
(1, '2026-06-25', '80.0', 184.00, 147.20, 39200.00, 11320.00, 2920.00, 690.00, 1280.00, 964.00, 56374.00, 1, '2026-06-25 08:00:00'),
(1, '2026-06-26', '81.0', 187.00, 151.47, 39350.00, 11360.00, 2935.00, 695.00, 1290.00, 972.00, 56602.00, 1, '2026-06-26 08:00:00'),
(1, '2026-06-27', '82.0', 175.00, 143.50, 39500.00, 11400.00, 2950.00, 700.00, 1300.00, 980.00, 56830.00, 1, '2026-06-27 08:00:00'),
(1, '2026-06-28', '83.0', 178.00, 147.74, 39650.00, 11440.00, 2965.00, 705.00, 1310.00, 988.00, 57058.00, 1, '2026-06-28 08:00:00'),
(1, '2026-06-29', '72.0', 181.00, 130.32, 39800.00, 11480.00, 2980.00, 710.00, 1320.00, 996.00, 57286.00, 1, '2026-06-29 08:00:00'),
(1, '2026-06-30', '73.0', 184.00, 134.32, 39950.00, 11520.00, 2995.00, 715.00, 1330.00, 1004.00, 57514.00, 1, '2026-06-30 08:00:00'),
(1, '2026-07-01', '74.0', 187.00, 138.38, 40100.00, 11560.00, 3010.00, 720.00, 1340.00, 1012.00, 57742.00, 1, '2026-07-01 08:00:00'),
(1, '2026-07-02', '75.0', 175.00, 131.25, 40250.00, 11600.00, 3025.00, 725.00, 1350.00, 1020.00, 57970.00, 1, '2026-07-02 08:00:00'),
(1, '2026-07-03', '76.0', 178.00, 135.28, 40400.00, 11640.00, 3040.00, 730.00, 1360.00, 1028.00, 58198.00, 1, '2026-07-03 08:00:00'),
(1, '2026-07-04', '77.0', 181.00, 139.37, 40550.00, 11680.00, 3055.00, 735.00, 1370.00, 1036.00, 58426.00, 1, '2026-07-04 08:00:00'),
(1, '2026-07-05', '78.0', 184.00, 143.52, 40700.00, 11720.00, 3070.00, 740.00, 1380.00, 1044.00, 58654.00, 1, '2026-07-05 08:00:00'),
(1, '2026-07-06', '79.0', 187.00, 147.73, 40850.00, 11760.00, 3085.00, 745.00, 1390.00, 1052.00, 58882.00, 1, '2026-07-06 08:00:00'),
(1, '2026-07-07', '80.0', 175.00, 140.00, 41000.00, 11800.00, 3100.00, 750.00, 1400.00, 1060.00, 59110.00, 1, '2026-07-07 08:00:00'),
(1, '2026-07-08', '81.0', 178.00, 144.18, 41150.00, 11840.00, 3115.00, 755.00, 1410.00, 1068.00, 59338.00, 1, '2026-07-08 08:00:00'),
(1, '2026-07-09', '82.0', 181.00, 148.42, 41300.00, 11880.00, 3130.00, 760.00, 1420.00, 1076.00, 59566.00, 1, '2026-07-09 08:00:00'),
(1, '2026-07-10', '83.0', 184.00, 152.72, 41450.00, 11920.00, 3145.00, 765.00, 1430.00, 1084.00, 59794.00, 1, '2026-07-10 08:00:00'),
(1, '2026-07-11', '72.0', 187.00, 134.64, 41600.00, 11960.00, 3160.00, 770.00, 1440.00, 1092.00, 60022.00, 1, '2026-07-11 08:00:00'),
(1, '2026-07-12', '73.0', 175.00, 127.75, 41750.00, 12000.00, 3175.00, 775.00, 1450.00, 1100.00, 60250.00, 1, '2026-07-12 08:00:00'),
(1, '2026-07-13', '74.0', 178.00, 131.72, 41900.00, 12040.00, 3190.00, 780.00, 1460.00, 1108.00, 60478.00, 1, '2026-07-13 08:00:00'),
(1, '2026-07-14', '75.0', 181.00, 135.75, 42050.00, 12080.00, 3205.00, 785.00, 1470.00, 1116.00, 60706.00, 1, '2026-07-14 08:00:00'),
(1, '2026-07-15', '76.0', 184.00, 139.84, 42200.00, 12120.00, 3220.00, 790.00, 1480.00, 1124.00, 60934.00, 1, '2026-07-15 08:00:00'),
(1, '2026-07-16', '77.0', 187.00, 143.99, 42350.00, 12160.00, 3235.00, 795.00, 1490.00, 1132.00, 61162.00, 1, '2026-07-16 08:00:00');

INSERT INTO `ops_report_fb_outlet` (`company_id`, `ops_report_id`, `outlet_name`, `covers_breakfast`, `covers_lunch`, `covers_dinner`, `sort_order`, `active`)
SELECT 1, r.id, 'OLIVEIRA BRASSERIE', '40', '55', '70', 0, 1
FROM `ops_report` r
WHERE r.company_id = 1 AND r.report_date BETWEEN '2026-07-01' AND '2026-07-16' AND r.active = 1;

INSERT INTO `ops_report_fb_outlet` (`company_id`, `ops_report_id`, `outlet_name`, `covers_breakfast`, `covers_lunch`, `covers_dinner`, `sort_order`, `active`)
SELECT 1, r.id, 'IN-ROOM DINING', '45', '62', '79', 1, 1
FROM `ops_report` r
WHERE r.company_id = 1 AND r.report_date BETWEEN '2026-07-01' AND '2026-07-16' AND r.active = 1;

INSERT INTO `ops_report_fb_outlet` (`company_id`, `ops_report_id`, `outlet_name`, `covers_breakfast`, `covers_lunch`, `covers_dinner`, `sort_order`, `active`)
SELECT 1, r.id, 'THE NEST COCKTAILS & BAR', '50', '69', '88', 2, 1
FROM `ops_report` r
WHERE r.company_id = 1 AND r.report_date BETWEEN '2026-07-01' AND '2026-07-16' AND r.active = 1;

-- Existing databases: DELETE duplicate rows per (company_id, employee_id, SHA2(plaintext url,256)); DROP INDEX uq_bookmarks_employee_url ON bookmarks;
-- ADD url_hash char(64) NOT NULL; backfill url_hash from SHA2(url,256) for shared rows or re-encrypt private rows via app;
-- CHANGE url to TEXT; ADD UNIQUE (company_id, employee_id, url_hash).
-- Private bookmark titles: CHANGE title to TEXT; re-encrypt private titles via app (shared rows stay plaintext).
-- Private bookmark notes: already TEXT; re-encrypt private notes via app (shared rows stay plaintext).
-- Seed default shared bookmarks
-- Retroactive default bookmarks for existing Admin users
INSERT INTO bookmarks (company_id, employee_id, title, url, url_hash, shared, active)
SELECT 
    e.company_id,
    e.id,
    b.title,
    b.url,
    SHA2(b.url, 256),
    1,
    1
FROM employees e
LEFT JOIN employee_roles ur ON ur.id = e.role_id
CROSS JOIN (
    SELECT 'ServiceNow' AS title, 'https://www.servicenow.com/' AS url UNION ALL
    SELECT 'Splunk', 'https://www.splunk.com/' UNION ALL
    SELECT 'M365', 'https://m365.cloud.microsoft/'
) b
WHERE 
    (
        LOWER(e.username) = 'admin'
        OR LOWER(ur.name) = 'admin'
    )
    AND NOT EXISTS (
        SELECT 1 
        FROM bookmarks bk
        WHERE bk.company_id = e.company_id
          AND bk.employee_id = e.id
          AND bk.url_hash = SHA2(b.url, 256)
    );

-- Additional Sample Data for Knowledge Base
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`) VALUES
(1, 'Network', 'VPN Setup Guide', 'To set up your VPN:\n1. Open Cisco AnyConnect.\n2. Enter vpn.techcorp.example.\n3. Log in with your windows credentials.\n4. Approve the DUO push notification.', 1),
(1, 'Password Management', 'How to reset your password', 'To reset your domain password:\n1. Press Ctrl+Alt+Del\n2. Select "Change a password"\n3. Follow the on-screen instructions.\nIf you are locked out, please call the IT helpdesk.', 1),
(1, 'Printers', 'Troubleshooting Printer Issues', 'If your printer is not working:\n1. Check if it is turned on and connected to the network.\n2. Ensure there is paper and toner.\n3. Restart the printer spooler on your PC.\n4. If issues persist, contact IT with the printer name/IP.', 1),
(1, 'Network', 'Connecting to Office WiFi', 'To connect to the "TechCorp_Internal" WiFi:\n1. Select the SSID from your device.\n2. Use your windows credentials (domain username and password).\n3. Accept the security certificate if prompted.', 1),
(1, 'Software', 'Installing Authorized Software', 'Software must be requested via the IT Portal. Once approved, it will appear in the "Software Center" on your desktop for one-click installation.', 1),
(1, 'Security', 'Reporting Suspicious Emails', 'If you receive a suspicious email (phishing):\n1. Do not click any links or download attachments.\n2. Click the "Report Phish" button in Outlook.\n3. Delete the email immediately.', 1);

-- Repeat for other companies if they exist in the seed
INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 2, category, title, content, active FROM knowledge_base WHERE company_id = 1;

INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 3, category, title, content, active FROM knowledge_base WHERE company_id = 1;

INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 4, category, title, content, active FROM knowledge_base WHERE company_id = 1;

INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`, `active`)
SELECT 5, category, title, content, active FROM knowledge_base WHERE company_id = 1;

-- Also add some IT Settings
INSERT INTO `it_settings` (`company_id`, `contact_email`, `contact_phone`, `hours_of_operation`, `escalation_procedure`) VALUES
(1, 'it-support@techcorp.example', '+1-212-555-0199', '24/7', 'For critical outages, call the On-Call Manager at +1-212-555-0911.'),
(2, 'support@datacenterplus.example', '+1-972-555-0200', '08:00 - 18:00 CST', 'Issues unresolved after 4 hours should be escalated to the IT Director.'),
(3, 'help@networksolutions.example', '+1-415-555-0300', '09:00 - 17:00 PST', 'Please submit a ticket via the portal for escalation.'),
(4, 'it@cloudtech.example', '+1-206-555-0400', '24/7', 'Contact the Level 2 support team via Slack #it-escalations.'),
(5, 'it-ops@enterpriseit.example', '+1-617-555-0500', '08:00 - 20:00 EST', 'Standard escalation through the ticketing system.');
