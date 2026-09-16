<?php
/**
 * Build db/01_schema.sql table list with module slug links and db/02_data_sample.sql coverage.
 *
 * Used by list_db_tables_sample_data.php.
 */

if (!function_exists('itm_list_db_tables_sample_data_resolve_slug')) {
    /**
     * @param array<string, mixed> $classifyRow
     */
    function itm_list_db_tables_sample_data_resolve_slug(string $tableName, array $classifyRow): string
    {
        $moduleFolder = trim((string) ($classifyRow['module_folder'] ?? ''));
        if ($moduleFolder !== '') {
            $parts = preg_split('/\s*,\s*/', $moduleFolder) ?: [];
            $first = trim((string) ($parts[0] ?? ''));
            if ($first !== '') {
                return $first;
            }
        }

        if (function_exists('itm_script_table_has_module') && itm_script_table_has_module($tableName)) {
            return $tableName;
        }

        return '';
    }
}

if (!function_exists('itm_list_db_tables_sample_data_status')) {
    /**
     * @param array<string, array<int, array<string, mixed>>> $sampleInserts
     * @param array<string, true> $exempt
     */
    function itm_list_db_tables_sample_data_status(
        string $tableName,
        array $sampleInserts,
        array $exempt,
        bool $hasCompanyId
    ): string {
        if (isset($exempt[$tableName])) {
            return 'exempt';
        }

        $rows = $sampleInserts[$tableName] ?? [];
        if ($rows !== []) {
            return 'yes';
        }

        if (!$hasCompanyId) {
            return 'n/a';
        }

        return 'no';
    }
}

if (!function_exists('itm_database_tables_sample_data_report')) {
    /**
     * @return array{
     *   schema_path:string,
     *   sample_path:string,
     *   table_count:int,
     *   summary:array{yes:int,no:int,exempt:int,n/a:int},
     *   tables:array<int,array{table:string,slug:string,sample_data:string,module_folder:string,has_module:bool}>
     * }
     */
    function itm_database_tables_sample_data_report(string $schemaPath, string $samplePath): array
    {
        $reportLib = __DIR__ . '/itm_database_tables_modules_report.php';
        if (is_readable($reportLib)) {
            require_once $reportLib;
        }

        $sampleExport = dirname(__DIR__, 2) . '/includes/itm_sample_sql_export.php';
        if (is_readable($sampleExport)) {
            require_once $sampleExport;
        }

        $tableColumnsMap = itm_parse_database_sql_table_columns($schemaPath);
        $moduleMap = itm_scan_module_crud_map();
        $modulesByCrudTable = itm_database_tables_modules_build_crud_index($moduleMap);
        $policyHiddenTables = itm_database_tables_modules_policy_hidden_map();

        $sampleBody = is_readable($samplePath) ? (string) file_get_contents($samplePath) : '';
        $sampleInserts = function_exists('itm_parse_database_sql_inserts')
            ? itm_parse_database_sql_inserts($sampleBody)
            : [];
        $exempt = function_exists('itm_sample_sql_exempt_tables')
            ? itm_sample_sql_exempt_tables()
            : [];

        $summary = [
            'yes' => 0,
            'no' => 0,
            'exempt' => 0,
            'n/a' => 0,
        ];

        $rows = [];
        foreach (array_keys($tableColumnsMap) as $tableName) {
            $sqlColumns = $tableColumnsMap[$tableName] ?? [];
            $classifyRow = itm_database_tables_modules_classify_table(
                $tableName,
                $moduleMap,
                $modulesByCrudTable,
                $policyHiddenTables,
                $sqlColumns
            );
            $hasCompanyId = in_array('company_id', $sqlColumns, true);
            $sampleData = itm_list_db_tables_sample_data_status(
                $tableName,
                $sampleInserts,
                $exempt,
                $hasCompanyId
            );
            if (isset($summary[$sampleData])) {
                $summary[$sampleData]++;
            }

            $slug = itm_list_db_tables_sample_data_resolve_slug($tableName, $classifyRow);
            $hasModule = $slug !== ''
                && function_exists('itm_script_table_has_module')
                && itm_script_table_has_module($slug);

            $rows[] = [
                'table' => $tableName,
                'slug' => $slug,
                'sample_data' => $sampleData,
                'module_folder' => (string) ($classifyRow['module_folder'] ?? ''),
                'has_module' => $hasModule,
            ];
        }

        return [
            'schema_path' => $schemaPath,
            'sample_path' => $samplePath,
            'table_count' => count($rows),
            'summary' => $summary,
            'tables' => $rows,
        ];
    }
}
