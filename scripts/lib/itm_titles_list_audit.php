<?php
/**
 * Shared helpers for titles_list.php / titles_list_show.php module <title> audits.
 */

if (!function_exists('itm_titles_list_expected_app_suffix_literal')) {
    function itm_titles_list_expected_app_suffix_literal(): string
    {
        return '<?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?>';
    }
}

if (!function_exists('itm_titles_list_title_has_app_name_suffix')) {
    /**
     * True when the <title> block uses the canonical app-name suffix helper.
     */
    function itm_titles_list_title_has_app_name_suffix(string $titleBlock): bool
    {
        return (bool) preg_match(
            '/sanitize\s*\(\s*\$app_name\s*\?\?\s*itm_ui_config_app_name\s*\(\s*\$currentUiConfig\s*\)\s*\)/i',
            $titleBlock
        );
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
        $expected = itm_titles_list_expected_app_suffix_literal();

        $lines = [
            '--- Summary ---',
            'PHP files scanned: ' . (int) $stats['scanned'],
            'With <title>: ' . (int) $stats['with_title'],
            'No <title>: ' . (int) $stats['no_title'],
            'Match app suffix: ' . (int) $stats['match'],
            'Not match app suffix: ' . (int) $stats['not_match'],
            'Expected suffix: ' . $expected,
            '---------------',
        ];

        foreach ($lines as $line) {
            $escaped = $isCli ? $line : htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo $escaped . $nl;
        }

        echo $nl;
    }
}
