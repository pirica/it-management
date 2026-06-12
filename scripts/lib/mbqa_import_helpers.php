<?php
/**
 * Module browser QA — import_db row builders (unique scope, tenant FK ids, header alignment).
 *
 * Why: import_db smoke tests must insert one row; duplicate unique keys and stale database.sql FK ids caused inserted=0.
 */

declare(strict_types=1);

function mbqa_parse_char_max_length(string $type): ?int
{
    if (preg_match('/^(?:var)?char\((\d+)\)/', $type, $match)) {
        return (int)$match[1];
    }

    return null;
}

/**
 * Why: QA import tags can exceed narrow varchar columns (e.g. cable_colors.color_name varchar(20)).
 */
function mbqa_fit_string_to_column_length(string $value, int $sequence, ?int $maxLen): string
{
    if ($maxLen === null || $maxLen <= 0 || strlen($value) <= $maxLen) {
        return $value;
    }

    $prefix = 'MBQA-' . $sequence . '-';
    $hashLen = $maxLen - strlen($prefix);
    if ($hashLen < 1) {
        return substr((string)$sequence, 0, $maxLen);
    }

    $short = $prefix . substr(md5($value), 0, $hashLen);

    return substr($short, 0, $maxLen);
}

/**
 * @return string[]
 */
function mbqa_primary_unique_scope_columns(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $sets = mbqa_table_unique_column_sets($conn, $table);
    $best = [];
    $bestScore = -1;
    foreach ($sets as $set) {
        if (!in_array('company_id', $set, true)) {
            continue;
        }
        $score = count($set);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $set;
        }
    }

    if (!empty($best)) {
        return $best;
    }

    if (empty($sets)) {
        return [];
    }

    usort($sets, static function (array $a, array $b): int {
        return count($b) <=> count($a);
    });

    return $sets[0];
}

/**
 * @return array<string, array{Field:string,Type:string}>
 */
function mbqa_table_columns_meta_by_name(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $meta = [];
    $res = mysqli_query($conn, 'SHOW COLUMNS FROM ' . $tableEsc);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '' && itm_is_safe_identifier($field)) {
            $meta[$field] = $row;
        }
    }

    return $meta;
}

/**
 * @param string[] $scopeCols
 * @return array<string, string>
 */
