<?php
/**
 * Shared helpers for scripts/perform_audit.php subprocess audit runner.
 */

if (!function_exists('itm_perform_audit_is_cli_php_binary')) {
    function itm_perform_audit_is_cli_php_binary($path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', (string)$path));
        if ($normalized === '' || !is_file($path)) {
            return false;
        }
        if (strpos($normalized, 'php-cgi') !== false) {
            return false;
        }
        if (substr($normalized, -4) === '.dll') {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_perform_audit_resolve_php_binary')) {
    function itm_perform_audit_resolve_php_binary(): string
    {
        $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
        if (is_file($laragonPhp)) {
            return $laragonPhp;
        }
        if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_perform_audit_is_cli_php_binary(PHP_BINARY)) {
            return (string)PHP_BINARY;
        }

        return 'php';
    }
}

if (!function_exists('itm_perform_audit_prepare_db_env')) {
    function itm_perform_audit_prepare_db_env(): void
    {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_USER=root');
        putenv('DB_PASS=itmanagement');
        putenv('DB_NAME=itmanagement');
        putenv('ITM_CLI_SCRIPT=1');
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASS'] = 'itmanagement';
        $_ENV['DB_NAME'] = 'itmanagement';
        $_ENV['ITM_CLI_SCRIPT'] = '1';
    }
}

if (!function_exists('itm_perform_audit_static_exclusions')) {
    /**
     * @return array<int,string>
     */
    function itm_perform_audit_static_exclusions(): array
    {
        return [
            'perform_audit.php',
            'scripts.php',
            'api.php',
            '_matrix_safe_run_once.php',
            'apply_bulk_actions_records_per_page_gate.php',
            'apply_bulk_delete_cancel_ux.php',
            'apply_crud_audit_soft_delete.php',
            'apply_crud_fk_label_search.php',
            'apply_crud_hidden_employee_id_alias.php',
            'apply_crud_rbac_guards.php',
            'apply_date_display_format.php',
            'apply_display_field_columns_search_alias.php',
            'apply_form_failed_save_display_fix.php',
            'apply_human_friendly_error_display.php',
            'apply_itm_actions_cell_markers.php',
            'apply_module_sample_data_seed.php',
            'apply_ui_action_emoji.php',
            'bypass_login.php',
            'bypass_v2.php',
            'cleanup_equipment_test_module_artifacts.php',
            'delete_clone_employee.php',
            'detect_fk_dropdown_ui_risk.php',
            'empty_folders.php',
            'ensure_equipment_type_modules.php',
            'ensure_files_htaccess_chain.php',
            'export_floor_plan_folders_seed.php',
            'fix_sql.php',
            'fix_sql_broad.php',
            'fix_sql_departments.php',
            'force_delete_company.php',
            'generate_tests.php',
            'health.php',
            'identify_modules.php',
            'normalize_database_sql_created_at.php',
            'repair_table_from_schema.php',
            'run_email_alert_rules.php',
            'seed_company_module_access.php',
            'sql_insert.php',
            'sync_modules_registry.php',
            'transfer_data_from_employee.php',
            'update_all_created_at.php',
            'run_tests.php',
            'run_tier2_checks.php',
            'auth_register_reset_human_test.php',
            'employees_delete_clear_table_test.php',
            'equipment_delete_clear_table_test.php',
            'explorer_human_test.php',
            'floor_designer_test.php',
            'floor_plans_folder_move_test.php',
            'idfs_sync_human_test.php',
            'module_browser_qa_build_report.php',
            'module_browser_qa_runner.php',
            'module_clean_tests_qa_runner.php',
            'tickets_related_equipment_delete_test.php',
            'test_ajax.php',
            'test_edit.php',
        ];
    }
}

if (!function_exists('itm_perform_audit_discover_scripts')) {
    /**
     * @return array<int,string>
     */
    function itm_perform_audit_discover_scripts(string $scriptsDir): array
    {
        $exclusions = array_flip(itm_perform_audit_static_exclusions());
        $scripts = [];

        foreach (glob($scriptsDir . '/*.php') ?: [] as $file) {
            $base = basename($file);
            if ($base === '' || isset($exclusions[$base])) {
                continue;
            }
            if (strpos($base, '_tmp_') === 0) {
                continue;
            }
            // Why: Security PoC and integration verify scripts need disposable users / args — not blanket audit.
            if (preg_match('/^(repro_|verify_)/', $base)) {
                continue;
            }
            $scripts[] = $base;
        }

        sort($scripts);

        return $scripts;
    }
}

if (!function_exists('itm_perform_audit_truncate_error_log')) {
    function itm_perform_audit_truncate_error_log(string $logPath): void
    {
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            return;
        }
        file_put_contents($logPath, '');
    }
}

