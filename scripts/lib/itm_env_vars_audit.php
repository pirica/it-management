<?php
/**
 * Why: Static scan for getenv / $_ENV / shell / Python env reads so .env.example stays aligned with code.
 */

if (!function_exists('itm_env_vars_audit_project_root')) {
    function itm_env_vars_audit_project_root()
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('itm_env_vars_audit_ignore_dir_names')) {
    /**
     * @return array<int,string>
     */
    function itm_env_vars_audit_ignore_dir_names()
    {
        return [
            '.git',
            '.github',
            'vendor',
            'phpunit',
            'qa-reports',
            'node_modules',
        ];
    }
}

if (!function_exists('itm_env_vars_audit_should_skip_path')) {
    function itm_env_vars_audit_should_skip_path($absolutePath)
    {
        $normalized = str_replace('\\', '/', (string)$absolutePath);
        if (strpos($normalized, '/phpunit/coverage/') !== false) {
            return true;
        }

        foreach (itm_env_vars_audit_ignore_dir_names() as $dirName) {
            if (preg_match('#/(?:' . preg_quote($dirName, '#') . ')(?:/|$)#', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_env_vars_audit_known_os_vars')) {
    /**
     * @return array<int,string>
     */
    function itm_env_vars_audit_known_os_vars()
    {
        return [
            'HOME',
            'PATH',
            'PATHEXT',
            'SystemRoot',
            'TEMP',
            'TMP',
            'USERPROFILE',
            'WINDIR',
        ];
    }
}

if (!function_exists('itm_env_vars_audit_known_tooling_vars')) {
    /**
     * @return array<int,string>
     */
    function itm_env_vars_audit_known_tooling_vars()
    {
        return [
            'DERIVED_TABLE_COUNT',
            'EXPECTED_TABLE_COUNT',
            'ITM_BASE_URL',
            'ITM_BSMA_JOURNAL_ACCESS_LEGACY_MIN',
            'ITM_BSMA_JOURNAL_ACCESS_OPTIMIZED_MAX',
            'ITM_BSMA_JOURNAL_STRUCTURE_OPTIMIZED_MAX',
            'ITM_BSMA_JOURNAL_TIMING_MIN_PCT',
            'ITM_BSMA_MAX_FULL_QUERIES',
            'ITM_BSMA_MIN_REDUCTION_PCT',
            'ITM_CLI_SCRIPT',
            'ITM_COMPANY_ID',
            'ITM_COVERAGE',
            'ITM_DB_HOST',
            'ITM_DB_NAME',
            'ITM_DB_PASS',
            'ITM_DB_USER',
            'ITM_FIELDS_MISSING_HTTP_SCRAPE',
            'ITM_HTTP_ENDPOINT_CONTRACT_TEST',
            'ITM_IDF_ID',
            'ITM_META_CACHE_TABLE',
            'ITM_OPS_SEARCH_DEMO_KEYWORD',
            'ITM_PASS',
            'ITM_PHP_BIN',
            'ITM_PYTHON_BIN',
            'ITM_SCREENSHOT_BASE_URL',
            'ITM_SCREENSHOT_MODULES',
            'ITM_SCREENSHOT_ONLY',
            'ITM_SKIP_DB_TESTS',
            'ITM_TEST_BASE_URL',
            'ITM_TEST_COMPANY_ID',
            'ITM_TEST_COOKIE',
            'ITM_USER',
            'MYSQL_HOST',
            'MYSQL_PASSWORD',
            'MYSQL_USER',
            'PHP_BIN',
            'ROOT',
            'TABLE_COUNT',
        ];
    }
}

if (!function_exists('itm_env_vars_audit_collect_from_content')) {
    /**
     * @param string $relativePath Repo-relative path for reporting.
     * @param string $content
     * @return array<string,array<int,string>> var => example relative paths
     */
    function itm_env_vars_audit_collect_from_content($relativePath, $content)
    {
        $found = [];
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        $patterns = [];
        if ($ext === 'php') {
            $patterns = [
                '/getenv\s*\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*\)/',
                '/\$_ENV\s*\[\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*\]/',
            ];
        } elseif ($ext === 'py') {
            $patterns = [
                '/os\.environ\.get\s*\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/',
                '/os\.getenv\s*\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/',
                '/os\.environ\s*\[\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*\]/',
            ];
        } elseif ($ext === 'sh') {
            $patterns = [
                '/\$\{([A-Z][A-Z0-9_]*)(?::-|})/',
            ];
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches) < 1 || empty($matches[1])) {
                continue;
            }
            foreach ($matches[1] as $name) {
                $name = (string)$name;
                if ($name === '') {
                    continue;
                }
                if (!isset($found[$name])) {
                    $found[$name] = [];
                }
                if (!in_array($relativePath, $found[$name], true)) {
                    $found[$name][] = $relativePath;
                }
            }
        }

        return $found;
    }
}

if (!function_exists('itm_env_vars_audit_merge_maps')) {
    /**
     * @param array<string,array<int,string>> $into
     * @param array<string,array<int,string>> $from
     * @return array<string,array<int,string>>
     */
    function itm_env_vars_audit_merge_maps(array $into, array $from)
    {
        foreach ($from as $name => $paths) {
            if (!isset($into[$name])) {
                $into[$name] = [];
            }
            foreach ($paths as $path) {
                if (!in_array($path, $into[$name], true)) {
                    $into[$name][] = $path;
                }
            }
        }

        return $into;
    }
}

if (!function_exists('itm_env_vars_audit_scan_tree')) {
    /**
     * @param string $root
     * @return array<string,array<int,string>>
     */
    function itm_env_vars_audit_scan_tree($root)
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return [];
        }

        $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');
        $used = [];
        $extensions = ['php', 'py', 'sh'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolute = str_replace('\\', '/', $fileInfo->getPathname());
            if (itm_env_vars_audit_should_skip_path($absolute)) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if (!in_array($ext, $extensions, true)) {
                continue;
            }

            $relative = ltrim(substr($absolute, strlen($rootReal)), '/');
            $content = @file_get_contents($absolute);
            if (!is_string($content) || $content === '') {
                continue;
            }

            $used = itm_env_vars_audit_merge_maps(
                $used,
                itm_env_vars_audit_collect_from_content($relative, $content)
            );
        }

        ksort($used, SORT_NATURAL | SORT_FLAG_CASE);

        return $used;
    }
}

