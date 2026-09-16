<?php
/**
 * Static audit: list/view FK columns must resolve to human-readable labels in cr_render_cell_value().
 *
 * Why: Scaffold modules often build $fkMap for forms/search but omit the shared
 * $GLOBALS['fkMap'][$field] branch in cr_render_cell_value(), so list cells show raw IDs
 * (e.g. problem_ticket_links problem_id instead of problems.title).
 */

require_once __DIR__ . '/itm_crud_boolean_cell_display_audit.php';

if (!function_exists('itm_raw_fk_column_audit_label_column_candidates')) {
    /**
     * @return list<string>
     */
    function itm_raw_fk_column_audit_label_column_candidates(): array
    {
        return [
            'name_type',
            'name',
            'title',
            'username',
            'account_name',
            'account_code',
            'code',
            'stage',
            'status',
            'approver_type_description',
            'description',
            'email',
            'mode_name',
            'display_name',
        ];
    }
}

if (!function_exists('itm_raw_fk_column_audit_schema_path')) {
    function itm_raw_fk_column_audit_schema_path(string $repoRoot): string
    {
        if (function_exists('itm_crud_boolean_cell_audit_schema_path')) {
            return itm_crud_boolean_cell_audit_schema_path($repoRoot);
        }

        return rtrim($repoRoot, '/\\') . '/db/01_schema.sql';
    }
}

if (!function_exists('itm_raw_fk_column_audit_parse_schema_table_columns')) {
    /**
     * @return array<string, list<string>>
     */
    function itm_raw_fk_column_audit_parse_schema_table_columns(string $schemaPath): array
    {
        static $cache = [];

        if (isset($cache[$schemaPath])) {
            return $cache[$schemaPath];
        }

        $cache[$schemaPath] = [];

        if (!is_readable($schemaPath)) {
            return $cache[$schemaPath];
        }

        $sql = (string) file_get_contents($schemaPath);
        if (!preg_match_all('/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is', $sql, $matches, PREG_SET_ORDER)) {
            return $cache[$schemaPath];
        }

        foreach ($matches as $match) {
            $tableName = (string) $match[1];
            $body = (string) $match[2];
            $columns = [];

            foreach (preg_split('/\R/', $body) ?: [] as $line) {
                $trimmed = trim((string) $line);
                if (preg_match('/^`([a-zA-Z0-9_]+)`\s+/i', $trimmed, $colMatch)) {
                    $columns[] = (string) $colMatch[1];
                }
            }

            $cache[$schemaPath][$tableName] = array_values(array_unique($columns));
        }

        return $cache[$schemaPath];
    }
}

