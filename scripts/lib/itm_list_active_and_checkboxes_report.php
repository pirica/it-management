<?php
/**
 * Shared report builder for list_active_and_checkboxes.php.
 */

if (!function_exists('itm_active_audit_status_driven_slugs')) {
    /**
     * @return array<int, string>
     */
    function itm_active_audit_status_driven_slugs(): array
    {
        return ['employees', 'equipment', 'patches_updates', 'tickets'];
    }
}

if (!function_exists('itm_active_audit_is_status_driven_slug')) {
    function itm_active_audit_is_status_driven_slug(string $slug): bool
    {
        return in_array($slug, itm_active_audit_status_driven_slugs(), true);
    }
}

if (!function_exists('itm_active_audit_form_entry_files')) {
    /**
     * @return array<int, string>
     */
    function itm_active_audit_form_entry_files(): array
    {
        return ['create.php', 'edit.php', 'index.php', 'list_all.php', 'view.php', 'delete.php'];
    }
}

if (!function_exists('itm_active_audit_read_crud_table')) {
    function itm_active_audit_read_crud_table(string $indexPath): ?string
    {
        if (!is_file($indexPath)) {
            return null;
        }
        $lines = @file($indexPath);
        if (!is_array($lines)) {
            return null;
        }
        foreach ($lines as $lineText) {
            if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $lineText, $matches)) {
                return (string) $matches[1];
            }
        }

        return null;
    }
}

if (!function_exists('itm_active_audit_load_tables_with_active_column')) {
    /**
     * @return array<string, true>
     */
    function itm_active_audit_load_tables_with_active_column($conn): array
    {
        $map = [];
        if (!$conn instanceof mysqli) {
            return $map;
        }

        $sql = 'SELECT TABLE_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $map;
        }
        $columnName = 'active';
        $stmt->bind_param('s', $columnName);
        if (!$stmt->execute()) {
            $stmt->close();
            return $map;
        }
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $table = (string) ($row['TABLE_NAME'] ?? '');
                if ($table !== '' && function_exists('itm_is_safe_identifier') && itm_is_safe_identifier($table)) {
                    $map[$table] = true;
                }
            }
        }
        $stmt->close();

        return $map;
    }
}

if (!function_exists('itm_active_audit_resolve_module_table')) {
    function itm_active_audit_resolve_module_table(string $moduleSlug, string $moduleDir, array $tablesWithActive): ?string
    {
        $candidates = [];
        $fromIndex = itm_active_audit_read_crud_table($moduleDir . '/index.php');
        if ($fromIndex !== null && $fromIndex !== '') {
            $candidates[] = $fromIndex;
        }
        $candidates[] = $moduleSlug;

        foreach ($candidates as $table) {
            if (isset($tablesWithActive[$table])) {
                return $table;
            }
        }

        return null;
    }
}

if (!function_exists('itm_active_audit_has_forbidden_text_active')) {
    function itm_active_audit_has_forbidden_text_active(string $content): bool
    {
        return (bool) preg_match(
            '/<input[^>]+type=["\']text["\'][^>]+name=["\']active["\']/i',
            $content
        ) || (bool) preg_match(
            '/<input[^>]+name=["\']active["\'][^>]+type=["\']text["\']/i',
            $content
        );
    }
}

if (!function_exists('itm_active_audit_has_hidden_active')) {
    function itm_active_audit_has_hidden_active(string $content): bool
    {
        if (strpos($content, 'itm_crud_render_form_hidden_active_input') !== false) {
            return true;
        }

        return (bool) preg_match(
            '/<input[^>]+type=["\']hidden["\'][^>]+name=["\']active["\']/i',
            $content
        ) || (bool) preg_match(
            '/<input[^>]+name=["\']active["\'][^>]+type=["\']hidden["\']/i',
            $content
        );
    }
}

if (!function_exists('itm_active_audit_has_dynamic_active_checkbox_loop')) {
    function itm_active_audit_has_dynamic_active_checkbox_loop(string $content): bool
    {
        if (stripos($content, 'itm-checkbox-control') === false) {
            return false;
        }

        return strpos($content, '$name === \'active\'') !== false
            || strpos($content, '$name === "active"') !== false
            || preg_match('/\$isTinyInt\s*\|\|\s*\$name\s*===\s*[\'"]active[\'"]/i', $content);
    }
}

