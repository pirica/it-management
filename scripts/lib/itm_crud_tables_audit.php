<?php
/**
 * Shared helpers for crud_tables.php, crud_titles.php, and crud_actions.php — module slug inventories (no database lookups).
 */

if (!function_exists('itm_crud_tables_load_slug_list_file')) {
    /**
     * @return array<string, string> slug => slug
     */
    function itm_crud_tables_load_slug_list_file(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $slugs = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('/^[a-z0-9_]+$/', $line)) {
                $slugs[$line] = $line;
            }
        }

        return $slugs;
    }
}

if (!function_exists('itm_crud_tables_load_skip_module_slugs')) {
    /**
     * Modules that intentionally omit $crud_table (bespoke UI + crud_tables extras).
     *
     * @return string[]
     */
    function itm_crud_tables_load_skip_module_slugs(?string $rootPath = null): array
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string)ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $rootPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR;
        $merged = itm_crud_tables_load_slug_list_file($rootPath . 'docs/list_bespoke_UI.txt')
            + itm_crud_tables_load_slug_list_file($rootPath . 'scripts/data/crud_tables_skip_modules.txt');

        $slugs = array_values($merged);
        sort($slugs, SORT_STRING);

        return $slugs;
    }
}

if (!function_exists('itm_crud_tables_detect_assignment')) {
    /**
     * @return array{line:int,text:string}|null
     */
    function itm_crud_tables_detect_assignment(string $filePath): ?array
    {
        $lines = @file($filePath);
        if (!is_array($lines)) {
            return null;
        }

        foreach ($lines as $lineNumber => $lineText) {
            if (preg_match('/\$crud_table\s*=/', $lineText)) {
                return [
                    'line' => $lineNumber + 1,
                    'text' => trim($lineText),
                ];
            }
        }

        return null;
    }
}

if (!function_exists('itm_crud_mapper_module_matches_is_prefix')) {
    function itm_crud_mapper_module_matches_is_prefix(string $moduleSlug): bool
    {
        return (bool)preg_match('/^is_/', $moduleSlug);
    }
}

if (!function_exists('itm_crud_titles_should_skip_module')) {
    /**
     * Modules that intentionally omit $crud_title in index.php.
     */
    function itm_crud_titles_should_skip_module(string $moduleSlug, ?string $rootPath = null): bool
    {
        if (itm_crud_mapper_module_matches_is_prefix($moduleSlug)) {
            return true;
        }

        $skipModules = array_fill_keys(itm_crud_tables_load_skip_module_slugs($rootPath), true);

        return isset($skipModules[$moduleSlug]);
    }
}

if (!function_exists('itm_crud_titles_detect_assignment')) {
    /**
     * @return array{line:int,text:string}|null
     */
    function itm_crud_titles_detect_assignment(string $filePath): ?array
    {
        $lines = @file($filePath);
        if (!is_array($lines)) {
            return null;
        }

        foreach ($lines as $lineNumber => $lineText) {
            if (preg_match('/\$crud_title\s*=/', $lineText)) {
                return [
                    'line' => $lineNumber + 1,
                    'text' => trim($lineText),
                ];
            }
        }

        return null;
    }
}

if (!function_exists('itm_crud_mapper_module_is_standard_crud')) {
    /**
     * Flattened scaffold modules (e.g. manufacturers/) define both markers in index.php.
     */
    function itm_crud_mapper_module_is_standard_crud(string $moduleSlug, ?string $rootPath = null): bool
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? (string)ROOT_PATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $indexPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR
            . 'modules' . DIRECTORY_SEPARATOR . $moduleSlug . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($indexPath)) {
            return false;
        }

        $content = (string)file_get_contents($indexPath);

        return (bool)preg_match('/\$uiColumns\s*=/', $content)
            && (bool)preg_match('/cr_manageable_columns\s*\(/', $content);
    }
}

if (!function_exists('itm_crud_actions_should_skip_module')) {
    /**
     * Modules that intentionally omit $crud_action in entry files (non-standard CRUD).
     */
    function itm_crud_actions_should_skip_module(string $moduleSlug, ?string $rootPath = null): bool
    {
        if (itm_crud_mapper_module_matches_is_prefix($moduleSlug)) {
            return true;
        }

        return !itm_crud_mapper_module_is_standard_crud($moduleSlug, $rootPath);
    }
}

if (!function_exists('itm_crud_actions_detect_assignment')) {
    /**
     * @return array{line:int,text:string,literal:?string,is_coalesce:bool}|null
     */
    function itm_crud_actions_detect_assignment(string $filePath): ?array
    {
        $lines = @file($filePath);
        if (!is_array($lines)) {
            return null;
        }

        foreach ($lines as $lineNumber => $lineText) {
            if (!preg_match('/\$crud_action\s*=\s*(.+);/', $lineText, $matches)) {
                continue;
            }

            $rhs = trim((string)$matches[1]);
            $literal = null;
            if (preg_match("/^['\"]([^'\"]+)['\"]$/", $rhs, $literalMatch)) {
                $literal = (string)$literalMatch[1];
            }

            return [
                'line' => $lineNumber + 1,
                'text' => trim($lineText),
                'literal' => $literal,
                'is_coalesce' => strpos($rhs, '??') !== false,
            ];
        }

        return null;
    }
}
