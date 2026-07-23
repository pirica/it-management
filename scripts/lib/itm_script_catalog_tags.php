<?php
/**
 * Static analysis: which schema tables a scripts/*.php entry touches via $conn.
 *
 * Scan scope: entry file + transitive requires under scripts/ only (not config/includes/modules).
 */

if (!function_exists('itm_script_catalog_tags_schema_tables')) {
    /**
     * @return array<string, true>
     */
    function itm_script_catalog_tags_schema_tables(string $rootPath): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $schemaPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql';
        $tables = [];
        if (!is_file($schemaPath)) {
            $cache = $tables;
            return $tables;
        }

        $content = file_get_contents($schemaPath);
        if (!is_string($content)) {
            $cache = $tables;
            return $tables;
        }

        if (preg_match_all('/CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $content, $matches)) {
            foreach ($matches[1] as $name) {
                $tables[(string)$name] = true;
            }
        }

        $cache = $tables;
        return $tables;
    }
}

if (!function_exists('itm_script_catalog_tags_scripts_root')) {
    function itm_script_catalog_tags_scripts_root(string $rootPath): string
    {
        return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_script_catalog_tags_resolve_require_path')) {
    /**
     * Resolve a require path relative to the including file; only paths under scripts/.
     */
    function itm_script_catalog_tags_resolve_require_path(string $includingFile, string $requireExpr, string $scriptsRoot): ?string
    {
        $requireExpr = trim($requireExpr);
        if ($requireExpr === '') {
            return null;
        }

        $path = $requireExpr;
        if (preg_match('/__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]/', $requireExpr, $m)) {
            $path = $m[1];
        } elseif (preg_match('/dirname\s*\(\s*__DIR__\s*\)\s*\.\s*[\'"]([^\'"]+)[\'"]/', $requireExpr, $m)) {
            $path = '..' . $m[1];
        } elseif (preg_match('/[\'"]([^\'"]+\.php)[\'"]/', $requireExpr, $m)) {
            $path = $m[1];
        }

        $path = str_replace('\\', '/', $path);
        $includingDir = dirname($includingFile);
        $candidate = $includingDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));

        if (!is_file($candidate)) {
            $candidate = rtrim($scriptsRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
        }

        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }

        $scriptsRootReal = realpath($scriptsRoot);
        if ($scriptsRootReal === false) {
            return null;
        }

        $normalizedReal = str_replace('\\', '/', $real);
        $normalizedScripts = str_replace('\\', '/', $scriptsRootReal);
        if (strpos($normalizedReal, $normalizedScripts) !== 0) {
            return null;
        }

        return $real;
    }
}

if (!function_exists('itm_script_catalog_tags_collect_script_bundle')) {
    /**
     * @return array<int, string> absolute file paths
     */
    function itm_script_catalog_tags_collect_script_bundle(string $entryPath, string $scriptsRoot): array
    {
        $scriptsRootReal = realpath($scriptsRoot);
        if ($scriptsRootReal === false) {
            return [];
        }

        $queue = [realpath($entryPath)];
        $seen = [];
        $bundle = [];

        while (!empty($queue)) {
            $file = array_shift($queue);
            if ($file === false || isset($seen[$file])) {
                continue;
            }
            $seen[$file] = true;

            $normalized = str_replace('\\', '/', $file);
            $scriptsNorm = str_replace('\\', '/', $scriptsRootReal);
            if (strpos($normalized, $scriptsNorm) !== 0 || !is_file($file)) {
                continue;
            }

            $bundle[] = $file;
            $content = file_get_contents($file);
            if (!is_string($content)) {
                continue;
            }

            if (preg_match_all('/\b(?:require_once|require|include_once|include)\s*(?:\(?\s*)?([^;)\n]+)/i', $content, $matches)) {
                foreach ($matches[1] as $expr) {
                    $expr = trim((string)$expr);
                    $expr = rtrim($expr, ');');
                    $resolved = itm_script_catalog_tags_resolve_require_path($file, $expr, $scriptsRoot);
                    if ($resolved !== null && !isset($seen[$resolved])) {
                        $queue[] = $resolved;
                    }
                }
            }
        }

        return $bundle;
    }
}

if (!function_exists('itm_script_catalog_tags_excluded_table')) {
    function itm_script_catalog_tags_excluded_table(string $tableName): bool
    {
        static $excluded = [
            'information_schema' => true,
            'performance_schema' => true,
            'mysql' => true,
        ];

        return isset($excluded[strtolower($tableName)]);
    }
}

