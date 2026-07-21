-- Trim company_module_share to share-capable modules only (data fix; schema unchanged).
-- Slugs must match itm_qr_share_capable_module_slugs() in includes/itm_qr_share.php.

DELETE cms
FROM `company_module_share` cms
INNER JOIN `modules_registry` mr ON mr.`id` = cms.`module_id`
WHERE mr.`module_slug` NOT IN (
  'notes', 'passwords', 'bookmarks', 'todo', 'events',
  'private_contacts', 'explorer', 'floor_plans', 'rack_planner',
  'employees', 'departments', 'equipment', 'catalogs', 'license_management',
  'inventory_items', 'suppliers', 'alerts', 'tickets', 'patches_updates', 'ops_report',
  'annual_budgets', 'approvals', 'approvals_stage', 'approver_type', 'approvers',
  'budget_categories', 'cost_centers', 'expenses', 'forecast_revisions',
  'forecast_revisions_status', 'gl_accounts', 'monthly_budgets'
);

INSERT IGNORE INTO `company_module_share` (`company_id`, `module_id`, `enabled`)
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
