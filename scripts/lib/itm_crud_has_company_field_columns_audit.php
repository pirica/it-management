<?php
/**
 * Static audit: $hasCompany must not be derived from UI-filtered $fieldColumns when
 * company_id is hidden from that array (breaks tenant scope + Add sample data).
 */

if (!function_exists('itm_crud_has_company_audit_relative_path')) {
    function itm_crud_has_company_audit_relative_path(string $repoRoot, string $absolutePath): string
    {
        $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
        $absolutePath = str_replace('\\', '/', $absolutePath);

        if (strpos($absolutePath, $repoRoot . '/') === 0) {
            return substr($absolutePath, strlen($repoRoot) + 1);
        }

        return $absolutePath;
    }
}

if (!function_exists('itm_crud_has_company_audit_module_slug_from_path')) {
    function itm_crud_has_company_audit_module_slug_from_path(string $relativePath): string
    {
        if (preg_match('#^modules/([^/]+)/#', $relativePath, $matches)) {
            return (string)$matches[1];
        }

        return '';
    }
}

if (!function_exists('itm_crud_has_company_audit_uses_columns_pattern')) {
    function itm_crud_has_company_audit_uses_columns_pattern(string $content): bool
    {
        return (bool) preg_match(
            '/\$hasCompany\s*=\s*false\s*;\s*foreach\s*\(\s*\$columns\s+as\s+\$c\s*\)[\s\S]{0,160}?company_id/s',
            $content
        );
    }
}

if (!function_exists('itm_crud_has_company_audit_uses_field_columns_pattern')) {
    function itm_crud_has_company_audit_uses_field_columns_pattern(string $content): bool
    {
        return (bool) preg_match(
            '/\$hasCompany\s*=\s*false\s*;\s*foreach\s*\(\s*\$fieldColumns\s+as\s+\$c\s*\)[\s\S]{0,160}?company_id/s',
            $content
        );
    }
}