if (!function_exists('itm_env_vars_audit_parse_dotenv_file')) {
    /**
     * @param string $path
     * @return array<int,string>
     */
    function itm_env_vars_audit_parse_dotenv_file($path)
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $names = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                if (preg_match('/#\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $commentMatch) === 1) {
                    $names[$commentMatch[1]] = true;
                }
                continue;
            }

            if (preg_match('/^([A-Z][A-Z0-9_]*)\s*=/', $line, $match) !== 1) {
                continue;
            }

            $names[$match[1]] = true;
        }

        $list = array_keys($names);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}

if (!function_exists('itm_env_vars_audit_classify_name')) {
    function itm_env_vars_audit_classify_name($name)
    {
        $name = (string)$name;
        if (in_array($name, itm_env_vars_audit_known_os_vars(), true)) {
            return 'os';
        }
        if (in_array($name, itm_env_vars_audit_known_tooling_vars(), true)) {
            return 'tooling';
        }

        return 'app';
    }
}

if (!function_exists('itm_env_vars_audit_build_report')) {
    /**
     * @param string $root
     * @return array<string,mixed>
     */
    function itm_env_vars_audit_build_report($root)
    {
        $used = itm_env_vars_audit_scan_tree($root);
        $examplePath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env.example';
        $documented = itm_env_vars_audit_parse_dotenv_file($examplePath);
        $documentedSet = array_fill_keys($documented, true);
        $usedNames = array_keys($used);

        $matched = [];
        $exampleOnly = [];
        $undocumented = [
            'app' => [],
            'tooling' => [],
            'os' => [],
        ];

        foreach ($documented as $name) {
            if (isset($used[$name])) {
                $matched[$name] = $used[$name];
            } else {
                $exampleOnly[] = $name;
            }
        }

        foreach ($usedNames as $name) {
            if (isset($documentedSet[$name])) {
                continue;
            }
            $bucket = itm_env_vars_audit_classify_name($name);
            $undocumented[$bucket][$name] = $used[$name];
        }

        foreach ($undocumented as $bucket => $items) {
            ksort($undocumented[$bucket], SORT_NATURAL | SORT_FLAG_CASE);
        }
        ksort($matched, SORT_NATURAL | SORT_FLAG_CASE);
        sort($exampleOnly, SORT_NATURAL | SORT_FLAG_CASE);

        $strictIssues = count($exampleOnly);
        $strictIssues += count($undocumented['app']);

        return [
            'env_example_path' => $examplePath,
            'documented' => $documented,
            'used' => $used,
            'matched' => $matched,
            'example_only' => $exampleOnly,
            'undocumented' => $undocumented,
            'strict_issue_count' => $strictIssues,
        ];
    }
}
