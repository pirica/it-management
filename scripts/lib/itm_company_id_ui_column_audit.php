<?php
/**
 * Static audit helpers: which flattened CRUD modules expose company_id on list UI.
 *
 * Why: AGENTS.md requires hiding company_id from UI; scaffold modules use
 * $hideCompanyIdTables + $uiColumns filter — this reports drift without a DB.
 * Scans every PHP file under modules/{slug}/** (not index.php alone).
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

if (!function_exists('itm_company_id_ui_column_discover_module_slugs')) {
    /**
     * @return list<string>
     */
    function itm_company_id_ui_column_discover_module_slugs(?string $rootPath = null): array
    {
        $rootPath = $rootPath ?? dirname(__DIR__, 2);
        $slugs = [];
        foreach (glob(rtrim($rootPath, '/\\') . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slugs[] = basename($dir);
        }
        sort($slugs);

        return $slugs;
    }
}

if (!function_exists('itm_company_id_ui_column_collect_module_php_files')) {
    /**
     * @return array<string, string> repo-relative path => file contents
     */
    function itm_company_id_ui_column_collect_module_php_files(string $slug, ?string $rootPath = null): array
    {
        $rootPath = $rootPath ?? dirname(__DIR__, 2);
        $moduleDir = rtrim($rootPath, '/\\') . '/modules/' . $slug;
        if (!is_dir($moduleDir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $absolute = $fileInfo->getPathname();
            $content = file_get_contents($absolute);
            if ($content === false) {
                continue;
            }
            $relative = 'modules/' . $slug . '/' . ltrim(str_replace('\\', '/', substr($absolute, strlen($moduleDir))), '/');
            $files[$relative] = (string) $content;
        }
        ksort($files);

        return $files;
    }
}

if (!function_exists('itm_company_id_ui_column_parse_crud_table_from_files')) {
    /**
     * @param array<string, string> $filesByPath
     */
    function itm_company_id_ui_column_parse_crud_table_from_files(array $filesByPath): ?string
    {
        $priority = ['index.php', 'list_all.php', 'create.php', 'edit.php', 'view.php', 'delete.php'];
        $ordered = [];
        foreach ($priority as $basename) {
            foreach ($filesByPath as $path => $content) {
                if (substr($path, -strlen($basename)) === $basename) {
                    $ordered[$path] = $content;
                }
            }
        }
        foreach ($filesByPath as $path => $content) {
            if (!isset($ordered[$path])) {
                $ordered[$path] = $content;
            }
        }

        foreach ($ordered as $content) {
            $table = itm_fields_missing_parse_crud_table_from_content($content);
            if ($table !== null && $table !== '') {
                return $table;
            }
        }

        return null;
    }
}

if (!function_exists('itm_company_id_ui_column_bespoke_hides_company_id')) {
    /**
     * Heuristic for module PHP that omits company_id from list/view UI loops.
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

if (!function_exists('itm_company_id_ui_column_files_with_hide_list')) {
    /**
     * @param array<string, string> $filesByPath
     * @return array<string, string>
     */
    function itm_company_id_ui_column_files_with_hide_list(array $filesByPath): array
    {
        $matches = [];
        foreach ($filesByPath as $path => $content) {
            if (preg_match('/\$hideCompanyIdTables\s*=\s*\[/', $content)) {
                $matches[$path] = $content;
            }
        }

        return $matches;
    }
}

if (!function_exists('itm_company_id_ui_column_list_surface_files')) {
    /**
     * Entry files that can render list/view column loops.
     *
     * @param array<string, string> $filesByPath
     * @return array<string, string>
     */
    function itm_company_id_ui_column_list_surface_files(array $filesByPath): array
    {
        $surfaces = [];
        foreach ($filesByPath as $path => $content) {
            if (!preg_match('/foreach\s*\(\s*\$(uiColumns|displayFieldColumns|visibleFieldColumns|viewColumns)\s+as/', $content)) {
                continue;
            }
            if (preg_match('#/(index|list_all|view)\.php$#', $path)) {
                $surfaces[$path] = $content;
            }
        }

        return $surfaces;
    }
}

if (!function_exists('itm_company_id_ui_column_module_scaffold_hides')) {
    /**
     * @param array<string, string> $filesByPath
     */
    function itm_company_id_ui_column_module_scaffold_hides(array $filesByPath, string $table): bool
    {
        $hideFiles = itm_company_id_ui_column_files_with_hide_list($filesByPath);
        if ($hideFiles === []) {
            return false;
        }

        foreach ($hideFiles as $content) {
            if (!itm_company_id_ui_column_scaffold_table_in_hide_list($content, $table)) {
                return false;
            }
        }

        $surfaces = itm_company_id_ui_column_list_surface_files($filesByPath);
        if ($surfaces === []) {
            return true;
        }

        foreach ($surfaces as $content) {
            if (!itm_company_id_ui_column_bespoke_hides_company_id($content)
                && !itm_company_id_ui_column_scaffold_table_in_hide_list($content, $table)
            ) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('itm_company_id_ui_column_module_bespoke_hides')) {
    /**
     * @param array<string, string> $filesByPath
     */
    function itm_company_id_ui_column_module_bespoke_hides(array $filesByPath): bool
    {
        $surfaces = itm_company_id_ui_column_list_surface_files($filesByPath);
        if ($surfaces === []) {
            foreach ($filesByPath as $content) {
                if (itm_company_id_ui_column_bespoke_hides_company_id($content)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($surfaces as $content) {
            if (!itm_company_id_ui_column_bespoke_hides_company_id($content)) {
                return false;
            }
        }

        return true;
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
     *   not_crud: array<string, string>,
     *   file_counts: array<string, int>
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
            'file_counts' => [],
        ];

        foreach (itm_company_id_ui_column_discover_module_slugs($rootPath) as $slug) {
            $filesByPath = itm_company_id_ui_column_collect_module_php_files($slug, $rootPath);
            $report['file_counts'][$slug] = count($filesByPath);

            $table = itm_company_id_ui_column_parse_crud_table_from_files($filesByPath);
            if ($table === null || $table === '') {
                $report['not_crud'][$slug] = '';
                continue;
            }

            if (!isset($tablesWithCompanyId[$table])) {
                $report['no_company_column'][$slug] = $table;
                continue;
            }

            $hideFiles = itm_company_id_ui_column_files_with_hide_list($filesByPath);
            if ($hideFiles !== []) {
                if (itm_company_id_ui_column_module_scaffold_hides($filesByPath, $table)) {
                    $report['scaffold_hidden'][$slug] = $table;
                } else {
                    $report['scaffold_exposed'][$slug] = $table;
                }
                continue;
            }

            if (itm_company_id_ui_column_module_bespoke_hides($filesByPath)) {
                $report['bespoke_hidden'][$slug] = $table;
            } else {
                $report['bespoke_exposed'][$slug] = $table;
            }
        }

        foreach ($report as $bucket => $rows) {
            if ($bucket === 'file_counts') {
                ksort($report['file_counts']);
                continue;
            }
            ksort($report[$bucket]);
        }

        return $report;
    }
}

if (!function_exists('itm_company_id_ui_column_format_module_line')) {
    /**
     * One report bullet for a module slug (plain text CLI; localhost link in browser).
     */
    function itm_company_id_ui_column_format_module_line(
        string $slug,
        string $table,
        int $fileCount,
        bool $linkModules = false
    ): string {
        $suffix = $table !== '' ? ' [' . $table . ']' : '';
        $tail = ' (' . $fileCount . ' php file(s))';
        if (!$linkModules) {
            return '  - ' . $slug . $suffix . $tail;
        }

        require_once __DIR__ . '/script_browser_nav.php';
        $moduleLink = itm_script_format_modules_file_local_dev_link(
            'modules/' . $slug . '/index.php',
            $slug
        );

        return '  - ' . $moduleLink . $suffix . $tail;
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
     *   not_crud: array<string, string>,
     *   file_counts: array<string, int>
     * } $report
     * @param bool $linkModules Browser HTML: link each slug to modules/{slug}/index.php (target _blank)
     */
    function itm_company_id_ui_column_format_report(array $report, string $nl = "\n", bool $linkModules = false): string
    {
        $lines = [];
        $lines[] = 'Company column UI audit (company_id on flattened CRUD list tables)';
        $lines[] = str_repeat('-', 72);
        $lines[] = 'Scans every PHP file under modules/{slug}/** (recursive).';
        if ($linkModules) {
            $lines[] = 'Module slugs link to http://localhost/it-management/modules/{slug}/index.php (new tab).';
        }
        $lines[] = 'Legend: scaffold = $hideCompanyIdTables present; exposed = Company column may render.';
        $lines[] = '';

        $sections = [
            'scaffold_exposed' => 'SCAFFOLD EXPOSES COMPANY (add table to $hideCompanyIdTables on all scaffold entry files)',
            'bespoke_exposed' => 'BESPOKE / OTHER EXPOSES COMPANY (no hide list or bespoke hide on list/view surfaces)',
            'scaffold_hidden' => 'SCAFFOLD HIDES COMPANY ($hideCompanyIdTables on all scaffold entry files)',
            'bespoke_hidden' => 'BESPOKE HIDES COMPANY (custom filter / hidden input on list/view surfaces)',
            'no_company_column' => 'CRUD WITHOUT company_id COLUMN (informational)',
            'not_crud' => 'NON-CRUD module (no $crud_table in any PHP file; informational)',
        ];

        foreach ($sections as $key => $title) {
            $rows = $report[$key];
            $lines[] = $title . ' (' . count($rows) . '):';
            if ($rows === []) {
                $lines[] = '  (none)';
            } else {
                foreach ($rows as $slug => $table) {
                    $fileCount = (int) ($report['file_counts'][$slug] ?? 0);
                    $lines[] = itm_company_id_ui_column_format_module_line(
                        (string) $slug,
                        (string) $table,
                        $fileCount,
                        $linkModules
                    );
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
