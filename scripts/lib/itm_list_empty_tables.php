<?php
/**
 * Shared tenant empty-table helpers for list_empty_tables.php and company sample-data reports.
 */

if (!function_exists('itm_list_empty_tables_resolve_tenant_tables')) {
    /**
     * @return array<int, string>
     */
    function itm_list_empty_tables_resolve_tenant_tables(mysqli $conn): array
    {
        $tables = [];
        $res = mysqli_query($conn, 'SHOW TABLES');
        while ($res && ($row = mysqli_fetch_row($res))) {
            $tableName = (string) ($row[0] ?? '');
            if ($tableName === '' || !itm_is_safe_identifier($tableName)) {
                continue;
            }
            if (!itm_table_has_column($conn, $tableName, 'company_id')) {
                continue;
            }
            $tables[] = $tableName;
        }
        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        return $tables;
    }
}

if (!function_exists('itm_list_empty_tables_tenant_live_row_count')) {
    function itm_list_empty_tables_tenant_live_row_count(mysqli $conn, string $tableName, int $companyId): int
    {
        if ($companyId <= 0 || !itm_is_safe_identifier($tableName)) {
            return -1;
        }

        $tableEsc = '`' . str_replace('`', '``', $tableName) . '`';
        $where = 'company_id = ' . (int) $companyId;
        if (itm_table_has_column($conn, $tableName, 'deleted_at')) {
            $where .= ' AND deleted_at IS NULL';
        }

        $sql = 'SELECT COUNT(*) AS row_count FROM ' . $tableEsc . ' WHERE ' . $where;
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return -1;
        }
        $row = mysqli_fetch_assoc($res);

        return isset($row['row_count']) ? (int) $row['row_count'] : -1;
    }
}

if (!function_exists('itm_list_empty_tables_collect_report')) {
    /**
     * @return array{
     *   company_id:int,
     *   empty_tables:array<int,array{table:string,module_href:string,has_module:bool}>,
     *   scanned_tables:int,
     *   non_empty_tables:int
     * }
     */
    function itm_list_empty_tables_collect_report(mysqli $conn, int $companyId, string $modulesRoot): array
    {
        $emptyTables = [];
        $scanned = 0;
        $nonEmpty = 0;

        foreach (itm_list_empty_tables_resolve_tenant_tables($conn) as $tableName) {
            $scanned++;
            $rowCount = itm_list_empty_tables_tenant_live_row_count($conn, $tableName, $companyId);
            if ($rowCount !== 0) {
                if ($rowCount > 0) {
                    $nonEmpty++;
                }
                continue;
            }

            $moduleIndex = rtrim($modulesRoot, '/\\') . DIRECTORY_SEPARATOR . $tableName . DIRECTORY_SEPARATOR . 'index.php';
            $hasModule = is_file($moduleIndex);
            $moduleHref = '../modules/' . rawurlencode($tableName) . '/index.php';

            $emptyTables[] = [
                'table' => $tableName,
                'module_href' => $moduleHref,
                'has_module' => $hasModule,
            ];
        }

        return [
            'company_id' => $companyId,
            'empty_tables' => $emptyTables,
            'scanned_tables' => $scanned,
            'non_empty_tables' => $nonEmpty,
        ];
    }
}
