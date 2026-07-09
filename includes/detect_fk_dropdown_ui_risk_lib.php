<?php
/**
 * Why: Shared FK dropdown risk detection for CLI and browser tooling.
 */

if (!function_exists('itm_detect_fk_safe_identifier')) {
    function itm_detect_fk_safe_identifier($name): bool
    {
        return is_string($name) && $name !== '' && (bool)preg_match('/^[a-zA-Z0-9_]+$/', $name);
    }
}

if (!function_exists('itm_detect_fk_connect')) {
    /**
     * @return mysqli|null
     */
    function itm_detect_fk_connect()
    {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($envPath)) {
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    [$name, $value] = array_map('trim', explode('=', $line, 2));
                    if ($name !== '' && getenv($name) === false) {
                        putenv($name . '=' . $value);
                        $_ENV[$name] = $value;
                    }
                }
            }
        }

        $host = getenv('DB_HOST') !== false ? (string)getenv('DB_HOST') : 'localhost';
        $user = getenv('DB_USER') !== false ? (string)getenv('DB_USER') : 'root';
        $pass = getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : 'itmanagement';
        $name = getenv('DB_NAME') !== false ? (string)getenv('DB_NAME') : 'itmanagement';

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @mysqli_connect($host, $user, $pass, $name);
        if (!$conn && $host === 'localhost') {
            $conn = @mysqli_connect('127.0.0.1', $user, $pass, $name);
        }

        if (!$conn) {
            return null;
        }

        mysqli_set_charset($conn, 'utf8mb4');

        return $conn;
    }
}

if (!function_exists('itm_detect_fk_business_key_columns')) {
    /**
     * @return string[]
     */
    function itm_detect_fk_business_key_columns($table, array $columns): array
    {
        $map = [
            'it_locations' => ['name', 'location_code'],
            'suppliers' => ['name', 'supplier_code'],
            'manufacturers' => ['name', 'code'],
            'inventory_categories' => ['name', 'code'],
            'departments' => ['name', 'code'],
            'racks' => ['name', 'rack_code'],
            'equipment_types' => ['name', 'code'],
            'cost_centers' => ['name', 'code'],
            'gl_accounts' => ['account_code'],
            'location_types' => ['name'],
            'access_levels' => ['name'],
            'employee_roles' => ['name'],
            'approvals_stage' => ['stage'],
            'forecast_revisions_status' => ['status'],
            'employee_positions' => ['name'],
            'employee_type' => ['name_type'],
            'approver_type' => ['approver_type_description'],
            'equipment' => ['name', 'serial_number'],
        ];

        if (isset($map[$table])) {
            $keys = [];
            foreach ($map[$table] as $column) {
                if (in_array($column, $columns, true)) {
                    $keys[] = $column;
                }
            }
            if ($keys !== []) {
                return $keys;
            }
        }

        if (in_array('name', $columns, true) && in_array('code', $columns, true)) {
            return ['name', 'code'];
        }

        if (in_array('name', $columns, true)) {
            return ['name'];
        }

        return [];
    }
}

if (!function_exists('itm_detect_fk_table_columns')) {
    /**
     * @return string[]
     */
    function itm_detect_fk_table_columns(mysqli $conn, $table): array
    {
        if (!itm_detect_fk_safe_identifier($table)) {
            return [];
        }

        $columns = [];
        $res = mysqli_query($conn, 'DESCRIBE `' . $table . '`');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $columns[] = (string)($row['Field'] ?? '');
        }

        return array_values(array_filter($columns));
    }
}

if (!function_exists('itm_detect_fk_relations')) {
    /**
     * @return array<int, array<string, string>>
     */
    function itm_detect_fk_relations(mysqli $conn, $schema): array
    {
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $sql = "SELECT kcu.TABLE_NAME AS child_table,
                       kcu.COLUMN_NAME AS child_column,
                       kcu.REFERENCED_TABLE_NAME AS ref_table,
                       kcu.REFERENCED_COLUMN_NAME AS ref_column
                FROM information_schema.KEY_COLUMN_USAGE kcu
                INNER JOIN information_schema.COLUMNS cc
                    ON cc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                   AND cc.TABLE_NAME = kcu.TABLE_NAME
                   AND cc.COLUMN_NAME = 'company_id'
                INNER JOIN information_schema.COLUMNS rc
                    ON rc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                   AND rc.TABLE_NAME = kcu.REFERENCED_TABLE_NAME
                   AND rc.COLUMN_NAME = 'company_id'
                WHERE kcu.TABLE_SCHEMA = '{$schemaEsc}'
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME";

        $relations = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $relations[] = [
                'child_table' => (string)$row['child_table'],
                'child_column' => (string)$row['child_column'],
                'ref_table' => (string)$row['ref_table'],
                'ref_column' => (string)$row['ref_column'],
            ];
        }

        return $relations;
    }
}

