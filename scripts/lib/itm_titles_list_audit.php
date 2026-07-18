<?php
/**
 * Shared helpers for titles_list.php / titles_list_show.php module <title> audits.
 */

if (!function_exists('itm_titles_list_expected_title_literal')) {
    function itm_titles_list_expected_title_literal(): string
    {
        return '<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>';
    }
}

if (!function_exists('itm_titles_list_normalize_title_block')) {
    function itm_titles_list_normalize_title_block(string $titleBlock): string
    {
        return preg_replace('/\s+/', ' ', trim($titleBlock));
    }
}

if (!function_exists('itm_titles_list_canonical_title_regex')) {
    function itm_titles_list_canonical_title_regex(): string
    {
        return '~^<title>\s*<\?=\s*sanitize\s*\(\s*\$crud_title\s*\)\s*\?>\s*-\s*<\?php\s+echo\s+sanitize\s*\(\s*\$app_name\s*\?\?\s*itm_ui_config_app_name\s*\(\s*\$currentUiConfig\s*\)\s*\)\s*;\s*\?>\s*</title>\s*$~i';
    }
}

if (!function_exists('itm_titles_list_title_matches_canonical')) {
    /**
     * True when the <title> block matches the canonical scaffold pattern.
     */
    function itm_titles_list_title_matches_canonical(string $titleBlock): bool
    {
        $normalized = itm_titles_list_normalize_title_block($titleBlock);
        if (strcasecmp($normalized, itm_titles_list_expected_title_literal()) === 0) {
            return true;
        }

        return (bool) preg_match(itm_titles_list_canonical_title_regex(), $normalized);
    }
}

if (!function_exists('itm_titles_list_title_has_app_name_suffix')) {
    /**
     * @deprecated Use itm_titles_list_title_matches_canonical().
     */
    function itm_titles_list_title_has_app_name_suffix(string $titleBlock): bool
    {
        return itm_titles_list_title_matches_canonical($titleBlock);
    }
}

if (!function_exists('itm_titles_list_collect_module_php_files')) {
    /**
     * @return array<int, string> absolute paths
     */
    function itm_titles_list_collect_module_php_files(string $modulesDir): array
    {
        if (!is_dir($modulesDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modulesDir, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }
}

if (!function_exists('itm_titles_list_module_path_from_root')) {
    function itm_titles_list_module_path_from_root(string $root, string $path): string
    {
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        $relativeNorm = str_replace('\\', '/', $relative);

        if (strpos($relativeNorm, 'modules/') !== 0) {
            return 'modules/' . ltrim($relativeNorm, '/');
        }

        return $relativeNorm;
    }
}

if (!function_exists('itm_titles_list_echo_summary')) {
    /**
     * @param array{scanned:int,with_title:int,match:int,not_match:int,no_title:int} $stats
     */
    function itm_titles_list_echo_summary(array $stats, bool $isCli): void
    {
        $nl = $isCli ? "\n" : '<br>';
        $expected = itm_titles_list_expected_title_literal();

        $lines = [
            '--- Summary ---',
            'PHP files scanned: ' . (int) $stats['scanned'],
            'With <title>: ' . (int) $stats['with_title'],
            'No <title>: ' . (int) $stats['no_title'],
            'Match canonical title: ' . (int) $stats['match'],
            'Not match canonical title: ' . (int) $stats['not_match'],
            'Expected title: ' . $expected,
            '---------------',
        ];

        foreach ($lines as $line) {
            $escaped = $isCli ? $line : htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo $escaped . $nl;
        }

        echo $nl;
    }
}
