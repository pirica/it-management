<?php
/**
 * Shared report builder for fields_missing.php (all modules) and employee_fields_missing.php.
 */

if (!function_exists('itm_fields_missing_global_ui_excluded_columns')) {
    /**
     * Columns not required on create/edit/view/index for scaffold audits.
     *
     * @return list<string>
     */
    function itm_fields_missing_global_ui_excluded_columns(): array
    {
        return [
            'id',
            'company_id',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'deleted_at',
        ];
    }
}

if (!function_exists('itm_fields_missing_status_driven_slugs')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_status_driven_slugs(): array
    {
        return ['employees', 'equipment', 'patches_updates', 'tickets'];
    }
}

if (!function_exists('itm_fields_missing_parse_database_sql_table_columns')) {
    /**
     * @return array<string, list<string>>
     */
    function itm_fields_missing_parse_database_sql_table_columns(?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
        $path = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'database.sql';
        if (!is_readable($path)) {
            return [];
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            return [];
        }

        $map = [];
        if (!preg_match_all('/CREATE TABLE `([^`]+)`\s*\((.*?)\)\s*ENGINE=/s', $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $table = (string) $match[1];
            $body = (string) $match[2];
            if (!preg_match_all('/^\s*`([^`]+)`/m', $body, $columns)) {
                continue;
            }
            $map[$table] = $columns[1];
        }

        return $map;
    }
}

if (!function_exists('itm_fields_missing_live_table_columns')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_live_table_columns(mysqli $conn, string $table): array
    {
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($table)) {
            return [];
        }

        $columns = [];
        $res = mysqli_query($conn, 'SHOW COLUMNS FROM `' . $table . '`');
        if (!$res) {
            return [];
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $columns[] = (string) ($row['Field'] ?? '');
        }

        return $columns;
    }
}

