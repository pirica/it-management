<?php
/**
 * Saved report views — filter/column presets for list modules (tickets, equipment, expenses).
 */

if (!function_exists('itm_saved_reports_supported_modules')) {
    function itm_saved_reports_supported_modules()
    {
        return ['tickets', 'equipment', 'expenses'];
    }
}

if (!function_exists('itm_saved_reports_filters_query_string')) {
    function itm_saved_reports_filters_query_string(array $filters, array $extra = [])
    {
        $merged = array_merge($filters, $extra);
        $parts = [];
        foreach ($merged as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'show_archived' && (int) $value !== 1) {
                continue;
            }
            $parts[$key] = $value;
        }
        return http_build_query($parts);
    }
}

if (!function_exists('itm_saved_reports_build_tabular_csv')) {
    function itm_saved_reports_build_tabular_csv(array $columns, array $labels, array $rows)
    {
        $header = [];
        foreach ($columns as $col) {
            $header[] = '"' . str_replace('"', '""', (string) ($labels[$col] ?? $col)) . '"';
        }
        $lines = [implode(',', $header)];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cells = [];
            foreach ($columns as $col) {
                $cells[] = '"' . str_replace('"', '""', (string) ($row[$col] ?? '')) . '"';
            }
            $lines[] = implode(',', $cells);
        }
        return implode("\r\n", $lines) . "\r\n";
    }
}

if (!function_exists('itm_saved_reports_module_config')) {
    /**
     * @return array{label:string,filters:array<string,string>,columns:array<string,string>,sortable:array<int,string>}
     */
    function itm_saved_reports_module_config($moduleSlug)
    {
        $moduleSlug = (string) $moduleSlug;
        $configs = [
            'tickets' => [
                'label' => 'Tickets',
                'filters' => [
                    'search' => 'string',
                    'show_archived' => 'bool',
                    'sort' => 'sort',
                    'dir' => 'dir',
                    'status_id' => 'int',
                    'priority_id' => 'int',
                    'assigned_to_employee_id' => 'int',
                    'due_date_from' => 'date',
                    'due_date_to' => 'date',
                ],
                'columns' => [
                    'id' => 'ID',
                    'ticket_external_code' => 'External Code',
                    'title' => 'Title',
                    'status_name' => 'Status',
                    'priority_name' => 'Priority',
                    'sla_status' => 'SLA',
                    'master_ticket_id' => 'Master Ticket',
                    'due_date' => 'Due Date',
                ],
                'sortable' => ['id', 'ticket_external_code', 'title', 'status_name', 'priority_name', 'due_date', 'master_ticket_id'],
            ],
            'equipment' => [
                'label' => 'Equipment',
                'filters' => [
                    'search' => 'string',
                    'sort' => 'sort',
                    'dir' => 'dir',
                    'equipment_type_name' => 'string',
                ],
                'columns' => [
                    'id' => 'ID',
                    'name' => 'Name',
                    'equipment_type_name' => 'Type',
                    'hostname' => 'Hostname',
                    'ip_address' => 'IP Address',
                    'idf_name' => 'IDF',
                    'rack_name' => 'Rack',
                    'location_name' => 'Location',
                    'manufacturer_name' => 'Manufacturer',
                    'mac_address' => 'MAC Address',
                    'department_label' => 'Department',
                    'status_name' => 'Status',
                ],
                'sortable' => ['id', 'name', 'equipment_type_name', 'hostname', 'ip_address', 'idf_name', 'rack_name', 'location_name', 'manufacturer_name', 'mac_address', 'department_label', 'status_name'],
            ],
            'expenses' => [
                'label' => 'Expenses',
                'filters' => [
                    'search' => 'string',
                    'sort' => 'sort',
                    'dir' => 'dir',
                    'date_from' => 'date',
                    'date_to' => 'date',
                    'paid_status_id' => 'int',
                    'supplier_id' => 'int',
                ],
                'columns' => [
                    'id' => 'ID',
                    'date' => 'Date',
                    'description' => 'Description',
                    'amount' => 'Amount',
                    'currency_code' => 'Currency',
                    'supplier_name' => 'Supplier',
                    'paid_status_name' => 'Paid Status',
                    'gl_account_name' => 'GL Account',
                    'cost_center_name' => 'Cost Center',
                    'invoice_number' => 'Invoice #',
                ],
                'sortable' => ['id', 'date', 'description', 'amount', 'currency_code', 'invoice_number'],
            ],
        ];
        return $configs[$moduleSlug] ?? [];
    }
}