if (!function_exists('itm_detect_fk_data_issues')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_detect_fk_data_issues(mysqli $conn, $schema, $companyFilter): array
    {
        $issues = [];
        $relations = itm_detect_fk_relations($conn, $schema);

        foreach ($relations as $relation) {
            $childTable = $relation['child_table'];
            $childColumn = $relation['child_column'];
            $refTable = $relation['ref_table'];
            $refColumn = $relation['ref_column'];

            if (!itm_detect_fk_safe_identifier($childTable)
                || !itm_detect_fk_safe_identifier($childColumn)
                || !itm_detect_fk_safe_identifier($refTable)
                || !itm_detect_fk_safe_identifier($refColumn)) {
                continue;
            }

            $refColumns = itm_detect_fk_table_columns($conn, $refTable);
            $businessKeys = itm_detect_fk_business_key_columns($refTable, $refColumns);
            if ($businessKeys === []) {
                continue;
            }

            $childColumns = itm_detect_fk_table_columns($conn, $childTable);
            if (!in_array('company_id', $childColumns, true)) {
                continue;
            }

            $companyWhere = $companyFilter > 0 ? (' AND c.company_id=' . (int)$companyFilter) : '';

            $sql = 'SELECT c.`id` AS child_id,
                           c.company_id AS child_company_id,
                           c.`' . $childColumn . '` AS fk_id,
                           r.company_id AS ref_company_id,
                           r.`' . implode('`, r.`', $businessKeys) . '`
                    FROM `' . $childTable . '` c
                    INNER JOIN `' . $refTable . '` r
                        ON r.`' . $refColumn . '` = c.`' . $childColumn . '`
                    WHERE c.`' . $childColumn . '` IS NOT NULL
                      AND c.`' . $childColumn . '` <> 0
                      AND c.company_id <> r.company_id' . $companyWhere . '
                    ORDER BY c.company_id, c.`id`';

            $res = mysqli_query($conn, $sql);
            if (!$res) {
                continue;
            }

            while ($row = mysqli_fetch_assoc($res)) {
                $childCompanyId = (int)$row['child_company_id'];
                $fkId = (int)$row['fk_id'];
                $tenantEquivalentId = 0;

                $whereParts = ['company_id = ' . $childCompanyId];
                foreach ($businessKeys as $keyColumn) {
                    $keyValue = isset($row[$keyColumn]) ? (string)$row[$keyColumn] : '';
                    if ($keyValue === '') {
                        $whereParts[] = '(`' . $keyColumn . "` = '' OR `" . $keyColumn . '` IS NULL)';
                    } else {
                        $whereParts[] = '`' . $keyColumn . "` = '" . mysqli_real_escape_string($conn, $keyValue) . "'";
                    }
                }

                $matchSql = 'SELECT `' . $refColumn . '` AS id FROM `' . $refTable . '`
                             WHERE ' . implode(' AND ', $whereParts) . '
                             ORDER BY `' . $refColumn . '` ASC LIMIT 1';
                $matchRes = mysqli_query($conn, $matchSql);
                if ($matchRes && ($matchRow = mysqli_fetch_assoc($matchRes))) {
                    $tenantEquivalentId = (int)$matchRow['id'];
                }

                $risk = 'cross_tenant_fk';
                if ($tenantEquivalentId > 0 && $tenantEquivalentId !== $fkId) {
                    $risk = 'duplicate_dropdown_risk';
                } elseif ($tenantEquivalentId <= 0) {
                    $risk = 'wrong_or_missing_tenant_row';
                }

                $keyLabelParts = [];
                foreach ($businessKeys as $keyColumn) {
                    $keyLabelParts[] = $keyColumn . '=' . (string)($row[$keyColumn] ?? '');
                }

                $issues[] = [
                    'risk' => $risk,
                    'child_table' => $childTable,
                    'child_id' => (int)$row['child_id'],
                    'child_company_id' => $childCompanyId,
                    'fk_column' => $childColumn,
                    'stored_fk_id' => $fkId,
                    'stored_ref_company_id' => (int)$row['ref_company_id'],
                    'tenant_equivalent_id' => $tenantEquivalentId,
                    'business_key' => implode(', ', $keyLabelParts),
                    'ref_table' => $refTable,
                    'module' => 'modules/' . $childTable . '/',
                ];
            }
        }

        return $issues;
    }
}