if (!function_exists('itm_raw_fk_column_audit_resolve_label_column')) {
    /**
     * @param list<string> $columns
     */
    function itm_raw_fk_column_audit_resolve_label_column(array $columns): string
    {
        foreach (itm_raw_fk_column_audit_label_column_candidates() as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('itm_raw_fk_column_audit_parse_schema_outbound_fks')) {
    /**
     * @return array<string, array<string, array{ref_table:string,ref_column:string,label_col:string}>>
     */
    function itm_raw_fk_column_audit_parse_schema_outbound_fks(string $schemaPath): array
    {
        static $cache = [];

        if (isset($cache[$schemaPath])) {
            return $cache[$schemaPath];
        }

        $cache[$schemaPath] = [];
        $tableColumns = itm_raw_fk_column_audit_parse_schema_table_columns($schemaPath);

        if (!is_readable($schemaPath)) {
            return $cache[$schemaPath];
        }

        $sql = (string) file_get_contents($schemaPath);
        if (!preg_match_all('/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is', $sql, $matches, PREG_SET_ORDER)) {
            return $cache[$schemaPath];
        }

        foreach ($matches as $match) {
            $tableName = (string) $match[1];
            $body = (string) $match[2];

            if (!preg_match_all(
                '/FOREIGN\s+KEY\s*\(\s*`([a-zA-Z0-9_]+)`\s*\)\s*REFERENCES\s+`([a-zA-Z0-9_]+)`\s*\(\s*`([a-zA-Z0-9_]+)`\s*\)/i',
                $body,
                $fkMatches,
                PREG_SET_ORDER
            )) {
                continue;
            }

            foreach ($fkMatches as $fkMatch) {
                $column = (string) $fkMatch[1];
                $refTable = (string) $fkMatch[2];
                $refColumn = (string) $fkMatch[3];
                $refColumns = $tableColumns[$refTable] ?? [];
                $labelCol = itm_raw_fk_column_audit_resolve_label_column($refColumns);

                if ($labelCol === '') {
                    continue;
                }

                $cache[$schemaPath][$tableName][$column] = [
                    'ref_table' => $refTable,
                    'ref_column' => $refColumn,
                    'label_col' => $labelCol,
                ];
            }
        }

        return $cache[$schemaPath];
    }
}

if (!function_exists('itm_raw_fk_column_audit_status_driven_slugs')) {
    /**
     * @return list<string>
     */
    function itm_raw_fk_column_audit_status_driven_slugs(): array
    {
        if (function_exists('itm_crud_boolean_cell_audit_status_driven_slugs')) {
            return itm_crud_boolean_cell_audit_status_driven_slugs();
        }

        return ['employees', 'equipment', 'patches_updates', 'tickets'];
    }
}

if (!function_exists('itm_raw_fk_column_audit_parse_list_excluded_fields')) {
    /**
     * Fields removed from list columns via vaultHiddenFields, listColumns filters, or cr_is_hidden_employee_field.
     *
     * @return list<string>
     */
    function itm_raw_fk_column_audit_parse_list_excluded_fields(string $indexContent): array
    {
        $excluded = [];

        if (preg_match('/\$vaultHiddenFields\s*=\s*\[([^\]]+)\]/', $indexContent, $match)) {
            if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', (string) $match[1], $fieldMatches)) {
                $excluded = array_merge($excluded, $fieldMatches[1]);
            }
        }

        if (preg_match_all('/!\s*in_array\s*\(\s*\$field\s*,\s*\[([^\]]+)\]/', $indexContent, $matches)) {
            foreach ($matches[1] as $arrayLiteral) {
                if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', (string) $arrayLiteral, $fieldMatches)) {
                    $excluded = array_merge($excluded, $fieldMatches[1]);
                }
            }
        }

        if (preg_match('/function\s+cr_is_hidden_employee_field\s*\([^)]*\)\s*\{[\s\S]*?\$hidden\s*=\s*\[([^\]]+)\]/', $indexContent, $match)
            && preg_match('/!\s*cr_is_hidden_employee_field\s*\(/', $indexContent) === 1) {
            if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', (string) $match[1], $fieldMatches)) {
                $excluded = array_merge($excluded, $fieldMatches[1]);
            }
        }

        return array_values(array_unique($excluded));
    }
}