if (!function_exists('itm_script_catalog_tags_collect_tables_from_sql')) {
    /**
     * @param array<string, true> $schemaTables
     * @return array<string, string>
     */
    function itm_script_catalog_tags_collect_tables_from_sql(string $sql, array $schemaTables): array
    {
        $tables = [];
        $patterns = [
            '/\bINSERT(?:\s+IGNORE)?\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bDELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bUPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET\b/i',
            '/\bFROM\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bJOIN\s+`?([a-zA-Z0-9_]+)`?/i',
            '/\bINTO\s+`?([a-zA-Z0-9_]+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $sql, $matches)) {
                continue;
            }
            foreach ($matches[1] as $tableName) {
                $tableName = (string)$tableName;
                if (itm_script_catalog_tags_excluded_table($tableName)) {
                    continue;
                }
                if (isset($schemaTables[$tableName])) {
                    $tables[$tableName] = $tableName;
                }
            }
        }

        return $tables;
    }
}

if (!function_exists('itm_script_catalog_tags_extract_crud_table_literal')) {
    function itm_script_catalog_tags_extract_crud_table_literal(string $entrySource): ?string
    {
        if (preg_match('/\$crud_table\s*=\s*\$crud_table\s*\?\?\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $entrySource, $match)) {
            return $match[1];
        }
        if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $entrySource, $match)) {
            return $match[1];
        }

        return null;
    }
}

if (!function_exists('itm_script_catalog_tags_extract_tables_from_source')) {
    /**
     * @param array<string, true> $schemaTables
     * @return array<string, string>
     */
    function itm_script_catalog_tags_extract_tables_from_source(string $source, array $schemaTables, bool $includeConnLiteralArgs = true): array
    {
        $tables = itm_script_catalog_tags_collect_tables_from_sql($source, $schemaTables);

        $sqlPatterns = [
            '/mysqli_prepare\s*\(\s*\$conn\s*,\s*["\']([^"\']+)["\']/is',
            '/mysqli_query\s*\(\s*\$conn\s*,\s*["\']([^"\']+)["\']/is',
            '/\$conn\s*->\s*prepare\s*\(\s*["\']([^"\']+)["\']/is',
            '/\$conn\s*->\s*query\s*\(\s*["\']([^"\']+)["\']/is',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (!preg_match_all($pattern, $source, $matches)) {
                continue;
            }
            foreach ($matches[1] as $sqlFragment) {
                foreach (itm_script_catalog_tags_collect_tables_from_sql((string)$sqlFragment, $schemaTables) as $tableName) {
                    $tables[$tableName] = $tableName;
                }
            }
        }

        if ($includeConnLiteralArgs && preg_match_all('/\(\s*\$conn\s*,\s*[\'"]([a-zA-Z0-9_]+)[\'"]/i', $source, $literalMatches)) {
            foreach ($literalMatches[1] as $tableName) {
                $tableName = (string)$tableName;
                if (isset($schemaTables[$tableName])) {
                    $tables[$tableName] = $tableName;
                }
            }
        }

        return $tables;
    }
}

if (!function_exists('itm_script_catalog_tags_resolve_scripts_data_path')) {
    /**
     * Resolve a data/*.json|txt|md or scripts/* reference to a file under scripts/data/ or scripts/.
     */
    function itm_script_catalog_tags_resolve_scripts_data_path(string $relativePath, string $scriptsRoot): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return null;
        }

        $scriptsRootReal = realpath($scriptsRoot);
        if ($scriptsRootReal === false) {
            return null;
        }

        $candidates = [];
        if (stripos($relativePath, 'scripts/data/') === 0) {
            $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, substr($relativePath, strlen('scripts/data/')));
        } elseif (stripos($relativePath, 'data/') === 0) {
            $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        } elseif (preg_match('#^/data/([a-zA-Z0-9_.-]+\.(?:json|txt|md))$#i', '/' . $relativePath, $dataMatch)) {
            $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, (string)$dataMatch[1]);
        } elseif (preg_match('#^/([a-zA-Z0-9_.-]+\.(?:json|txt|md))$#i', '/' . $relativePath, $rootMatch)) {
            $basename = (string)$rootMatch[1];
            $ext = strtolower((string)pathinfo($basename, PATHINFO_EXTENSION));
            $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $basename;
            if ($ext !== 'md') {
                $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . $basename;
            }
        } elseif (preg_match('#^(?:scripts/)?([a-zA-Z0-9_.-]+\.(?:json|txt|md))$#i', $relativePath, $basenameMatch)) {
            $basename = (string)$basenameMatch[1];
            $ext = strtolower((string)pathinfo($basename, PATHINFO_EXTENSION));
            $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $basename;
            if ($ext !== 'md') {
                $candidates[] = $scriptsRootReal . DIRECTORY_SEPARATOR . $basename;
            }
        } else {
            return null;
        }

        $scriptsNorm = str_replace('\\', '/', $scriptsRootReal);
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false || !is_file($real)) {
                continue;
            }
            $normalized = str_replace('\\', '/', $real);
            if (strpos($normalized, $scriptsNorm) === 0) {
                return $real;
            }
        }

        return null;
    }
}