function mbqa_pick_free_values_for_unique_scope(mysqli $conn, string $table, int $companyId, array $scopeCols): array
{
    $scopeCols = array_values(array_filter($scopeCols, static function (string $col): bool {
        return $col !== 'company_id';
    }));
    if ($companyId <= 0 || empty($scopeCols) || !itm_is_safe_identifier($table)) {
        return [];
    }

    $suffix = date('YmdHis');
    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $companyIdSql = (int)$companyId;

    if (in_array($table, ['approvers', 'employee_assignment_history'], true) && in_array('employee_id', $scopeCols, true)) {
        $pickSql = 'SELECT e.id AS employee_id
            FROM employees e
            LEFT JOIN ' . $tableEsc . ' t ON t.company_id=' . $companyIdSql . ' AND t.employee_id=e.id
            WHERE e.company_id=' . $companyIdSql . ' AND t.id IS NULL
            ORDER BY e.id ASC
            LIMIT 1';
        $pickRes = mysqli_query($conn, $pickSql);
        $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        if (!$pick && function_exists('itm_seed_table_from_database_sql')) {
            $seedErr = '';
            itm_seed_table_from_database_sql($conn, 'employees', $companyId, $seedErr);
            $pickRes = mysqli_query($conn, $pickSql);
            $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        }
        if ($pick) {
            $picked = ['employee_id' => (string)(int)($pick['employee_id'] ?? 0)];
            if ($table === 'approvers') {
                $empId = (int)$picked['employee_id'];
                $posRes = mysqli_query($conn, 'SELECT id FROM employee_positions WHERE company_id=' . $companyIdSql . ' ORDER BY id ASC LIMIT 1');
                $deptRes = mysqli_query($conn, 'SELECT department_id FROM employees WHERE id=' . $empId . ' AND company_id=' . $companyIdSql . ' LIMIT 1');
                $typeRes = mysqli_query($conn, 'SELECT id FROM approver_type WHERE company_id=' . $companyIdSql . ' ORDER BY id ASC LIMIT 1');
                if ($posRes && ($posRow = mysqli_fetch_assoc($posRes))) {
                    $picked['employee_position_id'] = (string)(int)($posRow['id'] ?? 0);
                }
                if ($deptRes && ($deptRow = mysqli_fetch_assoc($deptRes))) {
                    $picked['department_id'] = (string)(int)($deptRow['department_id'] ?? 0);
                }
                if ($typeRes && ($typeRow = mysqli_fetch_assoc($typeRes))) {
                    $picked['approver_type_id'] = (string)(int)($typeRow['id'] ?? 0);
                }
            }
            if ($table === 'employee_assignment_history') {
                $picked['assigned_date'] = date('Y-m-d');
            }

            return $picked;
        }

        return [];
    }

    if ($table === 'annual_budgets') {
        for ($year = (int)date('Y') + 1; $year <= (int)date('Y') + 5; $year++) {
            $pickSql = 'SELECT cc.id AS cc_id, gl.id AS gl_id
                FROM cost_centers cc
                INNER JOIN gl_accounts gl ON gl.company_id = cc.company_id
                LEFT JOIN annual_budgets ab ON ab.company_id = ' . $companyIdSql
                . ' AND ab.cost_center_id = cc.id AND ab.gl_account_id = gl.id AND ab.year = ' . (int)$year . '
                WHERE cc.company_id = ' . $companyIdSql . ' AND ab.id IS NULL
                ORDER BY cc.id ASC, gl.id ASC
                LIMIT 1';
            $pickRes = mysqli_query($conn, $pickSql);
            $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
            if ($pick) {
                return [
                    'cost_center_id' => (string)(int)($pick['cc_id'] ?? 0),
                    'gl_account_id' => (string)(int)($pick['gl_id'] ?? 0),
                    'year' => (string)$year,
                ];
            }
        }

        return [];
    }

    if ($table === 'monthly_budgets') {
        for ($month = 12; $month >= 1; $month--) {
            $pickSql = 'SELECT ab.id AS ab_id
                FROM annual_budgets ab
                LEFT JOIN monthly_budgets mb ON mb.company_id = ' . $companyIdSql
                . ' AND mb.annual_budget_id = ab.id AND mb.month = ' . (int)$month . '
                WHERE ab.company_id = ' . $companyIdSql . ' AND mb.id IS NULL
                ORDER BY ab.id ASC
                LIMIT 1';
            $pickRes = mysqli_query($conn, $pickSql);
            $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
            if ($pick) {
                return [
                    'annual_budget_id' => (string)(int)($pick['ab_id'] ?? 0),
                    'month' => (string)$month,
                ];
            }
        }

        return [];
    }

    if ($table === 'forecast_revisions') {
        for ($year = (int)date('Y') + 1; $year <= (int)date('Y') + 5; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $pickSql = 'SELECT cc.id AS cc_id, gl.id AS gl_id
                    FROM cost_centers cc
                    INNER JOIN gl_accounts gl ON gl.company_id = cc.company_id
                    LEFT JOIN forecast_revisions fr ON fr.company_id = ' . $companyIdSql
                    . ' AND fr.cost_center_id = cc.id AND fr.gl_account_id = gl.id'
                    . ' AND fr.year = ' . (int)$year . ' AND fr.month = ' . (int)$month . '
                    WHERE cc.company_id = ' . $companyIdSql . ' AND fr.id IS NULL
                    ORDER BY cc.id ASC, gl.id ASC
                    LIMIT 1';
                $pickRes = mysqli_query($conn, $pickSql);
                $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
                if ($pick) {
                    return [
                        'cost_center_id' => (string)(int)($pick['cc_id'] ?? 0),
                        'gl_account_id' => (string)(int)($pick['gl_id'] ?? 0),
                        'year' => (string)$year,
                        'month' => (string)$month,
                    ];
                }
            }
        }

        return [];
    }

    if ($table === 'approvals') {
        $pickSql = 'SELECT fr.id AS fr_id
            FROM forecast_revisions fr
            LEFT JOIN approvals a ON a.company_id = ' . $companyIdSql . ' AND a.forecast_revision_id = fr.id
            WHERE fr.company_id = ' . $companyIdSql . ' AND a.id IS NULL
            ORDER BY fr.id ASC
            LIMIT 1';
        $pickRes = mysqli_query($conn, $pickSql);
        $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        if (!$pick) {
            mbqa_insert_random_rows($conn, 'forecast_revisions', $companyId, 1, 1);
            $pickRes = mysqli_query($conn, $pickSql);
            $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        }
        if ($pick) {
            $picked = ['forecast_revision_id' => (string)(int)($pick['fr_id'] ?? 0)];
            $stageRes = mysqli_query($conn, 'SELECT id FROM approvals_stage WHERE company_id=' . $companyIdSql . ' ORDER BY id ASC LIMIT 1');
            $statusRes = mysqli_query($conn, 'SELECT id FROM forecast_revisions_status WHERE company_id=' . $companyIdSql . ' ORDER BY id ASC LIMIT 1');
            if ($stageRes && ($stageRow = mysqli_fetch_assoc($stageRes))) {
                $picked['stage'] = (string)(int)($stageRow['id'] ?? 0);
            }
            if ($statusRes && ($statusRow = mysqli_fetch_assoc($statusRes))) {
                $picked['status'] = (string)(int)($statusRow['id'] ?? 0);
            }

            return $picked;
        }

        return [];
    }

    if ($table === 'role_assignment_rights') {
        $pickSql = 'SELECT r1.id AS role_id, r2.id AS can_assign_role_id
            FROM user_roles r1
            INNER JOIN user_roles r2 ON r2.company_id = r1.company_id AND r2.id <> r1.id
            LEFT JOIN role_assignment_rights rar ON rar.company_id = ' . $companyIdSql
            . ' AND rar.role_id = r1.id AND rar.can_assign_role_id = r2.id
            WHERE r1.company_id = ' . $companyIdSql . ' AND rar.id IS NULL
            ORDER BY r1.id ASC, r2.id ASC
            LIMIT 1';
        $pickRes = mysqli_query($conn, $pickSql);
        $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        if ($pick) {
            return [
                'role_id' => (string)(int)($pick['role_id'] ?? 0),
                'can_assign_role_id' => (string)(int)($pick['can_assign_role_id'] ?? 0),
            ];
        }

        return [];
    }

    if ($table === 'role_module_permissions') {
        $pickRes = mysqli_query($conn, 'SELECT id FROM user_roles WHERE company_id=' . $companyIdSql . ' ORDER BY id ASC LIMIT 1');
        $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        if ($pick) {
            return [
                'role_id' => (string)(int)($pick['id'] ?? 0),
                'module_name' => 'QA-IMPORT-MODULE-' . $suffix,
            ];
        }

        return [];
    }

    if ($table === 'ip_addresses') {
        $subnetRes = mysqli_query($conn, 'SELECT id FROM ip_subnets WHERE company_id=' . $companyIdSql . ' ORDER BY id ASC LIMIT 1');
        $subnetId = 0;
        if ($subnetRes && ($subnetRow = mysqli_fetch_assoc($subnetRes))) {
            $subnetId = (int)($subnetRow['id'] ?? 0);
        }
        if ($subnetId <= 0) {
            return [];
        }

        $seed = (int)substr((string)time(), -3);
        for ($offset = 0; $offset < 200; $offset++) {
            $host = 10 + (($seed + $offset) % 200);
            $ipText = '192.168.10.' . $host;
            $ipEsc = mysqli_real_escape_string($conn, $ipText);
            $checkRes = mysqli_query($conn, 'SELECT id FROM ip_addresses WHERE company_id=' . $companyIdSql
                . ' AND subnet_id=' . (int)$subnetId . " AND ip_text='{$ipEsc}' LIMIT 1");
            if ($checkRes && mysqli_num_rows($checkRes) === 0) {
                return [
                    'subnet_id' => (string)$subnetId,
                    'ip_text' => $ipText,
                ];
            }
        }

        return [];
    }

    $picked = [];
    $columnsMeta = mbqa_table_columns_meta_by_name($conn, $table);

    if (count($scopeCols) === 1) {
        $col = $scopeCols[0];
        $refTable = mbqa_fk_reference_table($col, $fkMap);
        if ($refTable !== '' && itm_is_safe_identifier($refTable)) {
            $fkMeta = $fkMap[$col] ?? null;
            if (is_array($fkMeta) && !mbqa_fk_reference_column_is_numeric($conn, $fkMeta)) {
                return [];
            }
            $refEsc = '`' . str_replace('`', '``', $refTable) . '`';
            $colEsc = '`' . str_replace('`', '``', $col) . '`';
            $parentHasCompany = itm_table_has_column($conn, $refTable, 'company_id');
            $parentWhere = $parentHasCompany ? 'p.company_id=' . $companyIdSql : '1=1';
            $pickSql = 'SELECT p.id AS pick_id FROM ' . $refEsc . ' p
                LEFT JOIN ' . $tableEsc . ' t ON t.company_id=' . $companyIdSql . ' AND t.' . $colEsc . '=p.id
                WHERE ' . $parentWhere . ' AND t.id IS NULL
                ORDER BY p.id ASC
                LIMIT 1';
            $pickRes = mysqli_query($conn, $pickSql);
            $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
            if ($pick) {
                $picked[$col] = (string)(int)($pick['pick_id'] ?? 0);
            }

            return $picked;
        }
    }

    foreach ($scopeCols as $col) {
        if (!itm_is_safe_identifier($col) || isset($picked[$col])) {
            continue;
        }

        $refTable = mbqa_fk_reference_table($col, $fkMap);
        if ($refTable !== '' && itm_is_safe_identifier($refTable)) {
            $fkMeta = $fkMap[$col] ?? null;
            if (is_array($fkMeta) && !mbqa_fk_reference_column_is_numeric($conn, $fkMeta)) {
                continue;
            }
            $freeId = mbqa_pick_fk_value($conn, $refTable, $companyId, true, 1);
            if ($freeId > 0) {
                $colEsc = '`' . str_replace('`', '``', $col) . '`';
                $checkRes = mysqli_query($conn, 'SELECT id FROM ' . $tableEsc . ' WHERE company_id=' . $companyIdSql
                    . ' AND ' . $colEsc . '=' . (int)$freeId . ' LIMIT 1');
                if ($checkRes && mysqli_num_rows($checkRes) === 0) {
                    $picked[$col] = (string)$freeId;
                }
            }
            continue;
        }

        $type = strtolower((string)($columnsMeta[$col]['Type'] ?? ''));
        if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
            $raw = 'QA-IMPORT-' . str_replace('_', '-', $col) . '-' . $suffix;
            $maxLen = mbqa_parse_char_max_length($type);
            $seq = (int)preg_replace('/\D/', '', $suffix);
            if ($seq <= 0) {
                $seq = 1;
            }
            $picked[$col] = mbqa_fit_string_to_column_length($raw, $seq, $maxLen);
            continue;
        }

        if (preg_match('/\b(int|year|tinyint|smallint|mediumint|bigint)\b/', $type)) {
            for ($candidate = 1; $candidate <= 999; $candidate++) {
                $colEsc = '`' . str_replace('`', '``', $col) . '`';
                $checkRes = mysqli_query($conn, 'SELECT id FROM ' . $tableEsc . ' WHERE company_id=' . $companyIdSql
                    . ' AND ' . $colEsc . '=' . (int)$candidate . ' LIMIT 1');
                if ($checkRes && mysqli_num_rows($checkRes) === 0) {
                    $picked[$col] = (string)$candidate;
                    break;
                }
            }
        }
    }

    return $picked;
}

