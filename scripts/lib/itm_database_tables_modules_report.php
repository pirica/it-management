<?php
/**
 * Shared report builders: db/01_schema.sql or live MySQL tables vs modules/ folders.
 *
 * Used by compare_database_sql_modules.php and list_db_tables_without_modules.php.
 */

if (!function_exists('itm_database_tables_modules_single_line_text')) {
    function itm_database_tables_modules_single_line_text(string $text): string
    {
        return preg_replace('/\s+/', ' ', trim($text)) ?? '';
    }
}

if (!function_exists('itm_parse_database_sql_table_columns')) {
    /**
     * @return array<string, array<int, string>>
     */
    function itm_parse_database_sql_table_columns(string $sqlPath): array
    {
        if (!is_readable($sqlPath)) {
            return [];
        }

        if (!function_exists('itm_database_sql_unique_audit_parse_column_defs')) {
            $auditPath = dirname(__DIR__, 2) . '/includes/database_sql_unique_audit.php';
            if (is_readable($auditPath)) {
                require_once $auditPath;
            }
        }

        $sql = (string) file_get_contents($sqlPath);
        if (!preg_match_all(
            '/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $map = [];
        foreach ($matches as $match) {
            $tableName = (string) $match[1];
            if ($tableName === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($tableName)) {
                continue;
            }
            $map[$tableName] = function_exists('itm_database_sql_unique_audit_parse_column_defs')
                ? itm_database_sql_unique_audit_parse_column_defs((string) $match[2])
                : [];
        }

        ksort($map, SORT_NATURAL | SORT_FLAG_CASE);

        return $map;
    }
}

if (!function_exists('itm_database_tables_modules_columns_inline')) {
    /**
     * @param array<int, string> $columns
     */
    function itm_database_tables_modules_columns_inline(array $columns): string
    {
        return implode(', ', $columns);
    }
}

if (!function_exists('itm_parse_database_sql_table_names')) {
    /**
     * @return array<int, string>
     */
    function itm_parse_database_sql_table_names(string $sqlPath): array
    {
        return array_keys(itm_parse_database_sql_table_columns($sqlPath));
    }
}

if (!function_exists('itm_detect_module_crud_table')) {
    /**
     * Detects the database table a module targets from index.php and sibling entry files.
     */
    function itm_detect_module_crud_table(string $moduleName, string $moduleDir): ?string
    {
        $candidates = [];
        $indexPath = $moduleDir . '/index.php';
        if (is_file($indexPath)) {
            $candidates[] = $indexPath;
        }
        foreach (['create.php', 'list_all.php', 'view.php', 'edit.php'] as $entryFile) {
            $entryPath = $moduleDir . '/' . $entryFile;
            if (is_file($entryPath)) {
                $candidates[] = $entryPath;
            }
        }

        foreach ($candidates as $filePath) {
            $content = @file_get_contents($filePath);
            if (!is_string($content) || $content === '') {
                continue;
            }
            if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $m)) {
                return (string) $m[1];
            }
            if (preg_match('/\$crud_table\s*=\s*\$crud_table\s*\?\?\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $m)) {
                return (string) $m[1];
            }
            if (preg_match('/itm_handle_json_table_import\s*\(\s*\$conn\s*,\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $m)) {
                return (string) $m[1];
            }
        }

        if (is_file($indexPath)) {
            return $moduleName;
        }

        return null;
    }
}

if (!function_exists('itm_scan_module_crud_map')) {
    /**
     * @return array<string, array{module:string,crud_table:?string,has_index:bool}>
     */
    function itm_scan_module_crud_map(?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
        $rootPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR;
        $modulesRoot = $rootPath . 'modules/';
        $map = [];
        $entries = is_dir($modulesRoot) ? (scandir($modulesRoot) ?: []) : [];

        foreach ($entries as $moduleName) {
            if ($moduleName === '.' || $moduleName === '..') {
                continue;
            }
            $moduleDir = $modulesRoot . $moduleName;
            if (!is_dir($moduleDir)) {
                continue;
            }

            $hasIndex = is_file($moduleDir . '/index.php');
            $crudTable = itm_detect_module_crud_table($moduleName, $moduleDir);

            $map[$moduleName] = [
                'module' => $moduleName,
                'crud_table' => $crudTable,
                'has_index' => $hasIndex,
            ];
        }

        ksort($map, SORT_NATURAL | SORT_FLAG_CASE);

        return $map;
    }
}

if (!function_exists('itm_database_tables_modules_policy_hidden_map')) {
    /**
     * @return array<string, true>
     */
    function itm_database_tables_modules_policy_hidden_map(): array
    {
        $policyHiddenTables = function_exists('itm_sidebar_excluded_module_ids')
            ? array_fill_keys(itm_sidebar_excluded_module_ids(), true)
            : [];

        return $policyHiddenTables;
    }
}

if (!function_exists('itm_database_tables_modules_is_expected_internal')) {
    function itm_database_tables_modules_is_expected_internal(string $tableName, array $policyHiddenTables): bool
    {
        return (function_exists('itm_sidebar_module_is_hidden') && itm_sidebar_module_is_hidden($tableName))
            || isset($policyHiddenTables[$tableName]);
    }
}

if (!function_exists('itm_database_tables_modules_build_crud_index')) {
    /**
     * @param array<string, array{module:string,crud_table:?string,has_index:bool}> $moduleMap
     * @return array<string, array<int, string>>
     */
    function itm_database_tables_modules_build_crud_index(array $moduleMap): array
    {
        $modulesByCrudTable = [];
        foreach ($moduleMap as $moduleName => $meta) {
            $crud = (string) ($meta['crud_table'] ?? '');
            if ($crud !== '') {
                if (!isset($modulesByCrudTable[$crud])) {
                    $modulesByCrudTable[$crud] = [];
                }
                $modulesByCrudTable[$crud][] = $moduleName;
            }
        }

        return $modulesByCrudTable;
    }
}

if (!function_exists('itm_database_tables_modules_classify_table')) {
    /**
     * @param array<string, array{module:string,crud_table:?string,has_index:bool}> $moduleMap
     * @param array<string, array<int, string>> $modulesByCrudTable
     * @param array<string, true> $policyHiddenTables
     * @param array<int, string> $sqlColumns
     * @return array{table:string,status:string,module_folder:string,crud_table:string,columns:array<int,string>,columns_inline:string,notes:string}
     */
    function itm_database_tables_modules_classify_table(
        string $tableName,
        array $moduleMap,
        array $modulesByCrudTable,
        array $policyHiddenTables,
        array $sqlColumns
    ): array {
        $columnsInline = itm_database_tables_modules_columns_inline($sqlColumns);

        if (itm_database_tables_modules_is_expected_internal($tableName, $policyHiddenTables)) {
            return [
                'table' => $tableName,
                'status' => 'expected_internal',
                'module_folder' => '',
                'crud_table' => $tableName,
                'columns' => $sqlColumns,
                'columns_inline' => $columnsInline,
                'notes' => itm_database_tables_modules_single_line_text(
                    'Internal support table (no modules/ folder expected; managed inside a parent module).'
                ),
            ];
        }

        $moduleDir = $moduleMap[$tableName] ?? null;
        $moduleByName = $moduleDir !== null && !empty($moduleDir['has_index']);
        $crudOnNameModule = $moduleDir !== null ? (string) ($moduleDir['crud_table'] ?? '') : '';
        $linkedModules = $modulesByCrudTable[$tableName] ?? [];

        $status = 'table_no_module';
        $moduleFolder = '';
        $crudTable = '';
        $notes = itm_database_tables_modules_single_line_text(
            'No modules/' . $tableName . '/index.php and no module maps $crud_table here.'
        );

        if ($moduleByName && $crudOnNameModule === $tableName) {
            $status = 'matched';
            $moduleFolder = $tableName;
            $crudTable = $tableName;
            $notes = itm_database_tables_modules_single_line_text('modules/' . $tableName . '/ maps this table.');
        } elseif ($moduleByName && $crudOnNameModule !== '' && $crudOnNameModule !== $tableName) {
            $status = 'mismatch';
            $moduleFolder = $tableName;
            $crudTable = $crudOnNameModule;
            $notes = itm_database_tables_modules_single_line_text(
                'Folder name matches table but module maps a different table.'
            );
        } elseif ($moduleByName) {
            $status = 'matched';
            $moduleFolder = $tableName;
            $crudTable = $crudOnNameModule !== '' ? $crudOnNameModule : $tableName;
            $notes = itm_database_tables_modules_single_line_text(
                'modules/' . $tableName . '/index.php present (inferred mapping).'
            );
        } elseif ($linkedModules !== []) {
            $status = 'matched';
            $moduleFolder = implode(', ', $linkedModules);
            $crudTable = $tableName;
            $notes = itm_database_tables_modules_single_line_text(
                'Mapped via $crud_table from: ' . $moduleFolder . '.'
            );
        }

        return [
            'table' => $tableName,
            'status' => $status,
            'module_folder' => $moduleFolder,
            'crud_table' => $crudTable,
            'columns' => $sqlColumns,
            'columns_inline' => $columnsInline,
            'notes' => itm_database_tables_modules_single_line_text($notes),
        ];
    }
}

if (!function_exists('itm_database_live_table_names')) {
    /**
     * @return array<int, string>
     */
    function itm_database_live_table_names(mysqli $conn, ?string $schema = null): array
    {
        if ($schema === null) {
            $schema = defined('DB_NAME') ? (string) DB_NAME : '';
        }
        if ($schema === '') {
            return [];
        }

        $tables = [];
        $schemaEsc = $conn->real_escape_string($schema);
        $res = itm_run_query(
            $conn,
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $schemaEsc . "' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        );
        if ($res === false) {
            return [];
        }
        while ($row = $res->fetch_assoc()) {
            $tableName = (string) ($row['TABLE_NAME'] ?? '');
            if ($tableName !== '' && itm_is_safe_identifier($tableName)) {
                $tables[] = $tableName;
            }
        }

        return $tables;
    }
}

if (!function_exists('itm_database_live_table_columns')) {
    /**
     * @return array<string, array<int, string>>
     */
    function itm_database_live_table_columns(mysqli $conn, ?string $schema = null): array
    {
        if ($schema === null) {
            $schema = defined('DB_NAME') ? (string) DB_NAME : '';
        }
        if ($schema === '') {
            return [];
        }

        $map = [];
        $schemaEsc = $conn->real_escape_string($schema);
        $res = itm_run_query(
            $conn,
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '"
            . $schemaEsc
            . "' ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );
        if ($res === false) {
            return [];
        }
        while ($row = $res->fetch_assoc()) {
            $tableName = (string) ($row['TABLE_NAME'] ?? '');
            $columnName = (string) ($row['COLUMN_NAME'] ?? '');
            if ($tableName === '' || $columnName === '') {
                continue;
            }
            if (!isset($map[$tableName])) {
                $map[$tableName] = [];
            }
            $map[$tableName][] = $columnName;
        }

        ksort($map, SORT_NATURAL | SORT_FLAG_CASE);

        return $map;
    }
}

if (!function_exists('itm_compare_database_sql_modules_report')) {
    /**
     * @return array{
     *   source:string,
     *   schema:string,
     *   sql_path:string,
     *   table_count:int,
     *   module_count:int,
     *   summary:array<string,int>,
     *   tables:array<int,array<string,mixed>>,
     *   modules:array<int,array<string,mixed>>
     * }
     */
    function itm_compare_database_sql_modules_report(string $sqlPath): array
    {
        $tableColumnsMap = itm_parse_database_sql_table_columns($sqlPath);
        $tableNames = array_keys($tableColumnsMap);
        $moduleMap = itm_scan_module_crud_map();
        $tableSet = array_fill_keys($tableNames, true);
        $modulesByCrudTable = itm_database_tables_modules_build_crud_index($moduleMap);
        $policyHiddenTables = itm_database_tables_modules_policy_hidden_map();

        $tableRows = [];
        $summary = [
            'matched' => 0,
            'table_no_module' => 0,
            'expected_internal' => 0,
            'mismatch' => 0,
        ];

        foreach ($tableNames as $tableName) {
            $sqlColumns = $tableColumnsMap[$tableName] ?? [];
            $row = itm_database_tables_modules_classify_table(
                $tableName,
                $moduleMap,
                $modulesByCrudTable,
                $policyHiddenTables,
                $sqlColumns
            );
            $tableRows[] = $row;

            $status = (string) ($row['status'] ?? '');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        $moduleRows = [];
        $moduleSummary = [
            'matched' => 0,
            'module_no_table' => 0,
            'mismatch' => 0,
            'no_index' => 0,
        ];

        foreach ($moduleMap as $moduleName => $meta) {
            $hasIndex = !empty($meta['has_index']);
            $crudTable = (string) ($meta['crud_table'] ?? '');
            $status = 'matched';
            $notes = '';

            if (!$hasIndex) {
                $status = 'no_index';
                $notes = itm_database_tables_modules_single_line_text('Module folder exists without index.php.');
                $moduleSummary['no_index']++;
            } elseif ($crudTable === '') {
                $status = 'mismatch';
                $notes = itm_database_tables_modules_single_line_text('index.php has no $crud_table assignment.');
                $moduleSummary['mismatch']++;
            } elseif (!isset($tableSet[$crudTable])) {
                $status = 'module_no_table';
                $notes = itm_database_tables_modules_single_line_text(
                    '$crud_table not found in db/01_schema.sql CREATE TABLE list.'
                );
                $moduleSummary['module_no_table']++;
            } elseif ($crudTable !== $moduleName && !isset($tableSet[$moduleName])) {
                $status = 'matched';
                $notes = itm_database_tables_modules_single_line_text(
                    'Module name differs from table; $crud_table exists in db/01_schema.sql.'
                );
                $moduleSummary['matched']++;
            } elseif ($crudTable !== $moduleName && isset($tableSet[$moduleName])) {
                $status = 'matched';
                $notes = itm_database_tables_modules_single_line_text(
                    'Module folder matches a table name; $crud_table maps another existing table.'
                );
                $moduleSummary['matched']++;
            } else {
                $moduleSummary['matched']++;
                $notes = itm_database_tables_modules_single_line_text('Module folder and $crud_table align with db/.');
            }

            $mappedColumns = ($crudTable !== '' && isset($tableColumnsMap[$crudTable]))
                ? $tableColumnsMap[$crudTable]
                : [];
            $moduleRows[] = [
                'module' => $moduleName,
                'status' => $status,
                'crud_table' => $crudTable,
                'table_in_sql' => $crudTable !== '' && isset($tableSet[$crudTable]),
                'columns' => $mappedColumns,
                'columns_inline' => itm_database_tables_modules_columns_inline($mappedColumns),
                'notes' => itm_database_tables_modules_single_line_text($notes),
            ];
        }

        return [
            'source' => 'schema_sql',
            'schema' => '',
            'sql_path' => $sqlPath,
            'table_count' => count($tableNames),
            'module_count' => count($moduleMap),
            'summary' => [
                'tables_matched' => $summary['matched'],
                'tables_without_module' => $summary['table_no_module'],
                'tables_expected_internal' => $summary['expected_internal'],
                'tables_mismatch' => $summary['mismatch'],
                'modules_matched' => $moduleSummary['matched'],
                'modules_without_table' => $moduleSummary['module_no_table'],
                'modules_mismatch' => $moduleSummary['mismatch'],
                'modules_no_index' => $moduleSummary['no_index'],
            ],
            'tables' => $tableRows,
            'modules' => $moduleRows,
        ];
    }
}

if (!function_exists('itm_list_db_tables_without_modules_report')) {
    /**
     * Live database tables with no modules/ folder and no $crud_table mapping.
     *
     * @return array{
     *   source:string,
     *   schema:string,
     *   table_count:int,
     *   module_count:int,
     *   summary:array<string,int>,
     *   tables_without_module:array<int,array<string,mixed>>
     * }
     */
    function itm_list_db_tables_without_modules_report(mysqli $conn): array
    {
        $schema = defined('DB_NAME') ? (string) DB_NAME : '';
        $tableNames = itm_database_live_table_names($conn, $schema);
        $tableColumnsMap = itm_database_live_table_columns($conn, $schema);
        $moduleMap = itm_scan_module_crud_map();
        $modulesByCrudTable = itm_database_tables_modules_build_crud_index($moduleMap);
        $policyHiddenTables = itm_database_tables_modules_policy_hidden_map();

        $withoutModule = [];
        $summary = [
            'matched' => 0,
            'table_no_module' => 0,
            'expected_internal' => 0,
            'mismatch' => 0,
        ];

        foreach ($tableNames as $tableName) {
            $sqlColumns = $tableColumnsMap[$tableName] ?? [];
            $row = itm_database_tables_modules_classify_table(
                $tableName,
                $moduleMap,
                $modulesByCrudTable,
                $policyHiddenTables,
                $sqlColumns
            );
            $status = (string) ($row['status'] ?? '');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            if ($status === 'table_no_module') {
                $withoutModule[] = $row;
            }
        }

        return [
            'source' => 'live_database',
            'schema' => $schema,
            'table_count' => count($tableNames),
            'module_count' => count($moduleMap),
            'summary' => [
                'tables_matched' => $summary['matched'],
                'tables_without_module' => $summary['table_no_module'],
                'tables_expected_internal' => $summary['expected_internal'],
                'tables_mismatch' => $summary['mismatch'],
            ],
            'tables_without_module' => $withoutModule,
        ];
    }
}