if (!function_exists('itm_script_catalog_tags_extract_tables_from_plain_text')) {
    /**
     * @param array<string, true> $schemaTables
     * @return array<string, string>
     */
    function itm_script_catalog_tags_extract_tables_from_plain_text(string $text, array $schemaTables): array
    {
        $tables = [];
        $tableNames = array_keys($schemaTables);
        usort($tableNames, static function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($tableNames as $tableName) {
            if (preg_match('/\b' . preg_quote($tableName, '/') . '\b/', $text)) {
                $tables[$tableName] = $tableName;
            }
        }

        return $tables;
    }
}

if (!function_exists('itm_script_catalog_tags_normalize_data_ref')) {
    function itm_script_catalog_tags_normalize_data_ref(string $token): string
    {
        $token = str_replace('\\', '/', trim($token));
        if (preg_match('#^(?:scripts/)?data/(.+)$#i', $token, $match)) {
            return 'data/' . $match[1];
        }
        if (preg_match('#^/data/(.+)$#i', $token, $match)) {
            return 'data/' . $match[1];
        }
        if (preg_match('#^scripts/(.+)$#i', $token, $match)) {
            return $match[1];
        }

        return ltrim($token, '/');
    }
}

if (!function_exists('itm_script_catalog_tags_classify_data_refs')) {
    /**
     * Detect scripts/data/* and scripts/* .json / .txt / .md references in source or catalog copy.
     *
     * @return array{has_info: bool, has_markdown: bool, paths: array<int, string>}
     */
    function itm_script_catalog_tags_classify_data_refs(string $source, string $scriptsRoot): array
    {
        $hasInfo = false;
        $hasMarkdown = false;
        $paths = [];
        $patterns = [
            '#((?:scripts[/\\\\]+)?data[/\\\\][a-zA-Z0-9_.-]+\.(?:json|txt|md))#i',
            '#((?:scripts[/\\\\]+)[a-zA-Z0-9_.-]+\.(?:json|txt|md))#i',
            '#(/data/[a-zA-Z0-9_.-]+\.(?:json|txt|md))#i',
            '#[\'"](/[a-zA-Z0-9_.-]+\.(?:json|txt|md))[\'"]#i',
            '#[\'"]([a-zA-Z0-9_.-]+\.(?:json|txt|md))[\'"]#i',
        ];

        $tokens = [];
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $source, $matches)) {
                continue;
            }
            $found = isset($matches[1]) && $matches[1] !== [] ? $matches[1] : $matches[0];
            foreach ($found as $token) {
                $tokens[str_replace('\\', '/', (string)$token)] = true;
            }
        }

        foreach (array_keys($tokens) as $token) {
            $normalized = itm_script_catalog_tags_normalize_data_ref($token);
            $resolved = itm_script_catalog_tags_resolve_scripts_data_path($normalized, $scriptsRoot);
            $ext = strtolower((string)pathinfo($resolved !== null ? $resolved : $normalized, PATHINFO_EXTENSION));

            $scoped = $resolved !== null
                || (bool)preg_match('#(?:scripts/)?data/[a-zA-Z0-9_.-]+\.(?:json|txt|md)#i', $token)
                || (bool)preg_match('#scripts/[a-zA-Z0-9_.-]+\.(?:json|txt|md)#i', $token);

            if ($ext === 'md' && $resolved === null && !preg_match('#scripts/[a-zA-Z0-9_.-]+\.md#i', $token)
                && !preg_match('#(?:scripts/)?data/[a-zA-Z0-9_.-]+\.md#i', $token)) {
                continue;
            }

            if (!$scoped) {
                continue;
            }

            if ($resolved !== null) {
                $paths[$resolved] = $resolved;
            }

            if (in_array($ext, ['json', 'txt'], true)) {
                $hasInfo = true;
            } elseif ($ext === 'md') {
                $hasMarkdown = true;
            }
        }

        return [
            'has_info' => $hasInfo,
            'has_markdown' => $hasMarkdown,
            'paths' => array_values($paths),
        ];
    }
}