if (!function_exists('itm_active_audit_has_visible_active_checkbox')) {
    function itm_active_audit_has_visible_active_checkbox(string $content): bool
    {
        if (itm_active_audit_has_dynamic_active_checkbox_loop($content)) {
            return true;
        }

        return (bool) preg_match(
            '/<input[^>]+type=["\']checkbox["\'][^>]+name=["\']active["\']/i',
            $content
        ) || (bool) preg_match(
            '/<input[^>]+name=["\']active["\'][^>]+type=["\']checkbox["\']/i',
            $content
        );
    }
}

if (!function_exists('itm_active_audit_has_compliant_active_checkbox')) {
    function itm_active_audit_has_compliant_active_checkbox(string $content): bool
    {
        if (itm_active_audit_has_dynamic_active_checkbox_loop($content)) {
            return stripos($content, 'itm-check-indicator') !== false;
        }

        if (!itm_active_audit_has_visible_active_checkbox($content)) {
            return false;
        }

        return stripos($content, 'itm-checkbox-control') !== false
            && stripos($content, 'itm-check-indicator') !== false;
    }
}

if (!function_exists('itm_active_audit_has_any_active_reference')) {
    function itm_active_audit_has_any_active_reference(string $content): bool
    {
        return preg_match('/name=["\']active["\']/i', $content)
            || strpos($content, '$name === \'active\'') !== false
            || strpos($content, '$name === "active"') !== false
            || strpos($content, 'itm_crud_render_form_hidden_active_input') !== false
            || strpos($content, 'itm_crud_force_active_live') !== false;
    }
}

if (!function_exists('itm_active_audit_classify_file')) {
    /**
     * @return array<int, array{code:string,message:string}>
     */
    function itm_active_audit_classify_file(string $moduleSlug, string $basename, string $content): array
    {
        $issues = [];
        $isFormFile = in_array($basename, ['create.php', 'edit.php'], true);
        $statusDriven = itm_active_audit_is_status_driven_slug($moduleSlug);

        if (itm_active_audit_has_forbidden_text_active($content)) {
            $issues[] = [
                'code' => 'forbidden_text_active',
                'message' => 'Forbidden <input type="text" name="active">',
            ];
        }

        if ($statusDriven && $isFormFile) {
            if (itm_active_audit_has_visible_active_checkbox($content)) {
                $issues[] = [
                    'code' => 'status_driven_visible_active_checkbox',
                    'message' => 'Status-driven module must use hidden active=1, not a visible checkbox',
                ];
            } elseif (!itm_active_audit_has_hidden_active($content) && itm_active_audit_has_any_active_reference($content)) {
                $issues[] = [
                    'code' => 'status_driven_active_not_hidden',
                    'message' => 'Status-driven form references active without hidden input helper',
                ];
            }
        }

        if (!$statusDriven && itm_active_audit_has_visible_active_checkbox($content)) {
            if (!itm_active_audit_has_compliant_active_checkbox($content)) {
                $issues[] = [
                    'code' => 'scaffold_active_checkbox_not_compliant',
                    'message' => 'Active checkbox must use itm-checkbox-control + itm-check-indicator pattern',
                ];
            }
        }

        return $issues;
    }
}

