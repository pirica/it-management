<?php
/**
 * Decorated module link audit — runtime render probe + template scan.
 *
 * Why: Naive source scans false-positive on scaffold cr_render_cell_value() branches
 * (e.g. catalogs weblink HTML inside bank_accounts/index.php). This audit:
 * 1) Probes cr_render_cell_value() with the module's real $crud_table + db/01_schema columns.
 * 2) Scans template/HTML lines outside that function for decorated <a> tags.
 */

if (!function_exists('itm_module_decorated_links_collect_files')) {
    function itm_module_decorated_links_collect_files($modulesRoot)
    {
        $modulesRoot = rtrim((string)$modulesRoot, '/\\');
        if ($modulesRoot === '' || !is_dir($modulesRoot)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modulesRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }
            $files[] = $path;
        }
        sort($files);
        return $files;
    }
}

if (!function_exists('itm_module_decorated_links_slug_from_path')) {
    function itm_module_decorated_links_slug_from_path($modulesRoot, $filePath)
    {
        $modulesRoot = str_replace('\\', '/', rtrim((string)$modulesRoot, '/\\'));
        $filePath = str_replace('\\', '/', (string)$filePath);
        $prefix = $modulesRoot . '/';
        if (strpos($filePath, $prefix) !== 0) {
            return '';
        }
        $relative = substr($filePath, strlen($prefix));
        $parts = explode('/', $relative);
        return isset($parts[0]) ? (string)$parts[0] : '';
    }
}

if (!function_exists('itm_module_decorated_links_line_is_decorated_anchor')) {
    function itm_module_decorated_links_line_is_decorated_anchor($line)
    {
        $line = (string)$line;
        if (!preg_match('/<a\s[^>]*href\s*=/i', $line)) {
            return false;
        }
        if (preg_match('/\bclass\s*=\s*["\'][^"\']*\bbtn\b/i', $line)) {
            return false;
        }
        if (strpos($line, 'itm-plain-link') !== false) {
            return false;
        }
        if (preg_match('/text-decoration\s*:\s*none/i', $line) && preg_match('/color\s*:\s*inherit/i', $line)) {
            return false;
        }
        if (strpos($line, 'itm-user-config-sidebar-link') !== false) {
            return false;
        }
        return true;
    }
}

if (!function_exists('itm_module_decorated_links_html_is_decorated_anchor')) {
    function itm_module_decorated_links_html_is_decorated_anchor($html)
    {
        $html = (string)$html;
        if (stripos($html, '<a ') === false) {
            return false;
        }
        if (!preg_match('/<a\s[^>]*href\s*=/i', $html)) {
            return false;
        }
        return itm_module_decorated_links_line_is_decorated_anchor(preg_replace('/\s+/', ' ', $html));
    }
}

if (!function_exists('itm_module_decorated_links_extract_href')) {
    function itm_module_decorated_links_extract_href($line)
    {
        if (preg_match('/href\s*=\s*"([^"]*)"/i', $line, $m)) {
            return (string)$m[1];
        }
        if (preg_match("/href\s*=\s*'([^']*)'/i", $line, $m)) {
            return (string)$m[1];
        }
        if (preg_match('/href\s*=\s*<\?php\s+echo\s+[^;]+;\s*\?>/i', $line)) {
            return '(php-echo)';
        }
        return '(dynamic)';
    }
}

if (!function_exists('itm_module_decorated_links_read_crud_table')) {
    function itm_module_decorated_links_read_crud_table($indexPath)
    {
        if (!is_file($indexPath)) {
            return '';
        }
        $src = @file_get_contents($indexPath);
        if ($src === false) {
            return '';
        }
        if (preg_match("/\\\$crud_table\s*=\s*'([a-z0-9_]+)'/i", $src, $m)) {
            return (string)$m[1];
        }
        if (preg_match('/\$crud_table\s*=\s*"([a-z0-9_]+)"/i', $src, $m)) {
            return (string)$m[1];
        }
        return '';
    }
}

if (!function_exists('itm_module_decorated_links_schema_columns')) {
    /**
     * @return array<int, string>
     */
    function itm_module_decorated_links_schema_columns($repoRoot, $table)
    {
        $table = trim((string)$table);
        if ($table === '') {
            return [];
        }
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        $schemaFile = rtrim((string)$repoRoot, '/\\') . '/db/01_schema.sql';
        $src = @file_get_contents($schemaFile);
        if ($src === false) {
            $cache[$table] = [];
            return [];
        }
        $pattern = '/CREATE\s+TABLE\s+`?' . preg_quote($table, '/') . '`?\s*\((.*?)\)\s*ENGINE/si';
        if (!preg_match($pattern, $src, $m)) {
            $cache[$table] = [];
            return [];
        }
        $columns = [];
        foreach (preg_split('/\R/', $m[1]) as $line) {
            $line = trim($line);
            if (preg_match('/^`([a-z0-9_]+)`/i', $line, $col)) {
                $columns[] = (string)$col[1];
            }
        }
        $cache[$table] = array_values(array_unique($columns));
        return $cache[$table];
    }
}