/**
 * @param array<int, array<int, string>> $importRows
 * @return array<int, array<int, string>>
 */
function mbqa_apply_unique_scope_to_import_rows(mysqli $conn, string $table, int $companyId, array $importRows): array
{
    if (count($importRows) < 2 || $companyId <= 0 || !itm_is_safe_identifier($table)) {
        return $importRows;
    }
    if ($table === 'ip_subnets') {
        // The IPAM helper must keep cidr/network/gateway values valid together.
        return $importRows;
    }

    $scopeCols = mbqa_primary_unique_scope_columns($conn, $table);
    if (empty($scopeCols)) {
        return $importRows;
    }

    $freeValues = mbqa_pick_free_values_for_unique_scope($conn, $table, $companyId, $scopeCols);
    if (empty($freeValues)) {
        return $importRows;
    }

    $columnNames = mbqa_table_column_names($conn, $table);
    $headers = $importRows[0];
    $values = $importRows[1];
    foreach ($headers as $i => $header) {
        $col = mbqa_match_list_header_to_column((string)$header, $columnNames);
        if ($col !== null && isset($freeValues[$col])) {
            $values[$i] = $freeValues[$col];
        }
    }
    $importRows[1] = $values;

    return $importRows;
}

function mbqa_fk_reference_column_type(mysqli $conn, array $fkMeta): string
{
    $refTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
    $refCol = (string)($fkMeta['REFERENCED_COLUMN_NAME'] ?? 'id');
    if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refCol)) {
        return '';
    }

    $meta = mbqa_table_columns_meta_by_name($conn, $refTable);
    return strtolower((string)($meta[$refCol]['Type'] ?? ''));
}

