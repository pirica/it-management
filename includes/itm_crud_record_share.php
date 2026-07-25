<?php
/**
 * Shared QR / 6-digit share for flattened CRUD modules (record_id + label/value payload).
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';

if (!function_exists('itm_crud_record_share_sensitive_columns')) {
    function itm_crud_record_share_sensitive_columns()
    {
        return [
            'password',
            'vault_key_hash',
            'totp_secret',
            'reset_token',
            'reset_token_hash',
            'reset_token_expires_at',
            'company_id',
            'deleted_by',
            'deleted_at',
            'created_by',
            'created_at',
            'updated_by',
            'updated_at',
        ];
    }
}

if (!function_exists('itm_crud_record_share_module_configs')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function itm_crud_record_share_module_configs()
    {
        static $configs = null;
        if ($configs !== null) {
            return $configs;
        }

        $configs = [
            'departments' => ['table' => 'departments', 'label' => 'Department', 'fields' => ['code', 'name', 'description', 'email', 'phone', 'dect', 'extension', 'active']],
            'catalogs' => ['table' => 'catalogs', 'label' => 'Catalog', 'fields' => ['name', 'code', 'price', 'supplier_id', 'category_id', 'notes', 'active']],
            'license_management' => ['table' => 'license_management', 'label' => 'License', 'fields' => ['name', 'license_key', 'license_type_id', 'quantity', 'supplier_id', 'purchase_date', 'expiry_date', 'price', 'notes', 'active']],
            'inventory_items' => ['table' => 'inventory_items', 'label' => 'Inventory Item', 'fields' => ['name', 'item_code', 'serial', 'category_id', 'manufacturer_id', 'location_id', 'supplier_id', 'quantity_on_hand', 'quantity_minimum', 'price_eur', 'comments', 'active']],
            'suppliers' => ['table' => 'suppliers', 'label' => 'Supplier', 'fields' => ['name', 'status_id', 'contact_name', 'email', 'phone', 'website', 'notes', 'active']],
            'annual_budgets' => ['table' => 'annual_budgets', 'label' => 'Annual Budget', 'fields' => ['year', 'name', 'total_amount', 'notes', 'active']],
            'approvals' => ['table' => 'approvals', 'label' => 'Approval', 'fields' => ['forecast_revision_id', 'approvals_stage_id', 'status', 'amount', 'notes', 'active']],
            'approvals_stage' => ['table' => 'approvals_stage', 'label' => 'Approvals Stage', 'fields' => ['name', 'sort_order', 'active']],
            'approver_type' => ['table' => 'approver_type', 'label' => 'Approver Type', 'fields' => ['name', 'active']],
            'approvers' => ['table' => 'approvers', 'label' => 'Approver', 'fields' => ['employee_id', 'approver_type_id', 'active']],
            'budget_categories' => ['table' => 'budget_categories', 'label' => 'Budget Category', 'fields' => ['code', 'name', 'active']],
            'cost_centers' => ['table' => 'cost_centers', 'label' => 'Cost Center', 'fields' => ['code', 'name', 'active']],
            'expenses' => ['table' => 'expenses', 'label' => 'Expense', 'fields' => ['description', 'amount', 'expense_date', 'gl_account_id', 'notes', 'active']],
            'forecast_revisions' => ['table' => 'forecast_revisions', 'label' => 'Forecast Revision', 'fields' => ['name', 'forecast_revisions_status_id', 'revision_date', 'notes', 'active']],
            'forecast_revisions_status' => ['table' => 'forecast_revisions_status', 'label' => 'Forecast Status', 'fields' => ['name', 'active']],
            'gl_accounts' => ['table' => 'gl_accounts', 'label' => 'GL Account', 'fields' => ['code', 'name', 'active']],
            'monthly_budgets' => ['table' => 'monthly_budgets', 'label' => 'Monthly Budget', 'fields' => ['annual_budget_id', 'month', 'amount', 'notes', 'active']],
            'patches_updates' => ['table' => 'patches_updates', 'label' => 'Patch / Update', 'fields' => ['equipment_id', 'name', 'patches_updates_status_id', 'patches_updates_level_id', 'patch_date', 'notes', 'active']],
        ];

        return $configs;
    }
}

if (!function_exists('itm_crud_record_share_humanize_field')) {
    function itm_crud_record_share_humanize_field($field)
    {
        $field = (string)$field;
        if ($field === '') {
            return '';
        }
        if (function_exists('cr_humanize_field')) {
            return cr_humanize_field($field);
        }

        return ucwords(str_replace('_', ' ', $field));
    }
}

if (!function_exists('itm_crud_record_share_format_scalar')) {
    function itm_crud_record_share_format_scalar($field, $value)
    {
        if (function_exists('itm_format_cell_scalar_display')) {
            return (string)itm_format_cell_scalar_display($field, $value);
        }

        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value) && (string)(int)$value === (string)$value && in_array((string)$field, ['active'], true)) {
            return ((int)$value === 1) ? 'Active' : 'Inactive';
        }

        return (string)$value;
    }
}

if (!function_exists('itm_crud_record_share_resolve_fk_label')) {
    function itm_crud_record_share_resolve_fk_label($conn, $companyId, $column, $rawId)
    {
        $rawId = (int)$rawId;
        if ($rawId <= 0 || !($conn instanceof mysqli)) {
            return '';
        }
        $companyId = (int)$companyId;
        $column = (string)$column;

        $map = [
            'department_id' => ['departments', 'name'],
            'employee_id' => ['employees', "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))"],
            'assigned_to_employee_id' => ['employees', "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))"],
            'created_by_employee_id' => ['employees', "CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))"],
            'supplier_id' => ['suppliers', 'name'],
            'category_id' => ['inventory_categories', 'name'],
            'license_type_id' => ['license_types', 'name'],
            'status_id' => ['supplier_statuses', 'name'],
            'equipment_id' => ['equipment', 'hostname'],
            'patches_updates_status_id' => ['patches_updates_status', 'name'],
            'patches_updates_level_id' => ['patches_updates_level', 'name'],
            'forecast_revision_id' => ['forecast_revisions', 'name'],
            'approvals_stage_id' => ['approvals_stage', 'name'],
            'approver_type_id' => ['approver_type', 'name'],
            'gl_account_id' => ['gl_accounts', 'name'],
            'forecast_revisions_status_id' => ['forecast_revisions_status', 'name'],
            'annual_budget_id' => ['annual_budgets', 'name'],
        ];

        if (!isset($map[$column])) {
            if (substr($column, -3) === '_id') {
                return (string)$rawId;
            }

            return '';
        }

        [$table, $labelExpr] = $map[$column];
        if (!preg_match('/^[a-z0-9_]+$/', $table)) {
            return '';
        }

        $sql = 'SELECT ' . $labelExpr . ' AS lbl FROM `' . $table . '` WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $rawId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return trim((string)($row['lbl'] ?? ''));
    }
}

if (!function_exists('itm_crud_record_share_build_fields_from_row')) {
    function itm_crud_record_share_build_fields_from_row($conn, $companyId, array $row, array $fieldNames)
    {
        $fields = [];
        $sensitive = itm_crud_record_share_sensitive_columns();
        foreach ($fieldNames as $fieldName) {
            $fieldName = (string)$fieldName;
            if ($fieldName === '' || in_array($fieldName, $sensitive, true)) {
                continue;
            }
            $raw = $row[$fieldName] ?? '';
            if (substr($fieldName, -3) === '_id') {
                $display = itm_crud_record_share_resolve_fk_label($conn, $companyId, $fieldName, $raw);
                if ($display === '') {
                    $display = itm_crud_record_share_format_scalar($fieldName, $raw);
                }
            } else {
                $display = itm_crud_record_share_format_scalar($fieldName, $raw);
            }
            $fields[] = [
                'label' => itm_crud_record_share_humanize_field($fieldName),
                'value' => $display,
            ];
        }

        return $fields;
    }
}

if (!function_exists('itm_crud_record_share_join_script_path')) {
    function itm_crud_record_share_join_script_path($moduleSlug)
    {
        return 'modules/' . $moduleSlug . '/join.php';
    }
}

if (!function_exists('itm_crud_record_share_build_join_url')) {
    function itm_crud_record_share_build_join_url($moduleSlug, $accessToken)
    {
        return itm_qr_share_build_join_url(itm_crud_record_share_join_script_path($moduleSlug), $accessToken);
    }
}

if (!function_exists('itm_crud_record_share_encode_payload')) {
    function itm_crud_record_share_encode_payload(array $payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }
}

if (!function_exists('itm_crud_record_share_load_generic_row')) {
    function itm_crud_record_share_load_generic_row($conn, $table, $recordId, $companyId)
    {
        $table = (string)$table;
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        if ($recordId <= 0 || $companyId <= 0 || !preg_match('/^[a-z0-9_]+$/', $table)) {
            return null;
        }

        $sql = 'SELECT * FROM `' . $table . '` WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}

if (!function_exists('itm_crud_record_share_create_from_config')) {
    function itm_crud_record_share_create_from_config($conn, $moduleSlug, $recordId, $companyId, $employeeId, $ownerUsername)
    {
        $configs = itm_crud_record_share_module_configs();
        if (!isset($configs[$moduleSlug])) {
            return ['ok' => false, 'error' => 'Share is not configured for this module.'];
        }
        $cfg = $configs[$moduleSlug];
        $table = (string)($cfg['table'] ?? '');
        $row = itm_crud_record_share_load_generic_row($conn, $table, $recordId, $companyId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Record not found.'];
        }

        $fieldNames = (array)($cfg['fields'] ?? []);
        $fields = itm_crud_record_share_build_fields_from_row($conn, $companyId, $row, $fieldNames);
        $heading = (string)($cfg['label'] ?? $moduleSlug);
        foreach (['name', 'title', 'code', 'hostname'] as $headingCol) {
            if (!empty($row[$headingCol])) {
                $heading = trim((string)$row[$headingCol]);
                break;
            }
        }

        $payload = [
            'type' => 'crud_record',
            'heading' => $heading,
            'owner_username' => (string)$ownerUsername,
            'module_slug' => $moduleSlug,
            'module_label' => (string)($cfg['label'] ?? $moduleSlug),
            'fields' => $fields,
        ];

        return itm_qr_share_create_session($conn, $moduleSlug, [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $recordId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_employees')) {
    function itm_crud_record_share_create_employees($conn, $recordId, $companyId, $employeeId, $ownerUsername)
    {
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        $sql = 'SELECT e.*, d.name AS department_name, es.name AS employment_status_name, ep.name AS position_name '
            . 'FROM employees e '
            . 'LEFT JOIN departments d ON d.id = e.department_id '
            . 'LEFT JOIN employee_statuses es ON es.id = e.employment_status_id '
            . 'LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id '
            . 'WHERE e.id = ? AND e.company_id = ? AND e.is_hidden = 0 AND e.deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not load employee.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }

        $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        $fields = [
            ['label' => 'Username', 'value' => (string)($row['username'] ?? '')],
            ['label' => 'Name', 'value' => $name],
            ['label' => 'Department', 'value' => (string)($row['department_name'] ?? '')],
            ['label' => 'Position', 'value' => (string)($row['position_name'] ?? '')],
            ['label' => 'Status', 'value' => (string)($row['employment_status_name'] ?? '')],
            ['label' => 'Work Email', 'value' => (string)($row['work_email'] ?? '')],
            ['label' => 'Mobile Phone', 'value' => (string)($row['mobile_phone'] ?? '')],
        ];
        $payload = [
            'type' => 'crud_record',
            'heading' => $name !== '' ? $name : (string)($row['username'] ?? 'Employee'),
            'owner_username' => (string)$ownerUsername,
            'module_slug' => 'employees',
            'module_label' => 'Employee',
            'fields' => $fields,
        ];

        return itm_qr_share_create_session($conn, 'employees', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $recordId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_tickets')) {
    function itm_crud_record_share_create_tickets($conn, $recordId, $companyId, $employeeId, $ownerUsername)
    {
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        $sql = 'SELECT t.*, tc.name AS category_name, ts.name AS status_name, tp.name AS priority_name, '
            . 'e.name AS equipment_name, '
            . "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.last_name,''))), ''), a.username) AS assignee_name "
            . 'FROM tickets t '
            . 'LEFT JOIN ticket_categories tc ON tc.id = t.category_id '
            . 'LEFT JOIN ticket_statuses ts ON ts.id = t.status_id '
            . 'LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id '
            . 'LEFT JOIN equipment e ON e.id = t.equipment_id '
            . 'LEFT JOIN employees a ON a.id = t.assigned_to_employee_id '
            . 'WHERE t.id = ? AND t.company_id = ? AND t.deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not load ticket.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }

        $heading = trim((string)($row['ticket_number'] ?? '') . ' — ' . (string)($row['subject'] ?? ''));
        $fields = [
            ['label' => 'Ticket Number', 'value' => (string)($row['ticket_number'] ?? '')],
            ['label' => 'Subject', 'value' => (string)($row['subject'] ?? '')],
            ['label' => 'Status', 'value' => (string)($row['status_name'] ?? '')],
            ['label' => 'Priority', 'value' => (string)($row['priority_name'] ?? '')],
            ['label' => 'Category', 'value' => (string)($row['category_name'] ?? '')],
            ['label' => 'Assignee', 'value' => (string)($row['assignee_name'] ?? '')],
            ['label' => 'Equipment', 'value' => (string)($row['equipment_name'] ?? '')],
            ['label' => 'Due Date', 'value' => itm_crud_record_share_format_scalar('due_date', $row['due_date'] ?? '')],
            ['label' => 'Description', 'value' => (string)($row['description'] ?? '')],
        ];
        $payload = [
            'type' => 'crud_record',
            'heading' => $heading,
            'owner_username' => (string)$ownerUsername,
            'module_slug' => 'tickets',
            'module_label' => 'Ticket',
            'fields' => $fields,
        ];

        return itm_qr_share_create_session($conn, 'tickets', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $recordId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_alerts')) {
    function itm_crud_record_share_create_alerts($conn, $recordId, $companyId, $employeeId, $ownerUsername)
    {
        require_once ROOT_PATH . 'includes/alerts_visibility.php';
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $sql = 'SELECT a.*, '
            . "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))), ''), e.username) AS assignee_name "
            . 'FROM alerts a '
            . 'LEFT JOIN employees e ON e.id = a.assigned_to_employee_id '
            . 'WHERE a.id = ? AND a.company_id = ? AND a.deleted_at IS NULL AND '
            . itm_alerts_visibility_sql('a') . ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not load alert.'];
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $recordId, $companyId, $employeeId, $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Alert not found or not visible.'];
        }

        $fields = [
            ['label' => 'Title', 'value' => (string)($row['title'] ?? '')],
            ['label' => 'Description', 'value' => (string)($row['description'] ?? '')],
            ['label' => 'Assignee', 'value' => (string)($row['assignee_name'] ?? 'Global')],
            ['label' => 'Start', 'value' => itm_crud_record_share_format_scalar('start_datetime', $row['start_datetime'] ?? '')],
            ['label' => 'End', 'value' => itm_crud_record_share_format_scalar('end_datetime', $row['end_datetime'] ?? '')],
        ];
        $payload = [
            'type' => 'crud_record',
            'heading' => (string)($row['title'] ?? 'Alert'),
            'owner_username' => (string)$ownerUsername,
            'module_slug' => 'alerts',
            'module_label' => 'Alert',
            'fields' => $fields,
        ];

        return itm_qr_share_create_session($conn, 'alerts', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $recordId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_equipment')) {
    function itm_crud_record_share_create_equipment($conn, $recordId, $companyId, $employeeId, $ownerUsername, $shareKind = 'record')
    {
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        $shareKind = trim((string)$shareKind);
        if ($shareKind === 'switch_ports') {
            return itm_crud_record_share_create_equipment_switch_ports($conn, $recordId, $companyId, $employeeId, $ownerUsername);
        }

        $sql = 'SELECT e.*, et.name AS equipment_type_name, es.name AS status_name, d.name AS department_name, '
            . 's.name AS supplier_name, idf.name AS idf_name '
            . 'FROM equipment e '
            . 'LEFT JOIN equipment_types et ON et.id = e.equipment_type_id AND et.company_id = e.company_id '
            . 'LEFT JOIN equipment_statuses es ON es.id = e.status_id AND es.company_id = e.company_id '
            . 'LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id '
            . 'LEFT JOIN suppliers s ON s.id = e.supplier_id AND s.company_id = e.company_id '
            . 'LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id '
            . 'WHERE e.id = ? AND e.company_id = ? AND e.deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not load equipment.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Equipment not found.'];
        }

        $heading = trim((string)($row['hostname'] ?? ''));
        if ($heading === '') {
            $heading = trim((string)($row['name'] ?? 'Equipment'));
        }
        $fields = [
            ['label' => 'Hostname', 'value' => (string)($row['hostname'] ?? '')],
            ['label' => 'Type', 'value' => (string)($row['equipment_type_name'] ?? '')],
            ['label' => 'Status', 'value' => (string)($row['status_name'] ?? '')],
            ['label' => 'Department', 'value' => (string)($row['department_name'] ?? '')],
            ['label' => 'Supplier', 'value' => (string)($row['supplier_name'] ?? '')],
            ['label' => 'IDF', 'value' => (string)($row['idf_name'] ?? '')],
            ['label' => 'Serial', 'value' => (string)($row['serial_number'] ?? '')],
            ['label' => 'Warranty Expiry', 'value' => itm_crud_record_share_format_scalar('warranty_expiry', $row['warranty_expiry'] ?? '')],
            ['label' => 'Certificate Expiry', 'value' => itm_crud_record_share_format_scalar('certificate_expiry', $row['certificate_expiry'] ?? '')],
        ];
        $payload = [
            'type' => 'crud_record',
            'heading' => $heading,
            'owner_username' => (string)$ownerUsername,
            'module_slug' => 'equipment',
            'module_label' => 'Equipment',
            'fields' => $fields,
        ];

        return itm_qr_share_create_session($conn, 'equipment', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $recordId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_equipment_switch_ports')) {
    function itm_crud_record_share_create_equipment_switch_ports($conn, $switchId, $companyId, $employeeId, $ownerUsername)
    {
        $switchId = (int)$switchId;
        $companyId = (int)$companyId;
        $sql = 'SELECT hostname, name FROM equipment WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not load switch.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $switchId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $switchRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$switchRow) {
            return ['ok' => false, 'error' => 'Switch not found.'];
        }

        $ports = [];
        $portSql = 'SELECT sp.port_number, sp.label, sp.notes, ss.name AS status_name, cc.hex_color '
            . 'FROM switch_ports sp '
            . 'LEFT JOIN switch_status ss ON ss.id = sp.status_id AND ss.company_id = sp.company_id '
            . 'LEFT JOIN cable_colors cc ON cc.id = sp.color_id AND cc.company_id = sp.company_id '
            . 'WHERE sp.switch_id = ? AND sp.company_id = ? AND sp.deleted_at IS NULL '
            . 'ORDER BY sp.port_number ASC';
        $portStmt = mysqli_prepare($conn, $portSql);
        if ($portStmt) {
            mysqli_stmt_bind_param($portStmt, 'ii', $switchId, $companyId);
            mysqli_stmt_execute($portStmt);
            $portRes = mysqli_stmt_get_result($portStmt);
            while ($portRes && ($portRow = mysqli_fetch_assoc($portRes))) {
                $ports[] = [
                    'port_number' => (int)($portRow['port_number'] ?? 0),
                    'label' => (string)($portRow['label'] ?? ''),
                    'status' => (string)($portRow['status_name'] ?? ''),
                    'color' => (string)($portRow['hex_color'] ?? ''),
                    'notes' => (string)($portRow['notes'] ?? ''),
                ];
            }
            mysqli_stmt_close($portStmt);
        }

        $hostname = trim((string)($switchRow['hostname'] ?? $switchRow['name'] ?? 'Switch'));
        $payload = [
            'type' => 'equipment_switch_ports',
            'heading' => $hostname . ' — Switch Ports',
            'owner_username' => (string)$ownerUsername,
            'hostname' => $hostname,
            'ports' => $ports,
        ];

        return itm_qr_share_create_session($conn, 'equipment', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $switchId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_ops_report')) {
    function itm_crud_record_share_create_ops_report($conn, $recordId, $companyId, $employeeId, $ownerUsername)
    {
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        $sql = 'SELECT r.*, c.company AS company_name FROM ops_report r '
            . 'LEFT JOIN companies c ON c.id = r.company_id '
            . 'WHERE r.id = ? AND r.company_id = ? AND r.deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not load report.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Ops report not found.'];
        }

        $reportDate = (string)($row['report_date'] ?? '');
        $sections = [];
        $childTables = [
            'ops_report_fb_outlet' => 'F&B Outlet',
            'ops_report_walk_round' => 'Walk Round',
            'ops_report_courtesy_call' => 'Courtesy Call',
            'ops_report_guest_experience' => 'Guest Experience',
        ];
        foreach ($childTables as $table => $sectionLabel) {
            if (!preg_match('/^[a-z0-9_]+$/', $table)) {
                continue;
            }
            $childSql = 'SELECT * FROM `' . $table . '` WHERE ops_report_id = ? AND company_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC';
            $childStmt = mysqli_prepare($conn, $childSql);
            if (!$childStmt) {
                continue;
            }
            mysqli_stmt_bind_param($childStmt, 'ii', $recordId, $companyId);
            mysqli_stmt_execute($childStmt);
            $childRes = mysqli_stmt_get_result($childStmt);
            $rows = [];
            while ($childRes && ($childRow = mysqli_fetch_assoc($childRes))) {
                $rows[] = $childRow;
            }
            mysqli_stmt_close($childStmt);
            if ($rows !== []) {
                $sections[] = ['label' => $sectionLabel, 'rows' => $rows];
            }
        }

        $payload = [
            'type' => 'ops_report',
            'heading' => 'Ops Report ' . itm_crud_record_share_format_scalar('report_date', $reportDate),
            'owner_username' => (string)$ownerUsername,
            'company' => (string)($row['company_name'] ?? ''),
            'report_date' => itm_crud_record_share_format_scalar('report_date', $reportDate),
            'sections' => $sections,
        ];

        return itm_qr_share_create_session($conn, 'ops_report', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'record_id' => $recordId,
            'payload_json' => itm_crud_record_share_encode_payload($payload),
        ]);
    }
}

if (!function_exists('itm_crud_record_share_create_session')) {
    function itm_crud_record_share_create_session($conn, $moduleSlug, $recordId, $companyId, $employeeId, $ownerUsername, array $options = [])
    {
        $moduleSlug = trim((string)$moduleSlug);
        $recordId = (int)$recordId;
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($moduleSlug === '' || $recordId <= 0 || $companyId <= 0 || $employeeId <= 0) {
            return ['ok' => false, 'error' => 'Invalid request.'];
        }

        $shareKind = trim((string)($options['share_kind'] ?? 'record'));
        switch ($moduleSlug) {
            case 'employees':
                return itm_crud_record_share_create_employees($conn, $recordId, $companyId, $employeeId, $ownerUsername);
            case 'tickets':
                return itm_crud_record_share_create_tickets($conn, $recordId, $companyId, $employeeId, $ownerUsername);
            case 'alerts':
                return itm_crud_record_share_create_alerts($conn, $recordId, $companyId, $employeeId, $ownerUsername);
            case 'equipment':
                return itm_crud_record_share_create_equipment($conn, $recordId, $companyId, $employeeId, $ownerUsername, $shareKind);
            case 'ops_report':
                return itm_crud_record_share_create_ops_report($conn, $recordId, $companyId, $employeeId, $ownerUsername);
            default:
                return itm_crud_record_share_create_from_config($conn, $moduleSlug, $recordId, $companyId, $employeeId, $ownerUsername);
        }
    }
}

if (!function_exists('itm_crud_record_share_handle_ajax_request')) {
    function itm_crud_record_share_handle_ajax_request($conn, $moduleSlug)
    {
        if (!isset($_GET['ajax_action']) || (string)$_GET['ajax_action'] !== 'create_share_session') {
            return false;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        header('Content-Type: application/json; charset=utf-8');
        if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $recordId = (int)($_POST['id'] ?? 0);
        $companyId = (int)($_SESSION['company_id'] ?? 0);
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        $ownerUsername = (string)($_SESSION['username'] ?? '');
        $shareKind = trim((string)($_POST['share_kind'] ?? 'record'));

        $result = itm_crud_record_share_create_session($conn, $moduleSlug, $recordId, $companyId, $employeeId, $ownerUsername, [
            'share_kind' => $shareKind,
        ]);
        if (!$result['ok']) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Unable to create share session.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $session = $result['session'];
        echo json_encode([
            'ok' => true,
            'share_code' => (string)($session['share_code'] ?? ''),
            'join_url' => itm_crud_record_share_build_join_url($moduleSlug, (string)($session['access_token'] ?? '')),
            'expires_at' => (string)($session['expires_at'] ?? ''),
            'ttl_seconds' => itm_qr_share_session_ttl_seconds(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('itm_crud_record_share_render_action_buttons')) {
    function itm_crud_record_share_render_action_buttons($moduleSlug, $recordId, $shareLabel = '', array $extraPostFields = [])
    {
        $moduleSlug = trim((string)$moduleSlug);
        $recordId = (int)$recordId;
        if ($moduleSlug === '' || $recordId <= 0) {
            return '';
        }
        $ajaxUrl = 'index.php?ajax_action=create_share_session';
        $shareLabel = trim((string)$shareLabel);
        if ($shareLabel === '') {
            $shareLabel = $moduleSlug;
        }
        $extraJson = '';
        if ($extraPostFields !== []) {
            $extraJson = ', ' . json_encode($extraPostFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        ob_start();
        ?>
        <button type="button" class="btn btn-sm" onclick="itmOpenQrShareModal('<?php echo sanitize($ajaxUrl); ?>', <?php echo $recordId; ?><?php echo $extraPostFields !== [] ? ', ' . sanitize(json_encode($extraPostFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : ''; ?>)" title="Share to device">📱</button>
        <button type="button" class="btn btn-sm" onclick="itmOpenWhatsAppShare('<?php echo sanitize($ajaxUrl); ?>', <?php echo $recordId; ?><?php echo $extraPostFields !== [] ? ', ' . sanitize(json_encode($extraPostFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : ', null'; ?>, '<?php echo sanitize($shareLabel); ?>')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
        <button type="button" class="btn btn-sm" onclick="itmOpenOutlookShare('<?php echo sanitize($ajaxUrl); ?>', <?php echo $recordId; ?><?php echo $extraPostFields !== [] ? ', ' . sanitize(json_encode($extraPostFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : ', null'; ?>, '<?php echo sanitize($shareLabel); ?>')" title="Share on Outlook">📨</button>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('itm_crud_record_share_include_modal')) {
    function itm_crud_record_share_include_modal()
    {
        require_once ROOT_PATH . 'includes/itm_qr_share_modal.php';
    }
}

if (!function_exists('itm_crud_record_share_render_join_page')) {
    function itm_crud_record_share_render_join_page($conn, $moduleSlug)
    {
        require_once ROOT_PATH . 'includes/itm_qr_share_join.php';

        $moduleSlug = trim((string)$moduleSlug);
        $configs = itm_crud_record_share_module_configs();
        $label = isset($configs[$moduleSlug]['label']) ? (string)$configs[$moduleSlug]['label'] : ucwords(str_replace('_', ' ', $moduleSlug));
        $customLabels = [
            'employees' => 'Employee',
            'tickets' => 'Ticket',
            'alerts' => 'Alert',
            'equipment' => 'Equipment',
            'ops_report' => 'Ops Report',
        ];
        if (isset($customLabels[$moduleSlug])) {
            $label = $customLabels[$moduleSlug];
        }

        $accessToken = trim((string)($_GET['t'] ?? ''));
        $submittedCode = itm_qr_share_normalize_code($_POST['code'] ?? ($_GET['code'] ?? ''));
        $error = '';
        $session = null;

        if ($accessToken !== '') {
            $session = itm_qr_share_fetch_session_by_token($conn, $moduleSlug, $accessToken);
            if (!$session) {
                $error = 'This share link has expired or is invalid.';
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $submittedCode !== '') {
            $session = itm_qr_share_fetch_session_by_code($conn, $moduleSlug, $submittedCode);
            if (!$session) {
                $error = 'Code not found or expired. Check the code and try again.';
            } else {
                $accessToken = (string)$session['access_token'];
            }
        }

        $payload = $session ? itm_qr_share_decode_payload($session['payload_json'] ?? '') : null;
        itm_qr_share_render_join_page(
            $label,
            itm_crud_record_share_join_script_path($moduleSlug),
            $accessToken,
            $submittedCode,
            $error,
            $session,
            $payload
        );
    }
}