if (!function_exists('itm_detect_fk_code_issues')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_detect_fk_code_issues($modulesPath): array
    {
        $issues = [];
        if (!is_dir($modulesPath)) {
            return $issues;
        }

        $moduleDirs = array_values(array_filter(scandir($modulesPath), static function ($entry) use ($modulesPath) {
            return $entry !== '.' && $entry !== '..' && is_dir($modulesPath . DIRECTORY_SEPARATOR . $entry);
        }));

        foreach ($moduleDirs as $moduleName) {
            $indexPath = $modulesPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'index.php';
            if (!is_file($indexPath)) {
                continue;
            }

            $content = (string)file_get_contents($indexPath);
            if ($content === '') {
                continue;
            }

            $hasAppendFk = strpos($content, 'cr_append_selected_fk_option') !== false
                || (strpos($content, 'cr_fk_options_with_selected') !== false && strpos($content, 'function cr_fk_options_with_selected') !== false)
                || (bool)preg_match('/\$opts\[\]\s*=\s*\[\s*[\'"]id[\'"]\s*=>/i', $content)
                || (bool)preg_match('/\$opts\[\]\s*=\s*\$selectedFkOption\b/i', $content)
                || (strpos($content, 'cr_fk_option_by_id') !== false && strpos($content, '$opts[]') !== false);

            if (!$hasAppendFk) {
                continue;
            }

            $hasResolve = strpos($content, 'itm_fk_resolve_company_equivalent_id') !== false
                || strpos($content, 'itm_fk_append_selected_option') !== false
                || (strpos($content, 'function cr_append_selected_fk_option') !== false
                    && strpos($content, 'itm_fk_append_selected_option') !== false);

            if ($hasResolve) {
                continue;
            }

            $hasLegacyAppend = (bool)preg_match(
                '/function\s+cr_append_selected_fk_option\s*\([^)]*\)\s*\{[^}]*\$options\[\]\s*=\s*\[\s*[\'"]id[\'"]\s*=>\s*\$selectedId/s',
                $content
            );

            $hasPersistedFallback = (bool)preg_match(
                '/cr_fk_options_with_selected[\s\S]{0,400}\$options\[\]\s*=\s*\[/',
                $content
            ) && strpos($content, 'itm_fk_resolve_company_equivalent_id') === false;

            $risk = 'append_without_tenant_resolve';
            if ($hasLegacyAppend || $hasPersistedFallback) {
                $risk = 'duplicate_dropdown_code_risk';
            }

            $issues[] = [
                'risk' => $risk,
                'module' => 'modules/' . $moduleName . '/',
                'file' => 'modules/' . $moduleName . '/index.php',
                'note' => 'FK select builds company-scoped options and appends persisted id without itm_fk_resolve_company_equivalent_id().',
            ];
        }

        return $issues;
    }
}

if (!function_exists('itm_detect_fk_dropdown_ui_risk_run')) {
    /**
     * @param array{scan_scope?: string, company?: int, code_only?: bool, data_only?: bool} $options
     * @return array<string, mixed>
     */
    function itm_detect_fk_dropdown_ui_risk_run($root, $conn, array $options): array
    {
        $scanScope = (string)($options['scan_scope'] ?? 'full');
        $codeOnly = !empty($options['code_only']) || $scanScope === 'code_only';
        $dataOnly = !empty($options['data_only']) || $scanScope === 'data_only';
        $companyFilter = (int)($options['company'] ?? 0);

        $dataIssues = [];
        $codeIssues = [];
        $dbError = '';

        if (!$codeOnly) {
            $useConn = ($conn instanceof mysqli) ? $conn : itm_detect_fk_connect();
            $closeConn = !($conn instanceof mysqli);

            if ($useConn instanceof mysqli) {
                $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
                if ($schema === '') {
                    $schema = getenv('DB_NAME') !== false ? (string)getenv('DB_NAME') : 'itmanagement';
                }
                $dataIssues = itm_detect_fk_data_issues($useConn, $schema, $companyFilter);
                if ($closeConn) {
                    mysqli_close($useConn);
                }
            } else {
                $dbError = 'Database connection failed.';
            }
        }

        if (!$dataOnly) {
            $modulesPath = rtrim((string)$root, '/\\') . DIRECTORY_SEPARATOR . 'modules';
            $codeIssues = itm_detect_fk_code_issues($modulesPath);
        }

        return [
            'generated_at' => date('c'),
            'company_filter' => $companyFilter,
            'scan_scope' => $scanScope,
            'db_error' => $dbError,
            'summary' => [
                'data_issue_count' => count($dataIssues),
                'code_issue_count' => count($codeIssues),
                'duplicate_dropdown_data' => count(array_filter($dataIssues, static function ($row) {
                    return ($row['risk'] ?? '') === 'duplicate_dropdown_risk';
                })),
            ],
            'data_issues' => $dataIssues,
            'code_issues' => $codeIssues,
        ];
    }
}