if (!function_exists('itm_module_decorated_links_function_body')) {
    function itm_module_decorated_links_function_body($src, $functionName)
    {
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\)\s*\{/s';
        if (!preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $openBrace = (int)$m[0][1] + strlen($m[0][0]) - 1;
        $depth = 0;
        $len = strlen($src);
        for ($i = $openBrace; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $openBrace + 1, $i - $openBrace - 1);
                }
            }
        }
        return null;
    }
}

if (!function_exists('itm_module_decorated_links_function_line_ranges')) {
    /**
     * @return array{start:int,end:int}|null 1-based inclusive line numbers
     */
    function itm_module_decorated_links_function_line_ranges($src, $functionName)
    {
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(/s';
        if (!preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $startPos = (int)$m[0][1];
        $openBrace = strpos($src, '{', $startPos);
        if ($openBrace === false) {
            return null;
        }
        $depth = 0;
        $len = strlen($src);
        $closeBrace = null;
        for ($i = $openBrace; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $closeBrace = $i;
                    break;
                }
            }
        }
        if ($closeBrace === null) {
            return null;
        }
        $startLine = substr_count(substr($src, 0, $startPos), "\n") + 1;
        $endLine = substr_count(substr($src, 0, $closeBrace), "\n") + 1;
        return ['start' => $startLine, 'end' => $endLine];
    }
}

if (!function_exists('itm_module_decorated_links_probe_bootstrap')) {
    function itm_module_decorated_links_probe_bootstrap()
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        if (!function_exists('sanitize')) {
            function sanitize($data)
            {
                return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('itm_format_cell_scalar_display')) {
            function itm_format_cell_scalar_display($field, $value)
            {
                return (string)$value;
            }
        }
        if (!function_exists('itm_crud_render_audit_cell_value')) {
            function itm_crud_render_audit_cell_value($conn, $companyId, $field, $value)
            {
                return null;
            }
        }
        if (!function_exists('cr_normalize_external_url')) {
            function cr_normalize_external_url($text)
            {
                $text = trim((string)$text);
                if ($text === '') {
                    return '';
                }
                if (filter_var($text, FILTER_VALIDATE_URL)) {
                    return $text;
                }
                return 'https://' . ltrim($text, '/');
            }
        }
        if (!function_exists('cr_fk_label_by_id')) {
            function cr_fk_label_by_id($conn, $fk, $company_id, $rawId)
            {
                return '';
            }
        }
    }
}