function mbqa_fk_reference_column_is_numeric(mysqli $conn, array $fkMeta): bool
{
    $type = mbqa_fk_reference_column_type($conn, $fkMeta);
    return $type !== '' && (bool)preg_match('/\b(int|decimal|float|double|real|numeric)\b/', $type);
}

function mbqa_fk_value_exists_for_tenant(mysqli $conn, array $fkMeta, int $companyId, string $rawValue): bool
{
    $value = trim($rawValue);
    if ($value === '') {
        return false;
    }

    $refTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
    $refCol = (string)($fkMeta['REFERENCED_COLUMN_NAME'] ?? 'id');
    if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refCol)) {
        return false;
    }

    $tableEsc = '`' . str_replace('`', '``', $refTable) . '`';
    $colEsc = '`' . str_replace('`', '``', $refCol) . '`';
    $isNumericTarget = mbqa_fk_reference_column_is_numeric($conn, $fkMeta);
    if ($isNumericTarget) {
        if (!ctype_digit($value)) {
            return false;
        }
        $where = $colEsc . '=' . (int)$value;
    } else {
        $where = $colEsc . "='" . mysqli_real_escape_string($conn, $value) . "'";
    }
    if (itm_table_has_column($conn, $refTable, 'company_id') && $companyId > 0) {
        $where .= ' AND company_id=' . (int)$companyId;
    }

    $res = mysqli_query($conn, 'SELECT ' . $colEsc . ' FROM ' . $tableEsc . ' WHERE ' . $where . ' LIMIT 1');

    return $res && mysqli_num_rows($res) > 0;
}