if (!function_exists('itm_detect_fk_filter_issues_by_risk')) {
    /**
     * @param array<int, array<string, mixed>> $issues
     * @return array<int, array<string, mixed>>
     */
    function itm_detect_fk_filter_issues_by_risk(array $issues, $riskFilter): array
    {
        $riskFilter = (string)$riskFilter;
        if ($riskFilter === '' || $riskFilter === 'all') {
            return $issues;
        }

        return array_values(array_filter($issues, static function ($row) use ($riskFilter) {
            return (string)($row['risk'] ?? '') === $riskFilter;
        }));
    }
}

if (!function_exists('itm_detect_fk_risk_label')) {
    function itm_detect_fk_risk_label($riskCode): string
    {
        $map = [
            'duplicate_dropdown_risk' => 'Duplicate dropdown option',
            'cross_tenant_fk' => 'Cross-tenant foreign key',
            'wrong_or_missing_tenant_row' => 'Missing tenant FK row',
            'append_without_tenant_resolve' => 'Append FK without tenant resolve',
            'duplicate_dropdown_code_risk' => 'Duplicate dropdown (code pattern)',
        ];

        $riskCode = (string)$riskCode;

        return $map[$riskCode] ?? ucwords(str_replace('_', ' ', $riskCode));
    }
}

if (!function_exists('itm_detect_fk_column_label')) {
    function itm_detect_fk_column_label($columnName): string
    {
        $columnName = str_replace('_', ' ', trim((string)$columnName));
        if (substr($columnName, -3) === ' id') {
            $columnName = substr($columnName, 0, -3);
        }

        return $columnName === '' ? 'Foreign key' : ucwords($columnName);
    }
}

if (!function_exists('itm_detect_fk_data_issue_summary')) {
    /**
     * @param array<string, mixed> $issue
     */
    function itm_detect_fk_data_issue_summary(array $issue): string
    {
        $childCompanyId = (int)($issue['child_company_id'] ?? 0);
        $storedFkId = (int)($issue['stored_fk_id'] ?? 0);
        $refCompanyId = (int)($issue['stored_ref_company_id'] ?? 0);
        $tenantEquivalentId = (int)($issue['tenant_equivalent_id'] ?? 0);
        $fkColumn = (string)($issue['fk_column'] ?? '');
        $refTable = (string)($issue['ref_table'] ?? '');
        $businessKey = (string)($issue['business_key'] ?? '');
        $risk = (string)($issue['risk'] ?? '');
        $fkLabel = itm_detect_fk_column_label($fkColumn);

        if ($risk === 'duplicate_dropdown_risk' && $tenantEquivalentId > 0) {
            return sprintf(
                'Company %d row stores %s = %d (from company %d). Edit forms show two options; use tenant %s id %d (%s).',
                $childCompanyId,
                $fkLabel,
                $storedFkId,
                $refCompanyId,
                $refTable !== '' ? $refTable : 'reference',
                $tenantEquivalentId,
                $businessKey
            );
        }

        if ($risk === 'wrong_or_missing_tenant_row') {
            return sprintf(
                'Company %d row stores %s = %d (company %d) but no matching %s exists for this tenant (%s).',
                $childCompanyId,
                $fkLabel,
                $storedFkId,
                $refCompanyId,
                $refTable !== '' ? $refTable : 'reference row',
                $businessKey
            );
        }

        return sprintf(
            'Company %d row stores %s = %d pointing at company %d (%s).',
            $childCompanyId,
            $fkLabel,
            $storedFkId,
            $refCompanyId,
            $businessKey
        );
    }
}

if (!function_exists('itm_detect_fk_code_issue_summary')) {
    /**
     * @param array<string, mixed> $issue
     */
    function itm_detect_fk_code_issue_summary(array $issue): string
    {
        $module = trim((string)($issue['module'] ?? ''), '/');
        $risk = itm_detect_fk_risk_label((string)($issue['risk'] ?? ''));

        return $risk . ' in ' . ($module !== '' ? $module : 'module') . ': dropdown may append a persisted FK id from another tenant without resolving to the active company.';
    }
}