if (!function_exists('itm_script_catalog_tags_info_spawn_targets')) {
    /**
     * @return array<int, string> absolute paths
     */
    function itm_script_catalog_tags_info_spawn_targets(string $entrySource, string $scriptsRoot): array
    {
        return itm_script_catalog_tags_classify_data_refs($entrySource, $scriptsRoot)['paths'];
    }
}

if (!function_exists('itm_script_catalog_tags_merge_kind_tags')) {
    /**
     * @param array<int, string> $tags
     * @return array<int, string>
     */
    function itm_script_catalog_tags_merge_kind_tags(array $tags, bool $hasInfo, bool $hasMarkdown): array
    {
        $kindTags = [];
        if ($hasInfo) {
            $kindTags[] = 'Info';
        }
        if ($hasMarkdown) {
            $kindTags[] = 'Markdown';
        }
        if ($kindTags === []) {
            return $tags;
        }

        $merged = array_values(array_filter($tags, static function ($tag) {
            return $tag !== 'Codebase';
        }));
        if ($merged === []) {
            return $kindTags;
        }

        foreach ($kindTags as $kindTag) {
            if (!in_array($kindTag, $merged, true)) {
                $merged[] = $kindTag;
            }
        }

        return $merged;
    }
}

if (!function_exists('itm_script_catalog_tags_spawn_targets')) {
    /**
     * @return array<int, string> absolute paths under scripts/
     */
    function itm_script_catalog_tags_spawn_targets(string $entrySource, string $scriptsRoot): array
    {
        $targets = [];
        if (!preg_match_all('#(?:scripts[/\\\\]+)([a-zA-Z0-9_.-]+\.php)#i', $entrySource, $matches)) {
            return $targets;
        }

        $scriptsRootReal = realpath($scriptsRoot);
        if ($scriptsRootReal === false) {
            return $targets;
        }

        foreach ($matches[1] as $basename) {
            $candidate = $scriptsRootReal . DIRECTORY_SEPARATOR . (string)$basename;
            if (is_file($candidate)) {
                $targets[$candidate] = $candidate;
            }
        }

        return array_values($targets);
    }
}

if (!function_exists('itm_script_catalog_tags_resolve_tags')) {
    /**
     * @param array<int, string> $tables
     * @return array<int, string>
     */
    function itm_script_catalog_tags_resolve_tags(array $tables): array
    {
        $tables = array_values(array_unique($tables));
        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        if ($tables === []) {
            return ['Codebase'];
        }
        if (count($tables) > 2) {
            return ['Mixed'];
        }

        return $tables;
    }
}