if (!function_exists('itm_saved_reports_normalize_filter_value')) {
    function itm_saved_reports_normalize_filter_value($type, $value, array $config)
    {
        $type = (string) $type;
        if ($type === 'string') {
            $v = trim((string) $value);
            return $v === '' ? null : $v;
        }
        if ($type === 'bool') {
            return ((int) $value === 1 || $value === true || $value === '1' || $value === 'true') ? 1 : 0;
        }
        if ($type === 'int') {
            $n = (int) $value;
            return $n > 0 ? $n : null;
        }
        if ($type === 'date') {
            $v = trim((string) $value);
            if ($v === '') {
                return null;
            }
            if (function_exists('itm_parse_date_input')) {
                $parsed = itm_parse_date_input($v);
                return $parsed !== '' ? $parsed : null;
            }
            $ts = strtotime($v);
            return $ts ? date('Y-m-d', $ts) : null;
        }
        if ($type === 'sort') {
            $v = trim((string) $value);
            $sortable = $config['sortable'] ?? [];
            return in_array($v, $sortable, true) ? $v : 'id';
        }
        if ($type === 'dir') {
            $v = strtoupper(trim((string) $value));
            return $v === 'ASC' ? 'ASC' : 'DESC';
        }
        return null;
    }
}

if (!function_exists('itm_saved_reports_validate_filters')) {
    function itm_saved_reports_validate_filters($moduleSlug, $filters)
    {
        $config = itm_saved_reports_module_config($moduleSlug);
        if ($config === []) {
            return ['ok' => false, 'error' => 'Unsupported module.', 'filters' => []];
        }
        if (!is_array($filters)) {
            return ['ok' => false, 'error' => 'filters must be an object.', 'filters' => []];
        }
        $normalized = [];
        foreach ($config['filters'] as $key => $type) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }
            $val = itm_saved_reports_normalize_filter_value($type, $filters[$key], $config);
            if ($val !== null) {
                $normalized[$key] = $val;
            }
        }
        if (!isset($normalized['sort'])) {
            $normalized['sort'] = 'id';
        }
        if (!isset($normalized['dir'])) {
            $normalized['dir'] = 'DESC';
        }
        return ['ok' => true, 'error' => '', 'filters' => $normalized];
    }
}

if (!function_exists('itm_saved_reports_validate_columns')) {
    function itm_saved_reports_validate_columns($moduleSlug, $columns)
    {
        $config = itm_saved_reports_module_config($moduleSlug);
        if ($config === []) {
            return ['ok' => false, 'error' => 'Unsupported module.', 'columns' => []];
        }
        if (!is_array($columns)) {
            return ['ok' => false, 'error' => 'columns must be an array.', 'columns' => []];
        }
        $allowed = array_keys($config['columns']);
        $normalized = [];
        foreach ($columns as $col) {
            $col = trim((string) $col);
            if ($col !== '' && in_array($col, $allowed, true) && !in_array($col, $normalized, true)) {
                $normalized[] = $col;
            }
        }
        if ($normalized === []) {
            $normalized = $allowed;
        }
        return ['ok' => true, 'error' => '', 'columns' => $normalized];
    }
}

if (!function_exists('itm_saved_reports_validate_shared_scope')) {
    function itm_saved_reports_validate_shared_scope($scope)
    {
        $scope = strtolower(trim((string) $scope));
        return in_array($scope, ['private', 'department', 'company'], true) ? $scope : 'private';
    }
}

if (!function_exists('itm_saved_reports_employee_department_id')) {
    function itm_saved_reports_employee_department_id($conn, $companyId, $employeeId)
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT department_id FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int) ($row['department_id'] ?? 0);
    }
}

