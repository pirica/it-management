<?php
/**
 * Read-only helpers for .cursor/rules (Cursor project rules).
 */

if (!function_exists('itm_cursor_rules_directory')) {
    function itm_cursor_rules_directory(): string
    {
        $root = function_exists('itm_script_repo_root_path')
            ? itm_script_repo_root_path()
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'rules';
    }
}

if (!function_exists('itm_cursor_rules_list_basenames')) {
    /**
     * @return list<string>
     */
    function itm_cursor_rules_list_basenames(): array
    {
        $dir = itm_cursor_rules_directory();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.mdc') ?: [];
        $names = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $base = basename($file);
            if ($base === '' || $base === 'index.html') {
                continue;
            }
            $names[] = $base;
        }
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }
}

if (!function_exists('itm_cursor_rules_sanitize_basename')) {
    function itm_cursor_rules_sanitize_basename(string $basename): string
    {
        $basename = str_replace('\\', '/', trim($basename));
        $basename = basename($basename);

        if ($basename === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.mdc$/', $basename)) {
            return '';
        }

        return $basename;
    }
}

if (!function_exists('itm_cursor_rules_resolve_file')) {
    /**
     * @return string|null Absolute path when the file is inside .cursor/rules
     */
    function itm_cursor_rules_resolve_file(string $basename): ?string
    {
        $basename = itm_cursor_rules_sanitize_basename($basename);
        if ($basename === '') {
            return null;
        }

        $dir = itm_cursor_rules_directory();
        $realDir = realpath($dir);
        if ($realDir === false) {
            return null;
        }

        $candidate = $realDir . DIRECTORY_SEPARATOR . $basename;
        if (!is_file($candidate)) {
            return null;
        }

        $realFile = realpath($candidate);
        if ($realFile === false) {
            return null;
        }

        $dirPrefix = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $realDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($realFile, $dirPrefix) !== 0) {
            return null;
        }

        return $realFile;
    }
}
