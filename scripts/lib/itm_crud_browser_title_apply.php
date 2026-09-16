<?php
/**
 * Shared apply/check helpers for module icon in browser $crud_title.
 */

require_once __DIR__ . '/itm_titles_list_audit.php';

if (!function_exists('itm_crud_browser_title_canonical_title_tag_regex')) {
    function itm_crud_browser_title_canonical_title_tag_regex(): string
    {
        return '~<title>\s*<\?=\s*sanitize\s*\(\s*\$crud_title\s*\)\s*\?>\s*-\s*<\?php\s+echo\s+sanitize\s*\(\s*\$app_name~i';
    }
}

if (!function_exists('itm_crud_browser_title_file_has_canonical_title')) {
    function itm_crud_browser_title_file_has_canonical_title(string $content): bool
    {
        return preg_match(itm_crud_browser_title_canonical_title_tag_regex(), $content) === 1;
    }
}

if (!function_exists('itm_crud_browser_title_helper_marker')) {
    function itm_crud_browser_title_helper_marker(): string
    {
        return 'itm_crud_apply_module_icon_to_browser_title';
    }
}

if (!function_exists('itm_crud_browser_title_inject_snippet')) {
    function itm_crud_browser_title_inject_snippet(): string
    {
        return "require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';\n"
            . "    \$crud_title = itm_crud_apply_module_icon_to_browser_title(\$conn, (int)(\$company_id ?? 0), (int)(\$_SESSION['employee_id'] ?? 0), basename(dirname(\$_SERVER['PHP_SELF'])), (string)(\$crud_title ?? ''));\n";
    }
}

if (!function_exists('itm_crud_browser_title_strip_legacy_icon_assignments')) {
    /**
     * Remove hand-rolled icon + $crud_title blocks replaced by the shared helper.
     */
    function itm_crud_browser_title_strip_legacy_icon_assignments(string $content): string
    {
        $content = preg_replace(
            '/\nif\s*\(\s*\$\w+ResolvedModuleIcon\s*!==\s*[\'\"][\'\"]\s*\)\s*\{\s*\n\s*\$crud_title\s*=\s*trim\s*\(\s*\$\w+ResolvedModuleIcon\s*\.\s*[\'\"]\s*[\'\"]\s*\.\s*\$\w+Clean\w*\s*\)\s*;\s*\n\}/',
            '',
            $content
        ) ?? $content;

        $content = preg_replace(
            '/\n\$crud_title\s*=\s*trim\s*\(\s*\$\w+ResolvedModuleIcon\s*\.\s*[\'\"]\s*[\'\"]\s*\.\s*\$\w+\s*\)\s*;/',
            '',
            $content
        ) ?? $content;

        return $content;
    }
}

if (!function_exists('itm_crud_browser_title_apply_to_content')) {
    /**
     * @return array{changed:bool,reason:string}
     */
    function itm_crud_browser_title_apply_to_content(string $content): array
    {
        if (!itm_crud_browser_title_file_has_canonical_title($content)) {
            return ['changed' => false, 'reason' => 'no_canonical_title'];
        }
        if (strpos($content, itm_crud_browser_title_helper_marker()) !== false) {
            return ['changed' => false, 'reason' => 'already_applied'];
        }

        if (preg_match(itm_crud_browser_title_canonical_title_tag_regex(), $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return ['changed' => false, 'reason' => 'no_title_anchor'];
        }

        $titlePos = (int) $match[0][1];
        $beforeTitle = substr($content, 0, $titlePos);
        $lastClose = strrpos($beforeTitle, '?>');
        if ($lastClose === false) {
            return ['changed' => false, 'reason' => 'no_php_close_before_title'];
        }

        $content = itm_crud_browser_title_strip_legacy_icon_assignments($content);
        if (preg_match(itm_crud_browser_title_canonical_title_tag_regex(), $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return ['changed' => false, 'reason' => 'title_lost_after_strip'];
        }
        $titlePos = (int) $match[0][1];
        $beforeTitle = substr($content, 0, $titlePos);
        $lastClose = strrpos($beforeTitle, '?>');
        if ($lastClose === false) {
            return ['changed' => false, 'reason' => 'no_php_close_before_title'];
        }

        $snippet = '    ' . str_replace("\n", "\n    ", trim(itm_crud_browser_title_inject_snippet())) . "\n    ";
        $updated = substr($content, 0, $lastClose) . $snippet . substr($content, $lastClose);

        return ['changed' => true, 'reason' => 'patched', 'content' => $updated];
    }
}

if (!function_exists('itm_crud_browser_title_collect_module_php_files')) {
    /**
     * @return list<string> absolute paths
     */
    function itm_crud_browser_title_collect_module_php_files(string $modulesDir): array
    {
        $files = [];
        if (!is_dir($modulesDir)) {
            return $files;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modulesDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }
}
