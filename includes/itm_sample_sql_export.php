<?php
/**
 * Helpers to build db/02_data_sample.sql rows from db/02_data.sql and live MySQL.
 */

declare(strict_types=1);

if (!function_exists('itm_sample_sql_exempt_tables')) {
    /**
     * Tables excluded from mandatory sample templates (not Add sample data targets).
     *
     * @return array<string, true>
     */
    function itm_sample_sql_exempt_tables(): array
    {
        $tables = [
            'audit_logs',
            'employee_companies',
            'company_module_access',
            'employee_sidebar_preferences',
            'role_module_permissions',
            'role_hierarchy',
            'role_assignment_rights',
            'ui_configuration',
        ];

        foreach ([
            'share_sessions',
        ] as $shareTable) {
            $tables[] = $shareTable;
        }

        $exempt = [];
        foreach ($tables as $tableName) {
            $exempt[$tableName] = true;
        }

        return $exempt;
    }
}

if (!function_exists('itm_sample_sql_schema_tables_with_company_id')) {
    /**
     * @return array<int, string>
     */
    function itm_sample_sql_schema_tables_with_company_id(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $schemaPath = itm_database_sql_schema_path();
        if (!is_readable($schemaPath)) {
            return $cache;
        }

        $schema = (string)file_get_contents($schemaPath);
        if (!preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=/is', $schema, $matches, PREG_SET_ORDER)) {
            return $cache;
        }

        $exempt = itm_sample_sql_exempt_tables();
        foreach ($matches as $match) {
            $tableName = (string)($match[1] ?? '');
            if ($tableName === '' || isset($exempt[$tableName])) {
                continue;
            }
            if (stripos((string)($match[2] ?? ''), '`company_id`') === false) {
                continue;
            }
            $cache[] = $tableName;
        }

        sort($cache);

        return $cache;
    }
}

if (!function_exists('itm_sample_sql_quote_sql_value')) {
    function itm_sample_sql_quote_sql_value($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        $stringValue = (string)$value;

        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $stringValue) . "'";
    }
}

if (!function_exists('itm_sample_sql_format_insert_statement')) {
    /**
     * @param array{columns:array<int,string>,values:array<int,string>} $rowEntry
     */
    function itm_sample_sql_format_insert_statement(string $tableName, array $rowEntry): string
    {
        $columnsSql = implode(', ', array_map(static function ($col): string {
            $name = trim((string)$col, "` \t\n\r\0\x0B");

            return '`' . str_replace('`', '``', $name) . '`';
        }, $rowEntry['columns']));

        return 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . $columnsSql . ') VALUES ('
            . implode(', ', $rowEntry['values']) . ');';
    }
}

if (!function_exists('itm_sample_sql_filter_company_template_rows')) {
    /**
     * @param array<int, array{columns:array<int,string>,values:array<int,string>}> $insertRows
     * @return array<int, array{columns:array<int,string>,values:array<int,string>}>
     */
    function itm_sample_sql_filter_company_template_rows(array $insertRows, int $templateCompanyId): array
    {
        $filtered = [];
        foreach ($insertRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $companyIndex = null;
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === 'company_id') {
                    $companyIndex = $index;
                    break;
                }
            }

            if ($companyIndex === null) {
                $filtered[] = $rowEntry;
                continue;
            }

            $rawCompanyToken = trim((string)($rawValues[$companyIndex] ?? ''));
            if ($rawCompanyToken === '' || strtoupper($rawCompanyToken) === 'NULL') {
                continue;
            }
            $rawCompanyToken = trim($rawCompanyToken, "'\"");
            if ((int)$rawCompanyToken === $templateCompanyId) {
                $filtered[] = $rowEntry;
            }
        }

        return $filtered;
    }
}

if (!function_exists('itm_sample_sql_row_entry_from_assoc')) {
    /**
     * @param array<string, scalar|null> $row
     * @return array{columns:array<int,string>,values:array<int,string>}|null
     */
    function itm_sample_sql_row_entry_from_assoc(array $row, int $templateCompanyId): ?array
    {
        if ($row === []) {
            return null;
        }

        $columns = [];
        $values = [];
        foreach ($row as $columnName => $value) {
            if ($columnName === 'id') {
                continue;
            }
            if (!itm_is_safe_identifier($columnName)) {
                continue;
            }
            if ($columnName === 'company_id') {
                $value = $templateCompanyId;
            }
            $columns[] = '`' . $columnName . '`';
            $values[] = itm_sample_sql_quote_sql_value($value);
        }

        if ($columns === []) {
            return null;
        }

        return ['columns' => $columns, 'values' => $values];
    }
}

