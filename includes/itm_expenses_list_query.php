<?php
/**
 * Shared expenses list filter/query helpers (index.php + saved report views).
 */

if (!function_exists('itm_expenses_list_parse_filters')) {
    function itm_expenses_list_parse_filters(array $get)
    {
        $config = function_exists('itm_saved_reports_module_config')
            ? itm_saved_reports_module_config('expenses')
            : ['sortable' => ['id', 'date', 'description', 'amount', 'currency_code', 'invoice_number']];
        $sortable = $config['sortable'] ?? ['id'];
        $sort = (string) ($get['sort'] ?? 'id');
        $dir = strtoupper((string) ($get['dir'] ?? 'DESC'));
        if (!in_array($sort, $sortable, true)) {
            $sort = 'id';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'DESC';
        }

        $filters = [
            'search' => trim((string) ($get['search'] ?? '')),
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach (['date_from' => 'date_from', 'date_to' => 'date_to'] as $key => $_) {
            $raw = trim((string) ($get[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (function_exists('itm_parse_date_input')) {
                $parsed = itm_parse_date_input($raw);
                if ($parsed !== '') {
                    $filters[$key] = $parsed;
                }
            } elseif (strtotime($raw)) {
                $filters[$key] = date('Y-m-d', strtotime($raw));
            }
        }

        $paidStatusId = (int) ($get['paid_status_id'] ?? 0);
        if ($paidStatusId > 0) {
            $filters['paid_status_id'] = $paidStatusId;
        }
        $supplierId = (int) ($get['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $filters['supplier_id'] = $supplierId;
        }

        return $filters;
    }
}

if (!function_exists('itm_expenses_list_append_filter_sql')) {
    function itm_expenses_list_append_filter_sql($where, $conn, array $filters)
    {
        if (!empty($filters['date_from'])) {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . "`date` >= '" . mysqli_real_escape_string($conn, (string) $filters['date_from']) . "'";
        }
        if (!empty($filters['date_to'])) {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . "`date` <= '" . mysqli_real_escape_string($conn, (string) $filters['date_to']) . "'";
        }
        if (!empty($filters['paid_status_id'])) {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'paid_status_id = ' . (int) $filters['paid_status_id'];
        }
        if (!empty($filters['supplier_id'])) {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'supplier_id = ' . (int) $filters['supplier_id'];
        }
        return $where;
    }
}

if (!function_exists('itm_expenses_list_load_filter_options')) {
    function itm_expenses_list_load_filter_options($conn, $companyId)
    {
        $companyId = (int) $companyId;
        $paidStatuses = [];
        $paidOptRes = mysqli_query($conn, 'SELECT id, name FROM paid_statuses WHERE company_id = ' . $companyId . ' ORDER BY name ASC');
        while ($paidOptRes && ($paidOptRow = mysqli_fetch_assoc($paidOptRes))) {
            $paidStatuses[] = $paidOptRow;
        }
        $suppliers = [];
        $supOptRes = mysqli_query(
            $conn,
            'SELECT id, name FROM suppliers WHERE company_id = ' . $companyId . ' AND deleted_at IS NULL ORDER BY name ASC'
        );
        while ($supOptRes && ($supOptRow = mysqli_fetch_assoc($supOptRes))) {
            $suppliers[] = $supOptRow;
        }
        return ['paid_statuses' => $paidStatuses, 'suppliers' => $suppliers];
    }
}

if (!function_exists('itm_expenses_list_count')) {
    function itm_expenses_list_count($conn, $companyId, array $filters)
    {
        $companyId = (int) $companyId;
        $whereParts = ['e.company_id = ?', 'e.deleted_at IS NULL'];
        $bindTypes = 'i';
        $bindValues = [$companyId];

        $searchRaw = trim((string) ($filters['search'] ?? ''));
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
        $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM expenses e WHERE ' . $whereSql);
        if (!$countStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($countStmt, $bindTypes, ...$bindValues);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        mysqli_stmt_close($countStmt);
        return (int) ($countRow['total'] ?? 0);
    }
}

if (!function_exists('itm_expenses_list_fetch')) {
    function itm_expenses_list_fetch($conn, $companyId, array $filters, $limit, $offset)
    {
        $companyId = (int) $companyId;
        $limit = (int) $limit;
        $offset = (int) $offset;
        $sort = (string) ($filters['sort'] ?? 'id');
        $dir = (string) ($filters['dir'] ?? 'DESC');
        $config = function_exists('itm_saved_reports_module_config')
            ? itm_saved_reports_module_config('expenses')
            : ['sortable' => ['id']];
        $allowedSort = $config['sortable'] ?? ['id'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }
        $sortSql = 'e.' . preg_replace('/[^a-z0-9_]/i', '', $sort) . ' ' . ($dir === 'ASC' ? 'ASC' : 'DESC');

        $whereParts = ['e.company_id = ?', 'e.deleted_at IS NULL'];
        $bindTypes = 'i';
        $bindValues = [$companyId];

        $searchRaw = trim((string) ($filters['search'] ?? ''));
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

        $dataSql = 'SELECT e.id, e.date, e.description, e.amount, e.currency_code, e.invoice_number,
            sup.name AS supplier_name, ps.name AS paid_status_name, gl.name AS gl_account_name, cc.name AS cost_center_name
            FROM expenses e ' . $joinSql . ' WHERE ' . $whereSql . ' ORDER BY ' . $sortSql . ' LIMIT ? OFFSET ?';
        $bindTypesData = $bindTypes . 'ii';
        $bindValuesData = array_merge($bindValues, [$limit, $offset]);
        $dataStmt = mysqli_prepare($conn, $dataSql);
        if (!$dataStmt) {
            return [];
        }
        mysqli_stmt_bind_param($dataStmt, $bindTypesData, ...$bindValuesData);
        mysqli_stmt_execute($dataStmt);
        $res = mysqli_stmt_get_result($dataStmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($dataStmt);
        return $rows;
    }
}
