<?php
/**
 * Repair helpers for scaffold active checkbox compliance (itm-checkbox-control + itm-check-indicator).
 */

if (!function_exists('itm_active_checkbox_fix_issue_code')) {
    function itm_active_checkbox_fix_issue_code(): string
    {
        return 'scaffold_active_checkbox_not_compliant';
    }
}

if (!function_exists('itm_active_checkbox_fix_filter_violations')) {
    /**
     * @param array<string, mixed> $report
     * @return array<int, array{module:string,table:string,file:string,issues:array}>
     */
    function itm_active_checkbox_fix_filter_violations(array $report, ?string $moduleSlug = null): array
    {
        $code = itm_active_checkbox_fix_issue_code();
        $rows = [];
        foreach ($report['violations'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $module = (string)($row['module'] ?? '');
            if ($moduleSlug !== null && $moduleSlug !== '' && $module !== $moduleSlug) {
                continue;
            }
            $issues = [];
            foreach ($row['issues'] ?? [] as $issue) {
                if (!is_array($issue)) {
                    continue;
                }
                if ((string)($issue['code'] ?? '') === $code) {
                    $issues[] = $issue;
                }
            }
            if ($issues === []) {
                continue;
            }
            $row['issues'] = $issues;
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('itm_active_checkbox_fix_module_slugs')) {
    /**
     * @param array<int, array{module:string}> $violations
     * @return string[]
     */
    function itm_active_checkbox_fix_module_slugs(array $violations): array
    {
        $slugs = [];
        foreach ($violations as $row) {
            $slug = (string)($row['module'] ?? '');
            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }
        $list = array_keys($slugs);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}

if (!function_exists('itm_active_checkbox_fix_indicator_markup')) {
    function itm_active_checkbox_fix_indicator_markup(string $inputTag): string
    {
        if (preg_match(
            "/<\?php\s+echo\s+(.+)\s*\?\s*'checked'\s*:\s*''\s*;\s*\?>/s",
            $inputTag,
            $matches
        ) || preg_match(
            '/<\?php\s+echo\s+(.+)\s*\?\s*"checked"\s*:\s*""\s*;\s*\?>/s',
            $inputTag,
            $matches
        )) {
            $condition = trim((string)$matches[1]);

            return '<?php echo (' . $condition . ') ? \'✅\' : \'❌\'; ?>';
        }

        if (preg_match('/\bchecked\b/i', $inputTag)) {
            return '✅';
        }

        return '❌';
    }
}

if (!function_exists('itm_active_checkbox_fix_wrap_control')) {
    function itm_active_checkbox_fix_wrap_control(string $inputTag, string $labelText = 'Active'): string
    {
        $indicator = itm_active_checkbox_fix_indicator_markup($inputTag);

        return '<label class="itm-checkbox-control">' . "\n"
            . '                            ' . trim($inputTag) . "\n"
            . '                            <span>' . $labelText . ' <span class="itm-check-indicator" aria-hidden="true">'
            . $indicator
            . '</span></span>' . "\n"
            . '                        </label>';
    }
}

if (!function_exists('itm_active_checkbox_fix_content')) {
    /**
     * @return array{content:string,changed:bool,replacement_count:int}
     */
    function itm_active_checkbox_fix_content(string $content): array
    {
        $original = $content;
        $replacementCount = 0;

        $content = preg_replace_callback(
            '/<div class="role-flags-grid">\s*<label class="role-flag-option">\s*(<input type="checkbox" name="active"[\s\S]*?>\s*)\s*<\/label>\s*<\/div>/is',
            static function (array $matches) use (&$replacementCount) {
                $replacementCount++;

                return itm_active_checkbox_fix_wrap_control(trim((string)$matches[1]));
            },
            $content
        ) ?? $content;

        $content = preg_replace_callback(
            '/<label class="role-flag-option">\s*(<input type="checkbox" name="active"[\s\S]*?>\s*)\s*<span>Active<\/span>\s*<\/label>/is',
            static function (array $matches) use (&$replacementCount) {
                $replacementCount++;

                return itm_active_checkbox_fix_wrap_control(trim((string)$matches[1]));
            },
            $content
        ) ?? $content;

        $content = preg_replace_callback(
            '/<label class="itm-checkbox-control">\s*(<input type="checkbox" name="active"[\s\S]*?>\s*)\s*<span>Active[^<]*<\/span>\s*<\/label>/is',
            static function (array $matches) use (&$replacementCount) {
                $replacementCount++;

                return itm_active_checkbox_fix_wrap_control(trim((string)$matches[1]));
            },
            $content
        ) ?? $content;

        if ($replacementCount > 0) {
            $content = itm_active_checkbox_fix_ensure_js_listener($content);
        }

        return [
            'content' => $content,
            'changed' => $content !== $original,
            'replacement_count' => $replacementCount,
        ];
    }
}

if (!function_exists('itm_active_checkbox_fix_ensure_js_listener')) {
    function itm_active_checkbox_fix_ensure_js_listener(string $content): string
    {
        if (strpos($content, '.itm-checkbox-control input[type="checkbox"]') !== false) {
            return $content;
        }

        $snippet = <<<'JS'

document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) { indicator.textContent = event.target.checked ? '✅' : '❌'; }
});
JS;

        if (preg_match('/<\/script>\s*<\/body>/i', $content)) {
            return preg_replace(
                '/<\/script>\s*<\/body>/i',
                $snippet . "\n</script>\n</body>",
                $content,
                1
            ) ?? $content;
        }

        if (stripos($content, '</body>') !== false) {
            return str_ireplace(
                '</body>',
                "<script>\n" . trim($snippet) . "\n</script>\n</body>",
                $content
            );
        }

        return $content . "\n<script>\n" . trim($snippet) . "\n</script>\n";
    }
}

if (!function_exists('itm_active_checkbox_fix_apply_file')) {
    /**
     * @return array{changed:bool,replacement_count:int,skipped:bool,reason:string}
     */
    function itm_active_checkbox_fix_apply_file(string $absolutePath, bool $apply): array
    {
        if (!is_file($absolutePath)) {
            return [
                'changed' => false,
                'replacement_count' => 0,
                'skipped' => true,
                'reason' => 'missing',
            ];
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return [
                'changed' => false,
                'replacement_count' => 0,
                'skipped' => true,
                'reason' => 'unreadable',
            ];
        }

        $result = itm_active_checkbox_fix_content($content);
        if (!$result['changed']) {
            return [
                'changed' => false,
                'replacement_count' => 0,
                'skipped' => true,
                'reason' => 'no_match',
            ];
        }

        if ($apply) {
            file_put_contents($absolutePath, (string)$result['content']);
        }

        return [
            'changed' => true,
            'replacement_count' => (int)$result['replacement_count'],
            'skipped' => false,
            'reason' => '',
        ];
    }
}