if (!function_exists('itm_sample_sql_fetch_template_row_from_db')) {
    /**
     * Prefer company_id = template marker; otherwise first tenant row (rewritten to template company_id).
     *
     * @return array{columns:array<int,string>,values:array<int,string>}|null
     */
    function itm_sample_sql_fetch_template_row_from_db(mysqli $conn, string $tableName, int $templateCompanyId): ?array
    {
        if (!itm_is_safe_identifier($tableName) || $templateCompanyId <= 0) {
            return null;
        }

        $tableEsc = '`' . str_replace('`', '``', $tableName) . '`';
        $sql = 'SELECT * FROM ' . $tableEsc . ' WHERE company_id = ' . (int)$templateCompanyId . ' ORDER BY id ASC LIMIT 1';
        $res = mysqli_query($conn, $sql);
        $row = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
        if (!is_array($row)) {
            $sqlAny = 'SELECT * FROM ' . $tableEsc . ' ORDER BY company_id ASC, id ASC LIMIT 1';
            $resAny = mysqli_query($conn, $sqlAny);
            $row = ($resAny && mysqli_num_rows($resAny) > 0) ? mysqli_fetch_assoc($resAny) : null;
        }

        if (!is_array($row)) {
            return null;
        }

        return itm_sample_sql_row_entry_from_assoc($row, $templateCompanyId);
    }
}

if (!function_exists('itm_sample_sql_parent_fk_marker_id')) {
    /**
     * Template FK marker when parent row exists only in sample SQL (not yet in MySQL).
     */
    function itm_sample_sql_parent_fk_marker_id(
        mysqli $conn,
        string $refTable,
        int $templateCompanyId,
        array $rowsByTable = []
    ): int {
        if (function_exists('itm_first_tenant_row_id')) {
            $liveId = itm_first_tenant_row_id($conn, $refTable, $templateCompanyId);
            if ($liveId > 0) {
                return $liveId;
            }
        }

        if (!empty($rowsByTable[$refTable][0])) {
            return 1;
        }

        return 0;
    }
}