if (!function_exists('itm_perform_audit_read_log_delta')) {
    /**
     * @return array<int,string>
     */
    function itm_perform_audit_read_log_delta(string $logPath, int $offset): array
    {
        clearstatcache(true, $logPath);
        if (!is_file($logPath)) {
            return [];
        }

        $size = filesize($logPath);
        if ($size === false || $size <= $offset) {
            return [];
        }

        $fp = fopen($logPath, 'rb');
        if (!$fp) {
            return [];
        }
        fseek($fp, $offset);
        $chunk = stream_get_contents($fp);
        fclose($fp);
        if ($chunk === false || $chunk === '') {
            return [];
        }

        $lines = preg_split('/\R/', $chunk) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }
}

if (!function_exists('itm_perform_audit_is_php_error_line')) {
    function itm_perform_audit_is_php_error_line(string $line): bool
    {
        if ($line === '') {
            return false;
        }
        if (preg_match('/\bPHP\s+(Fatal error|Warning|Notice|Parse error|Deprecated|Strict Standards)\b/i', $line)) {
            return true;
        }
        if (preg_match('/\bUncaught\b/i', $line) && preg_match('/\b(Exception|Error)\b/', $line)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_perform_audit_filter_stdout_hits')) {
    /**
     * @param array<int,string> $output
     * @return array<int,string>
     */
    function itm_perform_audit_filter_stdout_hits(array $output): array
    {
        $hits = [];
        foreach ($output as $line) {
            $line = trim((string)$line);
            if ($line !== '' && itm_perform_audit_is_php_error_line($line)) {
                $hits[] = $line;
            }
        }

        return array_values(array_unique($hits));
    }
}

if (!function_exists('itm_perform_audit_load_allowlist')) {
    /**
     * @return array<string,array{reason:string,max_exit_code:int}>
     */
    function itm_perform_audit_load_allowlist(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $script = trim((string)($row['script'] ?? ''));
            if ($script === '') {
                continue;
            }
            $map[$script] = [
                'reason' => trim((string)($row['reason'] ?? '')),
                'max_exit_code' => (int)($row['max_exit_code'] ?? 1),
            ];
        }

        return $map;
    }
}

if (!function_exists('itm_perform_audit_is_allowlisted_exit')) {
    function itm_perform_audit_is_allowlisted_exit(
        string $script,
        int $exitCode,
        array $cliErrors,
        array $stdoutHits,
        array $allowlist
    ): bool {
        if (!isset($allowlist[$script])) {
            return false;
        }
        if ($cliErrors !== [] || $stdoutHits !== []) {
            return false;
        }

        return $exitCode !== 0 && $exitCode <= (int)$allowlist[$script]['max_exit_code'];
    }
}

if (!function_exists('itm_perform_audit_run_script')) {
    /**
     * @return array{exit_code:int,cli_errors:array<int,string>,stdout_hits:array<int,string>}
     */
    function itm_perform_audit_run_script(string $phpBinary, string $scriptPath, string $errorLogPath): array
    {
        $offset = is_file($errorLogPath) ? (int)filesize($errorLogPath) : 0;

        $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $logLines = itm_perform_audit_read_log_delta($errorLogPath, $offset);
        $logErrors = [];
        foreach ($logLines as $line) {
            if (itm_perform_audit_is_php_error_line($line)) {
                $logErrors[] = $line;
            }
        }

        $stdoutHits = itm_perform_audit_filter_stdout_hits($output);
        $cliErrors = array_values(array_unique(array_merge($logErrors, $stdoutHits)));

        return [
            'exit_code' => (int)$exitCode,
            'cli_errors' => $cliErrors,
            'stdout_hits' => $stdoutHits,
        ];
    }
}