if (!function_exists('itm_fields_missing_discover_module_targets')) {
    /**
     * @return list<array{module:string,table:string,index_path:string}>
     */
    function itm_fields_missing_discover_module_targets(?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
        $rootPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR;
        $modulesPath = $rootPath . 'modules';
        if (!is_dir($modulesPath)) {
            return [];
        }

        require_once __DIR__ . '/itm_crud_tables_audit.php';
        require_once __DIR__ . '/itm_list_active_and_checkboxes_report.php';

        $schemaTables = array_fill_keys(array_keys(itm_fields_missing_parse_database_sql_table_columns($rootPath)), true);
        $targets = [];

        foreach (scandir($modulesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($modulesPath . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }
            if (itm_crud_mapper_module_matches_is_prefix($entry)) {
                continue;
            }

            $indexPath = $modulesPath . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'index.php';
            if (!is_file($indexPath)) {
                continue;
            }

            $table = itm_active_audit_read_crud_table($indexPath);
            if ($table === null || $table === '') {
                $table = $entry;
            }
            if (!isset($schemaTables[$table])) {
                continue;
            }

            $targets[] = [
                'module' => $entry,
                'table' => $table,
                'index_path' => $indexPath,
            ];
        }

        usort($targets, static function (array $a, array $b): int {
            return strcmp($a['module'], $b['module']);
        });

        return $targets;
    }
}

if (!function_exists('itm_fields_missing_module_file_bundle')) {
    /**
     * @return array{create:string,edit:string,view:string,index:string,includes:string,list_all:string}
     */
    function itm_fields_missing_module_file_bundle(string $moduleSlug, ?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
        $base = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $moduleSlug . DIRECTORY_SEPARATOR;

        return [
            'create' => $base . 'create.php',
            'edit' => $base . 'edit.php',
            'view' => $base . 'view.php',
            'index' => $base . 'index.php',
            'includes' => $base . 'includes',
            'list_all' => $base . 'list_all.php',
        ];
    }
}

if (!function_exists('itm_fields_missing_file_bundle_has_field')) {
    function itm_fields_missing_file_bundle_has_field(string $field, array $paths): bool
    {
        foreach ($paths as $path) {
            if (is_dir($path)) {
                foreach (glob($path . '/*.php') ?: [] as $includeFile) {
                    $content = file_get_contents($includeFile);
                    if ($content !== false && preg_match('/name=["\']' . preg_quote($field, '/') . '["\']/', $content)) {
                        return true;
                    }
                }
                continue;
            }
            if (!is_readable($path)) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            if (preg_match('/name=["\']' . preg_quote($field, '/') . '["\']/', $content)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_employees_view_label_map')) {
    /**
     * @return array<string, list<string>>
     */
    function itm_fields_missing_employees_view_label_map(): array
    {
        return [
            'employee_position_id' => ['Position Title', 'position_name'],
            'department_id' => ['Department', 'department_name'],
            'office_key_card_department_id' => ['Office Key Card Department', 'office_key_card_department_name'],
            'employment_status_id' => ['Employment Status', 'employment_status_name'],
            'employee_type_id' => ['Employee Type', 'employee_type_name'],
            'workstation_mode_id' => ['Workstation Mode', 'workstation_mode_name'],
            'assignment_type_id' => ['Assignment Type', 'assignment_type_name'],
            'reports_to' => ['Reports To', 'manager_name'],
            'location_id' => ['IT Location', 'location_name'],
            'role_id' => ['Role', 'role_name'],
            'access_level_id' => ['Access Level', 'access_level_name'],
        ];
    }
}

if (!function_exists('itm_fields_missing_view_label_candidates')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_view_label_candidates(string $field, string $moduleSlug): array
    {
        $candidates = [$field, ucwords(str_replace('_', ' ', $field))];
        if ($moduleSlug === 'employees' && isset(itm_fields_missing_employees_view_label_map()[$field])) {
            $candidates = array_merge($candidates, itm_fields_missing_employees_view_label_map()[$field]);
        }

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('itm_fields_missing_view_has_field')) {
    function itm_fields_missing_view_has_field(string $field, string $viewPath, string $moduleSlug): bool
    {
        if (!is_readable($viewPath)) {
            return false;
        }
        $content = file_get_contents($viewPath);
        if ($content === false) {
            return false;
        }
        if ($moduleSlug === 'employees' && $field === 'photo' && strpos($content, 'emp_profile_photo_url') !== false) {
            return true;
        }
        foreach (itm_fields_missing_view_label_candidates($field, $moduleSlug) as $candidate) {
            if (strpos($content, "'{$candidate}'") !== false) {
                return true;
            }
            if (preg_match('/\$[a-zA-Z_][\w]*\[[\'"]' . preg_quote($candidate, '/') . '[\'"]\]/', $content)) {
                return true;
            }
            if (preg_match('/\[[\'"]' . preg_quote($field, '/') . '[\'"]\]/', $content)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_index_has_field')) {
    function itm_fields_missing_index_has_field(string $field, string $indexPath): bool
    {
        if (!is_readable($indexPath)) {
            return false;
        }
        $content = file_get_contents($indexPath);
        if ($content === false) {
            return false;
        }

        return (bool) preg_match("/['\"]{$field}['\"]/", $content);
    }
}

if (!function_exists('itm_fields_missing_index_is_dynamic_scaffold')) {
    function itm_fields_missing_index_is_dynamic_scaffold(string $indexPath): bool
    {
        if (!is_file($indexPath)) {
            return false;
        }
        require_once __DIR__ . '/itm_crud_tables_audit.php';
        $moduleSlug = basename(dirname($indexPath));

        return itm_crud_mapper_module_is_standard_crud($moduleSlug);
    }
}

if (!function_exists('itm_fields_missing_employees_critical_fields')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_employees_critical_fields(): array
    {
        return [
            'first_name', 'last_name', 'display_name', 'work_email', 'personal_email', 'mobile_phone',
            'external_number', 'dect', 'extension', 'on_contacts', 'on_orgchart', 'external_id', 'username',
            'role_id', 'access_level_id',
            'job_code', 'employee_position_id', 'reports_to', 'department_id', 'office_key_card_department_id',
            'raw_status_code', 'employment_status_id', 'employee_code', 'location_id',
            'request_date', 'requested_by', 'termination_requested_by',
            'start_date', 'employee_type_id', 'termination_date',
            'birthday', 'hide_year', 'photo', 'workstation_mode_id', 'assignment_type_id', 'comments',
        ];
    }
}

if (!function_exists('itm_fields_missing_employees_optional_fields')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_employees_optional_fields(): array
    {
        return ['duplicate'];
    }
}

if (!function_exists('itm_fields_missing_employees_system_access_columns')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_employees_system_access_columns(): array
    {
        return [
            'network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms',
            'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system',
            'digital_rev', 'office_key_card',
        ];
    }
}

if (!function_exists('itm_fields_missing_ui_fields_for_module')) {
    /**
     * @param list<string> $expectedColumns
     * @return list<string>
     */
    function itm_fields_missing_ui_fields_for_module(string $moduleSlug, array $expectedColumns): array
    {
        if ($moduleSlug === 'employees') {
            return itm_fields_missing_employees_critical_fields();
        }

        $excluded = array_fill_keys(itm_fields_missing_global_ui_excluded_columns(), true);
        if (in_array($moduleSlug, itm_fields_missing_status_driven_slugs(), true)) {
            $excluded['active'] = true;
        }

        $fields = [];
        foreach ($expectedColumns as $column) {
            if (isset($excluded[$column])) {
                continue;
            }
            $fields[] = $column;
        }

        return $fields;
    }
}

if (!function_exists('itm_fields_missing_resolve_form_paths')) {
    /**
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @return list<string>
     */
    function itm_fields_missing_resolve_form_paths(array $files): array
    {
        $formPaths = [$files['create'], $files['edit'], $files['includes']];
        if (is_readable($files['edit']) && strpos((string) file_get_contents($files['edit']), "require 'create.php'") !== false) {
            $formPaths = [$files['create'], $files['includes']];
        }
        if (is_readable($files['create']) && strpos((string) file_get_contents($files['create']), '$crud_action') !== false
            && strpos((string) file_get_contents($files['create']), "require 'index.php'") !== false
        ) {
            $formPaths = [$files['index'], $files['includes']];
        }

        return $formPaths;
    }
}

if (!function_exists('itm_fields_missing_strip_php_for_form_scan')) {
    function itm_fields_missing_strip_php_for_form_scan(string $content): string
    {
        return (string) preg_replace('/<\?php.*?\?>/s', '', $content);
    }
}

if (!function_exists('itm_fields_missing_file_has_visible_form_field')) {
    function itm_fields_missing_file_has_visible_form_field(string $field, string $content): bool
    {
        $content = itm_fields_missing_strip_php_for_form_scan($content);
        if (!preg_match_all(
            '/<(?:input|select|textarea)\b[^>]*\bname=["\']' . preg_quote($field, '/') . '["\'][^>]*>/i',
            $content,
            $matches
        )) {
            return false;
        }

        foreach ($matches[0] as $tag) {
            if (preg_match('/\btype=["\']hidden["\']/i', $tag)) {
                continue;
            }
            // Why: legacy scaffold stamps company_id with a readonly number input; not user-editable.
            if (preg_match('/\b(?:readonly|disabled)\b/i', $tag)) {
                continue;
            }
            if (preg_match('/<(?:textarea|select)\b/i', $tag)) {
                return true;
            }
            if (preg_match('/\btype=["\'](?:text|number|email|url|checkbox|date|color|password|tel|search)["\']/i', $tag)) {
                return true;
            }
            if (preg_match('/<input\b/i', $tag) && !preg_match('/\btype=/i', $tag)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_form_exposes_visible_field')) {
    /**
     * @param list<string> $paths
     */
    function itm_fields_missing_form_exposes_visible_field(string $field, array $paths): bool
    {
        foreach ($paths as $path) {
            if (is_dir($path)) {
                foreach (glob($path . '/*.php') ?: [] as $includeFile) {
                    $content = file_get_contents($includeFile);
                    if ($content !== false && itm_fields_missing_file_has_visible_form_field($field, $content)) {
                        return true;
                    }
                }
                continue;
            }
            if (!is_readable($path)) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content !== false && itm_fields_missing_file_has_visible_form_field($field, $content)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_audit_excluded_ui_columns')) {
    /**
     * @param list<string> $excludedColumns
     * @param list<string> $formPaths
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_audit_excluded_ui_columns(
        string $moduleSlug,
        array $excludedColumns,
        array $formPaths,
        array &$passes,
        array &$failures
    ): void {
        foreach ($excludedColumns as $field) {
            if ($field === '') {
                continue;
            }
            if (itm_fields_missing_form_exposes_visible_field($field, $formPaths)) {
                $failures[] = [
                    'code' => 'ui_excluded_exposed',
                    'message' => "{$moduleSlug} excluded UI column {$field}: visible on create/edit forms",
                ];
                continue;
            }
            $passes[] = "{$moduleSlug} excluded UI column {$field}: hidden or absent on create/edit forms";
        }
    }
}

if (!function_exists('itm_fields_missing_finalize_module_report')) {
    /**
     * @param list<string> $expectedColumns
     * @param list<string> $liveColumns
     * @param list<string> $uiAuditedColumns
     * @return array<string, mixed>
     */
    function itm_fields_missing_finalize_module_report(
        array $report,
        array $expectedColumns,
        array $liveColumns,
        array $uiAuditedColumns = []
    ): array {
        $report['expected_columns'] = array_values($expectedColumns);
        $report['live_columns'] = array_values($liveColumns);
        $report['ui_audited_columns'] = array_values($uiAuditedColumns);
        $report['ui_excluded_columns'] = array_values(array_diff($expectedColumns, $uiAuditedColumns));

        return $report;
    }
}

if (!function_exists('itm_fields_missing_format_columns_block')) {
    /**
     * @param array<string, mixed> $moduleReport
     */
    function itm_fields_missing_format_columns_block(array $moduleReport, string $nl): string
    {
        $expected = $moduleReport['expected_columns'] ?? [];
        $live = $moduleReport['live_columns'] ?? [];
        $uiAudited = $moduleReport['ui_audited_columns'] ?? [];
        $excluded = $moduleReport['ui_excluded_columns'] ?? [];

        if (!is_array($expected)) {
            $expected = [];
        }
        if (!is_array($live)) {
            $live = [];
        }
        if (!is_array($uiAudited)) {
            $uiAudited = [];
        }
        if (!is_array($excluded)) {
            $excluded = [];
        }

        $out = '  database.sql columns (' . count($expected) . '): ' . ($expected === [] ? '(none)' : implode(', ', $expected)) . $nl;
        $out .= '  live columns (' . count($live) . '): ' . ($live === [] ? '(none)' : implode(', ', $live)) . $nl;

        if ($uiAudited !== []) {
            $out .= '  UI audited columns (' . count($uiAudited) . '): ' . implode(', ', $uiAudited) . $nl;
        }
        if ($excluded !== []) {
            $out .= '  excluded from UI audit (' . count($excluded) . '): ' . implode(', ', $excluded) . $nl;
        }

        return $out . $nl;
    }
}

if (!function_exists('itm_fields_missing_format_failure_summary_block')) {
    /**
     * @param array{modules?:list<array<string,mixed>>} $report
     */
    function itm_fields_missing_format_failure_summary_block(
        array $report,
        string $nl,
        ?callable $formatLine = null
    ): string {
        $messages = [];
        foreach ($report['modules'] ?? [] as $moduleReport) {
            if (!is_array($moduleReport['failures'] ?? null)) {
                continue;
            }
            foreach ($moduleReport['failures'] as $failure) {
                if (!is_array($failure)) {
                    continue;
                }
                $message = trim((string) ($failure['message'] ?? ''));
                if ($message !== '') {
                    $messages[] = $message;
                }
            }
        }

        $count = count($messages);
        if ($count === 0) {
            return '';
        }

        $out = str_repeat('-', 72) . $nl;
        $out .= 'Failure summary (' . $count . '):' . $nl;
        foreach ($messages as $message) {
            $line = '[FAIL] ' . $message;
            $out .= ($formatLine !== null ? $formatLine($line) : $line) . $nl;
        }

        return $out . $nl;
    }
}

if (!function_exists('itm_fields_missing_collect_bespoke_skips')) {
    /**
     * @param list<array<string,mixed>> $moduleReports
     * @return list<array{module:string,table:string,ui_mode:string,reason:string}>
     */
    function itm_fields_missing_collect_bespoke_skips(
        array $moduleReports,
        ?string $moduleFilter = null,
        ?string $rootPath = null
    ): array {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
        $rootPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR;

        $skips = [];
        $modulesInReport = [];

        foreach ($moduleReports as $moduleReport) {
            $moduleSlug = (string) ($moduleReport['module'] ?? '');
            if ($moduleSlug === '') {
                continue;
            }
            $modulesInReport[$moduleSlug] = true;

            $uiMode = (string) ($moduleReport['ui_mode'] ?? '');
            if ($uiMode !== 'bespoke_skip' && $uiMode !== 'status_driven_skip') {
                continue;
            }
            if ($moduleFilter !== null && $moduleFilter !== '' && $moduleSlug !== $moduleFilter) {
                continue;
            }

            $skips[] = [
                'module' => $moduleSlug,
                'table' => (string) ($moduleReport['table'] ?? ''),
                'ui_mode' => $uiMode,
                'reason' => $uiMode === 'status_driven_skip'
                    ? 'status-driven UI — schema-only'
                    : 'bespoke/deferred UI — schema-only',
            ];
        }

        if ($moduleFilter === null || $moduleFilter === '') {
            require_once __DIR__ . '/itm_crud_tables_audit.php';
            $listedSlugs = itm_crud_tables_load_skip_module_slugs($rootPath);
            $listedModules = [];
            foreach ($skips as $skip) {
                $listedModules[$skip['module']] = true;
            }

            foreach ($listedSlugs as $slug) {
                if (isset($listedModules[$slug]) || isset($modulesInReport[$slug])) {
                    continue;
                }
                $indexPath = $rootPath . 'modules' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.php';
                if (!is_file($indexPath)) {
                    continue;
                }

                $skips[] = [
                    'module' => $slug,
                    'table' => '',
                    'ui_mode' => 'bespoke_skip',
                    'reason' => 'bespoke/deferred UI — no discoverable schema table',
                ];
            }
        }

        usort($skips, static function (array $a, array $b): int {
            $moduleCmp = strcmp($a['module'], $b['module']);
            if ($moduleCmp !== 0) {
                return $moduleCmp;
            }

            return strcmp($a['table'], $b['table']);
        });

        return $skips;
    }
}

if (!function_exists('itm_fields_missing_format_bespoke_skip_block')) {
    /**
     * @param list<array{module:string,table:string,ui_mode:string,reason:string}> $skips
     */
    function itm_fields_missing_format_bespoke_skip_block(
        array $skips,
        string $nl,
        ?callable $formatLine = null
    ): string {
        if ($skips === []) {
            return '';
        }

        $out = str_repeat('-', 72) . $nl;
        $out .= 'Bespoke / deferred UI modules — SKIP (' . count($skips) . '):' . $nl;
        foreach ($skips as $skip) {
            $moduleSlug = (string) ($skip['module'] ?? '');
            $table = trim((string) ($skip['table'] ?? ''));
            $reason = trim((string) ($skip['reason'] ?? 'bespoke/deferred UI'));
            $moduleRef = function_exists('itm_script_format_modules_file_link')
                ? itm_script_format_modules_file_link('modules/' . $moduleSlug . '/index.php')
                : 'modules/' . $moduleSlug . '/index.php';
            $tablePart = $table !== ''
                ? ' (table: ' . (function_exists('itm_script_format_table_link')
                    ? itm_script_format_table_link($table)
                    : $table) . ')'
                : '';
            $line = '[SKIP] ' . $moduleRef . $tablePart . ' — ' . $reason;
            $out .= ($formatLine !== null ? $formatLine($line) : $line) . $nl;
        }

        return $out . $nl;
    }
}

if (!function_exists('itm_fields_missing_audit_module')) {
    /**
     * @param list<string> $expectedColumns
     * @param list<string> $liveColumns
     * @return array<string, mixed>
     */
    function itm_fields_missing_audit_module(
        mysqli $conn,
        string $moduleSlug,
        string $table,
        array $expectedColumns,
        array $liveColumns,
        ?string $rootPath = null
    ): array {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $files = itm_fields_missing_module_file_bundle($moduleSlug, $rootPath);
        $schemaMissing = array_values(array_diff($expectedColumns, $liveColumns));
        $schemaExtra = array_values(array_diff($liveColumns, $expectedColumns));
        $failures = [];
        $infos = [];
        $passes = [];

        if ($schemaMissing !== []) {
            foreach ($schemaMissing as $column) {
                $failures[] = [
                    'code' => 'schema_missing',
                    'message' => "Live DB missing {$table}.{$column} (present in database.sql)",
                ];
            }
        } else {
            $passes[] = "Live {$table} includes every column defined in database.sql";
        }

        if ($schemaExtra !== []) {
            foreach ($schemaExtra as $column) {
                $infos[] = "Live DB has extra {$table}.{$column} not listed in database.sql";
            }
        }

        require_once __DIR__ . '/itm_crud_tables_audit.php';
        $bespokeModules = array_fill_keys(itm_crud_tables_load_skip_module_slugs($rootPath), true);
        $statusDriven = in_array($moduleSlug, itm_fields_missing_status_driven_slugs(), true);

        if (isset($bespokeModules[$moduleSlug])) {
            $infos[] = "{$moduleSlug} is bespoke/deferred UI — schema-only audit (see docs/list_bespoke_UI.txt)";

            return itm_fields_missing_finalize_module_report([
                'module' => $moduleSlug,
                'table' => $table,
                'schema_missing' => $schemaMissing,
                'schema_extra' => $schemaExtra,
                'ui_mode' => 'bespoke_skip',
                'failures' => $failures,
                'infos' => $infos,
                'passes' => $passes,
            ], $expectedColumns, $liveColumns);
        }

        if ($statusDriven && $moduleSlug !== 'employees') {
            $infos[] = "{$moduleSlug} is status-driven bespoke UI — schema-only audit (row active is soft-delete mirror)";

            return itm_fields_missing_finalize_module_report([
                'module' => $moduleSlug,
                'table' => $table,
                'schema_missing' => $schemaMissing,
                'schema_extra' => $schemaExtra,
                'ui_mode' => 'status_driven_skip',
                'failures' => $failures,
                'infos' => $infos,
                'passes' => $passes,
            ], $expectedColumns, $liveColumns);
        }

        $uiMode = 'manual';
        if ($moduleSlug === 'employees') {
            $uiMode = 'employees';
        } elseif (itm_fields_missing_index_is_dynamic_scaffold($files['index'])) {
            $uiMode = 'dynamic_scaffold';
            $passes[] = "{$moduleSlug} uses dynamic scaffold columns (\$uiColumns / cr_manageable_columns)";
            $uiAudited = itm_fields_missing_ui_fields_for_module($moduleSlug, $expectedColumns);
            $formPaths = itm_fields_missing_resolve_form_paths($files);
            itm_fields_missing_audit_excluded_ui_columns(
                $moduleSlug,
                array_values(array_intersect($expectedColumns, itm_fields_missing_global_ui_excluded_columns())),
                $formPaths,
                $passes,
                $failures
            );

            return itm_fields_missing_finalize_module_report([
                'module' => $moduleSlug,
                'table' => $table,
                'schema_missing' => $schemaMissing,
                'schema_extra' => $schemaExtra,
                'ui_mode' => $uiMode,
                'failures' => $failures,
                'infos' => $infos,
                'passes' => $passes,
            ], $expectedColumns, $liveColumns, $uiAudited);
        }

        $formPaths = itm_fields_missing_resolve_form_paths($files);

        $uiFields = itm_fields_missing_ui_fields_for_module($moduleSlug, $expectedColumns);
        foreach ($uiFields as $field) {
            $formOk = itm_fields_missing_file_bundle_has_field($field, $formPaths);
            $viewOk = itm_fields_missing_view_has_field($field, $files['view'], $moduleSlug);
            $indexOk = itm_fields_missing_index_has_field($field, $files['index']);

            if (!$formOk) {
                $failures[] = [
                    'code' => 'ui_form_missing',
                    'message' => "{$moduleSlug} create/edit missing form field: {$field}",
                ];
            }
            if (!$viewOk && is_readable($files['view'])) {
                $failures[] = [
                    'code' => 'ui_view_missing',
                    'message' => "{$moduleSlug} view.php missing display for: {$field}",
                ];
            }
            if (!$indexOk) {
                $failures[] = [
                    'code' => 'ui_index_missing',
                    'message' => "{$moduleSlug} index.php missing list/import reference for: {$field}",
                ];
            }
            if ($formOk && ($viewOk || !is_readable($files['view'])) && $indexOk) {
                $passes[] = "{$moduleSlug} UI covers {$field}";
            }
        }

        if ($moduleSlug === 'employees') {
            $uiFields = array_values(array_unique(array_merge(
                $uiFields,
                array_filter(
                    itm_fields_missing_employees_optional_fields(),
                    static function (string $field) use ($expectedColumns): bool {
                        return in_array($field, $expectedColumns, true);
                    }
                )
            )));
            foreach (itm_fields_missing_employees_optional_fields() as $field) {
                if (!in_array($field, $expectedColumns, true)) {
                    continue;
                }
                $formOk = itm_fields_missing_file_bundle_has_field($field, $formPaths);
                $viewOk = itm_fields_missing_view_has_field($field, $files['view'], $moduleSlug);
                $indexOk = itm_fields_missing_index_has_field($field, $files['index']);
                if (!$formOk || !$viewOk || !$indexOk) {
                    $gaps = [];
                    if (!$formOk) {
                        $gaps[] = 'create/edit';
                    }
                    if (!$viewOk) {
                        $gaps[] = 'view';
                    }
                    if (!$indexOk) {
                        $gaps[] = 'index';
                    }
                    $infos[] = "employees.{$field} not fully wired (" . implode(', ', $gaps) . ' missing)';
                } else {
                    $passes[] = "employees optional UI covers {$field}";
                }
            }

            foreach (itm_fields_missing_employees_system_access_columns() as $field) {
                if (!in_array($field, $expectedColumns, true)) {
                    continue;
                }
                if (itm_fields_missing_index_has_field($field, $files['index'])) {
                    $passes[] = "employees system access column {$field} referenced via employee_system_access join in index.php";
                } else {
                    $infos[] = "employees system access column {$field} is managed via employee_system_access matrix (not a direct form input)";
                }
            }

            foreach (['id', 'company_id', 'created_at', 'updated_at', 'user_id', 'is_hidden'] as $field) {
                if (in_array($field, $expectedColumns, true) && !in_array($field, itm_fields_missing_global_ui_excluded_columns(), true)) {
                    $infos[] = "employees.{$field} is meta/system scope (not required on create/edit forms)";
                }
            }

            if (is_readable($files['list_all']) && strpos((string) file_get_contents($files['list_all']), "header('Location: index.php')") !== false) {
                $passes[] = 'employees list_all.php redirects to index.php (list columns inherit from index.php)';
            }
        }

        itm_fields_missing_audit_excluded_ui_columns(
            $moduleSlug,
            array_values(array_intersect($expectedColumns, itm_fields_missing_global_ui_excluded_columns())),
            $formPaths,
            $passes,
            $failures
        );

        return itm_fields_missing_finalize_module_report([
            'module' => $moduleSlug,
            'table' => $table,
            'schema_missing' => $schemaMissing,
            'schema_extra' => $schemaExtra,
            'ui_mode' => $uiMode,
            'failures' => $failures,
            'infos' => $infos,
            'passes' => $passes,
        ], $expectedColumns, $liveColumns, $uiFields);
    }
}

if (!function_exists('itm_fields_missing_collect_report')) {
    /**
     * @return array{
     *   modules: list<array<string,mixed>>,
     *   tables_without_module: list<string>,
     *   bespoke_skips: list<array{module:string,table:string,ui_mode:string,reason:string}>,
     *   failure_count: int
     * }
     */
    function itm_fields_missing_collect_report(mysqli $conn, ?string $moduleFilter = null, ?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $schemaMap = itm_fields_missing_parse_database_sql_table_columns($rootPath);
        $targets = itm_fields_missing_discover_module_targets($rootPath);
        $modulesWithTable = [];
        $moduleReports = [];
        $failureCount = 0;

        foreach ($targets as $target) {
            $moduleSlug = (string) $target['module'];
            if ($moduleFilter !== null && $moduleFilter !== '' && $moduleSlug !== $moduleFilter) {
                continue;
            }

            $table = (string) $target['table'];
            $modulesWithTable[$table] = true;
            // Why: folder slug may match a schema table while $crud_table points elsewhere (company_module_access → modules_registry).
            if (isset($schemaMap[$moduleSlug])) {
                $modulesWithTable[$moduleSlug] = true;
            }
            $expected = $schemaMap[$table] ?? [];
            $live = itm_fields_missing_live_table_columns($conn, $table);
            $report = itm_fields_missing_audit_module($conn, $moduleSlug, $table, $expected, $live, $rootPath);
            $failureCount += count($report['failures']);
            $moduleReports[] = $report;
        }

        $companionAudited = [];
        foreach ($targets as $target) {
            $moduleSlug = (string) $target['module'];
            if ($moduleFilter !== null && $moduleFilter !== '' && $moduleSlug !== $moduleFilter) {
                continue;
            }

            $table = (string) $target['table'];
            if ($moduleSlug === $table || !isset($schemaMap[$moduleSlug]) || isset($companionAudited[$moduleSlug])) {
                continue;
            }

            $companionAudited[$moduleSlug] = true;
            $expected = $schemaMap[$moduleSlug];
            $live = itm_fields_missing_live_table_columns($conn, $moduleSlug);
            $report = itm_fields_missing_audit_module($conn, $moduleSlug, $moduleSlug, $expected, $live, $rootPath);
            $failureCount += count($report['failures']);
            $moduleReports[] = $report;
            $modulesWithTable[$moduleSlug] = true;
        }

        usort($moduleReports, static function (array $a, array $b): int {
            $moduleCmp = strcmp((string) ($a['module'] ?? ''), (string) ($b['module'] ?? ''));
            if ($moduleCmp !== 0) {
                return $moduleCmp;
            }

            return strcmp((string) ($a['table'] ?? ''), (string) ($b['table'] ?? ''));
        });

        $tablesWithoutModule = [];
        foreach (array_keys($schemaMap) as $tableName) {
            if (!isset($modulesWithTable[$tableName])) {
                $tablesWithoutModule[] = $tableName;
            }
        }
        sort($tablesWithoutModule, SORT_STRING);

        $bespokeSkips = itm_fields_missing_collect_bespoke_skips($moduleReports, $moduleFilter, $rootPath);

        return [
            'modules' => $moduleReports,
            'tables_without_module' => $tablesWithoutModule,
            'bespoke_skips' => $bespokeSkips,
            'failure_count' => $failureCount,
            'schema_table_count' => count($schemaMap),
            'module_count' => count($moduleReports),
        ];
    }
}