if (!function_exists('itm_module_decorated_links_probe_render_function')) {
    /**
     * @param array<int, string> $columns
     * @return array<int, array{field:string,sample:string,html:string}>
     */
    function itm_module_decorated_links_probe_render_function($functionBody, $crudTable, array $columns)
    {
        $functionBody = (string)$functionBody;
        $crudTable = trim((string)$crudTable);
        if ($functionBody === '' || $crudTable === '') {
            return [];
        }
        itm_module_decorated_links_probe_bootstrap();
        $GLOBALS['crud_table'] = $crudTable;
        $GLOBALS['crud_action'] = 'index';
        $GLOBALS['conn'] = null;
        $GLOBALS['company_id'] = 1;
        $GLOBALS['fkMap'] = [];
        $probeName = 'itm_module_decorated_links_cr_render_probe_' . substr(md5($functionBody), 0, 12);
        if (!function_exists($probeName)) {
            eval('function ' . $probeName . '($table, $field, $value) {
                $conn = $GLOBALS["conn"] ?? null;
                $company_id = $GLOBALS["company_id"] ?? 0;
                $companyId = (int)$company_id;
            ' . $functionBody . "\n}");
        }
        $fields = array_values(array_unique(array_merge(
            $columns,
            ['weblink', 'source_url', 'email', 'website', 'website1_value', 'comments', 'product_url', 'image', 'image_url']
        )));
        $samples = [
            'text' => 'Sample value',
            'url' => 'https://example.com/path',
            'email' => 'user@example.com',
        ];
        $hits = [];
        $prevLevel = error_reporting(E_ERROR | E_PARSE);
        foreach ($fields as $field) {
            foreach ($samples as $sampleLabel => $sampleValue) {
                try {
                    $html = $probeName($crudTable, $field, $sampleValue);
                } catch (Throwable $e) {
                    continue;
                }
                if (!itm_module_decorated_links_html_is_decorated_anchor($html)) {
                    continue;
                }
                $hits[] = [
                    'field' => (string)$field,
                    'sample' => (string)$sampleLabel,
                    'html' => trim(preg_replace('/\s+/', ' ', (string)$html)),
                ];
            }
        }
        error_reporting($prevLevel);
        return $hits;
    }
}

if (!function_exists('itm_module_decorated_links_line_in_range')) {
    function itm_module_decorated_links_line_in_range($lineNo, array $ranges)
    {
        foreach ($ranges as $range) {
            if ($lineNo >= (int)$range['start'] && $lineNo <= (int)$range['end']) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('itm_module_decorated_links_collect_report')) {
    /**
     * @return array<int, array{module_slug:string,rel_file:string,line:int,href:string,source:string}>
     */
    function itm_module_decorated_links_collect_report($repoRoot, array $options = [])
    {
        $modulesRoot = rtrim((string)$repoRoot, '/\\') . '/modules';
        $moduleFilter = trim((string)($options['module_slug'] ?? ''));
        $rows = [];
        $repoNorm = str_replace('\\', '/', rtrim((string)$repoRoot, '/\\'));
        $crudTableBySlug = [];
        $schemaColumnsCache = [];

        foreach (itm_module_decorated_links_collect_files($modulesRoot) as $filePath) {
            $slug = itm_module_decorated_links_slug_from_path($modulesRoot, $filePath);
            if ($moduleFilter !== '' && $slug !== $moduleFilter) {
                continue;
            }
            if ($slug === '') {
                continue;
            }
            if (!isset($crudTableBySlug[$slug])) {
                $crudTableBySlug[$slug] = itm_module_decorated_links_read_crud_table(
                    $modulesRoot . '/' . $slug . '/index.php'
                );
            }
            $fileNorm = str_replace('\\', '/', $filePath);
            $relFile = (strpos($fileNorm, $repoNorm . '/') === 0)
                ? substr($fileNorm, strlen($repoNorm) + 1)
                : $fileNorm;
            $src = @file_get_contents($filePath);
            if ($src === false) {
                continue;
            }
            $lines = preg_split('/\R/', $src);
            if (!is_array($lines)) {
                continue;
            }

            $excludedRanges = [];
            foreach (['cr_render_cell_value', 'cr_render_list_cell_value'] as $fn) {
                $range = itm_module_decorated_links_function_line_ranges($src, $fn);
                if ($range !== null) {
                    $excludedRanges[] = $range;
                }
            }

            $crudTable = $crudTableBySlug[$slug];
            if ($crudTable !== '') {
                foreach (['cr_render_cell_value', 'cr_render_list_cell_value'] as $fn) {
                    $body = itm_module_decorated_links_function_body($src, $fn);
                    if ($body === null) {
                        continue;
                    }
                    if (!isset($schemaColumnsCache[$crudTable])) {
                        $schemaColumnsCache[$crudTable] = itm_module_decorated_links_schema_columns($repoRoot, $crudTable);
                    }
                    foreach (itm_module_decorated_links_probe_render_function($body, $crudTable, $schemaColumnsCache[$crudTable]) as $hit) {
                        $rows[] = [
                            'module_slug' => $slug,
                            'rel_file' => $relFile,
                            'line' => 0,
                            'href' => 'render:' . $hit['field'] . ':' . $hit['sample'],
                            'source' => 'render_probe',
                        ];
                    }
                }
            }

            foreach ($lines as $idx => $line) {
                $lineNo = (int)$idx + 1;
                if (itm_module_decorated_links_line_in_range($lineNo, $excludedRanges)) {
                    continue;
                }
                if (!itm_module_decorated_links_line_is_decorated_anchor($line)) {
                    continue;
                }
                $rows[] = [
                    'module_slug' => $slug,
                    'rel_file' => $relFile,
                    'line' => $lineNo,
                    'href' => itm_module_decorated_links_extract_href($line),
                    'source' => 'template',
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('itm_module_decorated_links_summarize_by_slug')) {
    /**
     * @param array<int, array{module_slug:string}> $rows
     * @return array<string, int>
     */
    function itm_module_decorated_links_summarize_by_slug(array $rows)
    {
        $counts = [];
        foreach ($rows as $row) {
            $slug = (string)($row['module_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
}