function mbqa_fk_lookup_reference_value_by_id(mysqli $conn, array $fkMeta, int $companyId, int $fkId): string
{
    if ($fkId <= 0) {
        return '';
    }

    $refTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
    $refCol = (string)($fkMeta['REFERENCED_COLUMN_NAME'] ?? 'id');
    if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refCol)) {
        return '';
    }

    $tableEsc = '`' . str_replace('`', '``', $refTable) . '`';
    $colEsc = '`' . str_replace('`', '``', $refCol) . '`';
    $where = 'id=' . (int)$fkId;
    if (itm_table_has_column($conn, $refTable, 'company_id') && $companyId > 0) {
        $where .= ' AND company_id=' . (int)$companyId;
    }
    $res = mysqli_query($conn, 'SELECT ' . $colEsc . ' AS fk_value FROM ' . $tableEsc . ' WHERE ' . $where . ' LIMIT 1');
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || !array_key_exists('fk_value', $row)) {
        return '';
    }

    return trim((string)$row['fk_value']);
}

function mbqa_query_first_fk_value_for_tenant(mysqli $conn, array $fkMeta, int $companyId): string
{
    $refTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
    $refCol = (string)($fkMeta['REFERENCED_COLUMN_NAME'] ?? 'id');
    if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refCol)) {
        return '';
    }

    $tableEsc = '`' . str_replace('`', '``', $refTable) . '`';
    $colEsc = '`' . str_replace('`', '``', $refCol) . '`';
    $where = itm_table_has_column($conn, $refTable, 'company_id') && $companyId > 0
        ? ' WHERE company_id=' . (int)$companyId
        : '';
    $orderBy = itm_table_has_column($conn, $refTable, 'id') ? ' ORDER BY id ASC' : ' ORDER BY ' . $colEsc . ' ASC';
    $res = mysqli_query($conn, 'SELECT ' . $colEsc . ' AS fk_value FROM ' . $tableEsc . $where . $orderBy . ' LIMIT 1');
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || !array_key_exists('fk_value', $row)) {
        return '';
    }

    return trim((string)$row['fk_value']);
}

