<?php
/**
 * Static audit: view-visible CRUD fields must appear on create/edit forms.
 *
 * Why: fields_missing.php treats any foreach ($uiColumns) form loop as covering every
 * business column, but modules may filter $uiColumns for the list grid ($*ListHiddenFields)
 * while $viewColumns still shows those fields — edit must expose them via extra cards/helpers.
 */

if (!function_exists('itm_crud_is_form_hidden_audit_field')) {
    require_once __DIR__ . '/../../includes/itm_crud_audit_fields.php';
}

if (!function_exists('itm_fields_missing_discover_module_targets')) {
    require_once __DIR__ . '/itm_fields_missing_report.php';
}

if (!function_exists('itm_crud_view_edit_field_parity_parse_list_hidden_filters')) {
    /**
     * Parse $*ListHiddenFields arrays and which column variables they filter.
     *
     * @return list<array{name:string,fields:list<string>,targets:list<string>}>
     */
    function itm_crud_view_edit_field_parity_parse_list_hidden_filters(string $content): array
    {
        $filters = [];
        if (!preg_match_all(
            '/\$([a-zA-Z0-9_]*(?:ListHiddenFields|ListHidden))\s*=\s*\[([^\]]+)\]/s',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $match) {
            $varName = (string) ($match[1] ?? '');
            $body = (string) ($match[2] ?? '');
            $fields = [];
            if (preg_match_all("/'([a-zA-Z0-9_]+)'/", $body, $fieldMatches)) {
                $fields = $fieldMatches[1];
            }
            if ($varName === '' || $fields === []) {
                continue;
            }

            $targets = [];
            $escaped = preg_quote($varName, '/');
            if (preg_match(
                '/\$uiColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$uiColumns[\s\S]*?use\s*\(\s*\$' . $escaped . '\s*\)/',
                $content
            ) === 1) {
                $targets[] = 'uiColumns';
            }
            if (preg_match(
                '/\$viewColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$viewColumns[\s\S]*?use\s*\(\s*\$' . $escaped . '\s*\)/',
                $content
            ) === 1) {
                $targets[] = 'viewColumns';
            }
            if ($targets === []) {
                continue;
            }

            $filters[] = [
                'name' => $varName,
                'fields' => array_values(array_unique($fields)),
                'targets' => $targets,
            ];
        }

        return $filters;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_fields_hidden_from_variable')) {
    /**
     * @param list<array{name:string,fields:list<string>,targets:list<string>}> $filters
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_fields_hidden_from_variable(array $filters, string $variable): array
    {
        $hidden = [];
        foreach ($filters as $filter) {
            if (!in_array($variable, (array) ($filter['targets'] ?? []), true)) {
                continue;
            }
            foreach ((array) ($filter['fields'] ?? []) as $field) {
                $hidden[] = (string) $field;
            }
        }

        return array_values(array_unique($hidden));
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_ui_list_only_hidden_fields')) {
    /**
     * Fields removed from $uiColumns (list) but still on $viewColumns (detail).
     *
     * @param list<array{name:string,fields:list<string>,targets:list<string>}> $filters
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_ui_list_only_hidden_fields(array $filters): array
    {
        $uiHidden = itm_crud_view_edit_field_parity_fields_hidden_from_variable($filters, 'uiColumns');
        $viewHidden = itm_crud_view_edit_field_parity_fields_hidden_from_variable($filters, 'viewColumns');

        return array_values(array_diff($uiHidden, $viewHidden));
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_collect_edit_form_section_fields')) {
    /**
     * Scrape field slugs from *_edit_form_sections() helpers under modules/{slug}/includes/.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_collect_edit_form_section_fields(array $files): array
    {
        $includesDir = (string) ($files['includes'] ?? '');
        if ($includesDir === '' || !is_dir($includesDir)) {
            return [];
        }

        $fields = [];
        foreach (glob($includesDir . '/*.php') ?: [] as $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            if (preg_match('/function\s+[a-zA-Z0-9_]*_edit_form_sections\s*\(/', $content) !== 1) {
                continue;
            }

            if (preg_match_all("/'fields'\s*=>\s*\[([^\]]+)\]/s", $content, $sectionMatches)) {
                foreach ($sectionMatches[1] as $sectionBody) {
                    if (preg_match_all("/'([a-zA-Z0-9_]+)'/", (string) $sectionBody, $fieldMatches)) {
                        foreach ($fieldMatches[1] as $field) {
                            $fields[] = $field;
                        }
                    }
                }
            }

            if (preg_match(
                '/function\s+[a-zA-Z0-9_]*portal_rule_form_field_order\s*\([^)]*\)\s*(?::\s*\w+\s*)?\{\s*return\s*\[([^\]]+)\]/s',
                $content,
                $orderMatch
            ) === 1 && preg_match_all("/'([a-zA-Z0-9_]+)'/", (string) ($orderMatch[1] ?? ''), $orderFields)) {
                $fields = array_merge($fields, $orderFields[1]);
            }
        }

        return array_values(array_unique($fields));
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_index_references_edit_form_sections')) {
    function itm_crud_view_edit_field_parity_index_references_edit_form_sections(string $indexContent): bool
    {
        return preg_match('/\$[a-zA-Z0-9_]*FormEditSections\b/', $indexContent) === 1
            || preg_match('/[a-zA-Z0-9_]*_edit_form_section_columns\s*\(/', $indexContent) === 1;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_form_columns_excluded_fields')) {
    /**
     * Fields explicitly removed when building $formColumns from $uiColumns|$fieldColumns.
     *
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_form_columns_excluded_fields(string $content): array
    {
        if (!preg_match(
            '/\$formColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$(uiColumns|fieldColumns)\s*,\s*function\s*\(\s*\$col\s*\)\s*\{([^}]+)\}/s',
            $content,
            $match
        )) {
            return [];
        }

        $body = (string) ($match[2] ?? '');
        $excluded = [];
        if (preg_match_all("/['\"]([a-zA-Z0-9_]+)['\"]/", $body, $literalMatches)) {
            foreach ($literalMatches[1] as $literal) {
                if ($literal === 'Field' || $literal === 'col') {
                    continue;
                }
                if (strpos($body, "=== '{$literal}'") !== false || strpos($body, "=== \"{$literal}\"") !== false) {
                    $excluded[] = $literal;
                }
            }
        }

        return array_values(array_unique($excluded));
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_view_hidden_fields')) {
    /**
     * Business fields intentionally omitted from $viewColumns loops.
     *
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_view_hidden_fields(
        string $moduleSlug,
        string $indexContent,
        array $listHiddenFilters
    ): array {
        $hidden = itm_crud_view_edit_field_parity_fields_hidden_from_variable($listHiddenFilters, 'viewColumns');

        if (in_array($moduleSlug, itm_fields_missing_status_driven_slugs(), true)) {
            $hidden[] = 'active';
        }

        if (!preg_match('/foreach\s*\(\s*\$viewColumns\s+as/', $indexContent)) {
            return array_values(array_unique($hidden));
        }

        if (preg_match(
            '/\$viewColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$fieldColumns\s*,\s*function\s*\(\s*\$col\s*\)\s*use\s*\(\s*\$hideCompanyIdTables\s*\)\s*\{([^}]+)\}/s',
            $indexContent,
            $viewFilterMatch
        ) === 1) {
            $body = (string) ($viewFilterMatch[1] ?? '');
            if (strpos($body, "'active'") !== false || strpos($body, '"active"') !== false) {
                $hidden[] = 'active';
            }
        }

        return array_values(array_unique($hidden));
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_view_only_fields')) {
    /**
     * System-maintained or alias fields shown on view but not expected on create/edit.
     *
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_view_only_fields(string $indexContent): array
    {
        $fields = ['last_run_at', 'next_run_at', 'ran_at'];

        if (preg_match('/name=["\']enabled["\']/', $indexContent)) {
            $fields[] = 'active';
        }

        return array_values(array_unique($fields));
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_expected_view_fields')) {
    /**
     * Business columns expected on view detail for dynamic scaffold modules.
     *
     * @param list<string> $schemaColumns
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_expected_view_fields(
        string $moduleSlug,
        array $schemaColumns,
        string $indexContent
    ): array {
        $listHiddenFilters = itm_crud_view_edit_field_parity_parse_list_hidden_filters($indexContent);
        $viewHidden = array_fill_keys(
            array_merge(
                itm_crud_view_edit_field_parity_view_hidden_fields($moduleSlug, $indexContent, $listHiddenFilters),
                itm_crud_view_edit_field_parity_view_only_fields($indexContent)
            ),
            true
        );

        $fields = [];
        foreach (itm_fields_missing_ui_fields_for_module($moduleSlug, $schemaColumns) as $field) {
            if (!isset($viewHidden[$field])) {
                $fields[] = $field;
            }
        }

        sort($fields, SORT_STRING);

        return $fields;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_main_form_loop_variable')) {
    /**
     * Detect create/edit foreach loop variable even when modules use custom render helpers.
     */
    function itm_crud_view_edit_field_parity_main_form_loop_variable(string $indexContent): ?string
    {
        $block = itm_fields_missing_extract_create_edit_form_block($indexContent);
        if ($block === null) {
            return null;
        }
        if (preg_match('/foreach\s*\(\s*\$uiColumns\s+as/', $block)) {
            return 'uiColumns';
        }
        if (preg_match('/foreach\s*\(\s*\$formUiColumns\s+as/', $block)) {
            return 'formUiColumns';
        }
        if (preg_match('/foreach\s*\(\s*\$formColumns\s+as/', $block)) {
            return 'formColumns';
        }
        if (preg_match('/foreach\s*\(\s*\$fieldColumns\s+as/', $block)) {
            return 'fieldColumns';
        }

        return null;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_form_covers_field')) {
    /**
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     * @param list<string> $editSectionFields
     * @param list<string> $uiListOnlyHiddenFields
     * @param list<string> $formColumnExcludedFields
     */
    function itm_crud_view_edit_field_parity_form_covers_field(
        string $field,
        array $files,
        array $formPaths,
        string $indexContent,
        array $editSectionFields,
        array $uiListOnlyHiddenFields,
        array $formColumnExcludedFields,
        ?string $formLoopVariable
    ): bool {
        if (in_array($field, $editSectionFields, true)) {
            return true;
        }

        if (itm_fields_missing_form_exposes_literal_visible_field($field, $formPaths)) {
            return true;
        }

        if (in_array($field, $formColumnExcludedFields, true)) {
            return false;
        }

        $mainLoop = itm_crud_view_edit_field_parity_main_form_loop_variable($indexContent);
        if ($mainLoop === null) {
            $mainLoop = $formLoopVariable;
        }

        if ($mainLoop === 'uiColumns' && !in_array($field, $uiListOnlyHiddenFields, true)) {
            return true;
        }

        if ($mainLoop === 'formUiColumns' && !in_array($field, $uiListOnlyHiddenFields, true)) {
            return true;
        }

        if ($mainLoop === 'formColumns' && !in_array($field, $formColumnExcludedFields, true)) {
            return true;
        }

        if ($mainLoop === 'fieldColumns') {
            return true;
        }

        if ($mainLoop === null) {
            $createEditBlock = itm_fields_missing_extract_create_edit_form_block($indexContent) ?? '';
            if ($createEditBlock !== '' && itm_fields_missing_form_exposes_literal_visible_field($field, [$indexContent])) {
                return true;
            }
        }

        if (in_array($field, $uiListOnlyHiddenFields, true)) {
            return false;
        }

        return itm_fields_missing_dynamic_form_exposes_field($field, $formPaths);
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_module_entry_bundle_content')) {
    /**
     * Concatenate module entry files for schema-column reference scans.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     */
    function itm_crud_view_edit_field_parity_module_entry_bundle_content(array $files): string
    {
        $chunks = [];
        foreach (['index', 'create', 'edit', 'view', 'list_all'] as $key) {
            $path = (string) ($files[$key] ?? '');
            if ($path !== '' && is_readable($path)) {
                $content = file_get_contents($path);
                if ($content !== false) {
                    $chunks[] = $content;
                }
            }
        }

        $includesDir = (string) ($files['includes'] ?? '');
        if ($includesDir !== '' && is_dir($includesDir)) {
            foreach (glob($includesDir . '/*.php') ?: [] as $includePath) {
                $content = file_get_contents($includePath);
                if ($content !== false) {
                    $chunks[] = $content;
                }
            }
        }

        return implode("\n", $chunks);
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_reference_scopes')) {
    /**
     * @return list<string>
     */
    function itm_crud_view_edit_field_parity_reference_scopes(): array
    {
        return ['index', 'create', 'edit', 'view', 'list_all', 'includes'];
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_scope_label')) {
    function itm_crud_view_edit_field_parity_scope_label(string $scope): string
    {
        if ($scope === 'includes') {
            return 'includes PHP';
        }

        return $scope;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_read_file_content')) {
    function itm_crud_view_edit_field_parity_read_file_content(string $path): string
    {
        if ($path === '' || !is_readable($path)) {
            return '';
        }
        $content = file_get_contents($path);

        return $content !== false ? (string) $content : '';
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_scope_content')) {
    /**
     * PHP source scanned for quoted column names in one module entry scope.
     *
     * @param array{create:string,edit:string,view:string,index:string,includes:string,list_all:string} $files
     */
    function itm_crud_view_edit_field_parity_scope_content(array $files, string $scope): string
    {
        if ($scope === 'includes') {
            $includesDir = (string) ($files['includes'] ?? '');
            if ($includesDir === '' || !is_dir($includesDir)) {
                return '';
            }
            $chunks = [];
            foreach (glob($includesDir . '/*.php') ?: [] as $includePath) {
                $chunk = itm_crud_view_edit_field_parity_read_file_content((string) $includePath);
                if ($chunk !== '') {
                    $chunks[] = $chunk;
                }
            }

            return implode("\n", $chunks);
        }

        if ($scope === 'index') {
            return itm_crud_view_edit_field_parity_read_file_content((string) ($files['index'] ?? ''));
        }

        if (!in_array($scope, ['create', 'edit', 'view', 'list_all'], true)) {
            return '';
        }

        $entryPath = (string) ($files[$scope] ?? '');
        $chunks = [];
        $entryContent = itm_crud_view_edit_field_parity_read_file_content($entryPath);
        if ($entryContent !== '') {
            $chunks[] = $entryContent;
        }

        $indexPath = (string) ($files['index'] ?? '');
        if ($entryPath !== ''
            && $indexPath !== ''
            && is_readable($entryPath)
            && itm_fields_missing_file_requires_index($entryPath)
        ) {
            $indexContent = itm_crud_view_edit_field_parity_read_file_content($indexPath);
            if ($indexContent !== '') {
                $chunks[] = $indexContent;
            }
        }

        return implode("\n", $chunks);
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_column_quoted_in_content')) {
    function itm_crud_view_edit_field_parity_column_quoted_in_content(string $column, string $content): bool
    {
        if ($column === '' || $content === '') {
            return false;
        }
        $quoted = preg_quote($column, '/');

        return preg_match("/['\"]{$quoted}['\"]/", $content) === 1;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_build_reference_gaps')) {
    /**
     * Per-scope quoted-string gaps for every schema column (no global UI exclusions).
     *
     * @param list<string> $schemaColumns
     * @return list<array{column:string,scope:string}>
     */
    function itm_crud_view_edit_field_parity_build_reference_gaps(array $schemaColumns, array $files): array
    {
        $includesDir = (string) ($files['includes'] ?? '');
        $hasIncludesDir = ($includesDir !== '' && is_dir($includesDir));
        $scopeContentCache = [];
        $gaps = [];

        foreach ($schemaColumns as $column) {
            $column = (string) $column;
            if ($column === '') {
                continue;
            }

            foreach (itm_crud_view_edit_field_parity_reference_scopes() as $scope) {
                if ($scope === 'includes' && !$hasIncludesDir) {
                    continue;
                }

                if (!isset($scopeContentCache[$scope])) {
                    $scopeContentCache[$scope] = itm_crud_view_edit_field_parity_scope_content($files, $scope);
                }

                if (itm_crud_view_edit_field_parity_column_quoted_in_content($column, $scopeContentCache[$scope])) {
                    continue;
                }

                $gaps[] = [
                    'column' => $column,
                    'scope' => $scope,
                ];
            }
        }

        return $gaps;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_module_folder_local_url')) {
    function itm_crud_view_edit_field_parity_module_folder_local_url(string $moduleSlug): string
    {
        $moduleSlug = trim($moduleSlug);
        if ($moduleSlug === '') {
            return '';
        }
        if (!function_exists('itm_script_modules_repo_path_to_local_url')) {
            require_once __DIR__ . '/script_browser_nav.php';
        }

        return itm_script_modules_repo_path_to_local_url('modules/' . $moduleSlug . '/');
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_format_module_ref')) {
    function itm_crud_view_edit_field_parity_format_module_ref(string $moduleSlug): string
    {
        $moduleSlug = trim($moduleSlug);
        if ($moduleSlug === '') {
            return '';
        }
        if (!function_exists('itm_script_external_link_html')) {
            require_once __DIR__ . '/script_browser_nav.php';
        }

        if (function_exists('itm_script_is_cli_sapi') && itm_script_is_cli_sapi()) {
            $url = itm_crud_view_edit_field_parity_module_folder_local_url($moduleSlug);
            if ($url !== '' && substr($url, -1) !== '/') {
                $url .= '/';
            }

            return $url;
        }

        $href = '../modules/' . rawurlencode($moduleSlug) . '/';

        return itm_script_external_link_html($href, $moduleSlug);
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_format_reference_gap_line')) {
    /**
     * Tag-free human line: {moduleRef}.{column}: schema column not referenced in {scopeLabel}
     */
    function itm_crud_view_edit_field_parity_format_reference_gap_line(
        string $moduleSlug,
        string $column,
        string $scope
    ): string {
        $moduleRef = itm_crud_view_edit_field_parity_format_module_ref($moduleSlug);
        $scopeLabel = itm_crud_view_edit_field_parity_scope_label($scope);

        return $moduleRef . '.' . $column . ': schema column not referenced in ' . $scopeLabel;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_audit_module')) {
    /**
     * @param list<string> $schemaColumns
     * @return array{
     *   module:string,
     *   table:string,
     *   skipped:bool,
     *   skip_reason:string,
     *   failures:list<array{code:string,field:string,message:string}>,
     *   passes:list<string>,
     *   notes:list<string>,
     *   reference_gaps:list<array{column:string,scope:string}>
     * }
     */
    function itm_crud_view_edit_field_parity_audit_module(
        string $moduleSlug,
        string $table,
        array $schemaColumns,
        ?string $rootPath = null
    ): array {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        require_once __DIR__ . '/itm_crud_tables_audit.php';
        $bespokeModules = array_fill_keys(itm_crud_tables_load_skip_module_slugs($rootPath), true);

        $result = [
            'module' => $moduleSlug,
            'table' => $table,
            'skipped' => false,
            'skip_reason' => '',
            'failures' => [],
            'passes' => [],
            'notes' => [],
            'reference_gaps' => [],
        ];

        if (isset($bespokeModules[$moduleSlug])) {
            $result['skipped'] = true;
            $result['skip_reason'] = 'bespoke/deferred UI (docs/list_bespoke_UI.txt)';

            return $result;
        }

        $files = itm_fields_missing_module_file_bundle($moduleSlug, $rootPath);
        if (!is_readable($files['index'])) {
            $result['skipped'] = true;
            $result['skip_reason'] = 'missing index.php';

            return $result;
        }

        $indexContent = (string) file_get_contents($files['index']);
        if (!itm_fields_missing_index_is_dynamic_scaffold($files['index'])
            && $moduleSlug !== 'employees'
        ) {
            $result['skipped'] = true;
            $result['skip_reason'] = 'non-dynamic scaffold module';

            return $result;
        }

        if (!preg_match('/foreach\s*\(\s*\$viewColumns\s+as/', $indexContent)) {
            $result['skipped'] = true;
            $result['skip_reason'] = 'no $viewColumns detail loop';

            return $result;
        }

        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        if (!in_array($files['index'], $formPaths, true) && is_readable($files['index'])) {
            $formPaths[] = $files['index'];
        }
        $listHiddenFilters = itm_crud_view_edit_field_parity_parse_list_hidden_filters($indexContent);
        $uiListOnlyHidden = itm_crud_view_edit_field_parity_ui_list_only_hidden_fields($listHiddenFilters);
        $editSectionFields = itm_crud_view_edit_field_parity_collect_edit_form_section_fields($files);
        $formLoopVariable = itm_fields_missing_create_edit_form_loop_variable($indexContent);
        $formColumnExcluded = itm_crud_view_edit_field_parity_form_columns_excluded_fields($indexContent);

        if ($uiListOnlyHidden !== []) {
            $result['notes'][] = $moduleSlug . ': list-hidden from $uiColumns only: ' . implode(', ', $uiListOnlyHidden);
            if ($editSectionFields === [] && itm_crud_view_edit_field_parity_index_references_edit_form_sections($indexContent)) {
                $result['failures'][] = [
                    'code' => 'edit_sections_empty',
                    'field' => '',
                    'message' => "{$moduleSlug}: \$*FormEditSections referenced but no *_edit_form_sections() fields parsed under includes/",
                ];
            }
        }

        $expectedViewFields = itm_crud_view_edit_field_parity_expected_view_fields(
            $moduleSlug,
            $schemaColumns,
            $indexContent
        );

        foreach ($expectedViewFields as $field) {
            if (function_exists('itm_crud_is_form_hidden_audit_field')
                && itm_crud_is_form_hidden_audit_field($field)
            ) {
                continue;
            }
            if (function_exists('itm_crud_is_view_audit_field')
                && itm_crud_is_view_audit_field($field)
            ) {
                continue;
            }

            $formOk = itm_crud_view_edit_field_parity_form_covers_field(
                $field,
                $files,
                $formPaths,
                $indexContent,
                $editSectionFields,
                $uiListOnlyHidden,
                $formColumnExcluded,
                $formLoopVariable
            );

            if (!$formOk) {
                $result['failures'][] = [
                    'code' => 'view_edit_parity',
                    'field' => $field,
                    'message' => "{$moduleSlug}.{$field}: on view/detail but missing on create/edit forms",
                ];
                continue;
            }

            $result['passes'][] = "{$moduleSlug}.{$field}: view/edit parity OK";
        }

        $result['reference_gaps'] = itm_crud_view_edit_field_parity_build_reference_gaps($schemaColumns, $files);

        return $result;
    }
}

if (!function_exists('itm_crud_view_edit_field_parity_collect_report')) {
    /**
     * @return array{
     *   modules:list<array<string,mixed>>,
     *   failure_count:int,
     *   pass_count:int,
     *   reference_gap_count:int,
     *   skipped_count:int
     * }
     */
    function itm_crud_view_edit_field_parity_collect_report(?string $moduleFilter = null, ?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $schemaMap = itm_fields_missing_parse_database_sql_table_columns($rootPath);
        $targets = itm_fields_missing_discover_module_targets($rootPath);
        if ($moduleFilter !== null && $moduleFilter !== '') {
            $targets = array_values(array_filter($targets, static function (array $target) use ($moduleFilter): bool {
                return ($target['module'] ?? '') === $moduleFilter;
            }));
        }

        $modules = [];
        $failureCount = 0;
        $passCount = 0;
        $referenceGapCount = 0;
        $skippedCount = 0;

        foreach ($targets as $target) {
            $moduleSlug = (string) ($target['module'] ?? '');
            $table = (string) ($target['table'] ?? '');
            $schemaColumns = $schemaMap[$table] ?? [];
            $moduleReport = itm_crud_view_edit_field_parity_audit_module(
                $moduleSlug,
                $table,
                $schemaColumns,
                $rootPath
            );
            $modules[] = $moduleReport;
            if (!empty($moduleReport['skipped'])) {
                $skippedCount++;
                continue;
            }
            $failureCount += count((array) ($moduleReport['failures'] ?? []));
            $passCount += count((array) ($moduleReport['passes'] ?? []));
            $referenceGapCount += count((array) ($moduleReport['reference_gaps'] ?? []));
        }

        return [
            'modules' => $modules,
            'failure_count' => $failureCount,
            'pass_count' => $passCount,
            'reference_gap_count' => $referenceGapCount,
            'skipped_count' => $skippedCount,
        ];
    }
}