if (!function_exists('itm_sample_sql_synthesize_template_row')) {
    /**
     * Build a minimal company 1 marker row when no SQL/DB source exists yet.
     *
     * @param array<string, array<int, array{columns:array<int,string>,values:array<int,string>}>> $rowsByTable
     * @return array{columns:array<int,string>,values:array<int,string>}|null
     */
    function itm_sample_sql_synthesize_template_row(
        mysqli $conn,
        string $tableName,
        int $templateCompanyId,
        array $rowsByTable = []
    ): ?array {
        if (!itm_is_safe_identifier($tableName) || $templateCompanyId <= 0) {
            return null;
        }

        if (!function_exists('itm_seed_table_column_metas')) {
            require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
        }

        $columnMetas = itm_seed_table_column_metas($conn, $tableName);
        if ($columnMetas === []) {
            return null;
        }

        $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $tableName) : [];
        $assoc = [];

        foreach ($columnMetas as $meta) {
            $name = (string)$meta['name'];
            if ($name === 'id' || strpos($meta['extra'], 'auto_increment') !== false) {
                continue;
            }
            if (in_array($name, ['deleted_by', 'deleted_at', 'created_by', 'updated_by', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $type = (string)$meta['type'];
            $nullable = (($meta['null'] ?? '') === 'YES');
            $value = null;

            if ($name === 'company_id') {
                $value = $templateCompanyId;
            } elseif (isset($fkMap[$name])) {
                $refTable = (string)($fkMap[$name]['REFERENCED_TABLE_NAME'] ?? '');
                $fkId = 0;
                if ($refTable !== '') {
                    $fkId = itm_sample_sql_parent_fk_marker_id($conn, $refTable, $templateCompanyId, $rowsByTable);
                }
                if ($fkId > 0) {
                    $value = $fkId;
                } elseif (!$nullable) {
                    return null;
                }
            } elseif ($name === 'port_id_b' && $tableName === 'idf_links') {
                $value = 2;
            } elseif ($name === 'port_no' || $name === 'position_no') {
                $value = 1;
            } elseif ($name === 'device_name') {
                $value = 'Sample Device';
            } elseif ($name === 'ip_text') {
                $value = '192.168.1.10';
            } elseif ($name === 'application') {
                $value = 'Sample Application';
            } elseif ($name === 'employee_id' || $name === 'created_by_employee_id') {
                $value = 1;
            } elseif ($name === 'title_hash') {
                $value = hash('sha256', 'Sample Template');
            } elseif (preg_match("/^enum\\('(.+)'\\)/i", $type, $enumMatch)) {
                $options = explode("','", str_replace("\\'", "'", $enumMatch[1]));
                $value = $options[0] ?? 'free';
            } elseif (function_exists('itm_seed_fill_scalar_fallback_value')) {
                $scalar = itm_seed_fill_scalar_fallback_value($name, $type, $tableName);
                if ($scalar !== null) {
                    $value = is_numeric($scalar) ? (int)$scalar : $scalar;
                }
            }

            if ($value === null && !$nullable && ($meta['default'] ?? null) === null) {
                return null;
            }
            if ($value !== null) {
                $assoc[$name] = $value;
            }
        }

        return itm_sample_sql_row_entry_from_assoc($assoc, $templateCompanyId);
    }
}

if (!function_exists('itm_sample_sql_clone_row_entry_port_no')) {
    /**
     * @param array{columns:array<int,string>,values:array<int,string>} $rowEntry
     * @return array{columns:array<int,string>,values:array<int,string>}|null
     */
    function itm_sample_sql_clone_row_entry_port_no(array $rowEntry, int $portNo): ?array
    {
        $clone = $rowEntry;
        foreach ($clone['columns'] as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName === 'port_no') {
                $clone['values'][$index] = (string)(int)$portNo;

                return $clone;
            }
        }

        return null;
    }
}

if (!function_exists('itm_sample_sql_append_extra_template_rows')) {
    /**
     * Tables that need more than one marker row for FK children (e.g. idf_links pair).
     *
     * @param array<string, array<int, array{columns:array<int,string>,values:array<int,string>}>> $rowsByTable
     */
    function itm_sample_sql_append_extra_template_rows(mysqli $conn, array &$rowsByTable, int $templateCompanyId): void
    {
        if (!empty($rowsByTable['idf_ports'][0]) && count($rowsByTable['idf_ports']) < 2) {
            $secondPort = itm_sample_sql_clone_row_entry_port_no($rowsByTable['idf_ports'][0], 2);
            if ($secondPort !== null) {
                $rowsByTable['idf_ports'][] = $secondPort;
            }
        }

        if (empty($rowsByTable['idf_links']) && count($rowsByTable['idf_ports'] ?? []) >= 1) {
            $linkRow = itm_sample_sql_synthesize_template_row($conn, 'idf_links', $templateCompanyId, $rowsByTable);
            if ($linkRow !== null) {
                $rowsByTable['idf_links'] = [$linkRow];
            }
        }
    }
}

if (!function_exists('itm_sample_sql_ensure_table_template_row')) {
    /**
     * @param array<string, array<int, array{columns:array<int,string>,values:array<int,string>}>> $rowsByTable
     * @param array<string, true> $visited
     * @param array<string, true> $inProgress
     * @return array{columns:array<int,string>,values:array<int,string>}|null
     */
    function itm_sample_sql_ensure_table_template_row(
        mysqli $conn,
        string $tableName,
        int $templateCompanyId,
        array &$rowsByTable,
        array &$visited,
        array &$inProgress,
        array &$dbBackfilled,
        array &$synthesized
    ): ?array {
        if (!itm_is_safe_identifier($tableName) || $templateCompanyId <= 0) {
            return null;
        }
        if (!empty($rowsByTable[$tableName][0])) {
            return $rowsByTable[$tableName][0];
        }
        if (isset($inProgress[$tableName])) {
            return null;
        }
        if (isset($visited[$tableName])) {
            return null;
        }
        $inProgress[$tableName] = true;

        if (function_exists('itm_table_outbound_fk_map')) {
            foreach (itm_table_outbound_fk_map($conn, $tableName) as $fkMeta) {
                $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
                if ($parentTable !== '' && itm_is_safe_identifier($parentTable) && !isset(itm_sample_sql_exempt_tables()[$parentTable])) {
                    itm_sample_sql_ensure_table_template_row(
                        $conn,
                        $parentTable,
                        $templateCompanyId,
                        $rowsByTable,
                        $visited,
                        $inProgress,
                        $dbBackfilled,
                        $synthesized
                    );
                }
            }
        }

        $dbRow = itm_sample_sql_fetch_template_row_from_db($conn, $tableName, $templateCompanyId);
        if ($dbRow !== null) {
            $rowsByTable[$tableName] = [$dbRow];
            $dbBackfilled[] = $tableName;
            $visited[$tableName] = true;
            unset($inProgress[$tableName]);

            return $dbRow;
        }

        $synthRow = itm_sample_sql_synthesize_template_row($conn, $tableName, $templateCompanyId, $rowsByTable);
        if ($synthRow !== null) {
            $rowsByTable[$tableName] = [$synthRow];
            $synthesized[] = $tableName;
            $visited[$tableName] = true;
            unset($inProgress[$tableName]);

            return $synthRow;
        }

        unset($inProgress[$tableName]);

        return null;
    }
}

if (!function_exists('itm_sample_sql_collect_table_templates')) {
    /**
     * @return array{
     *   rows_by_table:array<string,array<int,array{columns:array<int,string>,values:array<int,string>}>>,
     *   missing_tables:array<int,string>,
     *   db_backfilled:array<int,string>,
     *   synthesized:array<int,string>
     * }
     */
    function itm_sample_sql_collect_table_templates(mysqli $conn = null, int $templateCompanyId = 1): array
    {
        $templateCompanyId = $templateCompanyId > 0 ? $templateCompanyId : 1;
        $rowsByTable = [];
        $missingTables = [];
        $dbBackfilled = [];
        $synthesized = [];

        $sqlBody = function_exists('itm_database_sql_read_data') ? itm_database_sql_read_data() : '';
        $parsed = ($sqlBody !== '' && function_exists('itm_parse_database_sql_inserts'))
            ? itm_parse_database_sql_inserts($sqlBody)
            : [];

        foreach ($parsed as $tableName => $insertRows) {
            if (!itm_is_safe_identifier($tableName)) {
                continue;
            }
            $keptRows = itm_sample_sql_filter_company_template_rows($insertRows, $templateCompanyId);
            if ($keptRows !== []) {
                $rowsByTable[$tableName] = $keptRows;
            }
        }

        $visited = [];
        $inProgress = [];
        foreach (itm_sample_sql_schema_tables_with_company_id() as $tableName) {
            if (!empty($rowsByTable[$tableName])) {
                $visited[$tableName] = true;
                continue;
            }
            if (!$conn instanceof mysqli) {
                $missingTables[] = $tableName;
                continue;
            }

            $row = itm_sample_sql_ensure_table_template_row(
                $conn,
                $tableName,
                $templateCompanyId,
                $rowsByTable,
                $visited,
                $inProgress,
                $dbBackfilled,
                $synthesized
            );
            if ($row === null) {
                $missingTables[] = $tableName;
            }
        }

        if ($conn instanceof mysqli) {
            itm_sample_sql_append_extra_template_rows($conn, $rowsByTable, $templateCompanyId);
            foreach (['idf_links'] as $extraTable) {
                if (empty($rowsByTable[$extraTable]) && in_array($extraTable, itm_sample_sql_schema_tables_with_company_id(), true)) {
                    $missingTables[] = $extraTable;
                }
            }
            $missingTables = array_values(array_unique(array_filter($missingTables, static function ($tableName) use ($rowsByTable): bool {
                return empty($rowsByTable[$tableName]);
            })));
        }

        $dbBackfilled = array_values(array_unique($dbBackfilled));
        $synthesized = array_values(array_unique($synthesized));

        ksort($rowsByTable);

        return [
            'rows_by_table' => $rowsByTable,
            'missing_tables' => $missingTables,
            'db_backfilled' => $dbBackfilled,
            'synthesized' => $synthesized,
        ];
    }
}

if (!function_exists('itm_sample_sql_build_sample_file_body')) {
    function itm_sample_sql_build_sample_file_body(array $rowsByTable, int $templateCompanyId = 1): string
    {
        $lines = [];
        $lines[] = '-- ITM runtime sample templates for Add sample data and MBQA sample_data step.';
        $lines[] = '-- NOT imported by scripts/import_database_split.sh — use db/02_data.sql for fresh installs.';
        $lines[] = '-- Rows with company_id = ' . $templateCompanyId . ' are parse markers only; the seeder stamps the active tenant.';
        $lines[] = '-- Generated by scripts/extract_02_data_sample.php from db/02_data.sql and company ' . $templateCompanyId . ' MySQL rows.';
        $lines[] = '-- Every tenant-scoped module table must have at least one template row (see scripts/check_02_data_sample_coverage.php).';
        $lines[] = '';

        foreach ($rowsByTable as $tableName => $insertRows) {
            if ($insertRows === []) {
                continue;
            }

            $columnsSql = implode(', ', array_map(static function ($col): string {
                $name = trim((string)$col, "` \t\n\r\0\x0B");

                return '`' . str_replace('`', '``', $name) . '`';
            }, $insertRows[0]['columns']));

            $valueTuples = [];
            foreach ($insertRows as $rowEntry) {
                $valueTuples[] = '(' . implode(', ', $rowEntry['values']) . ')';
            }

            $lines[] = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . $columnsSql . ') VALUES';
            $lines[] = implode(",\n", $valueTuples) . ';';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