if (!function_exists('itm_crud_has_company_audit_field_columns_has_company_offset')) {
    function itm_crud_has_company_audit_field_columns_has_company_offset(string $content): ?int
    {
        if (!preg_match(
            '/\$hasCompany\s*=\s*false\s*;\s*foreach\s*\(\s*\$fieldColumns\s+as\s+\$c\s*\)/s',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        return (int)$matches[0][1];
    }
}

if (!function_exists('itm_crud_has_company_audit_cr_is_hidden_hides_company_for_slug')) {
    /**
     * Why: only module-specific cr_is_hidden branches that list company_id break $hasCompany when applied to $fieldColumns.
     */
    function itm_crud_has_company_audit_cr_is_hidden_hides_company_for_slug(string $content, string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        $slugQuoted = preg_quote($slug, '/');

        if (preg_match(
            '/function\s+cr_is_hidden[\w]*\s*\([^)]*\)\s*\{[\s\S]*?if\s*\(\s*\$table\s*===\s*[\'"]'
            . $slugQuoted . '[\'"]\s*\)\s*\{[\s\S]{0,250}?[\'"]company_id[\'"]/s',
            $content
        )) {
            return true;
        }

        if ($slug === 'employees'
            && preg_match(
                '/function\s+cr_is_hidden[\w]*\s*\([^)]*\)\s*\{[\s\S]*?if\s*\(\s*\$table\s*!==\s*[\'"]employees[\'"]\s*\)[\s\S]{0,120}?return\s+false[\s\S]{0,200}?\$hidden\s*=\s*\[[^\]]*[\'"]company_id[\'"]/s',
                $content
            )) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_crud_has_company_audit_hides_company_id_from_field_columns')) {
    /**
     * Why: employee_notifications hid company_id in cr_is_hidden_* before $hasCompany loop on $fieldColumns.
     * List-only filters ($uiColumns / $visibleFieldColumns after $hasCompany) are not failures.
     */
    function itm_crud_has_company_audit_hides_company_id_from_field_columns(string $content, string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        $hasCompanyOffset = itm_crud_has_company_audit_field_columns_has_company_offset($content);
        if ($hasCompanyOffset === null) {
            return false;
        }

        $prefix = substr($content, 0, $hasCompanyOffset);
        $slugQuoted = preg_quote($slug, '/');

        if (preg_match(
            '/\$fieldColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$fieldColumns[\s\S]*?cr_is_hidden/s',
            $prefix
        ) && itm_crud_has_company_audit_cr_is_hidden_hides_company_for_slug($content, $slug)) {
            return true;
        }

        if (preg_match(
            '/\$fieldColumns\s*=\s*array_values\s*\(\s*array_filter\s*\(\s*\$fieldColumns[\s\S]{0,700}?'
            . '(?:crud_table[\s\S]{0,120}?===\s*[\'"]' . $slugQuoted . '[\'"][\s\S]{0,160}?[\'"]company_id[\'"]|[\'"]company_id[\'"][\s\S]{0,160}?crud_table[\s\S]{0,120}?===\s*[\'"]' . $slugQuoted . '[\'"])/s',
            $prefix
        )) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_crud_has_company_audit_collect_impacts')) {
    /**
     * @return array<int, string>
     */
    function itm_crud_has_company_audit_collect_impacts(string $content): array
    {
        $impacts = [];

        if (strpos($content, 'add_sample_data') !== false && strpos($content, '$hasCompany') !== false) {
            $impacts[] = 'add_sample_data';
        }
        if (preg_match('/if\s*\(\s*\$hasCompany\s*&&\s*\$company_id/', $content)) {
            $impacts[] = 'list_company_scope';
        }
        if (preg_match('/!\s*\$hasCompany\s*\|\|\s*\$company_id\s*<=?\s*0/', $content)) {
            $impacts[] = 'sample_data_requires_company_gate';
        }

        return $impacts;
    }
}

if (!function_exists('itm_crud_has_company_audit_analyze_file')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_crud_has_company_audit_analyze_file(string $repoRoot, string $absolutePath): ?array
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false || strpos($content, '$hasCompany') === false) {
            return null;
        }

        $relativePath = itm_crud_has_company_audit_relative_path($repoRoot, $absolutePath);
        $slug = itm_crud_has_company_audit_module_slug_from_path($relativePath);
        $usesColumns = itm_crud_has_company_audit_uses_columns_pattern($content);
        $usesFieldColumns = itm_crud_has_company_audit_uses_field_columns_pattern($content);
        $hidesCompanyId = itm_crud_has_company_audit_hides_company_id_from_field_columns($content, $slug);

        if (!$usesFieldColumns && !$usesColumns) {
            return [
                'path' => $relativePath,
                'slug' => $slug,
                'status' => 'skipped',
                'reason' => 'non_standard_hasCompany_pattern',
            ];
        }

        if ($usesColumns) {
            return [
                'path' => $relativePath,
                'slug' => $slug,
                'status' => 'pass',
                'reason' => 'hasCompany_from_columns',
            ];
        }

        if ($hidesCompanyId) {
            return [
                'path' => $relativePath,
                'slug' => $slug,
                'status' => 'fail',
                'reason' => 'hasCompany_from_fieldColumns_after_company_id_hidden',
                'impacts' => itm_crud_has_company_audit_collect_impacts($content),
            ];
        }

        return [
            'path' => $relativePath,
            'slug' => $slug,
            'status' => 'warn',
            'reason' => 'hasCompany_from_fieldColumns',
            'impacts' => itm_crud_has_company_audit_collect_impacts($content),
        ];
    }
}

if (!function_exists('itm_crud_has_company_audit_collect_report')) {
    /**
     * @return array{failures: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, passes: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    function itm_crud_has_company_audit_collect_report(string $repoRoot): array
    {
        $report = [
            'failures' => [],
            'warnings' => [],
            'passes' => [],
            'skipped' => [],
        ];

        $patterns = [
            $repoRoot . '/modules/*/index.php',
            $repoRoot . '/modules/*/list_all.php',
        ];

        $seen = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $absolutePath) {
                $relativePath = itm_crud_has_company_audit_relative_path($repoRoot, $absolutePath);
                if (isset($seen[$relativePath])) {
                    continue;
                }
                $seen[$relativePath] = true;

                $result = itm_crud_has_company_audit_analyze_file($repoRoot, $absolutePath);
                if ($result === null) {
                    continue;
                }

                $status = (string)($result['status'] ?? 'skipped');
                if ($status === 'fail') {
                    $report['failures'][] = $result;
                } elseif ($status === 'warn') {
                    $report['warnings'][] = $result;
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
        usort($report['warnings'], static function ($a, $b) {
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

if (!function_exists('itm_crud_has_company_audit_ensure_color_helpers')) {
    function itm_crud_has_company_audit_ensure_color_helpers(): void
    {
        if (!function_exists('colorText')) {
            require_once __DIR__ . '/script_cli_output.php';
        }
    }
}

if (!function_exists('itm_crud_has_company_audit_color_tag')) {
    function itm_crud_has_company_audit_color_tag(string $label): string
    {
        itm_crud_has_company_audit_ensure_color_helpers();

        $typeMap = [
            'FAIL' => 'fail',
            'WARN' => 'warn',
            'PASS' => 'pass',
            'SKIP' => 'info',
        ];
        $type = $typeMap[$label] ?? 'info';

        return colorText('[' . $label . ']', $type);
    }
}

if (!function_exists('itm_crud_has_company_audit_color_heading')) {
    function itm_crud_has_company_audit_color_heading(string $label, string $suffix): string
    {
        return itm_crud_has_company_audit_color_tag($label) . $suffix;
    }
}

if (!function_exists('itm_crud_has_company_audit_format_report')) {
    function itm_crud_has_company_audit_format_report(array $report, string $nl, bool $linkModules): string
    {
        require_once __DIR__ . '/script_browser_nav.php';

        $out = 'CRUD hasCompany audit (schema $columns vs UI $fieldColumns)' . $nl . $nl;
        $out .= itm_crud_has_company_audit_color_tag('FAIL') . ' = company_id hidden from $fieldColumns but $hasCompany still derived there.' . $nl;
        $out .= itm_crud_has_company_audit_color_tag('WARN') . ' = $hasCompany still uses $fieldColumns (prefer foreach ($columns as $c) before UI filters).' . $nl . $nl;

        if ($report['failures'] !== []) {
            $out .= itm_crud_has_company_audit_color_heading('FAIL', ' ' . count($report['failures']) . ' file(s):') . $nl;
            foreach ($report['failures'] as $row) {
                $out .= itm_crud_has_company_audit_format_row($row, $nl, $linkModules, 'FAIL');
            }
            $out .= $nl;
        }

        if ($report['warnings'] !== []) {
            $out .= itm_crud_has_company_audit_color_heading('WARN', ' ' . count($report['warnings']) . ' file(s):') . $nl;
            foreach ($report['warnings'] as $row) {
                $out .= itm_crud_has_company_audit_format_row($row, $nl, $linkModules, 'WARN');
            }
            $out .= $nl;
        }

        if ($report['passes'] !== []) {
            $out .= itm_crud_has_company_audit_color_heading('PASS', ' ' . count($report['passes']) . ' file(s) use $columns for hasCompany:') . $nl;
            foreach ($report['passes'] as $row) {
                $out .= itm_crud_has_company_audit_format_row($row, $nl, $linkModules, 'PASS');
            }
            $out .= $nl;
        } else {
            $out .= itm_crud_has_company_audit_color_heading('PASS', ' 0 file(s) use $columns for hasCompany.') . $nl . $nl;
        }

        if ($report['skipped'] !== []) {
            $out .= itm_crud_has_company_audit_color_heading('SKIP', ' ' . count($report['skipped']) . ' file(s) with non-standard hasCompany markup:') . $nl;
            foreach ($report['skipped'] as $row) {
                $out .= itm_crud_has_company_audit_format_row($row, $nl, $linkModules, 'SKIP');
            }
            $out .= $nl;
        } else {
            $out .= itm_crud_has_company_audit_color_heading('SKIP', ' 0 file(s) with non-standard hasCompany markup.') . $nl;
        }

        return $out;
    }
}

if (!function_exists('itm_crud_has_company_audit_format_row')) {
    function itm_crud_has_company_audit_format_row(array $row, string $nl, bool $linkModules, string $label): string
    {
        $path = (string)($row['path'] ?? '');
        $slug = (string)($row['slug'] ?? '');
        $impacts = (array)($row['impacts'] ?? []);
        $impactText = $impacts !== [] ? (' impacts=' . implode(',', $impacts)) : '';
        $reason = (string)($row['reason'] ?? '');
        $reasonText = ($label === 'SKIP' && $reason !== '') ? (' reason=' . $reason) : '';
        $coloredTag = itm_crud_has_company_audit_color_tag($label);

        if ($linkModules && $slug !== '') {
            $line = ' - ' . $coloredTag . ' ' . itm_script_format_module_link($slug, 'index.php', $path) . $impactText . $reasonText . $nl;
            return $line;
        }

        return ' - ' . $coloredTag . ' ' . $path . $impactText . $reasonText . $nl;
    }
}
