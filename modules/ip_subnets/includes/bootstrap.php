<?php
// Module root (ip_subnets/) for requires from includes/partials/render.php.
$ipSubnetsModuleDir = dirname(__DIR__);

// INITIALIZATION
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
// Exclude employee-specific sensitive fields.
$fieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_employee_field($col['Field']) && !cr_is_hidden_ipam_field($col['Field']);
}));
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'employee_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'ip_subnets', 'ip_addresses', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_statuses', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'suppliers', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'employees', 'departments'];
// Why: Create/edit omit audit meta; server stamps via itm_crud_render_form_hidden_audit_inputs().
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    $fieldName = (string)($col['Field'] ?? '');
    if (function_exists('itm_crud_is_form_hidden_audit_field') && itm_crud_is_form_hidden_audit_field($fieldName)) {
        return false;
    }
    if (function_exists('itm_crud_is_delete_form_hidden_field') && itm_crud_is_delete_form_hidden_field($fieldName)) {
        return false;
    }
    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field($fieldName)) {
        return false;
    }
    if ($fieldName === 'company_id') {
        return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
    }

    return true;
}));

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();