if (!function_exists('itm_saved_reports_can_view')) {
    function itm_saved_reports_can_view($conn, array $viewRow, $employeeId, $companyId)
    {
        $employeeId = (int) $employeeId;
        $companyId = (int) $companyId;
        if ($companyId <= 0 || (int) ($viewRow['company_id'] ?? 0) !== $companyId) {
            return false;
        }
        if ((int) ($viewRow['employee_id'] ?? 0) === $employeeId) {
            return true;
        }
        $scope = (string) ($viewRow['shared_scope'] ?? 'private');
        if ($scope === 'company') {
            return true;
        }
        if ($scope === 'department') {
            $shareDept = (int) ($viewRow['share_department_id'] ?? 0);
            if ($shareDept <= 0) {
                return false;
            }
            $viewerDept = itm_saved_reports_employee_department_id($conn, $companyId, $employeeId);
            return $viewerDept > 0 && $viewerDept === $shareDept;
        }
        return false;
    }
}

if (!function_exists('itm_saved_reports_fetch_by_id')) {
    function itm_saved_reports_fetch_by_id($conn, $viewId, $companyId)
    {
        $viewId = (int) $viewId;
        $companyId = (int) $companyId;
        if ($viewId <= 0 || $companyId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM saved_report_views WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $viewId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_saved_reports_decode_row')) {
    function itm_saved_reports_decode_row(array $row)
    {
        $filters = json_decode((string) ($row['filters_json'] ?? '{}'), true);
        $columns = json_decode((string) ($row['columns_json'] ?? '[]'), true);
        $row['filters'] = is_array($filters) ? $filters : [];
        $row['columns'] = is_array($columns) ? $columns : [];
        return $row;
    }
}

if (!function_exists('itm_saved_reports_list_visible')) {
    function itm_saved_reports_list_visible($conn, $companyId, $employeeId, $moduleSlug = null)
    {
        $companyId = (int) $companyId;
        $employeeId = (int) $employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return [];
        }
        $deptId = itm_saved_reports_employee_department_id($conn, $companyId, $employeeId);
        $sql = 'SELECT * FROM saved_report_views WHERE company_id = ? AND deleted_at IS NULL AND active = 1
            AND (
                employee_id = ?
                OR shared_scope = \'company\'
                OR (shared_scope = \'department\' AND share_department_id = ? AND share_department_id IS NOT NULL)
            )';
        $types = 'iii';
        $params = [$companyId, $employeeId, $deptId];
        if ($moduleSlug !== null && $moduleSlug !== '') {
            $sql .= ' AND module_slug = ?';
            $types .= 's';
            $params[] = (string) $moduleSlug;
        }
        $sql .= ' ORDER BY name ASC, id ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            if (itm_saved_reports_can_view($conn, $row, $employeeId, $companyId)) {
                $rows[] = itm_saved_reports_decode_row($row);
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_saved_reports_build_list_url')) {
    function itm_saved_reports_build_list_url($moduleSlug, array $filters)
    {
        $moduleSlug = (string) $moduleSlug;
        if (!in_array($moduleSlug, itm_saved_reports_supported_modules(), true)) {
            return '';
        }
        $base = '../' . $moduleSlug . '/index.php';
        $query = [];
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query[$key] = $value;
        }
        $qs = http_build_query($query);
        return $base . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('itm_saved_reports_capture_from_request')) {
    function itm_saved_reports_capture_from_request($moduleSlug, array $getParams, array $columnKeys = [])
    {
        $config = itm_saved_reports_module_config($moduleSlug);
        if ($config === []) {
            return ['filters' => [], 'columns' => []];
        }
        $filters = [];
        foreach ($config['filters'] as $key => $type) {
            if (!array_key_exists($key, $getParams)) {
                continue;
            }
            $val = itm_saved_reports_normalize_filter_value($type, $getParams[$key], $config);
            if ($val !== null) {
                $filters[$key] = $val;
            }
        }
        $colResult = itm_saved_reports_validate_columns($moduleSlug, $columnKeys !== [] ? $columnKeys : array_keys($config['columns']));
        return ['filters' => $filters, 'columns' => $colResult['columns']];
    }
}

if (!function_exists('itm_saved_reports_save')) {
    function itm_saved_reports_save($conn, array $payload)
    {
        $companyId = (int) ($payload['company_id'] ?? 0);
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $moduleSlug = trim((string) ($payload['module_slug'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $sharedScope = itm_saved_reports_validate_shared_scope($payload['shared_scope'] ?? 'private');
        $viewId = (int) ($payload['id'] ?? 0);

        if ($companyId <= 0 || $employeeId <= 0) {
            return ['ok' => false, 'error' => 'Session required.', 'id' => 0];
        }
        if (!in_array($moduleSlug, itm_saved_reports_supported_modules(), true)) {
            return ['ok' => false, 'error' => 'Unsupported module.', 'id' => 0];
        }
        if ($name === '' || strlen($name) > 200) {
            return ['ok' => false, 'error' => 'Name is required (max 200 characters).', 'id' => 0];
        }

        $filterResult = itm_saved_reports_validate_filters($moduleSlug, $payload['filters'] ?? []);
        if (!$filterResult['ok']) {
            return ['ok' => false, 'error' => $filterResult['error'], 'id' => 0];
        }
        $colResult = itm_saved_reports_validate_columns($moduleSlug, $payload['columns'] ?? []);
        if (!$colResult['ok']) {
            return ['ok' => false, 'error' => $colResult['error'], 'id' => 0];
        }

        $shareDeptId = null;
        if ($sharedScope === 'department') {
            $shareDeptId = itm_saved_reports_employee_department_id($conn, $companyId, $employeeId);
            if ($shareDeptId <= 0) {
                return ['ok' => false, 'error' => 'Department sharing requires a department on your employee profile.', 'id' => 0];
            }
        }

        $filtersJson = json_encode($filterResult['filters'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $columnsJson = json_encode($colResult['columns'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($viewId > 0) {
            $existing = itm_saved_reports_fetch_by_id($conn, $viewId, $companyId);
            if (!$existing || (int) $existing['employee_id'] !== $employeeId) {
                return ['ok' => false, 'error' => 'Only the owner can update this saved view.', 'id' => 0];
            }
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE saved_report_views SET name = ?, filters_json = ?, columns_json = ?, shared_scope = ?, share_department_id = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Database error.', 'id' => 0];
            }
            mysqli_stmt_bind_param($stmt, 'ssssiiiii', $name, $filtersJson, $columnsJson, $sharedScope, $shareDeptId, $employeeId, $viewId, $companyId, $employeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return ['ok' => true, 'error' => '', 'id' => $viewId];
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO saved_report_views (company_id, employee_id, module_slug, name, filters_json, columns_json, shared_scope, share_department_id, created_by, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error.', 'id' => 0];
        }
        mysqli_stmt_bind_param($stmt, 'iisssssii', $companyId, $employeeId, $moduleSlug, $name, $filtersJson, $columnsJson, $sharedScope, $shareDeptId, $employeeId);
        mysqli_stmt_execute($stmt);
        $newId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return ['ok' => $newId > 0, 'error' => $newId > 0 ? '' : 'Insert failed.', 'id' => $newId];
    }
}

if (!function_exists('itm_saved_reports_soft_delete')) {
    function itm_saved_reports_soft_delete($conn, $viewId, $employeeId, $companyId)
    {
        $viewId = (int) $viewId;
        $employeeId = (int) $employeeId;
        $companyId = (int) $companyId;
        $row = itm_saved_reports_fetch_by_id($conn, $viewId, $companyId);
        if (!$row || (int) $row['employee_id'] !== $employeeId) {
            return ['ok' => false, 'error' => 'Only the owner can delete this saved view.'];
        }
        if (!function_exists('itm_crud_build_soft_delete_sql')) {
            return ['ok' => false, 'error' => 'Soft delete helper missing.'];
        }
        $whereSql = 'id = ' . $viewId . ' AND company_id = ' . $companyId . ' AND employee_id = ' . $employeeId;
        $sql = itm_crud_build_soft_delete_sql('saved_report_views', $whereSql, $employeeId);
        itm_run_query($conn, $sql);
        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('itm_saved_reports_scheduled_slug')) {
    function itm_saved_reports_scheduled_slug($viewId)
    {
        return 'saved_view:' . (int) $viewId;
    }
}

if (!function_exists('itm_saved_reports_parse_scheduled_slug')) {
    function itm_saved_reports_parse_scheduled_slug($slug)
    {
        $slug = (string) $slug;
        if (strpos($slug, 'saved_view:') !== 0) {
            return 0;
        }
        return max(0, (int) substr($slug, strlen('saved_view:')));
    }
}

if (!function_exists('itm_saved_reports_run_query')) {
    /**
     * @return array{ok:bool,error:string,total:int,rows:array<int,array<string,mixed>>,columns:array<int,string>,labels:array<string,string>}
     */
    function itm_saved_reports_run_query($conn, $companyId, array $viewRow, array $options = [])
    {
        $companyId = (int) $companyId;
        $moduleSlug = (string) ($viewRow['module_slug'] ?? '');
        $viewRow = itm_saved_reports_decode_row($viewRow);
        $filters = $viewRow['filters'];
        $columns = $viewRow['columns'];
        $limit = max(1, min(500, (int) ($options['limit'] ?? 100)));
        $offset = max(0, (int) ($options['offset'] ?? 0));

        $config = itm_saved_reports_module_config($moduleSlug);
        if ($config === []) {
            return ['ok' => false, 'error' => 'Unsupported module.', 'total' => 0, 'rows' => [], 'columns' => [], 'labels' => []];
        }

        switch ($moduleSlug) {
            case 'tickets':
                return itm_saved_reports_run_tickets($conn, $companyId, $filters, $columns, $limit, $offset, $config);
            case 'equipment':
                return itm_saved_reports_run_equipment($conn, $companyId, $filters, $columns, $limit, $offset, $config);
            case 'expenses':
                return itm_saved_reports_run_expenses($conn, $companyId, $filters, $columns, $limit, $offset, $config);
            default:
                return ['ok' => false, 'error' => 'Unsupported module.', 'total' => 0, 'rows' => [], 'columns' => [], 'labels' => []];
        }
    }
}

if (!function_exists('itm_saved_reports_project_rows')) {
    function itm_saved_reports_project_rows(array $rawRows, array $columns, array $columnLabels)
    {
        $out = [];
        foreach ($rawRows as $raw) {
            $row = [];
            foreach ($columns as $col) {
                $row[$col] = $raw[$col] ?? null;
            }
            $out[] = $row;
        }
        $labels = [];
        foreach ($columns as $col) {
            $labels[$col] = $columnLabels[$col] ?? $col;
        }
        return ['rows' => $out, 'labels' => $labels];
    }
}

if (!function_exists('itm_saved_reports_run_tickets')) {
    function itm_saved_reports_run_tickets($conn, $companyId, array $filters, array $columns, $limit, $offset, array $config)
    {
        if (!function_exists('itm_tickets_list_count')) {
            require_once ROOT_PATH . 'includes/itm_tickets_list_query.php';
        }
        $total = itm_tickets_list_count($conn, (int) $companyId, $filters);
        $rawRows = itm_tickets_list_fetch($conn, (int) $companyId, $filters, (int) $limit, (int) $offset, true);
        $projected = itm_saved_reports_project_rows($rawRows, $columns, $config['columns']);
        return ['ok' => true, 'error' => '', 'total' => $total, 'rows' => $projected['rows'], 'columns' => $columns, 'labels' => $projected['labels']];
    }
}

if (!function_exists('itm_saved_reports_run_equipment')) {
    function itm_saved_reports_run_equipment($conn, $companyId, array $filters, array $columns, $limit, $offset, array $config)
    {
        if (!function_exists('itm_equipment_search_join_sql')) {
            require_once ROOT_PATH . 'includes/itm_equipment_search.php';
        }
        $searchRaw = trim((string) ($filters['search'] ?? ''));
        $isSearchActive = $searchRaw !== '';
        $joinSql = itm_equipment_search_join_sql($isSearchActive);
        $searchWhere = itm_equipment_build_search_where_sql($conn, $searchRaw);

        $typeFilter = trim((string) ($filters['equipment_type_name'] ?? ''));
        $typeSql = '';
        if ($typeFilter !== '') {
            $typeEsc = mysqli_real_escape_string($conn, strtolower($typeFilter));
            $typeSql = " AND LOWER(TRIM(et.name)) = '{$typeEsc}'";
        }

        $sort = (string) ($filters['sort'] ?? 'id');
        $dir = (string) ($filters['dir'] ?? 'DESC');
        $orderByMap = [
            'id' => 'e.id',
            'name' => 'e.name',
            'equipment_type_name' => 'et.name',
            'hostname' => 'e.hostname',
            'ip_address' => 'e.ip_address',
            'idf_name' => 'idf.name',
            'rack_name' => 'r.name',
            'location_name' => 'l.name',
            'manufacturer_name' => 'm.name',
            'mac_address' => 'e.mac_address',
            'department_label' => "COALESCE(NULLIF(TRIM(d.code), ''), d.name)",
            'status_name' => 'es.name',
        ];
        if (!isset($orderByMap[$sort])) {
            $sort = 'id';
        }
        $sortSql = $orderByMap[$sort] . ' ' . ($dir === 'ASC' ? 'ASC' : 'DESC');

        $where = "e.company_id = " . (int) $companyId . " AND e.deleted_at IS NULL" . $typeSql . $searchWhere;
        $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM equipment e ' . $joinSql . ' WHERE ' . $where);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $total = (int) ($countRow['total'] ?? 0);

        $selectSql = "SELECT e.id, e.name, et.name AS equipment_type_name, e.hostname, e.ip_address,
            idf.name AS idf_name, r.name AS rack_name, l.name AS location_name, m.name AS manufacturer_name,
            e.mac_address, COALESCE(NULLIF(TRIM(d.code), ''), d.name) AS department_label, es.name AS status_name
            FROM equipment e {$joinSql} WHERE {$where} ORDER BY {$sortSql} LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;
        $dataRes = mysqli_query($conn, $selectSql);
        $rawRows = [];
        while ($dataRes && ($row = mysqli_fetch_assoc($dataRes))) {
            $rawRows[] = $row;
        }
        $projected = itm_saved_reports_project_rows($rawRows, $columns, $config['columns']);
        return ['ok' => true, 'error' => '', 'total' => $total, 'rows' => $projected['rows'], 'columns' => $columns, 'labels' => $projected['labels']];
    }
}

if (!function_exists('itm_saved_reports_run_expenses')) {
    function itm_saved_reports_run_expenses($conn, $companyId, array $filters, array $columns, $limit, $offset, array $config)
    {
        $searchRaw = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'id');
        $dir = (string) ($filters['dir'] ?? 'DESC');
        $allowedSort = $config['sortable'] ?? ['id'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }
        $sortSql = 'e.' . preg_replace('/[^a-z0-9_]/i', '', $sort) . ' ' . ($dir === 'ASC' ? 'ASC' : 'DESC');

        $whereParts = ['e.company_id = ?', 'e.deleted_at IS NULL'];
        $bindTypes = 'i';
        $bindValues = [$companyId];

        if ($searchRaw !== '') {
            $searchPattern = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false) ? $searchRaw : '%' . $searchRaw . '%';
            $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
            $whereParts[] = "(CAST(e.id AS CHAR) LIKE '{$searchEsc}' OR e.description LIKE '{$searchEsc}' OR e.invoice_number LIKE '{$searchEsc}' OR CAST(e.amount AS CHAR) LIKE '{$searchEsc}')";
        }
        if (!empty($filters['date_from'])) {
            $whereParts[] = 'e.date >= ?';
            $bindTypes .= 's';
            $bindValues[] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $whereParts[] = 'e.date <= ?';
            $bindTypes .= 's';
            $bindValues[] = (string) $filters['date_to'];
        }
        if (!empty($filters['paid_status_id'])) {
            $whereParts[] = 'e.paid_status_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = (int) $filters['paid_status_id'];
        }
        if (!empty($filters['supplier_id'])) {
            $whereParts[] = 'e.supplier_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = (int) $filters['supplier_id'];
        }

        $whereSql = implode(' AND ', $whereParts);
        $joinSql = '
            LEFT JOIN suppliers sup ON sup.id = e.supplier_id AND sup.company_id = e.company_id
            LEFT JOIN paid_statuses ps ON ps.id = e.paid_status_id AND ps.company_id = e.company_id
            LEFT JOIN gl_accounts gl ON gl.id = e.gl_account_id AND gl.company_id = e.company_id
            LEFT JOIN cost_centers cc ON cc.id = e.cost_center_id AND cc.company_id = e.company_id';

        $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM expenses e WHERE ' . $whereSql);
        if (!$countStmt) {
            return ['ok' => false, 'error' => 'Query failed.', 'total' => 0, 'rows' => [], 'columns' => $columns, 'labels' => []];
        }
        mysqli_stmt_bind_param($countStmt, $bindTypes, ...$bindValues);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $total = (int) ($countRow['total'] ?? 0);
        mysqli_stmt_close($countStmt);

        $dataSql = 'SELECT e.id, e.date, e.description, e.amount, e.currency_code, e.invoice_number,
            sup.name AS supplier_name, ps.name AS paid_status_name, gl.name AS gl_account_name, cc.name AS cost_center_name
            FROM expenses e ' . $joinSql . ' WHERE ' . $whereSql . ' ORDER BY ' . $sortSql . ' LIMIT ? OFFSET ?';
        $bindTypesData = $bindTypes . 'ii';
        $bindValuesData = array_merge($bindValues, [(int) $limit, (int) $offset]);
        $dataStmt = mysqli_prepare($conn, $dataSql);
        if (!$dataStmt) {
            return ['ok' => false, 'error' => 'Query failed.', 'total' => $total, 'rows' => [], 'columns' => $columns, 'labels' => []];
        }
        mysqli_stmt_bind_param($dataStmt, $bindTypesData, ...$bindValuesData);
        mysqli_stmt_execute($dataStmt);
        $res = mysqli_stmt_get_result($dataStmt);
        $rawRows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rawRows[] = $row;
        }
        mysqli_stmt_close($dataStmt);

        $projected = itm_saved_reports_project_rows($rawRows, $columns, $config['columns']);
        return ['ok' => true, 'error' => '', 'total' => $total, 'rows' => $projected['rows'], 'columns' => $columns, 'labels' => $projected['labels']];
    }
}

if (!function_exists('itm_saved_reports_render_email_dataset')) {
    function itm_saved_reports_render_email_dataset(array $queryResult, $viewName = '')
    {
        $viewName = (string) $viewName;
        $labels = $queryResult['labels'] ?? [];
        $columns = $queryResult['columns'] ?? [];
        $rows = $queryResult['rows'] ?? [];
        $headerCells = '';
        foreach ($columns as $col) {
            $headerCells .= '<th>' . htmlspecialchars((string) ($labels[$col] ?? $col), ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $bodyRows = '';
        foreach ($rows as $row) {
            $bodyRows .= '<tr>';
            foreach ($columns as $col) {
                $val = $row[$col] ?? '';
                $bodyRows .= '<td>' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $bodyRows .= '</tr>';
        }
        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="' . max(1, count($columns)) . '">No rows match this saved view.</td></tr>';
        }
        $title = $viewName !== '' ? $viewName : 'Saved view';
        return [
            'title' => $title,
            'labels' => array_values($labels),
            'data' => array_map(static function ($row) {
                return is_array($row) ? implode(' | ', $row) : (string) $row;
            }, $rows),
            'html_table' => '<table border="1" cellpadding="6" cellspacing="0"><thead><tr>' . $headerCells . '</tr></thead><tbody>' . $bodyRows . '</tbody></table>',
            'total' => (int) ($queryResult['total'] ?? 0),
            'tabular_columns' => $columns,
            'tabular_rows' => $rows,
            'tabular_csv' => itm_saved_reports_build_tabular_csv($columns, $labels, $rows),
        ];
    }
}
