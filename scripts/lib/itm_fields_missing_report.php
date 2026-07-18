<?php
/**
 * Shared report builder for fields_missing.php (all modules) and employee_fields_missing.php.
 */

if (!function_exists('itm_crud_is_form_hidden_audit_field')) {
    require_once __DIR__ . '/../../includes/itm_crud_audit_fields.php';
}

if (!function_exists('itm_ui_resolve_list_table_screen')) {
    require_once __DIR__ . '/itm_ui_list_contract_checks.php';
}

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
     * @return array{create:string,edit:string,view:string,index:string,includes:string,list_all:string,delete:string}
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
            'delete' => $base . 'delete.php',
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

if (!function_exists('itm_fields_missing_dynamic_list_exposes_field')) {
    /**
     * Detect list/import columns emitted by foreach ($uiColumns|$displayFieldColumns|…) loops.
     */
    function itm_fields_missing_dynamic_list_exposes_field(string $field, string $indexPath): bool
    {
        if (!is_readable($indexPath)) {
            return false;
        }
        $content = (string) file_get_contents($indexPath);
        if (!preg_match('/foreach\s*\(\s*\$(uiColumns|displayFieldColumns|visibleFieldColumns)\s+as/', $content)) {
            return false;
        }
        if ($field === 'company_id' && itm_fields_missing_file_hides_company_id_via_ui_columns($content)) {
            return false;
        }
        if (function_exists('itm_crud_is_list_hidden_audit_field')
            && itm_crud_is_list_hidden_audit_field($field)
        ) {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_fields_missing_module_index_covers_field')) {
    function itm_fields_missing_module_index_covers_field(
        string $field,
        string $indexPath,
        bool $dynamicScaffold
    ): bool {
        if ($dynamicScaffold && itm_fields_missing_dynamic_list_exposes_field($field, $indexPath)) {
            return true;
        }

        return itm_fields_missing_index_has_field($field, $indexPath);
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

if (!function_exists('itm_fields_missing_file_requires_index')) {
    function itm_fields_missing_file_requires_index(string $filePath): bool
    {
        if (!is_readable($filePath)) {
            return false;
        }
        $content = (string) file_get_contents($filePath);

        return (bool) preg_match(
            "/require(?:_once)?\s+(?:__DIR__\s*\.\s*['\"]\/index\.php['\"]|['\"]index\.php['\"])\s*;/",
            $content
        );
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
        $routesToIndex = false;
        if (is_readable($files['create'])
            && strpos((string) file_get_contents($files['create']), '$crud_action') !== false
            && itm_fields_missing_file_requires_index($files['create'])
        ) {
            $routesToIndex = true;
        }
        if (is_readable($files['edit'])
            && strpos((string) file_get_contents($files['edit']), '$crud_action') !== false
            && itm_fields_missing_file_requires_index($files['edit'])
        ) {
            $routesToIndex = true;
        }
        if ($routesToIndex) {
            $formPaths = [$files['index'], $files['includes']];
            $moduleDir = dirname($files['index']);
            if (is_dir($moduleDir)) {
                foreach (glob($moduleDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $extraFile) {
                    $base = strtolower(basename($extraFile));
                    if ($base === 'index.php' || $base === 'list_all.php' || $base === 'delete.php' || $base === 'view.php') {
                        continue;
                    }
                    if (strpos($base, 'create') !== false || strpos($base, 'edit') !== false || strpos($base, 'form') !== false) {
                        $formPaths[] = $extraFile;
                    }
                }
            }
        } elseif (is_readable($files['index'])) {
            $indexContent = (string) file_get_contents($files['index']);
            if (preg_match("/in_array\s*\(\s*\\\$crud_action\s*,\s*\[\s*['\"]create['\"]/", $indexContent)
                && preg_match('/foreach\s*\(\s*\$(uiColumns|formColumns)\s+as/', $indexContent)
            ) {
                $formPaths[] = $files['index'];
            }
        }

        return array_values(array_unique($formPaths));
    }
}

if (!function_exists('itm_fields_missing_resolve_bespoke_form_paths')) {
    /**
     * Bespoke modules without a schema table often embed forms in index.php or extra entry files.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @return list<string>
     */
    function itm_fields_missing_resolve_bespoke_form_paths(array $files): array
    {
        $formPaths = itm_fields_missing_resolve_form_paths($files);
        foreach (['index', 'list_all'] as $key) {
            if (is_readable($files[$key])) {
                $formPaths[] = $files[$key];
            }
        }

        $moduleDir = dirname($files['index']);
        if (is_dir($moduleDir)) {
            foreach (glob($moduleDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $extraFile) {
                $base = strtolower(basename($extraFile));
                if ($base === 'index.php' || $base === 'list_all.php' || $base === 'delete.php' || $base === 'view.php') {
                    continue;
                }
                if (strpos($base, 'create') !== false || strpos($base, 'edit') !== false || strpos($base, 'form') !== false) {
                    $formPaths[] = $extraFile;
                }
            }
        }

        return array_values(array_unique($formPaths));
    }
}

if (!function_exists('itm_fields_missing_format_report_table_label')) {
    function itm_fields_missing_format_report_table_label(string $table): string
    {
        return $table !== '' ? $table : '-';
    }
}

if (!function_exists('itm_fields_missing_strip_php_for_form_scan')) {
    function itm_fields_missing_strip_php_for_form_scan(string $content): string
    {
        return (string) preg_replace('/<\?php.*?\?>/s', '', $content);
    }
}

if (!function_exists('itm_fields_missing_form_noise_field_names')) {
    /**
     * Non-schema control names that appear on module screens but are not table columns.
     *
     * @return list<string>
     */
    function itm_fields_missing_form_noise_field_names(): array
    {
        return [
            'csrf_token',
            'search',
            'bulk_action',
            'ids',
            'ajax_action',
            'import_file',
            'page',
            'sort',
            'dir',
            'add_sample_data',
            'import_excel_rows',
            'per_page',
            'records_per_page',
            'viewport',
            'theme',
            'action',
            'submit',
        ];
    }
}

if (!function_exists('itm_fields_missing_normalize_form_field_name')) {
    function itm_fields_missing_normalize_form_field_name(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        // Why: bulk checkboxes and multi-selects use name="ids[]".
        if (substr($name, -2) === '[]') {
            $name = substr($name, 0, -2);
        }

        return $name;
    }
}

if (!function_exists('itm_fields_missing_extract_form_field_names_from_content')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_extract_form_field_names_from_content(string $content): array
    {
        $scan = itm_fields_missing_strip_php_for_form_scan($content);
        $names = [];
        if (preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname\s*=\s*["\']([^"\']+)["\']/i', $scan, $matches)) {
            foreach ($matches[1] as $raw) {
                $name = itm_fields_missing_normalize_form_field_name((string) $raw);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }

        // Why: scaffold forms often emit dynamic name= echo of $name; also recover static name="field" still present in PHP source.
        if (preg_match_all('/\bname\s*=\s*["\']([a-zA-Z_][a-zA-Z0-9_]*)["\']/i', $content, $staticMatches)) {
            foreach ($staticMatches[1] as $raw) {
                $name = itm_fields_missing_normalize_form_field_name((string) $raw);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }

        $noise = array_fill_keys(itm_fields_missing_form_noise_field_names(), true);
        $out = [];
        foreach (array_keys($names) as $name) {
            if (isset($noise[$name])) {
                continue;
            }
            $out[] = $name;
        }
        sort($out, SORT_STRING);

        return $out;
    }
}

if (!function_exists('itm_fields_missing_extract_form_field_names')) {
    /**
     * @param list<string> $paths
     * @return list<string>
     */
    function itm_fields_missing_extract_form_field_names(array $paths): array
    {
        $names = [];
        foreach (itm_fields_missing_expand_form_scan_paths($paths) as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            foreach (itm_fields_missing_extract_form_field_names_from_content($content) as $name) {
                $names[$name] = true;
            }
        }

        $out = array_keys($names);
        sort($out, SORT_STRING);

        return $out;
    }
}

if (!function_exists('itm_fields_missing_collect_ui_fields')) {
    /**
     * Grab every create/edit form field possible: scraped controls + schema-derived scaffold set.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $expectedColumns
     * @return array{form_fields:list<string>,form_fields_other:list<string>,audited:list<string>,scraped_raw:list<string>}
     */
    function itm_fields_missing_collect_ui_fields(
        string $moduleSlug,
        array $files,
        array $expectedColumns,
        bool $allowSchemaDerivedFallback
    ): array {
        $formPaths = itm_fields_missing_resolve_form_paths($files);
        $moduleDir = dirname($files['index']);
        if (is_dir($moduleDir)) {
            foreach (glob($moduleDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $extraFile) {
                $base = strtolower(basename($extraFile));
                if ($base === 'index.php' || $base === 'list_all.php' || $base === 'delete.php' || $base === 'view.php') {
                    continue;
                }
                // Why: bespoke modules keep extra form entry files (create_folder.php, edit_folder.php, …).
                if (strpos($base, 'create') !== false || strpos($base, 'edit') !== false || strpos($base, 'form') !== false) {
                    $formPaths[] = $extraFile;
                }
            }
            $formPaths = array_values(array_unique($formPaths));
        }
        $scraped = itm_fields_missing_extract_form_field_names($formPaths);
        $expectedSet = array_fill_keys($expectedColumns, true);

        $formFields = [];
        $formFieldsOther = [];
        foreach ($scraped as $name) {
            if (isset($expectedSet[$name])) {
                $formFields[] = $name;
            } else {
                $formFieldsOther[] = $name;
            }
        }

        // Why: employees create uses includes + dynamic blocks; merge critical matrix with scraped names.
        if ($moduleSlug === 'employees') {
            foreach (itm_fields_missing_employees_critical_fields() as $name) {
                if (isset($expectedSet[$name]) && !in_array($name, $formFields, true)) {
                    $formFields[] = $name;
                }
            }
            foreach (itm_fields_missing_employees_optional_fields() as $name) {
                if (isset($expectedSet[$name]) && !in_array($name, $formFields, true) && in_array($name, $scraped, true)) {
                    $formFields[] = $name;
                }
            }
        }

        $globalExcluded = array_fill_keys(itm_fields_missing_global_ui_excluded_columns(), true);
        $audited = [];
        foreach ($formFields as $name) {
            if (!isset($globalExcluded[$name])) {
                $audited[] = $name;
            }
        }
        if ($allowSchemaDerivedFallback) {
            // Why: dynamic scaffold loops emit name=$name so scrape alone under-reports; union schema-derived UI set.
            foreach (itm_fields_missing_ui_fields_for_module($moduleSlug, $expectedColumns) as $name) {
                if (!in_array($name, $audited, true)) {
                    $audited[] = $name;
                }
            }
            // Why: when scrape only caught hidden id/company_id, surface the scaffold UI field set as form fields.
            $meaningfulScraped = [];
            foreach ($formFields as $name) {
                if (!isset($globalExcluded[$name])) {
                    $meaningfulScraped[] = $name;
                }
            }
            if ($meaningfulScraped === [] && $audited !== []) {
                $formFields = $audited;
            }
        }

        sort($formFields, SORT_STRING);
        sort($formFieldsOther, SORT_STRING);
        sort($audited, SORT_STRING);

        return [
            'form_fields' => array_values($formFields),
            'form_fields_other' => array_values($formFieldsOther),
            'audited' => array_values($audited),
            'scraped_raw' => $scraped,
        ];
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
            if (preg_match('/\btype=["\'](?:text|number|email|url|checkbox|date|datetime-local|color|password|tel|search)["\']/i', $tag)) {
                return true;
            }
            if (preg_match('/<input\b/i', $tag) && !preg_match('/\btype=/i', $tag)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_audit_meta_humanized_label')) {
    function itm_fields_missing_audit_meta_humanized_label(string $field): string
    {
        if (function_exists('cr_humanize_field')) {
            return (string) cr_humanize_field($field);
        }

        return ucwords(str_replace('_', ' ', $field));
    }
}

if (!function_exists('itm_fields_missing_extract_form_blocks')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_extract_form_blocks(string $content): array
    {
        if (!preg_match_all('/<form\b[\s\S]*?<\/form>/i', $content, $matches)) {
            return [$content];
        }

        return $matches[0];
    }
}

if (!function_exists('itm_fields_missing_file_has_audit_meta_label_in_form')) {
    /**
     * Method: humanized field label text inside a create/edit form (bespoke disabled display rows).
     */
    function itm_fields_missing_file_has_audit_meta_label_in_form(string $field, string $content): bool
    {
        $label = preg_quote(itm_fields_missing_audit_meta_humanized_label($field), '/');
        $labelPattern = '/<label\b[^>]*>\s*' . $label . '\s*<\/label>/i';

        foreach (itm_fields_missing_extract_form_blocks($content) as $formBlock) {
            if (preg_match($labelPattern, $formBlock)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_file_uses_humanized_field_marker_in_form')) {
    /**
     * Method: cr_humanize_field('created_at') (or equivalent) referenced inside a form block.
     */
    function itm_fields_missing_file_uses_humanized_field_marker_in_form(string $field, string $content): bool
    {
        $fieldQuoted = preg_quote($field, '/');
        $markerPattern = '/cr_humanize_field\s*\(\s*[\'"]' . $fieldQuoted . '[\'"]\s*\)/i';
        $dataPattern = '/\$data\s*\[\s*[\'"]' . $fieldQuoted . '[\'"]\s*\]/';

        foreach (itm_fields_missing_extract_form_blocks($content) as $formBlock) {
            if (preg_match($markerPattern, $formBlock) && preg_match($dataPattern, $formBlock)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_is_global_audit_meta_column')) {
    function itm_fields_missing_is_global_audit_meta_column(string $field): bool
    {
        return in_array($field, [
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'deleted_at',
            'deleted_by',
        ], true);
    }
}

if (!function_exists('itm_fields_missing_file_has_non_hidden_named_form_field')) {
    /**
     * Named create/edit control other than type=hidden (includes disabled/readonly display).
     */
    function itm_fields_missing_file_has_non_hidden_named_form_field(string $field, string $content): bool
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
            if (!preg_match('/\btype=["\']hidden["\']/i', $tag)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_file_renders_data_field_in_form')) {
    /**
     * Bespoke forms may echo $data['created_at'] in disabled inputs without a name attribute.
     */
    function itm_fields_missing_file_renders_data_field_in_form(string $field, string $content): bool
    {
        $pattern = '/\$data\s*\[\s*[\'"]' . preg_quote($field, '/') . '[\'"]\s*\]/';

        foreach (itm_fields_missing_extract_form_blocks($content) as $formBlock) {
            if (preg_match($pattern, $formBlock)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_form_exposes_audit_meta_on_form')) {
    /**
     * Stricter than generic visible scrape: audit meta must be absent or hidden-only on create/edit.
     *
     * @param list<string> $paths
     */
    function itm_fields_missing_form_exposes_audit_meta_on_form(string $field, array $paths, string $moduleSlug = '', array $files = []): bool
    {
        foreach (itm_fields_missing_expand_form_scan_paths($paths) as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            // Method 1: editable visible named controls (text, datetime-local, select, etc.).
            if (itm_fields_missing_file_has_visible_form_field($field, $content)) {
                return true;
            }
            // Method 2: any named control that is not type=hidden (includes disabled/readonly display).
            if (itm_fields_missing_file_has_non_hidden_named_form_field($field, $content)) {
                return true;
            }
            // Method 3: echo $data['field'] inside a form without relying on name=.
            if (itm_fields_missing_file_renders_data_field_in_form($field, $content)) {
                return true;
            }
            // Method 4: humanized audit label rendered in the form (Created At, Updated By, ...).
            if (itm_fields_missing_file_has_audit_meta_label_in_form($field, $content)) {
                return true;
            }
            // Method 5: cr_humanize_field marker plus $data['field'] in the same form block.
            if (itm_fields_missing_file_uses_humanized_field_marker_in_form($field, $content)) {
                return true;
            }
            // Method 7: pseudo HTML scrape — strip PHP tags and parse the remaining markup.
            if (itm_fields_missing_scraped_html_exposes_audit_meta_field(
                $field,
                itm_fields_missing_strip_php_for_form_scan($content)
            )) {
                return true;
            }
        }

        // Method 6: dynamic foreach ($uiColumns|$fieldColumns) name=$name loops.
        if (itm_fields_missing_dynamic_form_exposes_field($field, $paths)) {
            return true;
        }

        // Method 8: optional live HTTP scrape of create/edit screens (ITM_FIELDS_MISSING_HTTP_SCRAPE=1).
        if ($moduleSlug !== '' && $files !== []) {
            $liveHtml = itm_fields_missing_http_fetch_module_form_html($moduleSlug, $files);
            if ($liveHtml !== ''
                && itm_fields_missing_scraped_html_exposes_audit_meta_field($field, $liveHtml)
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_scraped_html_exposes_audit_meta_field')) {
    /**
     * Method 7/8: parse rendered (or PHP-stripped) HTML for visible audit meta on create/edit forms.
     */
    function itm_fields_missing_scraped_html_exposes_audit_meta_field(string $field, string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        $label = preg_quote(itm_fields_missing_audit_meta_humanized_label($field), '/');
        $labelPattern = '/<label\b[^>]*>\s*' . $label . '\s*<\/label>/i';
        $namePattern = '/\bname=["\']' . preg_quote($field, '/') . '["\']/i';

        foreach (itm_fields_missing_extract_form_blocks($html) as $formBlock) {
            if (preg_match_all('/<(?:input|select|textarea)\b[^>]*>/i', $formBlock, $tagMatches)) {
                foreach ($tagMatches[0] as $tag) {
                    if (!preg_match($namePattern, $tag)) {
                        continue;
                    }
                    if (!preg_match('/\btype=["\']hidden["\']/i', $tag)) {
                        return true;
                    }
                }
            }

            if (preg_match($labelPattern, $formBlock)) {
                return true;
            }

            if (preg_match(
                '/<div[^>]*class="[^"]*form-group[^"]*"[^>]*>[\s\S]*?<label[^>]*>\s*' . $label . '\s*<\/label>[\s\S]*?<input\b/i',
                $formBlock
            )) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_http_scrape_enabled')) {
    function itm_fields_missing_http_scrape_enabled(): bool
    {
        $flag = getenv('ITM_FIELDS_MISSING_HTTP_SCRAPE');

        return $flag === '1' || strtolower((string) $flag) === 'true' || strtolower((string) $flag) === 'yes';
    }
}

if (!function_exists('itm_fields_missing_http_fetch_module_form_html')) {
    /**
     * Method 8: fetch create/edit HTML via HTTP when Apache is available (opt-in).
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     */
    function itm_fields_missing_http_fetch_module_form_html(string $moduleSlug, array $files): string
    {
        if (!itm_fields_missing_http_scrape_enabled() || !function_exists('curl_init')) {
            return '';
        }

        if (!function_exists('itm_script_publish_isolated_http_session')) {
            $bootstrap = __DIR__ . '/itm_script_bootstrap.php';
            if (is_readable($bootstrap)) {
                require_once $bootstrap;
            }
        }
        if (!function_exists('itm_script_publish_isolated_http_session')) {
            return '';
        }

        $baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : 'http://localhost/it-management';
        $sessionId = itm_script_publish_isolated_http_session(1, 1, 'Admin');
        if ($sessionId === '') {
            return '';
        }

        $html = '';
        foreach (['create', 'edit'] as $key) {
            if (empty($files[$key]) || !is_readable($files[$key])) {
                continue;
            }
            $entry = basename((string) $files[$key]);
            $url = $baseUrl . '/modules/' . rawurlencode($moduleSlug) . '/' . rawurlencode($entry);
            if ($key === 'edit') {
                $url .= '?id=1';
            }

            $ch = curl_init($url);
            if ($ch === false) {
                continue;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Cookie: PHPSESSID=' . $sessionId],
            ]);
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (is_string($body) && $body !== '' && $httpCode > 0 && $httpCode < 400) {
                $html .= "\n" . $body;
            }
        }

        return $html;
    }
}

if (!function_exists('itm_fields_missing_parse_manageable_column_exclusions')) {
    /**
     * @return list<string>|null Column names removed by cr_manageable_columns() in file content.
     */
    function itm_fields_missing_parse_manageable_column_exclusions(string $content): ?array
    {
        if (!preg_match('/function\s+cr_manageable_columns\s*\([^)]*\)\s*\{([\s\S]*?)\n\}/', $content, $match)) {
            return null;
        }

        $body = $match[1];
        if (preg_match(
            "/in_array\s*\(\s*\\\$(?:c\['Field'\]|field)\s*,\s*\[([^\]]+)\]/",
            $body,
            $listMatch
        )) {
            preg_match_all("/'([^']+)'/", $listMatch[1], $names);

            return $names[1] ?? [];
        }

        if (preg_match('/\$exclude\s*=\s*\[([^\]]+)\]/', $body, $excludeMatch)
            && preg_match('/!in_array\s*\(\s*\$c\[\'Field\'\]\s*,\s*\$exclude/', $body)
        ) {
            preg_match_all("/'([^']+)'/", $excludeMatch[1], $names);
            $excluded = $names[1] ?? [];
            if (preg_match("/\\\$exclude\[\]\s*=\s*'([^']+)'/", $body, $pushMatch)
                && preg_match("/\['attempts'\]/", $body)
            ) {
                $table = itm_fields_missing_parse_crud_table_from_content($content);
                if ($table !== 'attempts') {
                    $excluded[] = $pushMatch[1];
                }
            }

            return array_values(array_unique($excluded));
        }

        if (preg_match_all(
            "/\(\s*\\\$(?:c|col)\['Field'\]\s*\?\?\s*''\s*\)\s*!==\s*'([^']+)'/",
            $body,
            $nullCoalesceMatches
        )) {
            return array_values(array_unique($nullCoalesceMatches[1]));
        }

        if (preg_match("/\(\s*\\\$c\['Field'\]\s*\)\s*!==\s*'([^']+)'/", $body, $singleMatch)) {
            return [$singleMatch[1]];
        }

        return null;
    }
}

if (!function_exists('itm_fields_missing_parse_crud_table_from_content')) {
    function itm_fields_missing_parse_crud_table_from_content(string $content): ?string
    {
        if (preg_match("/\\\$crud_table\s*=\s*'([^']+)'/", $content, $match)) {
            return $match[1];
        }

        return null;
    }
}

if (!function_exists('itm_fields_missing_file_hides_company_id_via_ui_columns')) {
    /**
     * Scaffold modules filter company_id out of $uiColumns when the table is in $hideCompanyIdTables.
     */
    function itm_fields_missing_file_hides_company_id_via_ui_columns(string $content): bool
    {
        $table = itm_fields_missing_parse_crud_table_from_content($content);
        if ($table === null || $table === '') {
            return false;
        }
        if (!preg_match('/\$hideCompanyIdTables\s*=\s*\[([^\]]+)\]/', $content, $hideMatch)) {
            return false;
        }
        if (!preg_match("/'" . preg_quote($table, '/') . "'/", $hideMatch[1])) {
            return false;
        }
        if (!preg_match('/\$uiColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$fieldColumns/', $content)) {
            return false;
        }
        if (!preg_match('/\$fieldName\s*!==\s*[\'"]company_id[\'"]/', $content)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_fields_missing_file_skips_dynamic_form_field')) {
    /**
     * Bespoke/special-case branches that replace or hide a column before the generic name=$name render.
     */
    function itm_fields_missing_file_skips_dynamic_form_field(string $field, string $content): bool
    {
        if ($field === 'company_id') {
            if (preg_match('/name=[\'"]company_ids\[\][\'"]/', $content)) {
                return true;
            }
            if (preg_match(
                '/\$name\s*===\s*[\'"]company_id[\'"][\s\S]*?(?:continue\s*;|type=[\'"]hidden[\'"]|\breadonly\b)/',
                $content
            )) {
                return true;
            }
            if (preg_match(
                '/elseif\s*\(\s*\$name\s*===\s*[\'"]company_id[\'"]\s*\)[\s\S]*?type=[\'"]hidden[\'"]/i',
                $content
            )) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_file_uses_dynamic_column_form_loop')) {
    function itm_fields_missing_file_uses_dynamic_column_form_loop(string $content): bool
    {
        if (!preg_match('/foreach\s*\(\s*\$(fieldColumns|uiColumns|formColumns)\s+as/', $content)) {
            return false;
        }

        return (bool) preg_match(
            '/name=\s*["\'][^"\']*<\?php\s+echo\s+sanitize\(\$name\)/',
            $content
        );
    }
}

if (!function_exists('itm_fields_missing_extract_create_edit_form_block')) {
    /**
     * Return the create/edit elseif branch body when present (bespoke partials + flattened index).
     */
    function itm_fields_missing_extract_create_edit_form_block(string $content): ?string
    {
        $openTag = null;
        $startPos = null;
        if (preg_match(
            "/<\?php\s+elseif\s*\(\s*in_array\s*\(\s*\\\$crud_action\s*,\s*\[\s*['\"]create['\"][^\]]*\]\s*,\s*true\s*\)\s*\)\s*:\s*\?>/",
            $content,
            $openMatch,
            PREG_OFFSET_CAPTURE
        )) {
            $openTag = $openMatch[0][0];
            $startPos = (int) $openMatch[0][1];
        } elseif (preg_match(
            "/<\?php\s+elseif\s*\(\s*\\\$crud_action\s*===\s*['\"]create['\"][^\)]*\)\s*:\s*\?>/",
            $content,
            $openMatch,
            PREG_OFFSET_CAPTURE
        )) {
            $openTag = $openMatch[0][0];
            $startPos = (int) $openMatch[0][1];
        }

        if ($openTag === null || $startPos === null) {
            return null;
        }

        $searchFrom = $startPos + strlen($openTag);
        if (preg_match(
            "/<\?php\s+elseif\s*\(\s*\\\$crud_action\b/",
            $content,
            $endMatch,
            PREG_OFFSET_CAPTURE,
            $searchFrom
        )) {
            return substr($content, $startPos, $endMatch[0][1] - $startPos);
        }

        if (preg_match(
            "/<\?php\s+endif\s*;\s*\?>/",
            $content,
            $endMatch,
            PREG_OFFSET_CAPTURE,
            $searchFrom
        )) {
            return substr($content, $startPos, $endMatch[0][1] - $startPos);
        }

        return null;
    }
}

if (!function_exists('itm_fields_missing_create_edit_form_loop_variable')) {
    function itm_fields_missing_create_edit_form_loop_variable(string $content): ?string
    {
        $block = itm_fields_missing_extract_create_edit_form_block($content);
        if ($block === null || !itm_fields_missing_file_uses_dynamic_column_form_loop($block)) {
            return null;
        }
        if (preg_match('/foreach\s*\(\s*\$fieldColumns\s+as/', $block)) {
            return 'fieldColumns';
        }
        if (preg_match('/foreach\s*\(\s*\$uiColumns\s+as/', $block)) {
            return 'uiColumns';
        }
        if (preg_match('/foreach\s*\(\s*\$formColumns\s+as/', $block)) {
            return 'formColumns';
        }

        return null;
    }
}

if (!function_exists('itm_fields_missing_dynamic_form_exposes_field')) {
    /**
     * Detect visible inputs emitted by foreach ($fieldColumns|$uiColumns) name=$name loops.
     *
     * @param list<string> $paths
     */
    function itm_fields_missing_dynamic_form_exposes_field(string $field, array $paths): bool
    {
        $expandedPaths = itm_fields_missing_expand_form_scan_paths($paths);
        $manageableExclusions = [];
        foreach ($expandedPaths as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            $parsed = itm_fields_missing_parse_manageable_column_exclusions($content);
            if ($parsed !== null) {
                $manageableExclusions = array_values(array_unique(array_merge($manageableExclusions, $parsed)));
            }
        }

        $globalExcluded = array_fill_keys(itm_fields_missing_global_ui_excluded_columns(), true);

        foreach ($expandedPaths as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            $createEditLoop = itm_fields_missing_create_edit_form_loop_variable($content);
            if ($createEditLoop === null) {
                continue;
            }

            $createEditBlock = itm_fields_missing_extract_create_edit_form_block($content) ?? $content;

            if ($createEditLoop === 'uiColumns' || $createEditLoop === 'formColumns') {
                if (function_exists('itm_crud_is_form_hidden_audit_field')
                    && itm_crud_is_form_hidden_audit_field($field)
                ) {
                    continue;
                }
                if (function_exists('itm_crud_is_delete_form_hidden_field')
                    && itm_crud_is_delete_form_hidden_field($field)
                ) {
                    continue;
                }
                if (function_exists('itm_crud_is_list_hidden_audit_field')
                    && itm_crud_is_list_hidden_audit_field($field)
                ) {
                    continue;
                }
                if ($field === 'company_id'
                    && itm_fields_missing_file_hides_company_id_via_ui_columns($content)
                ) {
                    continue;
                }
            } elseif ($createEditLoop === 'fieldColumns' && isset($globalExcluded[$field])) {
                if (itm_fields_missing_file_skips_dynamic_form_field($field, $createEditBlock)) {
                    continue;
                }
                if (in_array($field, $manageableExclusions, true)) {
                    continue;
                }

                return true;
            }

            if (itm_fields_missing_file_skips_dynamic_form_field($field, $createEditBlock)) {
                continue;
            }

            if (in_array($field, $manageableExclusions, true)) {
                continue;
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_form_exposes_literal_visible_field')) {
    /**
     * Static scrape only — used for bespoke gated meta checks (no dynamic loop inference).
     *
     * @param list<string> $paths
     */
    function itm_fields_missing_form_exposes_literal_visible_field(string $field, array $paths): bool
    {
        foreach (itm_fields_missing_expand_form_scan_paths($paths) as $path) {
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

if (!function_exists('itm_fields_missing_form_exposes_visible_field')) {
    /**
     * @param list<string> $paths
     */
    function itm_fields_missing_form_exposes_visible_field(string $field, array $paths): bool
    {
        foreach (itm_fields_missing_expand_form_scan_paths($paths) as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content !== false && itm_fields_missing_file_has_visible_form_field($field, $content)) {
                return true;
            }
        }

        return itm_fields_missing_dynamic_form_exposes_field($field, $paths);
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
        array &$failures,
        bool $literalOnly = false,
        array $files = []
    ): void {
        foreach ($excludedColumns as $field) {
            if ($field === '') {
                continue;
            }
            $exposed = false;
            if (itm_fields_missing_is_global_audit_meta_column($field)) {
                $exposed = itm_fields_missing_form_exposes_audit_meta_on_form(
                    $field,
                    $formPaths,
                    $moduleSlug,
                    $files
                );
            } elseif ($literalOnly) {
                $exposed = itm_fields_missing_form_exposes_literal_visible_field($field, $formPaths);
            } else {
                $exposed = itm_fields_missing_form_exposes_visible_field($field, $formPaths);
            }
            if ($exposed) {
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

if (!function_exists('itm_fields_missing_resolve_view_paths')) {
    /**
     * View wrappers that require index.php must be scanned on index for dynamic $viewColumns loops.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @return list<string>
     */
    function itm_fields_missing_resolve_view_paths(array $files): array
    {
        $paths = [];
        if (is_readable($files['view'])) {
            $viewContent = (string) file_get_contents($files['view']);
            if (itm_fields_missing_file_requires_index($files['view'])) {
                if (is_readable($files['index'])) {
                    $paths[] = $files['index'];
                }
            } else {
                $paths[] = $files['view'];
            }
        } elseif (is_readable($files['index'])) {
            $paths[] = $files['index'];
        }

        return array_values(array_unique($paths));
    }
}

if (!function_exists('itm_fields_missing_dynamic_view_exposes_field')) {
    /**
     * Detect detail rows emitted by foreach ($viewColumns as $col) loops.
     *
     * @param list<string> $paths
     */
    function itm_fields_missing_dynamic_view_exposes_field(string $field, array $paths): bool
    {
        foreach ($paths as $path) {
            if (!is_readable($path) || is_dir($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            if (!preg_match('/foreach\s*\(\s*\$viewColumns\s+as/', $content)) {
                continue;
            }
            if ($field === 'company_id' && itm_fields_missing_file_hides_company_id_via_ui_columns($content)) {
                continue;
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_module_view_covers_field')) {
    /**
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     */
    function itm_fields_missing_module_view_covers_field(string $field, array $files, string $moduleSlug): bool
    {
        $viewPaths = itm_fields_missing_resolve_view_paths($files);
        foreach ($viewPaths as $path) {
            if (itm_fields_missing_view_has_field($field, $path, $moduleSlug)) {
                return true;
            }
        }

        return itm_fields_missing_dynamic_view_exposes_field($field, $viewPaths);
    }
}

if (!function_exists('itm_fields_missing_view_paths_readable')) {
    /**
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     */
    function itm_fields_missing_view_paths_readable(array $files): bool
    {
        foreach (itm_fields_missing_resolve_view_paths($files) as $path) {
            if (is_readable($path)) {
                return true;
            }
        }

        return is_readable($files['view']);
    }
}

if (!function_exists('itm_fields_missing_audit_audited_ui_columns')) {
    /**
     * Positive UI coverage for business columns listed under UI audited columns.
     *
     * @param list<string> $auditedColumns
     * @param list<string> $formPaths
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_audit_audited_ui_columns(
        string $moduleSlug,
        array $auditedColumns,
        array $formPaths,
        array $files,
        array &$passes,
        array &$failures,
        bool $dynamicScaffold = false
    ): void {
        $viewRequired = itm_fields_missing_view_paths_readable($files);

        foreach ($auditedColumns as $field) {
            if ($field === '') {
                continue;
            }

            $formOk = $dynamicScaffold
                ? itm_fields_missing_form_exposes_visible_field($field, $formPaths)
                : itm_fields_missing_file_bundle_has_field($field, $formPaths);
            $viewOk = itm_fields_missing_module_view_covers_field($field, $files, $moduleSlug);
            $indexOk = itm_fields_missing_module_index_covers_field(
                $field,
                $files['index'],
                $dynamicScaffold
            );

            if (!$formOk) {
                $failures[] = [
                    'code' => 'ui_form_missing',
                    'message' => "{$moduleSlug} audited UI column {$field}: missing on create/edit forms",
                ];
            } else {
                $passes[] = "{$moduleSlug} audited UI column {$field}: present on create/edit forms";
            }

            if ($viewRequired) {
                if (!$viewOk) {
                    $failures[] = [
                        'code' => 'ui_view_missing',
                        'message' => "{$moduleSlug} audited UI column {$field}: missing on view",
                    ];
                } else {
                    $passes[] = "{$moduleSlug} audited UI column {$field}: present on view";
                }
            }

            if (!$indexOk) {
                $failures[] = [
                    'code' => 'ui_index_missing',
                    'message' => "{$moduleSlug} audited UI column {$field}: missing on index list/import",
                ];
            } else {
                $passes[] = "{$moduleSlug} audited UI column {$field}: present on index list/import";
            }
        }
    }
}

if (!function_exists('itm_fields_missing_append_schema_column_passes')) {
    /**
     * One PASS (or INFO when schema-only) per column present in both database.sql and live DB.
     *
     * @param list<string> $expectedColumns
     * @param list<string> $liveColumns
     * @param list<string> $passes
     * @param list<string> $infos
     */
    function itm_fields_missing_append_schema_column_passes(
        string $table,
        array $expectedColumns,
        array $liveColumns,
        array &$passes,
        array &$infos = [],
        bool $schemaOnly = false
    ): void {
        foreach ($expectedColumns as $column) {
            if (!in_array($column, $liveColumns, true)) {
                continue;
            }
            $line = "{$table}.{$column}: live matches database.sql";
            if ($schemaOnly) {
                $passes[] = $line;
                continue;
            }
            $passes[] = $line;
        }
    }
}

if (!function_exists('itm_fields_missing_merge_bespoke_form_paths')) {
    /**
     * Bespoke modules often duplicate create/edit in index.php and edit.php — scan all entry files.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $formPaths
     * @return list<string>
     */
    function itm_fields_missing_merge_bespoke_form_paths(array $files, array $formPaths): array
    {
        $merged = $formPaths;
        foreach (['index', 'edit', 'create', 'list_all'] as $key) {
            if (!empty($files[$key]) && is_readable($files[$key])) {
                $merged[] = $files[$key];
            }
        }

        return array_values(array_unique($merged));
    }
}

if (!function_exists('itm_fields_missing_collect_includes_php_paths')) {
    /**
     * @return list<string>
     */
    function itm_fields_missing_collect_includes_php_paths(string $includesDir): array
    {
        if (!is_dir($includesDir)) {
            return [];
        }

        $root = realpath($includesDir);
        if ($root === false) {
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && strtolower((string) $fileInfo->getExtension()) === 'php') {
                $paths[] = $fileInfo->getPathname();
            }
        }

        sort($paths, SORT_STRING);

        return $paths;
    }
}

if (!function_exists('itm_fields_missing_expand_form_scan_paths')) {
    /**
     * Flatten form scan paths: directories become recursive PHP files under includes/.
     *
     * @param list<string> $paths
     * @return list<string>
     */
    function itm_fields_missing_expand_form_scan_paths(array $paths): array
    {
        $expanded = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $expanded = array_merge($expanded, itm_fields_missing_collect_includes_php_paths($path));
                continue;
            }
            if (is_readable($path) && !is_dir($path)) {
                $expanded[] = $path;
            }
        }

        return array_values(array_unique($expanded));
    }
}

if (!function_exists('itm_fields_missing_build_hybrid_scan_content')) {
    /**
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $formPaths
     */
    function itm_fields_missing_build_hybrid_scan_content(array $files, array $formPaths): string
    {
        $paths = itm_fields_missing_merge_bespoke_form_paths($files, $formPaths);
        if (!empty($files['includes'])) {
            $paths = array_merge($paths, itm_fields_missing_collect_includes_php_paths((string) $files['includes']));
        }
        $paths = array_values(array_unique($paths));
        $chunks = [];
        foreach ($paths as $path) {
            if (is_readable($path) && !is_dir($path)) {
                $chunks[] = (string) file_get_contents($path);
            }
        }

        return implode("\n\n", $chunks);
    }
}

if (!function_exists('itm_fields_missing_apply_skipped_ui_coverage_gate')) {
    /**
     * Bespoke/status-driven modules: full business UI is skipped; gated contract checks still run.
     *
     * @param list<string> $expectedColumns
     * @param list<string> $formPaths
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_apply_skipped_ui_coverage_gate(
        string $moduleSlug,
        array $expectedColumns,
        array $formPaths,
        array $files,
        array &$passes,
        array &$failures,
        bool $statusDriven = false
    ): void {
        itm_fields_missing_audit_excluded_ui_columns(
            $moduleSlug,
            array_values(array_intersect($expectedColumns, itm_fields_missing_global_ui_excluded_columns())),
            $formPaths,
            $passes,
            $failures,
            false,
            $files
        );

        itm_fields_missing_audit_bespoke_scaffold_hybrid_contract(
            $moduleSlug,
            $files,
            $formPaths,
            $passes,
            $failures
        );

        itm_fields_missing_audit_bespoke_soft_delete_contract(
            $moduleSlug,
            $files,
            $expectedColumns,
            $passes,
            $failures
        );

        itm_fields_missing_audit_bespoke_page_ui_contract(
            $moduleSlug,
            $files,
            $passes,
            $failures
        );

        itm_fields_missing_audit_bespoke_list_ui_contract(
            $moduleSlug,
            $files,
            $passes,
            $failures
        );

        if ($statusDriven && in_array('active', $expectedColumns, true)) {
            if (itm_fields_missing_form_exposes_literal_visible_field('active', $formPaths)) {
                $failures[] = [
                    'code' => 'status_driven_active_exposed',
                    'message' => "{$moduleSlug} row active: visible on create/edit forms (status-driven module — use status FK badges only)",
                ];
            } else {
                $passes[] = "{$moduleSlug} row active: hidden on create/edit forms (status-driven)";
            }
        }
    }
}

if (!function_exists('itm_fields_missing_audit_bespoke_soft_delete_contract')) {
    /**
     * Bespoke scaffold hybrids (cr_manageable_columns) on tables with deleted_at must soft-delete
     * and filter live list rows — not hard DELETE or unfiltered SELECT lists.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $expectedColumns
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_audit_bespoke_soft_delete_contract(
        string $moduleSlug,
        array $files,
        array $expectedColumns,
        array &$passes,
        array &$failures
    ): void {
        if (!in_array('deleted_at', $expectedColumns, true) || !is_readable($files['index'])) {
            return;
        }

        $indexContent = (string) file_get_contents($files['index']);
        if (strpos($indexContent, 'cr_manageable_columns(') === false) {
            return;
        }

        $scanPaths = [$files['index']];
        $deletePath = (string) ($files['delete'] ?? '');
        if ($deletePath !== '' && is_readable($deletePath)) {
            $scanPaths[] = $deletePath;
        }

        $usesSoftDeleteHelper = false;
        $usesHardDelete = false;
        foreach ($scanPaths as $path) {
            $content = (string) file_get_contents($path);
            if (strpos($content, 'itm_crud_build_soft_delete_sql') !== false) {
                $usesSoftDeleteHelper = true;
            }
            if (preg_match('/\$deleteSql\s*=\s*[\'"]DELETE FROM/i', $content)
                && strpos($content, 'itm_crud_build_soft_delete_sql') === false
            ) {
                $usesHardDelete = true;
            }
        }

        if ($usesHardDelete) {
            $failures[] = [
                'code' => 'bespoke_hard_delete',
                'message' => "{$moduleSlug} bespoke gate: delete uses hard DELETE (expected itm_crud_build_soft_delete_sql soft-delete)",
            ];
        } elseif ($usesSoftDeleteHelper) {
            $passes[] = "{$moduleSlug} bespoke gate: delete uses itm_crud_build_soft_delete_sql()";
        }

        if (preg_match('/SELECT\s+\*\s+FROM/i', $indexContent)) {
            $hasLiveFilter = strpos($indexContent, 'deleted_at IS NULL') !== false
                || strpos($indexContent, 'itm_crud_append_not_deleted_predicate') !== false;
            if (!$hasLiveFilter) {
                $failures[] = [
                    'code' => 'bespoke_list_missing_soft_delete_filter',
                    'message' => "{$moduleSlug} bespoke gate: list query missing deleted_at IS NULL filter (or itm_crud_append_not_deleted_predicate)",
                ];
            } else {
                $passes[] = "{$moduleSlug} bespoke gate: list filters soft-deleted rows";
            }
        }
    }
}

if (!function_exists('itm_fields_missing_record_bespoke_ui_check_results')) {
    /**
     * @param array<string, array{status:string,details:string}> $checks
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_record_bespoke_ui_check_results(
        string $moduleSlug,
        array $checks,
        array &$passes,
        array &$failures,
        string $codePrefix = 'bespoke_ui'
    ): void {
        foreach ($checks as $label => $result) {
            $status = strtolower(trim((string) ($result['status'] ?? '')));
            if ($status === 'n/a' || $status === '') {
                continue;
            }
            if ($status === 'pass') {
                $passes[] = "{$moduleSlug} bespoke gate: {$label} OK";
                continue;
            }

            $details = trim((string) ($result['details'] ?? ''));
            if ($details === '') {
                $details = 'contract check failed';
            }

            $code = $codePrefix . '_' . strtolower(str_replace(' ', '_', $label));
            $failures[] = [
                'code' => $code,
                'message' => "{$moduleSlug} bespoke gate: {$label} NOT OK — {$details}",
            ];
        }
    }
}

if (!function_exists('itm_fields_missing_audit_bespoke_page_ui_contract')) {
    /**
     * Page chrome for bespoke/status-driven modules: browser title, favicon, list heading layout.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string,delete?:string} $files
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_audit_bespoke_page_ui_contract(
        string $moduleSlug,
        array $files,
        array &$passes,
        array &$failures
    ): void {
        if (!is_readable($files['index'])) {
            return;
        }

        $indexContent = (string) file_get_contents($files['index']);
        if (stripos($indexContent, '<head') === false && stripos($indexContent, 'data-itm-new-button-managed') === false) {
            return;
        }

        $hasCreateFile = is_readable($files['create']);
        $createContent = $hasCreateFile ? (string) file_get_contents($files['create']) : '';

        itm_fields_missing_record_bespoke_ui_check_results(
            $moduleSlug,
            [
                'Browser title' => itm_check_module_browser_title($indexContent),
                'Favicon' => itm_check_module_favicon_link($indexContent),
                'List heading layout' => itm_check_list_heading_layout($indexContent),
                'List heading emoji' => itm_check_list_heading_emoji($indexContent),
                'New button position' => itm_check_new_button_position($indexContent, $hasCreateFile, $createContent),
                'New button style' => itm_check_new_button_style($indexContent, $hasCreateFile, $createContent),
            ],
            $passes,
            $failures,
            'bespoke_page_ui'
        );
    }
}

if (!function_exists('itm_fields_missing_audit_bespoke_list_ui_contract')) {
    /**
     * List-table UI for bespoke/status-driven modules with an index/list_all table.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string,delete?:string} $files
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_audit_bespoke_list_ui_contract(
        string $moduleSlug,
        array $files,
        array &$passes,
        array &$failures
    ): void {
        if (!is_readable($files['index'])) {
            return;
        }

        $indexContent = (string) file_get_contents($files['index']);
        $isScaffoldHybrid = strpos($indexContent, 'cr_manageable_columns(') !== false;

        $listAllContent = is_readable($files['list_all']) ? (string) file_get_contents($files['list_all']) : '';
        $screen = itm_ui_resolve_list_table_screen($indexContent, $listAllContent);
        if ($screen['content'] === '' || stripos($screen['content'], '<table') === false) {
            return;
        }

        $source = $screen['source'];
        $listContent = $screen['content'];
        $deletePath = (string) ($files['delete'] ?? '');
        $hasDeleteFile = $deletePath !== '' && is_readable($deletePath);
        $hasCreateFile = is_readable($files['create']);
        $createContent = $hasCreateFile ? (string) file_get_contents($files['create']) : '';

        $checks = [
            'Search' => itm_check_search($listContent, $source),
            'Sort' => itm_check_sort($listContent, $source),
            'Pagination' => itm_check_pagination($listContent, $source),
            'Pagination titles' => itm_check_pagination_nav_titles($listContent, $source),
            'Bulk delete' => itm_check_bulk_delete_actions($listContent, $source, $hasDeleteFile),
            'Bulk cancel' => itm_check_bulk_cancel_contract($indexContent),
            'Actions layout' => itm_check_table_actions_layout($listContent, $source),
            // Why: New button position/style are page-header checks — bespoke_page_ui_contract already records them once.
            'New button' => itm_check_new_button($indexContent, $hasCreateFile, $createContent),
            'Import Excel' => itm_check_import_excel_contract($listContent, $indexContent, $source),
            'Export toolbar' => itm_check_export_toolbar_support($listContent, $indexContent),
            'POST CSRF' => itm_check_index_mutation_csrf($indexContent),
        ];

        if ($isScaffoldHybrid) {
            $checks['Sample data'] = itm_check_sample_data($indexContent);
        }

        itm_fields_missing_record_bespoke_ui_check_results(
            $moduleSlug,
            $checks,
            $passes,
            $failures,
            'bespoke_list_ui'
        );
    }
}

if (!function_exists('itm_fields_missing_audit_bespoke_scaffold_hybrid_contract')) {
    /**
     * Bespoke modules that still ship scaffold markers must hide audit meta on create/edit.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $formPaths
     * @param list<string> $passes
     * @param list<array{code:string,message:string}> $failures
     */
    function itm_fields_missing_audit_bespoke_scaffold_hybrid_contract(
        string $moduleSlug,
        array $files,
        array $formPaths,
        array &$passes,
        array &$failures
    ): void {
        if (!is_readable($files['index'])) {
            return;
        }
        $content = itm_fields_missing_build_hybrid_scan_content($files, $formPaths);
        if (strpos($content, 'cr_manageable_columns(') === false) {
            return;
        }

        $hasCreateEdit = (bool) preg_match(
            "/in_array\s*\(\s*\\\$crud_action\s*,\s*\[\s*['\"]create['\"]/",
            $content
        );
        if (!$hasCreateEdit) {
            return;
        }

        $usesUiColumnsFormLoop = $hasCreateEdit
            && (bool) preg_match('/foreach\s*\(\s*\$uiColumns\s+as/', $content)
            && (bool) preg_match('/name=\s*["\'][^"\']*<\?php\s+echo\s+sanitize\(\$name\)/', $content);
        $usesFieldColumnsFormLoop = $hasCreateEdit
            && (bool) preg_match('/foreach\s*\(\s*\$fieldColumns\s+as/', $content)
            && (bool) preg_match('/name=\s*["\'][^"\']*<\?php\s+echo\s+sanitize\(\$name\)/', $content);

        if ($usesFieldColumnsFormLoop && !$usesUiColumnsFormLoop) {
            $failures[] = [
                'code' => 'bespoke_fieldcolumns_form',
                'message' => "{$moduleSlug} bespoke gate: create/edit uses \$fieldColumns form loop (expected \$uiColumns with audit meta hidden)",
            ];
        } elseif ($usesUiColumnsFormLoop) {
            $passes[] = "{$moduleSlug} bespoke gate: create/edit uses \$uiColumns form loop";
        }

        if ($usesUiColumnsFormLoop || $usesFieldColumnsFormLoop) {
            if (!preg_match('/itm_crud_is_form_hidden_audit_field|itm_crud_is_delete_form_hidden_field/', $content)) {
                $failures[] = [
                    'code' => 'bespoke_ui_columns_audit_filter',
                    'message' => "{$moduleSlug} bespoke gate: \$uiColumns missing audit meta filter (itm_crud_is_form_hidden_audit_field / itm_crud_is_delete_form_hidden_field)",
                ];
            } else {
                $passes[] = "{$moduleSlug} bespoke gate: \$uiColumns filters audit meta fields";
            }

            if (strpos($content, 'itm_crud_render_form_hidden_audit_inputs') === false) {
                $failures[] = [
                    'code' => 'bespoke_hidden_audit_inputs',
                    'message' => "{$moduleSlug} bespoke gate: create/edit missing itm_crud_render_form_hidden_audit_inputs()",
                ];
            } else {
                $passes[] = "{$moduleSlug} bespoke gate: hidden audit inputs on create/edit";
            }
        }
    }
}

if (!function_exists('itm_fields_missing_module_ui_coverage_skipped')) {
    function itm_fields_missing_module_ui_coverage_skipped(array $moduleReport): bool
    {
        return !empty($moduleReport['ui_coverage_audit_skipped']);
    }
}

if (!function_exists('itm_fields_missing_count_actionable_failures')) {
    /**
     * Failures that count toward exit code and "Result: N failure(s)" — excludes bespoke/status-driven gate lines.
     *
     * @param list<array<string,mixed>> $moduleReports
     */
    function itm_fields_missing_count_actionable_failures(array $moduleReports): int
    {
        $total = 0;
        foreach ($moduleReports as $report) {
            if (itm_fields_missing_module_ui_coverage_skipped($report)) {
                continue;
            }
            $total += count($report['failures'] ?? []);
        }

        return $total;
    }
}

if (!function_exists('itm_fields_missing_count_skip_gate_failures')) {
    /**
     * @param list<array<string,mixed>> $moduleReports
     */
    function itm_fields_missing_count_skip_gate_failures(array $moduleReports): int
    {
        $total = 0;
        foreach ($moduleReports as $report) {
            if (!itm_fields_missing_module_ui_coverage_skipped($report)) {
                continue;
            }
            $total += count($report['failures'] ?? []);
        }

        return $total;
    }
}

if (!function_exists('itm_fields_missing_result_status_label')) {
    function itm_fields_missing_result_status_label(bool $uiCoverageSkipped, bool $passed): string
    {
        if ($uiCoverageSkipped) {
            return $passed ? '[SKIP][pass]' : '[SKIP][fail]';
        }

        return $passed ? '[PASS]' : '[FAIL]';
    }
}

if (!function_exists('itm_fields_missing_extract_failure_message')) {
    /**
     * @param mixed $failure
     */
    function itm_fields_missing_extract_failure_message($failure): string
    {
        if (is_array($failure)) {
            return trim((string) ($failure['message'] ?? ''));
        }

        return trim((string) $failure);
    }
}

if (!function_exists('itm_fields_missing_echo_status_line')) {
    function itm_fields_missing_echo_status_line(string $line, string $nl): void
    {
        $escaped = itm_script_escape_browser_pre_text($line);
        if (function_exists('itm_script_format_status_line')) {
            echo itm_script_format_status_line($escaped) . $nl;
            return;
        }

        $type = 'info';
        if (preg_match('/^\[SKIP\]\[pass\]|\[PASS\]/', $line)) {
            $type = 'pass';
        } elseif (preg_match('/^\[SKIP\]\[fail\]|\[FAIL\]/', $line)) {
            $type = 'fail';
        }
        echo colorText($escaped, $type) . $nl;
    }
}

if (!function_exists('itm_fields_missing_echo_module_check_lines')) {
    /**
     * @param array<string, mixed> $moduleReport
     */
    function itm_fields_missing_echo_module_check_lines(array $moduleReport, string $nl): void
    {
        $skippedUi = !empty($moduleReport['ui_coverage_audit_skipped']);
        $moduleSlug = (string) ($moduleReport['module'] ?? '');

        // Why: Fail lines must stay visible — long bespoke modules print many [SKIP][pass] OK lines first.
        foreach ($moduleReport['failures'] ?? [] as $failure) {
            $message = itm_fields_missing_extract_failure_message($failure);
            if ($message === '') {
                continue;
            }
            $label = itm_fields_missing_result_status_label($skippedUi, false);
            itm_fields_missing_echo_status_line("{$label} {$message}", $nl);
        }
        foreach ($moduleReport['passes'] ?? [] as $passLine) {
            $passText = trim((string) $passLine);
            if ($passText === '') {
                continue;
            }
            $label = itm_fields_missing_result_status_label($skippedUi, true);
            itm_fields_missing_echo_status_line("{$label} {$passText}", $nl);
        }
        foreach ($moduleReport['infos'] ?? [] as $infoLine) {
            itm_fields_missing_echo_status_line('[INFO] ' . (string) $infoLine, $nl);
        }

        if ($skippedUi && $moduleSlug !== '') {
            $failCount = count($moduleReport['failures'] ?? []);
            if ($failCount > 0) {
                itm_fields_missing_echo_status_line(
                    '[SKIP][fail] ' . $moduleSlug . ' — bespoke gate: ' . $failCount . ' failure(s)',
                    $nl
                );
            } else {
                itm_fields_missing_echo_status_line(
                    '[SKIP][pass] ' . $moduleSlug . ' — bespoke gate passed (full UI coverage not audited)',
                    $nl
                );
            }
        }
    }
}

if (!function_exists('itm_fields_missing_ui_coverage_audit_skipped')) {
    function itm_fields_missing_ui_coverage_audit_skipped(string $uiMode, bool $hasSchemaTable): bool
    {
        if ($uiMode === 'bespoke_skip' || $uiMode === 'status_driven_skip') {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_fields_missing_index_uses_manageable_columns')) {
    function itm_fields_missing_index_uses_manageable_columns(string $indexPath): bool
    {
        if (!is_readable($indexPath)) {
            return false;
        }

        return strpos((string) file_get_contents($indexPath), 'cr_manageable_columns(') !== false;
    }
}

if (!function_exists('itm_fields_missing_infer_form_columns')) {
    /**
     * @param list<string> $expectedColumns
     * @return list<string>
     */
    function itm_fields_missing_infer_form_columns(
        string $moduleSlug,
        string $indexPath,
        array $expectedColumns,
        string $uiMode
    ): array {
        if ($expectedColumns === []) {
            return [];
        }

        if ($moduleSlug === 'employees' || $uiMode === 'employees') {
            $expectedSet = array_fill_keys($expectedColumns, true);
            $fields = [];
            foreach (array_merge(
                itm_fields_missing_employees_critical_fields(),
                itm_fields_missing_employees_optional_fields()
            ) as $name) {
                if (isset($expectedSet[$name])) {
                    $fields[] = $name;
                }
            }
            $fields = array_values(array_unique($fields));
            sort($fields, SORT_STRING);

            return $fields;
        }

        if ($uiMode === 'dynamic_scaffold'
            || itm_fields_missing_index_is_dynamic_scaffold($indexPath)
            || itm_fields_missing_index_uses_manageable_columns($indexPath)
        ) {
            return itm_fields_missing_ui_fields_for_module($moduleSlug, $expectedColumns);
        }

        return [];
    }
}

if (!function_exists('itm_fields_missing_infer_form_source_label')) {
    function itm_fields_missing_infer_form_source_label(
        string $indexPath,
        string $uiMode,
        string $moduleSlug
    ): string {
        if ($moduleSlug === 'employees' || $uiMode === 'employees') {
            return 'employees critical/optional matrix';
        }
        if ($uiMode === 'dynamic_scaffold' || itm_fields_missing_index_is_dynamic_scaffold($indexPath)) {
            return '$uiColumns';
        }
        if (itm_fields_missing_index_uses_manageable_columns($indexPath)) {
            return 'cr_manageable_columns';
        }

        return '';
    }
}

if (!function_exists('itm_fields_missing_scraped_table_form_fields')) {
    /**
     * @param list<string> $scrapedRaw
     * @param list<string> $expectedColumns
     * @return list<string>
     */
    function itm_fields_missing_scraped_table_form_fields(array $scrapedRaw, array $expectedColumns): array
    {
        if ($expectedColumns === []) {
            $scraped = array_values($scrapedRaw);
            sort($scraped, SORT_STRING);

            return $scraped;
        }

        $expectedSet = array_fill_keys($expectedColumns, true);
        $scraped = [];
        foreach ($scrapedRaw as $name) {
            if (isset($expectedSet[$name])) {
                $scraped[] = $name;
            }
        }
        sort($scraped, SORT_STRING);

        return $scraped;
    }
}

if (!function_exists('itm_fields_missing_prepare_ui_report_payload')) {
    /**
     * @param array{form_fields:list<string>,form_fields_other:list<string>,audited:list<string>,scraped_raw:list<string>} $uiCollected
     * @return array{
     *   scraped_table:list<string>,
     *   form_other:list<string>,
     *   inferred:list<string>,
     *   inferred_source:string,
     *   audited:list<string>,
     *   skipped:bool
     * }
     */
    function itm_fields_missing_prepare_ui_report_payload(
        string $moduleSlug,
        array $files,
        array $expectedColumns,
        string $uiMode,
        array $uiCollected
    ): array {
        $hasSchemaTable = $expectedColumns !== [];
        $scrapedTable = itm_fields_missing_scraped_table_form_fields(
            $uiCollected['scraped_raw'] ?? [],
            $hasSchemaTable ? $expectedColumns : []
        );
        $inferred = itm_fields_missing_infer_form_columns(
            $moduleSlug,
            $files['index'],
            $expectedColumns,
            $uiMode
        );
        $skipped = itm_fields_missing_ui_coverage_audit_skipped($uiMode, $expectedColumns !== []);

        return [
            'scraped_table' => $scrapedTable,
            'form_other' => $uiCollected['form_fields_other'] ?? [],
            'inferred' => $inferred,
            'inferred_source' => itm_fields_missing_infer_form_source_label($files['index'], $uiMode, $moduleSlug),
            'audited' => $skipped ? [] : ($uiCollected['audited'] ?? []),
            'skipped' => $skipped,
        ];
    }
}

if (!function_exists('itm_fields_missing_append_dynamic_ui_infos')) {
    /**
     * @param list<string> $scrapedTable
     * @param list<string> $inferred
     * @param list<string> $infos
     */
    function itm_fields_missing_append_dynamic_ui_infos(
        string $moduleSlug,
        string $uiMode,
        array $scrapedTable,
        array $inferred,
        string $indexPath,
        array &$infos
    ): void {
        if ($uiMode !== 'bespoke_skip') {
            return;
        }

        $usesManageable = itm_fields_missing_index_uses_manageable_columns($indexPath);
        if ($usesManageable && $inferred !== [] && count($scrapedTable) < count($inferred)) {
            $infos[] = "{$moduleSlug} create/edit use dynamic name=\$name loops (cr_manageable_columns) — static scrape under-reports; see Inferred form columns";
        } elseif ($scrapedTable === [] && $inferred === [] && !$usesManageable) {
            $infos[] = "{$moduleSlug} bespoke inline/grid or API UI — no literal table-column name= scrape (see docs/list_bespoke_UI.txt)";
        }
    }
}

if (!function_exists('itm_fields_missing_finalize_module_report')) {
    /**
     * @param list<string> $expectedColumns
     * @param list<string> $liveColumns
     * @param list<string> $uiAuditedColumns
     * @param list<string> $uiFormFieldsScraped
     * @param list<string> $uiFormFieldsOther
     * @param list<string> $uiInferredFormColumns
     * @return array<string, mixed>
     */
    function itm_fields_missing_finalize_module_report(
        array $report,
        array $expectedColumns,
        array $liveColumns,
        array $uiAuditedColumns = [],
        array $uiFormFieldsScraped = [],
        array $uiFormFieldsOther = [],
        array $uiInferredFormColumns = [],
        ?string $inferredFormSource = null
    ): array {
        $uiMode = (string) ($report['ui_mode'] ?? '');
        $hasSchemaTable = $expectedColumns !== [] || trim((string) ($report['table'] ?? '')) !== '';
        $skipped = array_key_exists('ui_coverage_audit_skipped', $report)
            ? (bool) $report['ui_coverage_audit_skipped']
            : itm_fields_missing_ui_coverage_audit_skipped($uiMode, $hasSchemaTable);

        $report['expected_columns'] = array_values($expectedColumns);
        $report['live_columns'] = array_values($liveColumns);
        $report['ui_coverage_audit_skipped'] = $skipped;
        $report['ui_form_fields_scraped'] = array_values($uiFormFieldsScraped);
        // Why: JSON consumers still read ui_form_fields; scraped literal names are the stable contract.
        $report['ui_form_fields'] = array_values($uiFormFieldsScraped);
        $report['ui_form_fields_other'] = array_values($uiFormFieldsOther);
        $report['ui_inferred_form_columns'] = array_values($uiInferredFormColumns);
        $report['ui_inferred_form_source'] = $inferredFormSource ?? '';

        if ($skipped) {
            $report['ui_audited_columns'] = [];
            $report['ui_excluded_columns'] = [];
        } else {
            $report['ui_audited_columns'] = array_values($uiAuditedColumns);
            $report['ui_excluded_columns'] = array_values(array_diff($expectedColumns, $uiAuditedColumns));
        }

        return $report;
    }
}

if (!function_exists('itm_fields_missing_format_inline_list_section')) {
    /**
     * @param list<string> $items
     */
    function itm_fields_missing_format_inline_list_section(
        string $heading,
        array $items,
        string $nl,
        string $emptyText
    ): string {
        if ($items === []) {
            return '  ' . $heading . ' (0): ' . $emptyText . $nl;
        }

        return '  ' . $heading . ' (' . count($items) . '): ' . implode(', ', $items) . $nl;
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
        $uiFormScraped = $moduleReport['ui_form_fields_scraped'] ?? $moduleReport['ui_form_fields'] ?? [];
        $uiFormOther = $moduleReport['ui_form_fields_other'] ?? [];
        $uiInferred = $moduleReport['ui_inferred_form_columns'] ?? [];
        $inferredSource = (string) ($moduleReport['ui_inferred_form_source'] ?? '');
        $uiAudited = $moduleReport['ui_audited_columns'] ?? [];
        $excluded = $moduleReport['ui_excluded_columns'] ?? [];
        $uiMode = (string) ($moduleReport['ui_mode'] ?? '');
        $skipped = (bool) ($moduleReport['ui_coverage_audit_skipped'] ?? false);

        if (!is_array($expected)) {
            $expected = [];
        }
        if (!is_array($live)) {
            $live = [];
        }
        if (!is_array($uiFormScraped)) {
            $uiFormScraped = [];
        }
        if (!is_array($uiFormOther)) {
            $uiFormOther = [];
        }
        if (!is_array($uiInferred)) {
            $uiInferred = [];
        }
        if (!is_array($uiAudited)) {
            $uiAudited = [];
        }
        if (!is_array($excluded)) {
            $excluded = [];
        }

        $hasSchemaTable = $expected !== [] || trim((string) ($moduleReport['table'] ?? '')) !== '';
        $out = '';

        if ($hasSchemaTable) {
            $out .= itm_fields_missing_format_inline_list_section(
                'database.sql columns',
                $expected,
                $nl,
                '(none)'
            );
            $out .= itm_fields_missing_format_inline_list_section(
                'live columns',
                $live,
                $nl,
                '(none)'
            );
        } else {
            $out .= '  database.sql columns (0): (none)' . $nl;
        }

        if ($skipped) {
            if (!$hasSchemaTable) {
                $out .= '  UI coverage audit: skipped (no schema table — UI form scrape only)' . $nl;
            } elseif ($uiMode === 'status_driven_skip') {
                $out .= '  UI coverage audit: skipped (status-driven bespoke UI — schema-only)' . $nl;
            } else {
                $out .= '  UI coverage audit: skipped (bespoke/deferred UI — schema-only)' . $nl;
            }
        }

        $scrapeHeading = $hasSchemaTable
            ? 'Static HTML name= scrape (create/edit)'
            : 'Static HTML name= scrape (index/create/edit)';
        $scrapeEmpty = $hasSchemaTable
            ? '(none — literal name= not found in create/edit for table columns)'
            : '(none scraped from index/create/edit)';
        $out .= itm_fields_missing_format_inline_list_section(
            $scrapeHeading,
            $uiFormScraped,
            $nl,
            $scrapeEmpty
        );

        if ($uiInferred !== []) {
            $inferredHeading = 'Inferred form columns';
            if ($inferredSource !== '') {
                $inferredHeading .= ' (' . $inferredSource . ')';
            }
            $out .= itm_fields_missing_format_inline_list_section(
                $inferredHeading,
                $uiInferred,
                $nl,
                '(none)'
            );
        }

        if (!$skipped) {
            $out .= itm_fields_missing_format_inline_list_section(
                'UI audited columns',
                $uiAudited,
                $nl,
                '(none — UI coverage audit did not collect business columns)'
            );
            $out .= itm_fields_missing_format_inline_list_section(
                'excluded from UI audit',
                $excluded,
                $nl,
                '(none — all expected columns are in the UI audit set)'
            );
        }

        if ($uiFormOther !== []) {
            $out .= itm_fields_missing_format_inline_list_section(
                'UI form fields other',
                $uiFormOther,
                $nl,
                '(none)'
            );
        }

        return $out . $nl;
    }
}

if (!function_exists('itm_fields_missing_format_legend')) {
    function itm_fields_missing_format_legend(string $nl): string
    {
        $out = 'Section legend (same for every module; ui: tag shows audit path):' . $nl;
        $out .= '  database.sql columns / live columns — canonical schema vs live MySQL (when the module has a table)' . $nl;
        $out .= '  UI coverage audit: skipped — gated bespoke UI contract (page: title/favicon/list heading layout+emoji/new button position+style; list: search/sort/pagination/import/export/sample data)' . $nl;
        $out .= '  Bespoke gate list UI — Search, Sort, Pagination (Settings records_per_page), bulk actions, Actions column' . $nl;
        $out .= '  List heading layout — centered h1 + Settings new_button_position left/right gates' . $nl;
        $out .= '  List heading emoji — $moduleListHeading via itm_sidebar_label_for_module() or itm_resolve_module_sidebar_icon()' . $nl;
        $out .= '  [SKIP][pass] / [SKIP][fail] — one line per gated check (OK / NOT OK); n/a checks are omitted' . $nl;
        $out .= '  [SKIP][pass] module summary does not audit business columns — see Audit summary footer' . $nl;
        $out .= '  Static HTML name= scrape — literal name="..." in create/edit (or index for UI-only); not the full dynamic UI' . $nl;
        $out .= '  Inferred form columns — derived from $uiColumns, cr_manageable_columns, or employees matrix' . $nl;
        $out .= '  UI audited columns / excluded from UI audit — only when UI coverage audit ran' . $nl;
        $out .= '  [PASS] audited UI column — dynamic scaffold business columns (create/edit, view, index via $uiColumns loops)' . $nl;
        $out .= '  [PASS] excluded UI column — meta columns must stay hidden on create/edit forms' . $nl;
        $out .= '  UI form fields other — non-table controls (CSRF, bulk actions, import helpers, …)' . $nl;

        return $out . $nl;
    }
}

if (!function_exists('itm_fields_missing_format_skip_gate_failure_summary_block')) {
    /**
     * Bespoke/status-driven [SKIP][fail] lines (informational — not in actionable failure_count).
     *
     * @param array{modules?:list<array<string,mixed>>} $report
     */
    function itm_fields_missing_format_skip_gate_failure_summary_block(
        array $report,
        string $nl,
        ?callable $formatLine = null
    ): string {
        $messages = [];
        foreach ($report['modules'] ?? [] as $moduleReport) {
            if (!itm_fields_missing_module_ui_coverage_skipped($moduleReport)) {
                continue;
            }
            if (!is_array($moduleReport['failures'] ?? null)) {
                continue;
            }
            foreach ($moduleReport['failures'] as $failure) {
                $message = itm_fields_missing_extract_failure_message($failure);
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
        $out .= 'Bespoke gate failure summary (' . $count . ' — informational, not in Result total):' . $nl;
        foreach ($messages as $message) {
            $line = '[SKIP][fail] ' . $message;
            $out .= ($formatLine !== null ? $formatLine($line) : $line) . $nl;
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
            if (itm_fields_missing_module_ui_coverage_skipped($moduleReport)) {
                continue;
            }
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
     * One row per module: tables collapsed, short SKIP reason.
     *
     * @param list<array<string,mixed>> $moduleReports
     * @return list<array{module:string,tables:list<string>,ui_mode:string,reason:string}>
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

        /** @var array<string, array{module:string,tables:list<string>,ui_mode:string,reason:string}> $byModule */
        $byModule = [];
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

            $table = trim((string) ($moduleReport['table'] ?? ''));
            $reason = $uiMode === 'status_driven_skip' ? 'status-driven UI' : 'bespoke UI';
            if (!isset($byModule[$moduleSlug])) {
                $byModule[$moduleSlug] = [
                    'module' => $moduleSlug,
                    'tables' => [],
                    'ui_mode' => $uiMode,
                    'reason' => $reason,
                ];
            }
            if ($table !== '' && !in_array($table, $byModule[$moduleSlug]['tables'], true)) {
                $byModule[$moduleSlug]['tables'][] = $table;
            }
            if ($uiMode === 'status_driven_skip') {
                $byModule[$moduleSlug]['ui_mode'] = $uiMode;
                $byModule[$moduleSlug]['reason'] = $reason;
            }
        }

        if ($moduleFilter === null || $moduleFilter === '') {
            require_once __DIR__ . '/itm_crud_tables_audit.php';
            foreach (itm_crud_tables_load_skip_module_slugs($rootPath) as $slug) {
                if (isset($byModule[$slug]) || isset($modulesInReport[$slug])) {
                    continue;
                }
                $indexPath = $rootPath . 'modules' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.php';
                if (!is_file($indexPath)) {
                    continue;
                }
                $byModule[$slug] = [
                    'module' => $slug,
                    'tables' => [],
                    'ui_mode' => 'bespoke_skip',
                    'reason' => 'no schema table',
                ];
            }
        }

        $skips = array_values($byModule);
        usort($skips, static function (array $a, array $b): int {
            return strcmp($a['module'], $b['module']);
        });
        foreach ($skips as &$skip) {
            sort($skip['tables'], SORT_STRING);
        }
        unset($skip);

        return $skips;
    }
}

if (!function_exists('itm_fields_missing_format_bespoke_skip_block')) {
    /**
     * Compact end-of-run SKIP table: module | table(s) | reason.
     *
     * @param list<array{module:string,tables?:list<string>,table?:string,ui_mode:string,reason:string}> $skips
     */
    function itm_fields_missing_format_bespoke_skip_block(
        array $skips,
        string $nl,
        ?callable $formatLine = null
    ): string {
        if ($skips === []) {
            return '';
        }

        $rows = [];
        $moduleWidth = strlen('module');
        $tableWidth = strlen('table(s)');
        foreach ($skips as $skip) {
            $moduleSlug = (string) ($skip['module'] ?? '');
            $tables = $skip['tables'] ?? null;
            if (!is_array($tables)) {
                $legacy = trim((string) ($skip['table'] ?? ''));
                $tables = $legacy !== '' ? [$legacy] : [];
            }
            // Why: ASCII placeholder keeps CLI column padding stable (UTF-8 em dash breaks strlen widths).
            $tableText = $tables === [] ? '-' : implode(', ', $tables);
            $reason = trim((string) ($skip['reason'] ?? 'bespoke UI'));
            $moduleWidth = max($moduleWidth, strlen($moduleSlug));
            $tableWidth = max($tableWidth, strlen($tableText));
            $rows[] = [
                'module' => $moduleSlug,
                'tables' => $tableText,
                'reason' => $reason,
            ];
        }

        $out = str_repeat('-', 72) . $nl;
        $out .= 'Bespoke / deferred UI — SKIP (' . count($rows) . ' modules):' . $nl;
        $header = sprintf(
            '%-6s  %-' . $moduleWidth . 's  %-' . $tableWidth . 's  %s',
            '[SKIP]',
            'module',
            'table(s)',
            'reason'
        );
        $out .= $header . $nl;

        foreach ($rows as $row) {
            $moduleRef = function_exists('itm_script_format_module_link')
                ? itm_script_format_module_link($row['module'], '', $row['module'])
                : $row['module'];
            $tableRef = $row['tables'];
            if ($tableRef !== '-' && function_exists('itm_script_format_table_link') && !itm_script_is_cli_sapi()) {
                $parts = [];
                foreach (explode(', ', $row['tables']) as $tableName) {
                    $parts[] = itm_script_format_table_link($tableName);
                }
                $tableRef = implode(', ', $parts);
            }

            // Why: pad plain CLI text; browser links break sprintf widths so keep readable spacing.
            if (function_exists('itm_script_is_cli_sapi') && itm_script_is_cli_sapi()) {
                $line = sprintf(
                    '%-6s  %-' . $moduleWidth . 's  %-' . $tableWidth . 's  %s',
                    '[SKIP]',
                    $row['module'],
                    $row['tables'],
                    $row['reason']
                );
            } else {
                $line = '[SKIP]  ' . $moduleRef . '  ' . $tableRef . '  ' . htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8');
            }
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
        }

        if ($schemaExtra !== []) {
            foreach ($schemaExtra as $column) {
                $infos[] = "Live DB has extra {$table}.{$column} not listed in database.sql";
            }
        }
        if ($expectedColumns !== []) {
            // Schema match lines are appended after UI mode is known (PASS vs schema-only INFO).
        }

        require_once __DIR__ . '/itm_crud_tables_audit.php';
        $bespokeModules = array_fill_keys(itm_crud_tables_load_skip_module_slugs($rootPath), true);
        $statusDriven = in_array($moduleSlug, itm_fields_missing_status_driven_slugs(), true);
        $isDynamicScaffold = itm_fields_missing_index_is_dynamic_scaffold($files['index']);
        $uiCollected = itm_fields_missing_collect_ui_fields(
            $moduleSlug,
            $files,
            $expectedColumns,
            $isDynamicScaffold || $moduleSlug === 'employees'
        );
        $uiFormFields = $uiCollected['form_fields'];
        $uiFormFieldsOther = $uiCollected['form_fields_other'];
        $uiAuditedCollected = $uiCollected['audited'];
        $formPaths = itm_fields_missing_resolve_form_paths($files);

        if (isset($bespokeModules[$moduleSlug])) {
            $infos[] = "{$moduleSlug} is bespoke/deferred UI — schema-only audit (see docs/list_bespoke_UI.txt)";
            $uiPayload = itm_fields_missing_prepare_ui_report_payload(
                $moduleSlug,
                $files,
                $expectedColumns,
                'bespoke_skip',
                $uiCollected
            );
            itm_fields_missing_append_dynamic_ui_infos(
                $moduleSlug,
                'bespoke_skip',
                $uiPayload['scraped_table'],
                $uiPayload['inferred'],
                $files['index'],
                $infos
            );
            $gateFormPaths = itm_fields_missing_merge_bespoke_form_paths($files, $formPaths);
            itm_fields_missing_apply_skipped_ui_coverage_gate(
                $moduleSlug,
                $expectedColumns,
                $gateFormPaths,
                $files,
                $passes,
                $failures,
                false
            );
            itm_fields_missing_append_schema_column_passes(
                $table,
                $expectedColumns,
                $liveColumns,
                $passes,
                $infos,
                true
            );

            return itm_fields_missing_finalize_module_report([
                'module' => $moduleSlug,
                'table' => $table,
                'schema_missing' => $schemaMissing,
                'schema_extra' => $schemaExtra,
                'ui_mode' => 'bespoke_skip',
                'ui_coverage_audit_skipped' => true,
                'failures' => $failures,
                'infos' => $infos,
                'passes' => $passes,
            ], $expectedColumns, $liveColumns, [], $uiPayload['scraped_table'], $uiPayload['form_other'], $uiPayload['inferred'], $uiPayload['inferred_source']);
        }

        if ($statusDriven) {
            $infos[] = "{$moduleSlug} is status-driven bespoke UI — schema-only audit (row active is soft-delete mirror)";
            $uiPayload = itm_fields_missing_prepare_ui_report_payload(
                $moduleSlug,
                $files,
                $expectedColumns,
                'status_driven_skip',
                $uiCollected
            );
            $gateFormPaths = itm_fields_missing_merge_bespoke_form_paths($files, $formPaths);
            itm_fields_missing_apply_skipped_ui_coverage_gate(
                $moduleSlug,
                $expectedColumns,
                $gateFormPaths,
                $files,
                $passes,
                $failures,
                true
            );
            itm_fields_missing_append_schema_column_passes(
                $table,
                $expectedColumns,
                $liveColumns,
                $passes,
                $infos,
                true
            );

            return itm_fields_missing_finalize_module_report([
                'module' => $moduleSlug,
                'table' => $table,
                'schema_missing' => $schemaMissing,
                'schema_extra' => $schemaExtra,
                'ui_mode' => 'status_driven_skip',
                'ui_coverage_audit_skipped' => true,
                'failures' => $failures,
                'infos' => $infos,
                'passes' => $passes,
            ], $expectedColumns, $liveColumns, [], $uiPayload['scraped_table'], $uiPayload['form_other'], $uiPayload['inferred'], $uiPayload['inferred_source']);
        }

        $uiMode = 'manual';
        if ($isDynamicScaffold) {
            $uiMode = 'dynamic_scaffold';
            $uiAudited = $uiCollected['audited'];
            itm_fields_missing_audit_excluded_ui_columns(
                $moduleSlug,
                array_values(array_intersect($expectedColumns, itm_fields_missing_global_ui_excluded_columns())),
                $formPaths,
                $passes,
                $failures,
                false,
                $files
            );
            itm_fields_missing_audit_audited_ui_columns(
                $moduleSlug,
                $uiAudited,
                $formPaths,
                $files,
                $passes,
                $failures,
                true
            );

            $uiPayload = itm_fields_missing_prepare_ui_report_payload(
                $moduleSlug,
                $files,
                $expectedColumns,
                $uiMode,
                $uiCollected
            );
            itm_fields_missing_append_schema_column_passes(
                $table,
                $expectedColumns,
                $liveColumns,
                $passes,
                $infos,
                false
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
            ], $expectedColumns, $liveColumns, $uiAudited, $uiPayload['scraped_table'], $uiPayload['form_other'], $uiPayload['inferred'], $uiPayload['inferred_source']);
        }

        $uiFields = $uiCollected['audited'] !== []
            ? $uiCollected['audited']
            : itm_fields_missing_ui_fields_for_module($moduleSlug, $expectedColumns);
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
                $infos[] = 'employees list_all.php redirects to index.php (list columns inherit from index.php)';
            }
        }

        itm_fields_missing_audit_excluded_ui_columns(
            $moduleSlug,
            array_values(array_intersect($expectedColumns, itm_fields_missing_global_ui_excluded_columns())),
            $formPaths,
            $passes,
            $failures,
            false,
            $files
        );

        $uiPayload = itm_fields_missing_prepare_ui_report_payload(
            $moduleSlug,
            $files,
            $expectedColumns,
            $uiMode,
            $uiCollected
        );
        itm_fields_missing_append_schema_column_passes(
            $table,
            $expectedColumns,
            $liveColumns,
            $passes,
            $infos,
            false
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
        ], $expectedColumns, $liveColumns, $uiFields, $uiPayload['scraped_table'], $uiPayload['form_other'], $uiPayload['inferred'], $uiPayload['inferred_source']);
    }
}

if (!function_exists('itm_fields_missing_audit_bespoke_ui_only')) {
    /**
     * Schema-less bespoke module: scrape module forms only (no database.sql / live column pass).
     *
     * @return array<string, mixed>
     */
    function itm_fields_missing_audit_bespoke_ui_only(string $moduleSlug, ?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $files = itm_fields_missing_module_file_bundle($moduleSlug, $rootPath);
        $formPaths = itm_fields_missing_resolve_bespoke_form_paths($files);
        $scraped = itm_fields_missing_extract_form_field_names($formPaths);
        $passes = [];
        $infos = [
            "{$moduleSlug} is bespoke/deferred UI — no schema table; UI form scrape only (see docs/list_bespoke_UI.txt)",
        ];

        if (is_readable($files['index'])) {
            $indexContent = (string) file_get_contents($files['index']);
            if (preg_match('/require\s+[\'\"]\.\.\/equipment\/index\.php[\'\"]/', $indexContent)) {
                $infos[] = "{$moduleSlug} delegates to modules/equipment/index.php — form fields owned by equipment";
            }
        }

        $scrapedTable = itm_fields_missing_scraped_table_form_fields($scraped, []);

        return itm_fields_missing_finalize_module_report([
            'module' => $moduleSlug,
            'table' => '',
            'schema_missing' => [],
            'schema_extra' => [],
            'ui_mode' => 'bespoke_skip',
            'ui_coverage_audit_skipped' => true,
            'failures' => [],
            'infos' => $infos,
            'passes' => $passes,
        ], [], [], [], $scrapedTable, [], [], '');
    }
}

if (!function_exists('itm_fields_missing_append_bespoke_ui_only_reports')) {
    /**
     * @param list<array<string,mixed>> $moduleReports
     * @return list<array<string,mixed>>
     */
    function itm_fields_missing_append_bespoke_ui_only_reports(
        array $moduleReports,
        ?string $moduleFilter = null,
        ?string $rootPath = null
    ): array {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
        $rootPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR;

        require_once __DIR__ . '/itm_crud_tables_audit.php';

        $reported = [];
        foreach ($moduleReports as $report) {
            $slug = (string) ($report['module'] ?? '');
            if ($slug !== '') {
                $reported[$slug] = true;
            }
        }

        foreach (itm_crud_tables_load_skip_module_slugs($rootPath) as $slug) {
            if ($moduleFilter !== null && $moduleFilter !== '' && $slug !== $moduleFilter) {
                continue;
            }
            if (isset($reported[$slug])) {
                continue;
            }
            $indexPath = $rootPath . 'modules' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.php';
            if (!is_file($indexPath)) {
                continue;
            }
            $moduleReports[] = itm_fields_missing_audit_bespoke_ui_only($slug, $rootPath);
            $reported[$slug] = true;
        }

        return $moduleReports;
    }
}

if (!function_exists('itm_fields_missing_collect_report')) {
    /**
     * @return array{
     *   modules: list<array<string,mixed>>,
     *   tables_without_module: list<string>,
     *   bespoke_skips: list<array{module:string,tables:list<string>,ui_mode:string,reason:string}>,
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
            $moduleReports[] = $report;
            $modulesWithTable[$moduleSlug] = true;
        }

        $moduleReports = itm_fields_missing_append_bespoke_ui_only_reports($moduleReports, $moduleFilter, $rootPath);

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

        $failureCount = itm_fields_missing_count_actionable_failures($moduleReports);
        $skipGateFailureCount = itm_fields_missing_count_skip_gate_failures($moduleReports);

        $summary = itm_fields_missing_build_report_summary($moduleReports);

        return [
            'modules' => $moduleReports,
            'tables_without_module' => $tablesWithoutModule,
            'bespoke_skips' => itm_fields_missing_collect_bespoke_skips($moduleReports),
            'failure_count' => $failureCount,
            'skip_gate_failure_count' => $skipGateFailureCount,
            'summary' => $summary,
            'schema_table_count' => count($schemaMap),
            'module_count' => count($moduleReports),
        ];
    }
}

if (!function_exists('itm_fields_missing_build_report_summary')) {
    /**
     * @param list<array<string,mixed>> $moduleReports
     * @return array<string, int|string>
     */
    function itm_fields_missing_build_report_summary(array $moduleReports): array
    {
        $byUiMode = [];
        $skipPassModules = 0;
        $skipFailModules = 0;
        $scaffoldFailModules = 0;
        $scaffoldAuditedPassLines = 0;

        foreach ($moduleReports as $report) {
            $uiMode = (string) ($report['ui_mode'] ?? 'unknown');
            $byUiMode[$uiMode] = ($byUiMode[$uiMode] ?? 0) + 1;
            $failCount = count($report['failures'] ?? []);
            $skipped = !empty($report['ui_coverage_audit_skipped']);

            if ($skipped) {
                if ($failCount > 0) {
                    $skipFailModules++;
                } else {
                    $skipPassModules++;
                }
                continue;
            }

            if ($failCount > 0) {
                $scaffoldFailModules++;
            }

            foreach ($report['passes'] ?? [] as $passLine) {
                if (strpos((string) $passLine, ' audited UI column ') !== false) {
                    $scaffoldAuditedPassLines++;
                }
            }
        }

        return [
            'modules_total' => count($moduleReports),
            'ui_mode_counts' => $byUiMode,
            'skip_pass_modules' => $skipPassModules,
            'skip_fail_modules' => $skipFailModules,
            'scaffold_fail_modules' => $scaffoldFailModules,
            'scaffold_audited_pass_lines' => $scaffoldAuditedPassLines,
            'failure_lines_total' => itm_fields_missing_count_actionable_failures($moduleReports),
            'skip_gate_failure_lines' => itm_fields_missing_count_skip_gate_failures($moduleReports),
        ];
    }
}

if (!function_exists('itm_fields_missing_format_audit_summary')) {
    /**
     * @param array<string, mixed> $report
     */
    function itm_fields_missing_format_audit_summary(array $report, string $nl): string
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $out = str_repeat('-', 72) . $nl;
        $out .= 'Audit summary (read this — [SKIP][pass] is not a full UI pass):' . $nl;
        $out .= '  Bespoke / status-driven gated modules: '
            . (int) ($summary['skip_pass_modules'] ?? 0) . ' [SKIP][pass], '
            . (int) ($summary['skip_fail_modules'] ?? 0) . ' [SKIP][fail] '
            . '(' . (int) ($summary['skip_gate_failure_lines'] ?? 0) . ' gated failure line(s); not counted in Result total)' . $nl;
        $out .= '  Dynamic scaffold / manual full UI modules: '
            . (int) ($summary['scaffold_audited_pass_lines'] ?? 0) . ' audited UI [PASS] lines, '
            . (int) ($summary['scaffold_fail_modules'] ?? 0) . ' module(s) with [FAIL], '
            . (int) ($summary['failure_lines_total'] ?? 0) . ' actionable failure line(s)' . $nl;
        if (isset($summary['ui_mode_counts']) && is_array($summary['ui_mode_counts'])) {
            $parts = [];
            foreach ($summary['ui_mode_counts'] as $mode => $count) {
                $parts[] = $mode . '=' . (int) $count;
            }
            sort($parts, SORT_STRING);
            $out .= '  ui modes: ' . implode(', ', $parts) . $nl;
        }

        return $out . $nl;
    }
}
