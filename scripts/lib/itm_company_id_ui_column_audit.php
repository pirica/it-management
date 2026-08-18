<?php
/**
 * Static audit helpers: which flattened CRUD modules expose company_id on list UI.
 *
 * Why: AGENTS.md requires hiding company_id from UI; scaffold modules use
 * $hideCompanyIdTables + $uiColumns filter — this reports drift without a DB.
 */

require_once __DIR__ . '/itm_fields_missing_report.php';

if (!function_exists('itm_company_id_ui_column_tables_with_company_id')) {
    /**
     * @return array<string, true>
     */
    function itm_company_id_ui_column_tables_with_company_id(?string $rootPath = null): array
    {
        $schema = itm_fields_missing_parse_database_sql_table_columns($rootPath);
        $tables = [];
        foreach ($schema as $table => $columns) {
            if (in_array('company_id', $columns, true)) {
                $tables[$table] = true;
            }
        }

        return $tables;
    }
}

if (!function_exists('itm_company_id_ui_column_bespoke_hides_company_id')) {
    /**
     * Heuristic for non-scaffold index.php files that omit company_id from list UI.
     */
    function itm_company_id_ui_column_bespoke_hides_company_id(string $content): bool
    {
        if (itm_fields_missing_file_hides_company_id_via_ui_columns($content)) {
            return true;
        }

        if (preg_match('/\$fieldName\s*!==\s*[\'"]company_id[\'"]/', $content)) {
            return true;
        }
        if (preg_match('/\$col\s*!==\s*[\'"]company_id[\'"]/', $content)) {
            return true;
        }
        if (preg_match('/\$name\s*===\s*[\'"]company_id[\'"][\s\S]{0,240}type=[\'"]hidden[\'"]/i', $content)) {
            return true;
        }
        if (preg_match('/unset\s*\(\s*\$uiColumns\s*\[\s*[\'"]company_id[\'"]\s*\]\s*\)/', $content)) {
            return true;
        }
        if (preg_match('/array_diff\s*\([^)]*company_id/', $content)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_company_id_ui_column_scaffold_table_in_hide_list')) {
    function itm_company_id_ui_column_scaffold_table_in_hide_list(string $content, string $table): bool
    {
        if (!preg_match('/\$hideCompanyIdTables\s*=\s*\[([^\]]+)\]/', $content, $hideMatch)) {
            return false;
        }

        return (bool) preg_match("/'" . preg_quote($table, '/') . "'/", $hideMatch[1]);
    }
}

if (!function_exists('itm_company_id_ui_column_collect_report')) {
    /**
     * @return array{
     *   scaffold_hidden: array<string, string>,
     *   scaffold_exposed: array<string, string>,
     *   bespoke_hidden: array<string, string>,
     *   bespoke_exposed: array<string, string>,
     *   no_company_column: array<string, string>,
     *   not_crud: array<string, string>
     * }
     */
    function itm_company_id_ui_column_collect_report(?string $rootPath = null): array
    {
        $rootPath = $rootPath ?? dirname(__DIR__, 2);
        $tablesWithCompanyId = itm_company_id_ui_column_tables_with_company_id($rootPath);

        $report = [
            'scaffold_hidden' => [],
            'scaffold_exposed' => [],
            'bespoke_hidden' => [],
            'bespoke_exposed' => [],
            'no_company_column' => [],
            'not_crud' => [],
        ];

        foreach (glob($rootPath . '/modules/*/index.php') ?: [] as $indexPath) {
            $slug = basename(dirname($indexPath));
            $content = (string) file_get_contents($indexPath);
            $table = itm_fields_missing_parse_crud_table_from_content($content);
            if ($table === null || $table === '') {
                $report['not_crud'][$slug] = '';
                continue;
            }

            if (!isset($tablesWithCompanyId[$table])) {
                $report['no_company_column'][$slug] = $table;
                continue;
            }

            $hasHideList = (bool) preg_match('/\$hideCompanyIdTables\s*=\s*\[/', $content);
            if ($hasHideList) {
                if (itm_company_id_ui_column_scaffold_table_in_hide_list($content, $table)) {
                    $report['scaffold_hidden'][$slug] = $table;
                } else {
                    $report['scaffold_exposed'][$slug] = $table;
                }
                continue;
            }

            if (itm_company_id_ui_column_bespoke_hides_company_id($content)) {
                $report['bespoke_hidden'][$slug] = $table;
            } else {
                $report['bespoke_exposed'][$slug] = $table;
            }
        }

        foreach ($report as $bucket => $rows) {
            ksort($report[$bucket]);
        }

        return $report;
    }
}

if (!function_exists('itm_company_id_ui_column_format_report')) {
    /**
     * @param array{
     *   scaffold_hidden: array<string, string>,
     *   scaffold_exposed: array<string, string>,
     *   bespoke_hidden: array<string, string>,
     *   bespoke_exposed: array<string, string>,
     *   no_company_column: array<string, string>,
     *   not_crud: array<string, string>
     * } $report
     */
    function itm_company_id_ui_column_format_report(array $report, string $nl = "\n"): string
    {
        $lines = [];
        $lines[] = 'Company column UI audit (company_id on flattened CRUD list tables)';
        $lines[] = str_repeat('-', 72);
        $lines[] = 'Legend: scaffold = $hideCompanyIdTables present; exposed = Company column may render.';
        $lines[] = '';

        $sections = [
            'scaffold_exposed' => 'SCAFFOLD EXPOSES COMPANY (add table to $hideCompanyIdTables)',
            'bespoke_exposed' => 'BESPOKE / OTHER EXPOSES COMPANY (no hide list or bespoke hide)',
            'scaffold_hidden' => 'SCAFFOLD HIDES COMPANY ($hideCompanyIdTables)',
            'bespoke_hidden' => 'BESPOKE HIDES COMPANY (custom filter / hidden input)',
            'no_company_column' => 'CRUD WITHOUT company_id COLUMN (informational)',
            'not_crud' => 'NON-CRUD index.php (no $crud_table; informational)',
        ];

        foreach ($sections as $key => $title) {
            $rows = $report[$key];
            $lines[] = $title . ' (' . count($rows) . '):';
            if ($rows === []) {
                $lines[] = '  (none)';
            } else {
                foreach ($rows as $slug => $table) {
                    $suffix = $table !== '' ? ' [' . $table . ']' : '';
                    $lines[] = '  - ' . $slug . $suffix;
                }
            }
            $lines[] = '';
        }

        $exposedTotal = count($report['scaffold_exposed']) + count($report['bespoke_exposed']);
        $lines[] = 'TOTAL EXPOSED: ' . $exposedTotal;
        $lines[] = '  scaffold: ' . count($report['scaffold_exposed']);
        $lines[] = '  bespoke/other: ' . count($report['bespoke_exposed']);

        return implode($nl, $lines) . $nl;
    }
}
