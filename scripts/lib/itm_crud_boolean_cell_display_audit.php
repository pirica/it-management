<?php
/**
 * Static audit: list/view tinyint(1) columns must not fall through to raw 0/1 text.
 *
 * Why: Scaffold CRUD uses badges for active and ✅/❌ (shared helper or bespoke branch) for other checkbox columns.
 */

if (!function_exists('itm_crud_boolean_cell_audit_repo_relative_path')) {
    function itm_crud_boolean_cell_audit_repo_relative_path(string $repoRoot, string $absolutePath): string
    {
        $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
        $absolutePath = str_replace('\\', '/', $absolutePath);

        if (strpos($absolutePath, $repoRoot . '/') === 0) {
            return substr($absolutePath, strlen($repoRoot) + 1);
        }

        return $absolutePath;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_module_slug_from_path')) {
    function itm_crud_boolean_cell_audit_module_slug_from_path(string $relativePath): string
    {
        if (preg_match('#^modules/([^/]+)/#', $relativePath, $matches)) {
            return (string)$matches[1];
        }

        return '';
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_status_driven_slugs')) {
    /**
     * @return array<int, string>
     */
    function itm_crud_boolean_cell_audit_status_driven_slugs(): array
    {
        if (function_exists('itm_active_audit_status_driven_slugs')) {
            return itm_active_audit_status_driven_slugs();
        }

        return ['employees', 'equipment', 'patches_updates', 'tickets'];
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_schema_path')) {
    function itm_crud_boolean_cell_audit_schema_path(string $repoRoot): string
    {
        if (function_exists('itm_database_sql_schema_path')) {
            return itm_database_sql_schema_path();
        }

        return rtrim($repoRoot, '/\\') . '/db/01_schema.sql';
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_parse_schema_tinyint_columns')) {
    /**
     * @return array<string, array<string, string>> table => field => full column type
     */
    function itm_crud_boolean_cell_audit_parse_schema_tinyint_columns(string $schemaPath): array
    {
        static $cache = [];

        if (isset($cache[$schemaPath])) {
            return $cache[$schemaPath];
        }

        $cache[$schemaPath] = [];

        if (!is_readable($schemaPath)) {
            return $cache[$schemaPath];
        }

        $sql = (string)file_get_contents($schemaPath);
        if (!preg_match_all('/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is', $sql, $matches, PREG_SET_ORDER)) {
            return $cache[$schemaPath];
        }

        foreach ($matches as $match) {
            $tableName = (string)$match[1];
            $body = (string)$match[2];
            $fields = [];

            foreach (preg_split('/\R/', $body) ?: [] as $line) {
                $trimmed = trim((string)$line);
                if (!preg_match('/^`([a-zA-Z0-9_]+)`\s+(.*)$/i', $trimmed, $colMatch)) {
                    continue;
                }

                $colName = (string)$colMatch[1];
                $colDef = rtrim(trim((string)$colMatch[2]), ',');
                if (preg_match('/\btinyint\s*\(\s*1\s*\)/i', $colDef)) {
                    $fields[$colName] = $colDef;
                }
            }

            if ($fields !== []) {
                $cache[$schemaPath][$tableName] = $fields;
            }
        }

        return $cache[$schemaPath];
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_extract_crud_table')) {
    function itm_crud_boolean_cell_audit_extract_crud_table(string $content): string
    {
        if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*;/', $content, $matches)) {
            return (string)$matches[1];
        }

        return '';
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_extract_function_body')) {
    function itm_crud_boolean_cell_audit_extract_function_body(string $content, string $functionName): string
    {
        if (!preg_match('/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\)\s*\{/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $cursor = (int)$matches[0][1] + strlen((string)$matches[0][0]);
        $length = strlen($content);
        $depth = 1;

        for (; $cursor < $length; $cursor++) {
            $char = $content[$cursor];
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, (int)$matches[0][1], $cursor - (int)$matches[0][1] + 1);
                }
            }
        }

        return '';
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_parse_hidden_field_names')) {
    /**
     * @return array<int, string>
     */
    function itm_crud_boolean_cell_audit_parse_hidden_field_names(string $content, string $table): array
    {
        $hidden = [];
        $tableQuoted = preg_quote($table, '/');

        if (preg_match_all(
            '/if\s*\(\s*(?:\$table\s*===|\(\s*\$GLOBALS\[[\'"]crud_table[\'"]\][^\)]*\)\s*===)\s*[\'"]'
            . $tableQuoted . '[\'"]\s*\)\s*\{[\s\S]{0,320}?in_array\s*\(\s*\$field\s*,\s*\[([^\]]+)\]/s',
            $content,
            $matches
        )) {
            foreach ($matches[1] as $arrayLiteral) {
                if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', (string)$arrayLiteral, $fieldMatches)) {
                    foreach ($fieldMatches[1] as $fieldName) {
                        $hidden[] = (string)$fieldName;
                    }
                }
            }
        }

        return array_values(array_unique($hidden));
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_is_field_hidden_from_surface')) {
    function itm_crud_boolean_cell_audit_is_field_hidden_from_surface(
        string $field,
        string $moduleSlug,
        string $table,
        string $surface,
        array $hiddenByModule
    ): bool {
        if (in_array($field, $hiddenByModule, true)) {
            return true;
        }

        if ($field === 'company_id') {
            return true;
        }

        if ($field === 'active' && in_array($moduleSlug, itm_crud_boolean_cell_audit_status_driven_slugs(), true)) {
            return true;
        }

        if ($surface === 'list' && function_exists('itm_crud_is_list_hidden_audit_field')
            && itm_crud_is_list_hidden_audit_field($field)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_field_has_badge_rendering')) {
    /**
     * Why: Some modules render non-active tinyint(1) columns as badges (e.g. employee_roles sidebar_show Show/Hide).
     */
    function itm_crud_boolean_cell_audit_field_has_badge_rendering(string $renderBody, string $field): bool
    {
        if ($renderBody === '' || $field === '') {
            return false;
        }

        $fieldQuoted = preg_quote($field, '/');
        $fieldEquals = '\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"]';

        if (preg_match('/' . $fieldEquals . '[\s\S]{0,500}?badge-(success|danger)/s', $renderBody)) {
            return true;
        }

        if (preg_match('/\|\|\s*' . $fieldEquals . '[\s\S]{0,800}?badge-(success|danger)/s', $renderBody)) {
            return true;
        }

        if (preg_match('/' . $fieldEquals . '\s*\|\|[\s\S]{0,800}?badge-(success|danger)/s', $renderBody)) {
            return true;
        }

        // Why: Combined boolean columns (e.g. employee_sidebar_preferences active + is_visible) use in_array + badges.
        if (preg_match(
            '/in_array\s*\(\s*\$field\s*,\s*\[[^\]]*[\'"]' . $fieldQuoted . '[\'"][^\]]*\][\s\S]{0,500}?badge-(success|danger)/s',
            $renderBody
        )) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_field_has_active_badge_rendering')) {
    function itm_crud_boolean_cell_audit_field_has_active_badge_rendering(string $renderBody): bool
    {
        return itm_crud_boolean_cell_audit_field_has_badge_rendering($renderBody, 'active');
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_field_has_checkbox_emoji_rendering')) {
    function itm_crud_boolean_cell_audit_field_has_checkbox_emoji_rendering(
        string $renderBody,
        string $field,
        string $table
    ): bool {
        if ($renderBody === '') {
            return false;
        }

        if (strpos($renderBody, 'itm_crud_render_checkbox_boolean_cell_value') !== false) {
            return true;
        }

        $fieldQuoted = preg_quote($field, '/');
        $tableQuoted = preg_quote($table, '/');

        if (preg_match('/\$field\s*===\s*[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,260}?✅/s', $renderBody)) {
            return true;
        }

        if (preg_match('/\$table\s*===\s*[\'"]' . $tableQuoted . '[\'"][\s\S]{0,160}?\$field\s*===\s*[\'"]'
            . $fieldQuoted . '[\'"][\s\S]{0,260}?✅/s', $renderBody)) {
            return true;
        }

        if (preg_match('/in_array\s*\(\s*\$field\s*,\s*\[[^\]]*[\'"]' . $fieldQuoted . '[\'"][^\]]*\][\s\S]{0,260}?✅/s', $renderBody)) {
            return true;
        }

        if (preg_match('/[\'"]' . $fieldQuoted . '[\'"][\s\S]{0,80}\]\s*,[\s\S]{0,260}?✅/s', $renderBody)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_surface_from_path')) {
    function itm_crud_boolean_cell_audit_surface_from_path(string $relativePath): string
    {
        if (preg_match('#/view\.php$#', $relativePath)) {
            return 'view';
        }

        return 'list';
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_analyze_file')) {
    /**
     * @param array<string, array<string, string>> $schemaTinyintColumns
     * @return array<string, mixed>|null
     */
    function itm_crud_boolean_cell_audit_analyze_file(
        string $repoRoot,
        string $absolutePath,
        array $schemaTinyintColumns
    ): ?array {
        $content = @file_get_contents($absolutePath);
        if ($content === false || strpos($content, 'function cr_render_cell_value') === false) {
            return null;
        }

        $relativePath = itm_crud_boolean_cell_audit_repo_relative_path($repoRoot, $absolutePath);
        $moduleSlug = itm_crud_boolean_cell_audit_module_slug_from_path($relativePath);
        $table = itm_crud_boolean_cell_audit_extract_crud_table($content);
        if ($table === '' || !isset($schemaTinyintColumns[$table])) {
            return [
                'path' => $relativePath,
                'slug' => $moduleSlug,
                'table' => $table,
                'status' => 'skipped',
                'reason' => 'no_schema_tinyint_columns',
            ];
        }

        $renderBody = itm_crud_boolean_cell_audit_extract_function_body($content, 'cr_render_cell_value');
        if ($renderBody === '') {
            return [
                'path' => $relativePath,
                'slug' => $moduleSlug,
                'table' => $table,
                'status' => 'skipped',
                'reason' => 'cr_render_cell_value_body_unparsed',
            ];
        }

        $surface = itm_crud_boolean_cell_audit_surface_from_path($relativePath);
        $hiddenByModule = itm_crud_boolean_cell_audit_parse_hidden_field_names($content, $table);
        $missing = [];

        foreach ($schemaTinyintColumns[$table] as $field => $columnDef) {
            if (itm_crud_boolean_cell_audit_is_field_hidden_from_surface(
                $field,
                $moduleSlug,
                $table,
                $surface,
                $hiddenByModule
            )) {
                continue;
            }

            $handled = false;
            if ($field === 'active') {
                $handled = itm_crud_boolean_cell_audit_field_has_active_badge_rendering($renderBody);
            }

            if (!$handled) {
                $handled = itm_crud_boolean_cell_audit_field_has_badge_rendering($renderBody, $field)
                    || itm_crud_boolean_cell_audit_field_has_checkbox_emoji_rendering($renderBody, $field, $table);
            }

            if (!$handled) {
                $missing[] = [
                    'field' => $field,
                    'type' => $columnDef,
                    'surface' => $surface,
                ];
            }
        }

        if ($missing === []) {
            return [
                'path' => $relativePath,
                'slug' => $moduleSlug,
                'table' => $table,
                'status' => 'pass',
                'reason' => 'boolean_cell_display_ok',
            ];
        }

        return [
            'path' => $relativePath,
            'slug' => $moduleSlug,
            'table' => $table,
            'status' => 'fail',
            'reason' => 'missing_boolean_cell_display',
            'missing' => $missing,
        ];
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_collect_report')) {
    /**
     * @return array{failures: array<int, array<string, mixed>>, passes: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    function itm_crud_boolean_cell_audit_collect_report(string $repoRoot): array
    {
        require_once dirname(__DIR__, 2) . '/includes/itm_database_sql_source.php';
        require_once dirname(__DIR__, 2) . '/includes/itm_crud_audit_fields.php';

        $schemaPath = itm_crud_boolean_cell_audit_schema_path($repoRoot);
        $schemaTinyintColumns = itm_crud_boolean_cell_audit_parse_schema_tinyint_columns($schemaPath);

        $report = [
            'failures' => [],
            'passes' => [],
            'skipped' => [],
        ];

        $patterns = [
            $repoRoot . '/modules/*/index.php',
            $repoRoot . '/modules/*/view.php',
            $repoRoot . '/modules/*/list_all.php',
        ];

        $seen = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $absolutePath) {
                $relativePath = itm_crud_boolean_cell_audit_repo_relative_path($repoRoot, $absolutePath);
                if (isset($seen[$relativePath])) {
                    continue;
                }
                $seen[$relativePath] = true;

                $result = itm_crud_boolean_cell_audit_analyze_file($repoRoot, $absolutePath, $schemaTinyintColumns);
                if ($result === null) {
                    continue;
                }

                $status = (string)($result['status'] ?? 'skipped');
                if ($status === 'fail') {
                    $report['failures'][] = $result;
                } elseif ($status === 'pass') {
                    $report['passes'][] = $result;
                } else {
                    $report['skipped'][] = $result;
                }
            }
        }

        usort($report['failures'], static function ($a, $b) {
            return strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
        });
        usort($report['passes'], static function ($a, $b) {
            return strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
        });
        usort($report['skipped'], static function ($a, $b) {
            return strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
        });

        return $report;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_ensure_color_helpers')) {
    function itm_crud_boolean_cell_audit_ensure_color_helpers(): void
    {
        if (!function_exists('colorText')) {
            require_once __DIR__ . '/script_cli_output.php';
        }
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_color_tag')) {
    function itm_crud_boolean_cell_audit_color_tag(string $label): string
    {
        itm_crud_boolean_cell_audit_ensure_color_helpers();

        $typeMap = [
            'FAIL' => 'fail',
            'PASS' => 'pass',
            'SKIP' => 'info',
        ];
        $type = $typeMap[$label] ?? 'info';

        return colorText('[' . $label . ']', $type);
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_color_heading')) {
    function itm_crud_boolean_cell_audit_color_heading(string $label, string $suffix): string
    {
        return itm_crud_boolean_cell_audit_color_tag($label) . $suffix;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_format_row')) {
    function itm_crud_boolean_cell_audit_format_row(array $row, string $nl, bool $linkModules, string $label): string
    {
        require_once __DIR__ . '/script_browser_nav.php';

        $path = (string)($row['path'] ?? '');
        $slug = (string)($row['slug'] ?? '');
        $table = (string)($row['table'] ?? '');
        $missing = (array)($row['missing'] ?? []);
        $coloredTag = itm_crud_boolean_cell_audit_color_tag($label);

        $missingParts = [];
        foreach ($missing as $item) {
            $field = (string)($item['field'] ?? '');
            $surface = (string)($item['surface'] ?? '');
            if ($field === '') {
                continue;
            }
            $missingParts[] = $field . ($surface !== '' ? (' (' . $surface . ')') : '');
        }
        $missingText = $missingParts !== [] ? (' missing=' . implode(',', $missingParts)) : '';
        $tableText = $table !== '' ? (' table=' . $table) : '';
        $reason = (string)($row['reason'] ?? '');
        $reasonText = ($label === 'SKIP' && $reason !== '') ? (' reason=' . $reason) : '';

        if ($linkModules && $slug !== '') {
            return ' - ' . $coloredTag . ' ' . itm_script_format_module_link($slug, 'index.php', $path)
                . $tableText . $missingText . $reasonText . $nl;
        }

        return ' - ' . $coloredTag . ' ' . $path . $tableText . $missingText . $reasonText . $nl;
    }
}

if (!function_exists('itm_crud_boolean_cell_audit_format_report')) {
    function itm_crud_boolean_cell_audit_format_report(array $report, string $nl, bool $linkModules): string
    {
        $out = 'CRUD boolean cell display audit (tinyint(1) list/view cells)' . $nl . $nl;
        $out .= itm_crud_boolean_cell_audit_color_tag('FAIL') . ' = visible tinyint(1) column falls through to raw 0/1 in cr_render_cell_value().' . $nl;
        $out .= itm_crud_boolean_cell_audit_color_tag('PASS') . ' = active uses badges; other checkbox columns use itm_crud_render_checkbox_boolean_cell_value(), bespoke ✅/❌, badge labels, or in_array(...) badge blocks.' . $nl . $nl;

        if ($report['failures'] !== []) {
            $out .= itm_crud_boolean_cell_audit_color_heading('FAIL', ' ' . count($report['failures']) . ' file(s):') . $nl;
            foreach ($report['failures'] as $row) {
                $out .= itm_crud_boolean_cell_audit_format_row($row, $nl, $linkModules, 'FAIL');
            }
            $out .= $nl;
        }

        if ($report['passes'] !== []) {
            $out .= itm_crud_boolean_cell_audit_color_heading('PASS', ' ' . count($report['passes']) . ' file(s):') . $nl;
            foreach ($report['passes'] as $row) {
                $out .= itm_crud_boolean_cell_audit_format_row($row, $nl, $linkModules, 'PASS');
            }
            $out .= $nl;
        } else {
            $out .= itm_crud_boolean_cell_audit_color_heading('PASS', ' 0 file(s).') . $nl . $nl;
        }

        if ($report['skipped'] !== []) {
            $out .= itm_crud_boolean_cell_audit_color_heading('SKIP', ' ' . count($report['skipped']) . ' file(s):') . $nl;
            foreach ($report['skipped'] as $row) {
                $out .= itm_crud_boolean_cell_audit_format_row($row, $nl, $linkModules, 'SKIP');
            }
            $out .= $nl;
        } else {
            $out .= itm_crud_boolean_cell_audit_color_heading('SKIP', ' 0 file(s).') . $nl;
        }

        return $out;
    }
}