if (!function_exists('itm_raw_fk_column_audit_is_field_hidden_from_list')) {
    function itm_raw_fk_column_audit_is_field_hidden_from_list(
        string $field,
        string $moduleSlug,
        array $hiddenByModule,
        array $listExcludedFields = []
    ): bool {
        if (in_array($field, $hiddenByModule, true)) {
            return true;
        }

        if (in_array($field, $listExcludedFields, true)) {
            return true;
        }

        if ($field === 'company_id') {
            return true;
        }

        if ($field === 'active' && in_array($moduleSlug, itm_raw_fk_column_audit_status_driven_slugs(), true)) {
            return true;
        }

        if (function_exists('itm_crud_is_list_hidden_audit_field')
            && itm_crud_is_list_hidden_audit_field($field)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_raw_fk_column_audit_render_has_global_fkmap_handler')) {
    function itm_raw_fk_column_audit_render_has_global_fkmap_handler(string $renderBody): bool
    {
        if ($renderBody === '') {
            return false;
        }

        if (preg_match('/\$GLOBALS\s*\[\s*[\'"]fkMap[\'"]\s*\]\s*\[\s*\$field\s*\]/', $renderBody) === 1) {
            return true;
        }

        if (preg_match('/isset\s*\(\s*\$GLOBALS\s*\[\s*[\'"]fkMap[\'"]\s*\]\s*\[\s*\$field\s*\]\s*\)/', $renderBody) === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_raw_fk_column_audit_field_has_audit_actor_rendering')) {
    function itm_raw_fk_column_audit_field_has_audit_actor_rendering(string $renderBody, string $field): bool
    {
        if (!in_array($field, ['created_by', 'updated_by', 'deleted_by'], true)) {
            return false;
        }

        return strpos($renderBody, 'itm_crud_render_audit_cell_value') !== false;
    }
}

if (!function_exists('itm_raw_fk_column_audit_render_accepts_row_param')) {
    function itm_raw_fk_column_audit_render_accepts_row_param(string $renderBody): bool
    {
        return $renderBody !== ''
            && preg_match('/function\s+cr_render_cell_value\s*\([^)]*\$row\b/', $renderBody) === 1;
    }
}

if (!function_exists('itm_raw_fk_column_audit_index_passes_row_to_render')) {
    function itm_raw_fk_column_audit_index_passes_row_to_render(string $indexContent): bool
    {
        return $indexContent !== ''
            && preg_match('/cr_render_cell_value\s*\([^;]+\$row\s*\)/', $indexContent) === 1;
    }
}

if (!function_exists('itm_raw_fk_column_audit_field_has_join_row_label_rendering')) {
    /**
     * Bespoke modules (e.g. alerts) JOIN label tables in the list query and pass $row into
     * cr_render_cell_value() — category_name, first_name, etc. instead of fkMap resolution.
     *
     * @param array{ref_table:string,ref_column:string,label_col:string} $meta
     */
    function itm_raw_fk_column_audit_field_has_join_row_label_rendering(
        string $renderBody,
        string $indexContent,
        string $field,
        string $table,
        array $meta
    ): bool {
        $refTable = (string) ($meta['ref_table'] ?? '');
        $labelCol = (string) ($meta['label_col'] ?? '');
        if ($renderBody === '' || $indexContent === '' || $field === '' || $refTable === '') {
            return false;
        }

        if (!itm_raw_fk_column_audit_render_accepts_row_param($renderBody)) {
            return false;
        }

        if (!itm_raw_fk_column_audit_index_passes_row_to_render($indexContent)) {
            return false;
        }

        $refTableQuoted = preg_quote($refTable, '/');
        if (!preg_match('/\bJOIN\s+`?' . $refTableQuoted . '`?\b/i', $indexContent)) {
            return false;
        }

        $fieldQuoted = preg_quote($field, '/');
        $tableQuoted = preg_quote($table, '/');
        $fieldBranchPattern = '/(?:'
            . '\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"]'
            . '|in_array\s*\(\s*\$field\s*,\s*\[[^\]]*[\'"]' . $fieldQuoted . '[\'"][^\]]*\]'
            . '|\$table\s*===\s*[\'"]' . $tableQuoted . '[\'"][\s\S]{0,400}?\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"]'
            . ')/';

        if (preg_match($fieldBranchPattern, $renderBody, $fieldMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return false;
        }

        $window = substr($renderBody, (int) $fieldMatch[0][1], 2000);
        $rowLabelPatterns = [
            '/\$row\s*\[\s*[\'"][^\'"]*' . preg_quote('_' . $labelCol, '/') . '[\'"]\s*\]/',
            '/isset\s*\(\s*\$row\s*\[\s*[\'"][^\'"]+[\'"]\s*\]\s*\)/',
            '/\$row\s*\[\s*\$prefix\s*\.\s*[\'"]first_name[\'"]\s*\]/',
            '/\$row\s*\[\s*[\'"]first_name[\'"]\s*\]/',
            '/\$row\s*\[\s*[\'"]last_name[\'"]\s*\]/',
            '/\$row\s*\[\s*[\'"]username[\'"]\s*\]/',
            '/\$row\s*\[\s*[\'"]display_name[\'"]\s*\]/',
            '/\$row\s*\[\s*[\'"]full_name[\'"]\s*\]/',
        ];

        foreach ($rowLabelPatterns as $pattern) {
            if (preg_match($pattern, $window) === 1) {
                return true;
            }
        }

        $base = preg_replace('/_id$/', '', $field);
        if ($base !== '' && $base !== $field) {
            if (preg_match('/\$row\s*\[\s*[\'"]' . preg_quote($base, '/') . '_/', $window) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_raw_fk_column_audit_field_has_inline_sql_label_rendering')) {
    /**
     * @param array{ref_table:string,ref_column:string,label_col:string} $meta
     */
    function itm_raw_fk_column_audit_field_has_inline_sql_label_rendering(
        string $renderBody,
        string $field,
        string $table,
        array $meta
    ): bool {
        $refTable = (string) ($meta['ref_table'] ?? '');
        $labelCol = (string) ($meta['label_col'] ?? '');
        if ($renderBody === '' || $field === '' || $refTable === '' || $labelCol === '') {
            return false;
        }

        $fieldQuoted = preg_quote($field, '/');
        $tableQuoted = preg_quote($table, '/');
        $refQuoted = preg_quote($refTable, '/');
        $labelQuoted = preg_quote($labelCol, '/');

        $hasFieldBranch = preg_match(
            '/\$table\s*===\s*[\'"]' . $tableQuoted . '[\'"][\s\S]{0,260}?in_array\s*\(\s*\$field\s*,\s*\[[^\]]*[\'"]' . $fieldQuoted . '[\'"]/s',
            $renderBody
        ) === 1
            || preg_match('/\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"]/', $renderBody) === 1;

        if (!$hasFieldBranch) {
            return false;
        }

        return preg_match('/mysqli_query\s*\(\s*\$conn/', $renderBody) === 1
            && preg_match('/FROM\s+`?' . $refQuoted . '`?\b/i', $renderBody) === 1
            && preg_match('/\b' . $labelQuoted . '\b/', $renderBody) === 1;
    }
}

if (!function_exists('itm_raw_fk_column_audit_index_has_inline_fkmap_label')) {
    function itm_raw_fk_column_audit_index_has_inline_fkmap_label(string $indexContent, string $field): bool
    {
        if ($indexContent === '' || $field === '') {
            return false;
        }

        $fieldQuoted = preg_quote($field, '/');
        $resolverAlt = itm_raw_fk_column_audit_bespoke_label_resolver_alternation();

        $patterns = [
            '/isset\s*\(\s*\$fkMap\s*\[\s*\$f\s*\]\s*\)[\s\S]{0,500}?(' . $resolverAlt . ')\s*\(\s*\$conn\s*,\s*\$fkMap\s*\[\s*\$f\s*\]/s',
            '/isset\s*\(\s*\$fkMap\s*\[\s*[\'"]' . $fieldQuoted . '[\'"]\s*\][\s\S]{0,500}?(' . $resolverAlt . ')/s',
            '/\$f\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,500}?isset\s*\(\s*\$fkMap/s',
            '/\$name\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,500}?isset\s*\(\s*\$fkMap\s*\[\s*\$name\s*\]/s',
            '/\$f\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,500}?(' . $resolverAlt . ')/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $indexContent) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_raw_fk_column_audit_bespoke_label_resolver_alternation')) {
    function itm_raw_fk_column_audit_bespoke_label_resolver_alternation(): string
    {
        return implode('|', [
            'cr_fk_label_by_id',
            'itm_fk_label_by_id',
            'itm_user_label_by_id_for_company',
            'cr_user_label_by_id',
            'cr_username_for_employee_id',
            'itm_fk_label_column_for_table',
        ]);
    }
}

if (!function_exists('itm_raw_fk_column_audit_field_has_bespoke_label_rendering')) {
    function itm_raw_fk_column_audit_field_has_bespoke_label_rendering(
        string $renderBody,
        string $field,
        string $table,
        string $indexContent = '',
        array $meta = []
    ): bool {
        if ($renderBody === '' || $field === '') {
            return false;
        }

        if ($indexContent !== '' && $meta !== []
            && itm_raw_fk_column_audit_field_has_join_row_label_rendering($renderBody, $indexContent, $field, $table, $meta)) {
            return true;
        }

        $fieldQuoted = preg_quote($field, '/');
        $tableQuoted = preg_quote($table, '/');
        $resolverAlt = itm_raw_fk_column_audit_bespoke_label_resolver_alternation();

        $patterns = [
            '/\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,900}?(' . $resolverAlt . ')/s',
            '/\$table\s*===\s*[\'"]' . $tableQuoted . '[\'"][\s\S]{0,220}?\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,900}?(' . $resolverAlt . ')/s',
            '/in_array\s*\(\s*\$field\s*,\s*\[[^\]]*[\'"]' . $fieldQuoted . '[\'"][^\]]*\][\s\S]{0,900}?(' . $resolverAlt . ')/s',
            '/in_array\s*\(\s*\$table\s*,\s*\[[^\]]*\]\s*,\s*true\s*\)\s*&&\s*\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,900}?(' . $resolverAlt . ')/s',
            '/[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,120}?\][\s\S]{0,900}?(' . $resolverAlt . ')/s',
            '/\$row\s*\[\s*[\'"]' . $fieldQuoted . '[\'"]\s*\][\s\S]{0,900}?(' . $resolverAlt . ')/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $renderBody) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_raw_fk_column_audit_resolve_handler_label')) {
    function itm_raw_fk_column_audit_resolve_handler_label(
        string $renderBody,
        string $indexContent,
        string $field,
        string $table,
        array $meta,
        bool $hasGlobalFkMap
    ): array {
        if ($hasGlobalFkMap) {
            return ['handler' => 'fkMap', 'notes' => 'Shared $GLOBALS[\'fkMap\'] branch'];
        }

        if (itm_raw_fk_column_audit_field_has_join_row_label_rendering($renderBody, $indexContent, $field, $table, $meta)) {
            return ['handler' => 'join_row', 'notes' => 'JOIN + $row bespoke label branch'];
        }

        if (itm_raw_fk_column_audit_index_has_inline_fkmap_label($indexContent, $field)) {
            return ['handler' => 'list_fkmap', 'notes' => 'List template isset($fkMap[$f]) + label resolver'];
        }

        if (itm_raw_fk_column_audit_field_has_inline_sql_label_rendering($renderBody, $field, $table, $meta)) {
            return ['handler' => 'inline_sql', 'notes' => 'Inline SQL lookup in cr_render_cell_value()'];
        }

        return ['handler' => 'bespoke', 'notes' => 'Bespoke FK label branch'];
    }
}

if (!function_exists('itm_raw_fk_column_audit_field_has_label_rendering')) {
    /**
     * @param array{ref_table:string,ref_column:string,label_col:string} $meta
     */
    function itm_raw_fk_column_audit_field_has_label_rendering(
        string $renderBody,
        string $field,
        string $table,
        string $indexContent = '',
        array $meta = []
    ): bool {
        if (itm_raw_fk_column_audit_render_has_global_fkmap_handler($renderBody)) {
            return true;
        }

        if (itm_raw_fk_column_audit_field_has_audit_actor_rendering($renderBody, $field)) {
            return true;
        }

        if ($indexContent !== ''
            && itm_raw_fk_column_audit_index_has_inline_fkmap_label($indexContent, $field)) {
            return true;
        }

        if (itm_raw_fk_column_audit_field_has_inline_sql_label_rendering($renderBody, $field, $table, $meta)) {
            return true;
        }

        return itm_raw_fk_column_audit_field_has_bespoke_label_rendering(
            $renderBody,
            $field,
            $table,
            $indexContent,
            $meta
        );
    }
}

if (!function_exists('itm_raw_fk_column_audit_probe_bootstrap')) {
    function itm_raw_fk_column_audit_probe_bootstrap(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;

        if (!function_exists('sanitize')) {
            function sanitize($data)
            {
                return htmlspecialchars((string) $data, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('itm_format_cell_scalar_display')) {
            function itm_format_cell_scalar_display($field, $value)
            {
                return (string) $value;
            }
        }
        if (!function_exists('itm_crud_render_audit_cell_value')) {
            function itm_crud_render_audit_cell_value($conn, $companyId, $field, $value)
            {
                return null;
            }
        }
        if (!function_exists('cr_fk_label_by_id')) {
            function cr_fk_label_by_id($conn, $fk, $company_id, $rawId)
            {
                if (function_exists('itm_fk_label_by_id')) {
                    return itm_fk_label_by_id($conn, $fk, (int) $company_id, (int) $rawId);
                }

                return '';
            }
        }
    }
}

if (!function_exists('itm_raw_fk_column_audit_probe_render_html')) {
    function itm_raw_fk_column_audit_probe_render_html(
        string $functionBody,
        string $crudTable,
        string $field,
        $value
    ): string {
        $functionBody = (string) $functionBody;
        $crudTable = trim($crudTable);
        $field = trim($field);
        if ($functionBody === '' || $crudTable === '' || $field === '') {
            return '';
        }

        itm_raw_fk_column_audit_probe_bootstrap();

        $GLOBALS['crud_table'] = $crudTable;
        $GLOBALS['conn'] = $GLOBALS['conn'] ?? null;
        $GLOBALS['company_id'] = (int) ($GLOBALS['company_id'] ?? 0);
        $GLOBALS['fkMap'] = is_array($GLOBALS['fkMap'] ?? null) ? $GLOBALS['fkMap'] : [];

        $probeName = 'itm_raw_fk_column_probe_' . substr(md5($functionBody), 0, 12);
        if (!function_exists($probeName)) {
            eval('function ' . $probeName . '($table, $field, $value) {
                $conn = $GLOBALS["conn"] ?? null;
                $company_id = $GLOBALS["company_id"] ?? 0;
                $companyId = (int)$company_id;
            ' . $functionBody . "\n}");
        }

        $prevLevel = error_reporting(E_ERROR | E_PARSE);
        try {
            return (string) $probeName($crudTable, $field, $value);
        } catch (Throwable $e) {
            return '';
        } finally {
            error_reporting($prevLevel);
        }
    }
}

if (!function_exists('itm_raw_fk_column_audit_live_repro_is_raw_numeric')) {
    function itm_raw_fk_column_audit_live_repro_is_raw_numeric(string $html, $rawValue): bool
    {
        $raw = trim((string) $rawValue);
        if ($raw === '' || !ctype_digit($raw)) {
            return false;
        }

        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return false;
        }

        return $text === $raw;
    }
}

if (!function_exists('itm_raw_fk_column_audit_module_slugs')) {
    /**
     * @return list<string>
     */
    function itm_raw_fk_column_audit_module_slugs(string $root): array
    {
        $root = rtrim($root, '/\\');
        $modulesRoot = $root . '/modules';
        if (!is_dir($modulesRoot)) {
            return [];
        }

        $slugs = [];
        foreach (scandir($modulesRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_file($modulesRoot . '/' . $entry . '/index.php')) {
                $slugs[] = $entry;
            }
        }
        sort($slugs);

        return $slugs;
    }
}

if (!function_exists('itm_raw_fk_column_audit_analyze_index')) {
    /**
     * @param array<string, array<string, array{ref_table:string,ref_column:string,label_col:string}>> $schemaFks
     * @return list<array<string, mixed>>
     */
    function itm_raw_fk_column_audit_analyze_index(
        string $repoRoot,
        string $slug,
        array $schemaFks,
        ?mysqli $conn = null,
        int $companyId = 0
    ): array {
        require_once dirname(__DIR__, 2) . '/includes/itm_crud_audit_fields.php';

        $indexPath = rtrim(str_replace('\\', '/', $repoRoot), '/') . '/modules/' . $slug . '/index.php';
        if (!is_readable($indexPath)) {
            return [];
        }

        $content = (string) file_get_contents($indexPath);
        $table = itm_crud_boolean_cell_audit_extract_crud_table($content);
        if ($table === '' || !isset($schemaFks[$table]) || $schemaFks[$table] === []) {
            return [];
        }

        if (strpos($content, 'function cr_render_cell_value') === false) {
            return [[
                'status' => 'skip',
                'slug' => $slug,
                'table' => $table,
                'column' => '',
                'ref_table' => '',
                'label_col' => '',
                'handler' => 'none',
                'repro' => false,
                'notes' => 'No cr_render_cell_value() — bespoke list renderer',
            ]];
        }

        $renderBody = itm_crud_boolean_cell_audit_extract_function_body($content, 'cr_render_cell_value');
        if ($renderBody === '') {
            return [[
                'status' => 'skip',
                'slug' => $slug,
                'table' => $table,
                'column' => '',
                'ref_table' => '',
                'label_col' => '',
                'handler' => 'none',
                'repro' => false,
                'notes' => 'Could not parse cr_render_cell_value() body',
            ]];
        }

        $hiddenByModule = itm_crud_boolean_cell_audit_parse_hidden_field_names($content, $table);
        $listExcludedFields = itm_raw_fk_column_audit_parse_list_excluded_fields($content);
        $hasGlobalFkMap = itm_raw_fk_column_audit_render_has_global_fkmap_handler($renderBody);

        $fkMap = [];
        if ($conn instanceof mysqli && function_exists('itm_table_outbound_fk_map')) {
            $fkMap = itm_table_outbound_fk_map($conn, $table);
        }

        $rows = [];
        foreach ($schemaFks[$table] as $column => $meta) {
            if (itm_raw_fk_column_audit_is_field_hidden_from_list($column, $slug, $hiddenByModule, $listExcludedFields)) {
                continue;
            }

            $resolved = itm_raw_fk_column_audit_field_has_label_rendering(
                $renderBody,
                $column,
                $table,
                $content,
                $meta
            );
            $status = $resolved ? 'ok' : 'raw';
            $repro = false;
            if ($resolved) {
                $handlerInfo = itm_raw_fk_column_audit_resolve_handler_label(
                    $renderBody,
                    $content,
                    $column,
                    $table,
                    $meta,
                    $hasGlobalFkMap
                );
                $handler = (string) ($handlerInfo['handler'] ?? 'bespoke');
                $notes = (string) ($handlerInfo['notes'] ?? 'Bespoke FK label branch');
            } else {
                $handler = 'none';
                $notes = 'List/view falls through to raw FK id';
            }

            if ($status === 'raw' && $conn instanceof mysqli && $companyId > 0 && itm_is_safe_identifier($table) && itm_is_safe_identifier($column)) {
                $sql = 'SELECT `' . $column . '` AS fk_val FROM `' . $table . '` WHERE `company_id` = ? AND `'
                    . $column . '` IS NOT NULL AND `' . $column . '` > 0';
                if (function_exists('itm_crud_append_not_deleted_predicate')) {
                    $sql .= itm_crud_append_not_deleted_predicate($table);
                }
                $sql .= ' ORDER BY `id` DESC LIMIT 1';

                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $companyId);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $sampleRow = $result ? mysqli_fetch_assoc($result) : null;
                    mysqli_stmt_close($stmt);

                    if ($sampleRow && isset($sampleRow['fk_val'])) {
                        $GLOBALS['conn'] = $conn;
                        $GLOBALS['company_id'] = $companyId;
                        $GLOBALS['fkMap'] = $fkMap;
                        $html = itm_raw_fk_column_audit_probe_render_html(
                            $renderBody,
                            $table,
                            $column,
                            $sampleRow['fk_val']
                        );
                        if (itm_raw_fk_column_audit_live_repro_is_raw_numeric($html, $sampleRow['fk_val'])) {
                            $repro = true;
                            $status = 'repro';
                            $notes = 'Live render shows raw id ' . (string) $sampleRow['fk_val'];
                        }
                    }
                }
            }

            $rows[] = [
                'status' => $status,
                'slug' => $slug,
                'table' => $table,
                'column' => $column,
                'ref_table' => (string) ($meta['ref_table'] ?? ''),
                'label_col' => (string) ($meta['label_col'] ?? ''),
                'handler' => $handler,
                'repro' => $repro,
                'notes' => $notes,
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_raw_fk_column_audit_run')) {
    /**
     * @param array{
     *   root:string,
     *   only_raw?:bool,
     *   only_repro?:bool,
     *   all?:bool,
     *   conn?:mysqli|null,
     *   company_id?:int
     * } $options
     * @return list<array<string, mixed>>
     */
    function itm_raw_fk_column_audit_run(array $options): array
    {
        $root = rtrim((string) ($options['root'] ?? ''), '/\\');
        $onlyRaw = !empty($options['only_raw']);
        $onlyRepro = !empty($options['only_repro']);
        $showAll = !empty($options['all']);
        $conn = $options['conn'] ?? null;
        $companyId = (int) ($options['company_id'] ?? 0);

        $schemaPath = itm_raw_fk_column_audit_schema_path($root);
        $schemaFks = itm_raw_fk_column_audit_parse_schema_outbound_fks($schemaPath);

        $slugs = itm_raw_fk_column_audit_module_slugs($root);

        $rows = [];
        foreach ($slugs as $slug) {
            $moduleRows = itm_raw_fk_column_audit_analyze_index(
                $root,
                $slug,
                $schemaFks,
                $conn instanceof mysqli ? $conn : null,
                $companyId
            );
            foreach ($moduleRows as $row) {
                $status = (string) ($row['status'] ?? 'ok');
                if ($onlyRepro && $status !== 'repro') {
                    continue;
                }
                if ($onlyRaw && !in_array($status, ['raw', 'repro'], true)) {
                    continue;
                }
                if ($showAll || in_array($status, ['raw', 'repro', 'skip'], true)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }
}
