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
            'ITM_PHPUNIT_MEMORY_LIMIT',
            'ITM_PYTHON_BIN',
            'ITM_SCREENSHOT_BASE_URL',
            'ITM_SCREENSHOT_FORM_LOGIN',
            'ITM_SCREENSHOT_MODULES',
            'ITM_SCREENSHOT_ONLY',
            'ITM_SKIP_DB_TESTS',
            'ITM_TEST_BASE_URL',
            'ITM_TEST_COMPANY_ID',
            'ITM_TEST_COOKIE',
            'ITM_USER',
            'ITM_API_V2_KEY',
            'ITM_API_V2_TICKET_ID',
            'ITM_API_V2_EQUIPMENT_ID',
            'ITM_API_V2_EQUIPMENT_STATUS_ID',
            'ITM_API_V2_EQUIPMENT_TYPE_ID',
            'ITM_DIST_API_KEY',
            'ITM_DIST_EXTERNAL_RESERVATION_ID',
            'ITM_HOSPITALITY_SCREENSHOT_DIR',
            'MYSQL_BIN',
            'MYSQL_HOST',
            'MYSQL_EXE',
            'MYSQL_PASSWORD',
            'MYSQL_PORT',
            'MYSQL_USER',
            'PHP_BIN',
            'PHP_EXE',
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

        // Why: Some helpers iterate env name lists then call getenv($var) — e.g. NVD API key aliases.
        if ($ext === 'php' && preg_match_all(
            '/foreach\s*\(\s*\[([^\]]+)\]\s+as\s+\$\w+\)\s*\{[^}]*getenv\s*\(\s*\$\w+/s',
            $content,
            $foreachMatches
        ) >= 1) {
            foreach ($foreachMatches[1] as $arrayLiteral) {
                if (preg_match_all('/[\'"]([A-Z][A-Z0-9_]*)[\'"]/', (string)$arrayLiteral, $nameMatches) < 1) {
                    continue;
                }
                foreach ($nameMatches[1] as $name) {
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

if (!function_exists('itm_env_vars_audit_unquote_dotenv_value')) {
    function itm_env_vars_audit_unquote_dotenv_value($value)
    {
        $value = trim((string)$value);
        if (
            (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"')
            || (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'")
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('itm_env_vars_audit_parse_dotenv_values')) {
    /**
     * Active KEY=value assignments from a dotenv file (comments ignored).
     *
     * @param string $path
     * @return array{readable:bool,values:array<string,string>}
     */
    function itm_env_vars_audit_parse_dotenv_values($path)
    {
        $result = [
            'readable' => false,
            'values' => [],
        ];

        if (!is_readable($path)) {
            return $result;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return $result;
        }

        $result['readable'] = true;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $eqPos));
            if ($name === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
                continue;
            }

            $rawValue = trim(substr($line, $eqPos + 1));
            $result['values'][$name] = itm_env_vars_audit_unquote_dotenv_value($rawValue);
        }

        ksort($result['values'], SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    }
}

if (!function_exists('itm_env_vars_audit_is_sensitive_env_name')) {
    function itm_env_vars_audit_is_sensitive_env_name($name)
    {
        $upper = strtoupper((string)$name);
        if ($upper === '') {
            return false;
        }

        if (strpos($upper, 'PASS') !== false) {
            return true;
        }
        if (strpos($upper, 'SECRET') !== false) {
            return true;
        }
        if (strpos($upper, 'TOKEN') !== false) {
            return true;
        }
        if (substr($upper, -4) === '_KEY' || substr($upper, -7) === 'API_KEY') {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_env_vars_audit_format_live_env_value_display')) {
    /**
     * Live `.env` column — never echo real values (only presence).
     *
     * @param string|null $value null = key absent in live file
     */
    function itm_env_vars_audit_format_live_env_value_display($value)
    {
        if ($value === null) {
            return '—';
        }

        if ($value === '') {
            return '(empty)';
        }

        return '(Not Empty)';
    }
}

if (!function_exists('itm_env_vars_audit_format_env_value_display')) {
    /**
     * Safe display for audit tables — masks secrets; never echo raw passwords/keys in browser.
     *
     * @param string $name
     * @param string|null $value null = key absent in that file
     */
    function itm_env_vars_audit_format_env_value_display($name, $value)
    {
        if ($value === null) {
            return '—';
        }

        if ($value === '') {
            return '(empty)';
        }

        if (itm_env_vars_audit_is_sensitive_env_name($name)) {
            return '(set, ' . strlen((string)$value) . ' chars)';
        }

        $text = (string)$value;
        if (strlen($text) > 72) {
            return substr($text, 0, 69) . '...';
        }

        return $text;
    }
}

if (!function_exists('itm_env_vars_audit_compare_env_files')) {
    /**
     * Row-by-row compare of live `.env` vs `.env.example` (live values never shown in output).
     *
     * @param string $root
     * @return array<string,mixed>
     */
    function itm_env_vars_audit_compare_env_files($root)
    {
        $examplePath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env.example';
        $livePath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env';

        $exampleNames = itm_env_vars_audit_parse_dotenv_file($examplePath);
        $exampleParsed = itm_env_vars_audit_parse_dotenv_values($examplePath);
        $liveParsed = itm_env_vars_audit_parse_dotenv_values($livePath);

        $exampleSet = array_fill_keys($exampleNames, true);
        $allKeys = array_unique(array_merge($exampleNames, array_keys($liveParsed['values'])));
        sort($allKeys, SORT_NATURAL | SORT_FLAG_CASE);

        $rows = [];
        $summary = [
            'aligned' => 0,
            'value_drift' => 0,
            'missing_in_live' => 0,
            'only_in_live' => 0,
            'live_file_missing' => !$liveParsed['readable'],
        ];

        foreach ($allKeys as $name) {
            $inExample = isset($exampleSet[$name]);
            $inLive = $liveParsed['readable'] && array_key_exists($name, $liveParsed['values']);
            $exampleValue = $inExample ? ($exampleParsed['values'][$name] ?? '') : null;
            $liveValue = $inLive ? $liveParsed['values'][$name] : null;

            if (!$liveParsed['readable']) {
                $status = $inExample ? 'no_live_file' : 'only_in_live';
            } elseif (!$inExample && $inLive) {
                $status = 'only_in_live';
            } elseif ($inExample && !$inLive) {
                $status = 'missing_in_live';
            } elseif ($exampleValue === $liveValue) {
                $status = 'aligned';
            } else {
                $status = 'value_drift';
            }

            if ($status === 'aligned') {
                $summary['aligned']++;
            } elseif ($status === 'value_drift') {
                $summary['value_drift']++;
            } elseif ($status === 'missing_in_live' || $status === 'no_live_file') {
                $summary['missing_in_live']++;
            } elseif ($status === 'only_in_live') {
                $summary['only_in_live']++;
            }

            $rows[] = [
                'name' => $name,
                'in_example' => $inExample,
                'in_live' => $inLive,
                'example_value' => $exampleValue,
                'live_nonempty' => $inLive && $liveValue !== '',
                'example_display' => itm_env_vars_audit_format_env_value_display($name, $exampleValue),
                'live_display' => itm_env_vars_audit_format_live_env_value_display($liveValue),
                'status' => $status,
            ];
        }

        return [
            'env_example_path' => $examplePath,
            'env_live_path' => $livePath,
            'env_live_readable' => $liveParsed['readable'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }
}

if (!function_exists('itm_env_vars_audit_status_label')) {
    function itm_env_vars_audit_status_label($status)
    {
        $map = [
            'aligned' => 'aligned',
            'value_drift' => 'value drift',
            'missing_in_live' => 'missing in .env',
            'only_in_live' => 'only in .env',
            'no_live_file' => 'no .env file',
        ];

        return $map[(string)$status] ?? (string)$status;
    }
}

if (!function_exists('itm_env_vars_audit_print_env_compare_cli')) {
    /**
     * @param array<string,mixed> $compare
     * @param string $nl
     */
    function itm_env_vars_audit_print_env_compare_cli(array $compare, $nl)
    {
        echo 'Compare live .env vs .env.example' . $nl;
        echo '.env.example: ' . str_replace('\\', '/', (string)$compare['env_example_path']) . $nl;
        echo 'live .env: ' . str_replace('\\', '/', (string)$compare['env_live_path']);
        if (empty($compare['env_live_readable'])) {
            echo ' (not found or not readable)';
        }
        echo $nl;

        $summary = $compare['summary'] ?? [];
        echo 'Summary: aligned=' . (int)($summary['aligned'] ?? 0)
            . ' value_drift=' . (int)($summary['value_drift'] ?? 0)
            . ' missing_in_live=' . (int)($summary['missing_in_live'] ?? 0)
            . ' only_in_live=' . (int)($summary['only_in_live'] ?? 0) . $nl;
        echo $nl;

        $header = sprintf(
            '%-32s | %-22s | %-22s | %s',
            'Variable',
            '.env.example',
            'live .env',
            'Status'
        );
        echo $header . $nl;
        echo str_repeat('-', min(120, strlen($header))) . $nl;

        foreach ($compare['rows'] ?? [] as $row) {
            $name = (string)($row['name'] ?? '');
            $exampleDisplay = (string)($row['example_display'] ?? '—');
            $liveDisplay = (string)($row['live_display'] ?? '—');
            $status = itm_env_vars_audit_status_label((string)($row['status'] ?? ''));

            if (strlen($exampleDisplay) > 22) {
                $exampleDisplay = substr($exampleDisplay, 0, 19) . '...';
            }
            if (strlen($liveDisplay) > 22) {
                $liveDisplay = substr($liveDisplay, 0, 19) . '...';
            }

            printf(
                "%-32s | %-22s | %-22s | %s" . $nl,
                $name,
                $exampleDisplay,
                $liveDisplay,
                $status
            );
        }
        echo $nl;
    }
}

if (!function_exists('itm_env_vars_audit_echo_env_compare_html')) {
    /**
     * @param array<string,mixed> $compare
     */
    function itm_env_vars_audit_echo_env_compare_html(array $compare)
    {
        $esc = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };

        $summary = $compare['summary'] ?? [];
        $examplePath = str_replace('\\', '/', (string)($compare['env_example_path'] ?? ''));
        $livePath = str_replace('\\', '/', (string)($compare['env_live_path'] ?? ''));
        $liveReadable = !empty($compare['env_live_readable']);

        echo '<div class="itm-env-compare-card" style="margin:16px 0;font-family:Segoe UI,system-ui,sans-serif;font-size:14px;line-height:1.45;">';
        echo '<h2 style="margin:0 0 8px;font-size:1.1rem;">Compare live .env vs .env.example</h2>';
        echo '<p style="margin:0 0 12px;color:#57606a;">';
        echo '<code>' . $esc($examplePath) . '</code>';
        echo ' · live: <code>' . $esc($livePath) . '</code>';
        if (!$liveReadable) {
            echo ' — <strong>not found</strong> (copy <code>.env.example</code> to <code>.env</code> for local overrides)';
        }
        echo '</p>';
        echo '<p style="margin:0 0 12px;">';
        echo 'aligned <strong>' . (int)($summary['aligned'] ?? 0) . '</strong> · ';
        echo 'value drift <strong>' . (int)($summary['value_drift'] ?? 0) . '</strong> · ';
        echo 'missing in .env <strong>' . (int)($summary['missing_in_live'] ?? 0) . '</strong> · ';
        echo 'only in .env <strong>' . (int)($summary['only_in_live'] ?? 0) . '</strong>';
        echo '</p>';
        echo '<div style="overflow-x:auto;max-width:100%;">';
        echo '<table style="border-collapse:collapse;width:max-content;min-width:100%;font-size:13px;">';
        echo '<thead><tr>';
        echo '<th style="border:1px solid #d0d7de;padding:8px 10px;background:#f6f8fa;text-align:left;">Variable</th>';
        echo '<th style="border:1px solid #d0d7de;padding:8px 10px;background:#f6f8fa;text-align:left;">.env.example</th>';
        echo '<th style="border:1px solid #d0d7de;padding:8px 10px;background:#f6f8fa;text-align:left;">live .env</th>';
        echo '<th style="border:1px solid #d0d7de;padding:8px 10px;background:#f6f8fa;text-align:left;">Status</th>';
        echo '</tr></thead><tbody>';

        foreach ($compare['rows'] ?? [] as $row) {
            $status = (string)($row['status'] ?? '');
            $rowStyle = '';
            if ($status === 'value_drift') {
                $rowStyle = 'background:#fff8c5;';
            } elseif ($status === 'only_in_live' || $status === 'missing_in_live' || $status === 'no_live_file') {
                $rowStyle = 'background:#fff1e5;';
            }

            echo '<tr style="' . $esc($rowStyle) . '">';
            echo '<td style="border:1px solid #d0d7de;padding:8px 10px;white-space:nowrap;"><code>' . $esc($row['name'] ?? '') . '</code></td>';
            echo '<td style="border:1px solid #d0d7de;padding:8px 10px;white-space:nowrap;">' . $esc($row['example_display'] ?? '—') . '</td>';
            echo '<td style="border:1px solid #d0d7de;padding:8px 10px;white-space:nowrap;">' . $esc($row['live_display'] ?? '—') . '</td>';
            echo '<td style="border:1px solid #d0d7de;padding:8px 10px;white-space:nowrap;">' . $esc(itm_env_vars_audit_status_label($status)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
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
            'env_compare' => itm_env_vars_audit_compare_env_files($root),
        ];
    }
}