function mbqa_resolve_tenant_fk_import_value(mysqli $conn, array $fkMeta, int $companyId, string $preferredRaw = ''): string
{
    $preferred = trim($preferredRaw);
    if (strcasecmp($preferred, 'null') === 0) {
        $preferred = '';
    }

    $isNumericTarget = mbqa_fk_reference_column_is_numeric($conn, $fkMeta);
    if ($preferred !== '') {
        if ($isNumericTarget) {
            // Keep non-numeric labels (e.g. "RJ45", "Disabled") so module import handlers can resolve them.
            if (!ctype_digit($preferred)) {
                return $preferred;
            }
            if (mbqa_fk_value_exists_for_tenant($conn, $fkMeta, $companyId, $preferred)) {
                return ctype_digit($preferred) ? (string)(int)$preferred : $preferred;
            }
            if (ctype_digit($preferred) && function_exists('itm_fk_resolve_company_equivalent_id')) {
                $resolved = itm_fk_resolve_company_equivalent_id($conn, $fkMeta, $companyId, (int)$preferred);
                if ($resolved > 0 && mbqa_fk_value_exists_for_tenant($conn, $fkMeta, $companyId, (string)$resolved)) {
                    return (string)$resolved;
                }
            }
        } else {
            if (mbqa_fk_value_exists_for_tenant($conn, $fkMeta, $companyId, $preferred)) {
                return $preferred;
            }
            if (ctype_digit($preferred)) {
                $fromId = mbqa_fk_lookup_reference_value_by_id($conn, $fkMeta, $companyId, (int)$preferred);
                if ($fromId !== '' && mbqa_fk_value_exists_for_tenant($conn, $fkMeta, $companyId, $fromId)) {
                    return $fromId;
                }
            }
        }
    }

    return mbqa_query_first_fk_value_for_tenant($conn, $fkMeta, $companyId);
}

/**
 * @param array<int, array<int, string>> $importRows
 * @return array<int, array<int, string>>
 */
function mbqa_ensure_import_row_tenant_fk_values(mysqli $conn, string $table, int $companyId, array $importRows): array
{
    if (count($importRows) < 2 || $companyId <= 0 || !itm_is_safe_identifier($table)) {
        return $importRows;
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    if (empty($fkMap)) {
        return $importRows;
    }

    $columnNames = mbqa_table_column_names($conn, $table);
    $headers = $importRows[0];
    $values = $importRows[1];
    foreach ($headers as $i => $header) {
        $col = mbqa_match_list_header_to_column((string)$header, $columnNames);
        if ($col === null || !isset($fkMap[$col])) {
            continue;
        }

        $raw = trim((string)($values[$i] ?? ''));
        if (strcasecmp($raw, 'null') === 0) {
            $raw = '';
        }

        $resolved = mbqa_resolve_tenant_fk_import_value($conn, $fkMap[$col], $companyId, $raw);
        if ($resolved !== '') {
            $values[$i] = $resolved;
        }
    }
    $importRows[1] = $values;

    return $importRows;
}

/**
 * @return array<int, array<int, string>>
 */
function mbqa_build_fallback_import_rows(mysqli $conn, string $table, int $companyId): array
{
    if ($companyId <= 0 || !itm_is_safe_identifier($table)) {
        return [];
    }

    $byColumn = mbqa_database_sql_values_by_column($conn, $table, $companyId);
    $scopeCols = mbqa_primary_unique_scope_columns($conn, $table);
    $scopeValues = mbqa_pick_free_values_for_unique_scope($conn, $table, $companyId, $scopeCols);
    foreach ($scopeValues as $col => $val) {
        if ($val !== '') {
            $byColumn[$col] = $val;
        }
    }

    if ($table === 'ip_addresses') {
        $byColumn['status'] = $byColumn['status'] ?? 'free';
        $byColumn['active'] = $byColumn['active'] ?? '1';
    }

    if (empty($byColumn)) {
        return [];
    }

    $headers = [];
    $values = [];
    foreach ($byColumn as $col => $val) {
        if (!itm_is_safe_identifier((string)$col)) {
            continue;
        }
        $headers[] = function_exists('itm_humanize_field_name')
            ? itm_humanize_field_name((string)$col)
            : mbqa_humanize_field_label((string)$col);
        $values[] = (string)$val;
    }

    if (empty($headers)) {
        return [];
    }

    $rows = [$headers, $values];

    return mbqa_align_import_headers_for_crud_import(
        $conn,
        $table,
        mbqa_apply_unique_scope_to_import_rows(
            $conn,
            $table,
            $companyId,
            mbqa_ensure_import_row_tenant_fk_values($conn, $table, $companyId, $rows)
        )
    );
}

/**
 * @return array<int, array<int, string>>
 */
function mbqa_ip_addresses_import_rows(mysqli $conn, int $companyId): array
{
    $picked = mbqa_pick_free_values_for_unique_scope(
        $conn,
        'ip_addresses',
        $companyId,
        ['company_id', 'subnet_id', 'ip_text']
    );
    if (empty($picked['subnet_id']) || empty($picked['ip_text'])) {
        return [];
    }

    return [
        ['Subnet', 'IP Address', 'Status', 'Active'],
        [(string)$picked['subnet_id'], (string)$picked['ip_text'], 'free', '1'],
    ];
}
