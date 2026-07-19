<?php
/**
 * Tier 2 static check_* script list and subprocess runner for run_tier2_checks.php.
 *
 * Why: SCRIPTS_TEST_MATRIX.md is the canonical tier map; parsing keeps the batch runner in sync.
 */

require_once __DIR__ . '/itm_perform_audit.php';

if (!function_exists('itm_tier2_check_scripts_matrix_path')) {
    function itm_tier2_check_scripts_matrix_path(string $root): string
    {
        return rtrim($root, '/\\') . '/scripts/SCRIPTS_TEST_MATRIX.md';
    }
}

if (!function_exists('itm_tier2_check_scripts_canonical_fallback')) {
    /**
     * @return array<int,string>
     */
    function itm_tier2_check_scripts_canonical_fallback(): array
    {
        return [
            'check_audit_logs_coverage.php',
            'check_codacy_xss_echo.php',
            'check_crud_audit_soft_delete.php',
            'check_crud_rbac_coverage.php',
            'check_database_sql_company_name_uniques.php',
            'check_delimiters.php',
            'check_display_field_columns_search.php',
            'check_duplicates.php',
            'check_employees_clear_table_transaction.php',
            'check_equipment_clear_table_delete.php',
            'check_index_table_compliance.php',
            'check_multi_tenant_leaks.php',
            'check_phones.php',
            'check_points.php',
            'check_script_disposable_employees.php',
            'check_sql_errors.php',
            'check_stale_user_id_sql.php',
            'check_stale_user_terminology.php',
            'check_standard_crud_delegate_requires.php',
            'check_ui_action_emoji.php',
            'check_ui_configuration_coverage.php',
        ];
    }
}

if (!function_exists('itm_tier2_check_scripts_parse_matrix')) {
    /**
     * @return array<int,string>
     */
    function itm_tier2_check_scripts_parse_matrix(string $matrixPath): array
    {
        if (!is_file($matrixPath)) {
            return [];
        }

        $lines = file($matrixPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $scripts = [];
        foreach ($lines as $line) {
            if (!preg_match('/^\|\s*2\s*\|\s*`([^`]+)`\s*\|/', (string)$line, $m)) {
                continue;
            }
            $basename = (string)$m[1];
            if (strpos($basename, 'check_') !== 0 || substr($basename, -4) !== '.php') {
                continue;
            }
            $scripts[] = $basename;
        }

        $scripts = array_values(array_unique($scripts));
        sort($scripts);

        return $scripts;
    }
}

if (!function_exists('itm_tier2_check_scripts_resolve_list')) {
    /**
     * @return array{scripts: array<int,string>, source: string}
     */
    function itm_tier2_check_scripts_resolve_list(string $root): array
    {
        $parsed = itm_tier2_check_scripts_parse_matrix(itm_tier2_check_scripts_matrix_path($root));
        if ($parsed !== []) {
            return ['scripts' => $parsed, 'source' => 'SCRIPTS_TEST_MATRIX.md'];
        }

        return ['scripts' => itm_tier2_check_scripts_canonical_fallback(), 'source' => 'built-in fallback'];
    }
}

if (!function_exists('itm_tier2_check_scripts_resolve_php_binary')) {
    function itm_tier2_check_scripts_resolve_php_binary(): string
    {
        $env = getenv('PHP_BIN');
        if (is_string($env) && $env !== '' && is_file($env)) {
            return $env;
        }

        return itm_perform_audit_resolve_php_binary();
    }
}

if (!function_exists('itm_tier2_check_scripts_strip_ansi')) {
    function itm_tier2_check_scripts_strip_ansi(string $text): string
    {
        return (string)preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }
}

if (!function_exists('itm_tier2_check_scripts_tail_output')) {
    function itm_tier2_check_scripts_tail_output(string $text, int $maxChars = 4000): string
    {
        $text = itm_tier2_check_scripts_strip_ansi($text);
        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return substr($text, -$maxChars);
    }
}

if (!function_exists('itm_tier2_check_scripts_run_one')) {
    /**
     * @return array{exit: int, seconds: float, output: string}
     */
    function itm_tier2_check_scripts_run_one(string $phpBinary, string $scriptsDir, string $basename): array
    {
        $scriptPath = $scriptsDir . DIRECTORY_SEPARATOR . $basename;
        if (!is_file($scriptPath)) {
            return [
                'exit' => 127,
                'seconds' => 0.0,
                'output' => '[MISSING] ' . $basename,
            ];
        }

        $start = microtime(true);
        // Why: exec + quoted paths matches perform_audit.php and avoids Windows proc_open cmd parsing failures.
        $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
        $lines = [];
        $exit = 0;
        exec($cmd, $lines, $exit);
        $out = implode("\n", $lines);
        if ($out !== '' && substr($out, -1) !== "\n") {
            $out .= "\n";
        }

        return [
            'exit' => (int)$exit,
            'seconds' => round(microtime(true) - $start, 2),
            'output' => $out,
        ];
    }
}

if (!function_exists('itm_tier2_check_scripts_filter_only')) {
    /**
     * @param array<int,string> $scripts
     * @param array<int,string> $onlyBasenames
     * @return array<int,string>
     */
    function itm_tier2_check_scripts_filter_only(array $scripts, array $onlyBasenames): array
    {
        if ($onlyBasenames === []) {
            return $scripts;
        }

        $want = [];
        foreach ($onlyBasenames as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            if (strpos($item, '/') !== false || strpos($item, '\\') !== false) {
                $item = basename(str_replace('\\', '/', $item));
            }
            if (substr($item, -4) !== '.php') {
                $item .= '.php';
            }
            $want[$item] = true;
        }

        $filtered = [];
        foreach ($scripts as $script) {
            if (isset($want[$script])) {
                $filtered[] = $script;
            }
        }

        return $filtered;
    }
}