if (!function_exists('itm_collect_active_and_checkboxes_report')) {
    /**
     * @return array{
     *   summary: array{
     *     modules_scanned:int,
     *     modules_with_active_table:int,
     *     files_scanned:int,
     *     violations:int,
     *     compliant_checkbox_files:int,
     *     hidden_active_files:int,
     *     active_reference_files:int
     *   },
     *   violations: array<int, array{module:string,table:string,file:string,issues:array<int,array{code:string,message:string}>}>,
     *   compliant_checkbox: array<int, array{module:string,file:string}>,
     *   hidden_active: array<int, array{module:string,file:string}>,
     *   active_references: array<int, array{module:string,file:string}>
     * }
     */
    function itm_collect_active_and_checkboxes_report($conn): array
    {
        $modulesRoot = ROOT_PATH . 'modules/';
        $tablesWithActive = itm_active_audit_load_tables_with_active_column($conn);
        $formFiles = itm_active_audit_form_entry_files();

        $summary = [
            'modules_scanned' => 0,
            'modules_with_active_table' => 0,
            'files_scanned' => 0,
            'violations' => 0,
            'compliant_checkbox_files' => 0,
            'hidden_active_files' => 0,
            'active_reference_files' => 0,
        ];
        $violations = [];
        $compliantCheckbox = [];
        $hiddenActive = [];
        $activeReferences = [];

        $entries = scandir($modulesRoot) ?: [];
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($entries as $moduleSlug) {
            if ($moduleSlug === '.' || $moduleSlug === '..') {
                continue;
            }
            $moduleDir = $modulesRoot . $moduleSlug;
            if (!is_dir($moduleDir)) {
                continue;
            }
            $summary['modules_scanned']++;

            $tableName = itm_active_audit_resolve_module_table($moduleSlug, $moduleDir, $tablesWithActive);
            if ($tableName === null) {
                continue;
            }
            $summary['modules_with_active_table']++;

            foreach ($formFiles as $basename) {
                $path = $moduleDir . '/' . $basename;
                if (!is_file($path)) {
                    continue;
                }
                $content = (string) file_get_contents($path);
                $summary['files_scanned']++;
                $relativePath = 'modules/' . $moduleSlug . '/' . $basename;

                $fileIssues = itm_active_audit_classify_file($moduleSlug, $basename, $content);
                if ($fileIssues !== []) {
                    $violations[] = [
                        'module' => $moduleSlug,
                        'table' => $tableName,
                        'file' => $relativePath,
                        'issues' => $fileIssues,
                    ];
                    $summary['violations'] += count($fileIssues);
                }

                if (itm_active_audit_has_compliant_active_checkbox($content)) {
                    $compliantCheckbox[] = ['module' => $moduleSlug, 'file' => $relativePath];
                    $summary['compliant_checkbox_files']++;
                }
                if (itm_active_audit_has_hidden_active($content)) {
                    $hiddenActive[] = ['module' => $moduleSlug, 'file' => $relativePath];
                    $summary['hidden_active_files']++;
                }
                if (itm_active_audit_has_any_active_reference($content)) {
                    $activeReferences[] = ['module' => $moduleSlug, 'file' => $relativePath];
                    $summary['active_reference_files']++;
                }
            }
        }

        return [
            'summary' => $summary,
            'violations' => $violations,
            'compliant_checkbox' => $compliantCheckbox,
            'hidden_active' => $hiddenActive,
            'active_references' => $activeReferences,
        ];
    }
}

if (!function_exists('itm_active_audit_echo_summary')) {
    /**
     * @param array<string, mixed> $report
     */
    function itm_active_audit_echo_summary(array $report, string $nl): void
    {
        $summary = $report['summary'] ?? [];
        echo '--- Summary ---' . $nl;
        echo 'Modules scanned: ' . (int) ($summary['modules_scanned'] ?? 0) . $nl;
        echo 'Modules with active DB column: ' . (int) ($summary['modules_with_active_table'] ?? 0) . $nl;
        echo 'Form entry files scanned: ' . (int) ($summary['files_scanned'] ?? 0) . $nl;
        echo 'Violations: ' . (int) ($summary['violations'] ?? 0) . $nl;
        echo 'Compliant active checkbox files: ' . (int) ($summary['compliant_checkbox_files'] ?? 0) . $nl;
        echo 'Hidden active files: ' . (int) ($summary['hidden_active_files'] ?? 0) . $nl;
        echo 'Files referencing active: ' . (int) ($summary['active_reference_files'] ?? 0) . $nl;
        echo 'Status-driven slugs: ' . implode(', ', itm_active_audit_status_driven_slugs()) . $nl;
        echo '---------------' . $nl . $nl;
    }
}