if (!function_exists('itm_script_catalog_tags_tables_from_filename')) {
    /**
     * Infer schema tables referenced by the script filename (underscore tokens and singular stems).
     *
     * @param array<string, true> $schemaTables
     * @return array<string, string>
     */
    function itm_script_catalog_tags_tables_from_filename(string $slug, array $schemaTables): array
    {
        $base = strtolower((string)preg_replace('/\.[^.]+$/', '', $slug));
        if ($base === '') {
            return [];
        }

        $parts = preg_split('/[_-]+/', $base) ?: [];
        $tables = [];

        foreach ($parts as $part) {
            if ($part === '' || strlen($part) < 3) {
                continue;
            }
            if (isset($schemaTables[$part])) {
                $tables[$part] = $part;
                continue;
            }
            $plural = $part . 's';
            if (isset($schemaTables[$plural])) {
                $tables[$plural] = $plural;
            }
        }

        $tableNames = array_keys($schemaTables);
        usort($tableNames, static function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($tableNames as $tableName) {
            $quoted = preg_quote($tableName, '/');
            if (preg_match('/(?:^|[_-])' . $quoted . '(?:[_-]|$)/', $base)) {
                $tables[$tableName] = $tableName;
                continue;
            }
            if (substr($tableName, -1) === 's' && strlen($tableName) > 4) {
                $singular = substr($tableName, 0, -1);
                if (preg_match('/(?:^|[_-])' . preg_quote($singular, '/') . '(?:[_-]|$)/', $base)) {
                    $tables[$tableName] = $tableName;
                }
            }
        }

        return $tables;
    }
}

if (!function_exists('itm_script_catalog_tags_scan_script')) {
    /**
     * @param array<string, true> $schemaTables
     * @return array{tables: array<int, string>, tags: array<int, string>, bundle: array<int, string>}
     */
    function itm_script_catalog_tags_scan_script(string $entryPath, string $rootPath, array $schemaTables): array
    {
        $scriptsRoot = itm_script_catalog_tags_scripts_root($rootPath);
        $entryReal = realpath($entryPath);
        if ($entryReal === false || !is_file($entryReal)) {
            return ['tables' => [], 'tags' => ['Codebase'], 'bundle' => []];
        }

        $bundle = itm_script_catalog_tags_collect_script_bundle($entryReal, $scriptsRoot);
        if ($bundle === []) {
            $bundle = [$entryReal];
        }

        $tables = [];
        $entrySource = is_file($entryReal) ? (string)file_get_contents($entryReal) : '';

        foreach ($bundle as $file) {
            $content = file_get_contents($file);
            if (!is_string($content)) {
                continue;
            }
            foreach (itm_script_catalog_tags_extract_tables_from_source($content, $schemaTables) as $tableName) {
                $tables[$tableName] = $tableName;
            }
        }

        $crudTable = itm_script_catalog_tags_extract_crud_table_literal($entrySource);
        if ($crudTable !== null && isset($schemaTables[$crudTable])) {
            $tables[$crudTable] = $crudTable;
        }

        foreach (itm_script_catalog_tags_spawn_targets($entrySource, $scriptsRoot) as $spawnPath) {
            $spawnBundle = itm_script_catalog_tags_collect_script_bundle($spawnPath, $scriptsRoot);
            foreach ($spawnBundle as $file) {
                $content = file_get_contents($file);
                if (!is_string($content)) {
                    continue;
                }
                foreach (itm_script_catalog_tags_extract_tables_from_source($content, $schemaTables) as $tableName) {
                    $tables[$tableName] = $tableName;
                }
            }
        }

        $infoSources = [$entrySource];
        foreach ($bundle as $file) {
            $bundleContent = file_get_contents($file);
            if (is_string($bundleContent)) {
                $infoSources[] = $bundleContent;
            }
        }
        $hasInfo = false;
        $hasMarkdown = false;
        $infoPaths = [];
        foreach ($infoSources as $infoSource) {
            $classified = itm_script_catalog_tags_classify_data_refs($infoSource, $scriptsRoot);
            if ($classified['has_info']) {
                $hasInfo = true;
            }
            if ($classified['has_markdown']) {
                $hasMarkdown = true;
            }
            foreach ($classified['paths'] as $infoPath) {
                $infoPaths[$infoPath] = $infoPath;
            }
        }
        foreach ($infoPaths as $infoPath) {
            $infoContent = file_get_contents($infoPath);
            if (!is_string($infoContent)) {
                continue;
            }
            foreach (itm_script_catalog_tags_extract_tables_from_plain_text($infoContent, $schemaTables) as $tableName) {
                $tables[$tableName] = $tableName;
            }
        }

        foreach (itm_script_catalog_tags_tables_from_filename(basename($entryReal), $schemaTables) as $tableName) {
            $tables[$tableName] = $tableName;
        }

        $tableList = array_values($tables);
        sort($tableList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'tables' => $tableList,
            'tags' => itm_script_catalog_tags_merge_kind_tags(
                itm_script_catalog_tags_resolve_tags($tableList),
                $hasInfo,
                $hasMarkdown
            ),
            'bundle' => $bundle,
        ];
    }
}

if (!function_exists('itm_script_catalog_tags_extract_row_slug')) {
    /**
     * Resolve catalog slug from row HTML (plain-text first td, nested <code><a>, or any relative href).
     */
    function itm_script_catalog_tags_extract_row_slug(string $rowHtml): ?string
    {
        if (preg_match_all('/href=["\']([^"\']+)["\']/i', $rowHtml, $hrefMatches)) {
            foreach ($hrefMatches[1] as $href) {
                $href = (string)$href;
                if (preg_match('#^https?://#i', $href)) {
                    continue;
                }
                $slug = basename(parse_url($href, PHP_URL_PATH) ?: $href);
                if ($slug !== '') {
                    return $slug;
                }
            }
        }

        if (preg_match('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $tdMatch)) {
            $text = trim(html_entity_decode(strip_tags($tdMatch[1]), ENT_QUOTES, 'UTF-8'));
            if ($text !== '') {
                return basename($text);
            }
        }

        return null;
    }
}

if (!function_exists('itm_script_catalog_tags_for_slug')) {
    /**
     * @param array<string, mixed> $overrides
     * @return array{tables: array<int, string>, tags: array<int, string>, slug: string}
     */
    function itm_script_catalog_tags_for_slug(string $slug, string $rootPath, array $schemaTables, array $overrides = [], string $rowContext = ''): array
    {
        if (isset($overrides[$slug]) && is_array($overrides[$slug]) && !empty($overrides[$slug]['override'])) {
            $tags = $overrides[$slug]['tags'] ?? ['Codebase'];
            if (!is_array($tags)) {
                $tags = ['Codebase'];
            }

            return [
                'tables' => [],
                'tags' => array_values($tags),
                'slug' => $slug,
            ];
        }

        if (preg_match('/\.py$/i', $slug)) {
            return ['tables' => [], 'tags' => ['Python'], 'slug' => $slug];
        }

        if (preg_match('/\.sh$/i', $slug)) {
            return ['tables' => [], 'tags' => ['Server'], 'slug' => $slug];
        }

        if (preg_match('/\.(?:json|txt)$/i', $slug)) {
            return ['tables' => [], 'tags' => ['Info'], 'slug' => $slug];
        }

        if (preg_match('/\.md$/i', $slug)) {
            return ['tables' => [], 'tags' => ['Markdown'], 'slug' => $slug];
        }

        if (!preg_match('/\.php$/i', $slug)) {
            return ['tables' => [], 'tags' => ['Codebase'], 'slug' => $slug];
        }

        $scriptPath = itm_script_catalog_tags_scripts_root($rootPath) . $slug;
        if (!is_file($scriptPath)) {
            return ['tables' => [], 'tags' => ['Codebase'], 'slug' => $slug];
        }

        $scan = itm_script_catalog_tags_scan_script($scriptPath, $rootPath, $schemaTables);
        $tags = $scan['tags'];

        if ($rowContext !== '') {
            $rowText = html_entity_decode(strip_tags($rowContext), ENT_QUOTES, 'UTF-8');
            $rowClassify = itm_script_catalog_tags_classify_data_refs(
                $rowText,
                itm_script_catalog_tags_scripts_root($rootPath)
            );
            $tags = itm_script_catalog_tags_merge_kind_tags(
                $tags,
                $rowClassify['has_info'],
                $rowClassify['has_markdown']
            );
        }

        return [
            'tables' => $scan['tables'],
            'tags' => $tags,
            'slug' => $slug,
        ];
    }
}

if (!function_exists('itm_script_catalog_tags_for_href')) {
    /**
     * @param array<string, mixed> $overrides
     * @return array{tables: array<int, string>, tags: array<int, string>, slug: string}
     */
    function itm_script_catalog_tags_for_href(string $href, string $rootPath, array $schemaTables, array $overrides = []): array
    {
        if (preg_match('#^https?://#i', $href)) {
            $slug = basename(parse_url($href, PHP_URL_PATH) ?: $href);

            return ['tables' => [], 'tags' => ['Codebase'], 'slug' => $slug];
        }

        $slug = basename(parse_url($href, PHP_URL_PATH) ?: $href);

        return itm_script_catalog_tags_for_slug($slug, $rootPath, $schemaTables, $overrides);
    }
}

if (!function_exists('itm_script_catalog_tags_render_cell')) {
    /**
     * @param array<int, string> $tags
     */
    function itm_script_catalog_tags_render_cell(array $tags): string
    {
        $parts = [];
        foreach ($tags as $tag) {
            $escaped = htmlspecialchars((string)$tag, ENT_QUOTES, 'UTF-8');
            $kind = 'table';
            if ($tag === 'Mixed') {
                $kind = 'mixed';
            } elseif ($tag === 'Codebase') {
                $kind = 'codebase';
            } elseif ($tag === 'Python') {
                $kind = 'python';
            } elseif ($tag === 'Server') {
                $kind = 'server';
            } elseif ($tag === 'Info') {
                $kind = 'info';
            } elseif ($tag === 'Markdown') {
                $kind = 'markdown';
            }
            $parts[] = '<span class="scripts-badge scripts-badge-tag" data-tag-kind="' . $kind . '">' . $escaped . '</span>';
        }

        return '<td class="scripts-tags-cell"><span class="scripts-tag-badges">' . implode('', $parts) . '</span></td>';
    }
}

if (!function_exists('itm_script_catalog_tags_data_attr')) {
    /**
     * @param array<int, string> $tags
     */
    function itm_script_catalog_tags_data_attr(array $tags): string
    {
        return htmlspecialchars(implode(' ', $tags), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('itm_script_catalog_tags_load_manifest')) {
    /**
     * @return array<string, mixed>
     */
    function itm_script_catalog_tags_load_manifest(string $rootPath): array
    {
        $path = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'script_catalog_tags.json';
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('itm_script_catalog_tags_save_manifest')) {
    /**
     * @param array<string, mixed> $manifest
     */
    function itm_script_catalog_tags_save_manifest(string $rootPath, array $manifest): bool
    {
        $path = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'script_catalog_tags.json';
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        return file_put_contents($path, $json . "\n") !== false;
    }
}

if (!function_exists('itm_script_catalog_tags_parse_catalog_rows')) {
    /**
     * @return array<int, array{href: string, slug: string, row_html: string, offset: int}>
     */
    function itm_script_catalog_tags_parse_catalog_rows(string $catalogHtml): array
    {
        $rows = [];
        if (!preg_match_all('/<tr\b([^>]*)>(.*?)<\/tr>/is', $catalogHtml, $matches, PREG_OFFSET_CAPTURE)) {
            return $rows;
        }

        foreach ($matches[0] as $idx => $fullMatch) {
            $rowHtml = $matches[2][$idx][0];
            if (strpos($rowHtml, 'scripts-access-cell') === false) {
                continue;
            }

            $slug = itm_script_catalog_tags_extract_row_slug($rowHtml);
            if ($slug === null || $slug === '') {
                continue;
            }

            $href = '';
            if (preg_match('/href=["\']([^"\']+)["\']/i', $rowHtml, $hrefMatch)) {
                $href = (string)$hrefMatch[1];
            }

            $rows[] = [
                'href' => $href,
                'slug' => $slug,
                'row_html' => $rowHtml,
                'offset' => $fullMatch[1],
                'full' => $fullMatch[0],
                'attrs' => $matches[1][$idx][0],
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_script_catalog_tags_patch_row')) {
    /**
     * @param array<int, string> $tags
     */
    function itm_script_catalog_tags_patch_row(string $rowAttrs, string $rowInner, array $tags): string
    {
        $dataTags = itm_script_catalog_tags_data_attr($tags);
        $tagsCell = itm_script_catalog_tags_render_cell($tags);

        if (preg_match('/\bdata-tags=["\'][^"\']*["\']/i', $rowAttrs)) {
            $rowAttrs = preg_replace('/\bdata-tags=["\'][^"\']*["\']/i', 'data-tags="' . $dataTags . '"', $rowAttrs) ?? $rowAttrs;
        } else {
            $rowAttrs = rtrim($rowAttrs) . ' data-tags="' . $dataTags . '"';
        }

        if (preg_match('/<td\s+class=["\']scripts-tags-cell["\'][^>]*>.*?<\/td>/is', $rowInner)) {
            $rowInner = preg_replace(
                '/<td\s+class=["\']scripts-tags-cell["\'][^>]*>.*?<\/td>/is',
                $tagsCell,
                $rowInner,
                1
            ) ?? $rowInner;
        } else {
            // Insert tags cell after access cell.
            $rowInner = preg_replace(
                '/(<td\s+class=["\']scripts-access-cell["\'][^>]*>.*?<\/td>)/is',
                '$1' . "\n                    " . $tagsCell,
                $rowInner,
                1
            ) ?? $rowInner;
        }

        return '<tr' . $rowAttrs . '>' . $rowInner . '</tr>';
    }
}
