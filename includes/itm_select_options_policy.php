<?php
/**
 * Select Options API table policy.
 *
 * Why: select_options_api.php is a generic quick-add endpoint; only low-risk
 * reference/lookup tables may be inserted. Sensitive identity and RBAC tables
 * are blocked even if a caller supplies a valid identifier.
 */

if (!function_exists('itm_select_options_blocked_tables')) {
    /**
     * Tables that must never accept quick-add inserts (identity, RBAC, audit).
     *
     * @return string[]
     */
    function itm_select_options_blocked_tables()
    {
        return [
            'access_levels',
            'audit_logs',
            'employee_system_access',
            'password_folders',
            'passwords',
            'role_hierarchy',
            'role_module_permissions',
            'settings',
            'ui_configuration',
            'user_companies',
            'user_roles',
            'users',
        ];
    }
}

if (!function_exists('itm_select_options_allowed_tables')) {
    /**
     * Whitelist of lookup tables permitted for dropdown quick-add.
     *
     * @return string[]
     */
    function itm_select_options_allowed_tables()
    {
        return [
            'annual_budgets',
            'approvals_stage',
            'approver_type',
            'assignment_types',
            'bookmark_folders',
            'budget_categories',
            'cable_colors',
            'companies',
            'cost_centers',
            'departments',
            'employee_positions',
            'employee_statuses',
            'equipment',
            'equipment_environment',
            'equipment_fiber',
            'equipment_fiber_count',
            'equipment_fiber_patch',
            'equipment_fiber_rack',
            'equipment_poe',
            'equipment_rj45',
            'equipment_statuses',
            'equipment_types',
            'event_categories',
            'forecast_revisions_status',
            'gl_accounts',
            'idf_device_type',
            'idfs',
            'inventory_categories',
            'it_locations',
            'location_types',
            'manufacturers',
            'note_labels',
            'patches_updates_level',
            'patches_updates_status',
            'printer_device_types',
            'rack_statuses',
            'racks',
            'rj45_speed',
            'supplier_statuses',
            'suppliers',
            'switch_port_numbering_layout',
            'switch_port_types',
            'switch_status',
            'ticket_categories',
            'ticket_priorities',
            'ticket_statuses',
            'todo_categories',
            'vlans',
            'warranty_types',
            'workstation_device_types',
            'workstation_modes',
            'workstation_office',
            'workstation_os_types',
            'workstation_os_versions',
            'workstation_ram',
        ];
    }
}

if (!function_exists('itm_select_options_sensitive_extra_fields')) {
    /**
     * Column names that must never be supplied via extra_fields quick-add.
     *
     * @return string[]
     */
    function itm_select_options_sensitive_extra_fields()
    {
        return [
            'access_level_id',
            'password',
            'password_hash',
            'role_id',
        ];
    }
}

if (!function_exists('itm_select_options_is_table_allowed')) {
    /**
     * Returns true when the target table may be used by select_options_api.php.
     */
    function itm_select_options_is_table_allowed($table)
    {
        $table = is_string($table) ? trim($table) : '';
        if ($table === '' || !itm_is_safe_identifier($table)) {
            return false;
        }
        if (in_array($table, itm_select_options_blocked_tables(), true)) {
            return false;
        }

        return in_array($table, itm_select_options_allowed_tables(), true);
    }
}

if (!function_exists('itm_select_options_filter_extra_fields')) {
    /**
     * Strips privilege-related keys from quick-add payloads.
     *
     * @param array<string, string> $extraFields
     * @return array<string, string>
     */
    function itm_select_options_filter_extra_fields(array $extraFields)
    {
        foreach (itm_select_options_sensitive_extra_fields() as $blockedField) {
            unset($extraFields[$blockedField]);
        }

        return $extraFields;
    }
}
